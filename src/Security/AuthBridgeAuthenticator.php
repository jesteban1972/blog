<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Security/AuthBridgeAuthenticator.php

/**
 * this is the consolidated, universal blueprint file that can be dropped verbatim
 * into every client application.
 */

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * this class acts as the security bridge between the client application and the central Authorization Center. it
 * synchronizes the local security state with the global SSO state by validating the 'pendoncete_jwt' via introspection.
 * if the JWT is expired, it performs a 'silent refresh' to maintain a smooth user's experience across the domain.
 *
 * as an overview, this class performs:
 *   - global SSO identity (JWT)
 *   - volatile session identity (SessionUser)
 *   - local persistent identity (User entity)
 *   - automatic recovery (silent refresh)
 */
class AuthBridgeAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    private string $authHost;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $ssoLogger,
        private string $cookieDomain,
        private array $immuneRoutes,
        private string $authIntrospectUrl,
        private string $authRefreshUrl,
        string $authPublicUrl,
    ) {
        $this->authHost = (string) parse_url($authPublicUrl, PHP_URL_HOST);
    }

    public function supports(Request $request): ?bool
    {
        if ($request->attributes->get('_route') === 'app_logout_local') {
            return false;
        }

        return $request->cookies->has('pendoncete_jwt')
            || $request->cookies->has('pendoncete_refresh')
            || $request->getSession()->has('user');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $session = $request->getSession();
        $sessionData = $session->get('user');

        $jwt = $request->cookies->get('pendoncete_jwt');
        $refreshToken = $request->cookies->get('pendoncete_refresh');

        // 1. primary jwt introspection
        $userData = $jwt ? $this->doIntrospect($jwt) : null;

        // 2. silent refresh fallback
        if (!$userData && $refreshToken) {
            $this->ssoLogger->info('JWT expired or missing, attempting silent refresh', [
                'ip' => $request->getClientIp()
            ]);

            $refreshResult = $this->doRefresh($refreshToken);

            if ($refreshResult) {
                $userData = $refreshResult['user'];
                $request->attributes->set('_sso_new_cookies', $refreshResult['cookies']);
                $session->set('user', $userData);
                $sessionData = $userData;
            }
        }

        // 3. sync session data
        if ($sessionData) {
            if ($userData) {
                $session->set('user', $userData);
                $sessionData = $userData;
            }

            $identifier = $this->extractIdentifier($sessionData);

            if (empty($identifier)) {
                $this->ssoLogger->error('session user identifier is missing.');
                throw new AuthenticationException('session user identifier is missing.');
            }

            return new SelfValidatingPassport(new UserBadge($identifier));
        }

        if ($userData) {
            $identifier = $this->extractIdentifier($userData);

            if (empty($identifier)) {
                throw new AuthenticationException('invalid SSO response structure.');
            }

            $session->set('user', $userData);
            return new SelfValidatingPassport(new UserBadge($identifier));
        }

        $this->ssoLogger->warning('authentication failed (no valid JWT or refresh token)', [
            'ip' => $request->getClientIp()
        ]);

        throw new AuthenticationException('SSO session expired.');
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        /** @var User|SessionUser $user */
        $user = $token->getUser();
        $session = $request->getSession();

        $route = (string) $request->attributes->get('_route');
        $newCookies = $request->attributes->get('_sso_new_cookies');
        $userData = $session->get('user');

        // 1. update last login
        if ($user instanceof User) {
            $user->setLastLogin(new \DateTime());
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        // 2. synchronize language state
        $cookieLocale = $request->cookies->get('pendoncete_ux_language');
        $dbLocale = $user->getUxLanguage() ?: 'en';
        $targetLocale = $cookieLocale ?: $dbLocale;

        $session->set('_locale', $targetLocale);
        $request->setLocale($targetLocale);

        if ($cookieLocale && $cookieLocale !== $dbLocale && $user instanceof User) {
            $user->setUxLanguage($cookieLocale);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $languageCookie = Cookie::create(
            name: 'pendoncete_ux_language',
            value: $targetLocale,
            expire: time() + 31536000,
            path: '/',
            domain: $this->cookieDomain,
            secure: $request->isSecure(),
            httpOnly: false,
            sameSite: Cookie::SAMESITE_LAX
        );

        // 3. map consent claim
        if ($user instanceof User) {
            $isConsentedValue = $userData['isConsented'] ?? false;
            if (!$user->isConsented() && (bool)$isConsentedValue === true) {
                $user->setIsConsented(true);
            }
        }

        // 4. silent refresh cookie delivery
        if ($newCookies) {
            $this->ssoLogger->info('SSO silent refresh detected; issuing redirect to sync security cookies.');

            $response = new RedirectResponse($request->getUri());
            $response->headers->setCookie($languageCookie);

            foreach ($newCookies as $cookieString) {
                $response->headers->set('Set-Cookie', $cookieString, false);
            }

            return $response;
        }

        // 5. immunity check (prevents redirect loops)
        if (
            in_array($route, $this->immuneRoutes, true) ||
            $request->isXmlHttpRequest() ||
            $request->headers->has('X-Live-Component-Request')
        ) {
            return null;
        }

        // 6. gatekeeper for mandatory promotion & consent
//        $isPromoted = $user instanceof User;
//        $hasConsented = $isPromoted && $user->isConsented();
//
//        if ($isPromoted && $hasConsented) {
//            if ($route === 'app_login_callback' || $route === 'app_login') {
//                $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
//                $url = $targetPath ?: $this->urlGenerator->generate('app_home');
//
//                $response = new RedirectResponse($url);
//                $response->headers->setCookie($languageCookie);
//
//                return $response;
//            }
//
//            return null;
//        }
//
//        // escort unpromoted or unconsented users to wizard
//        $this->ssoLogger->info('promotion or consent missing; escorting to wizard.', [
//            'id' => $user->getUserIdentifier()
//        ]);
//
//        $redirectUrl = $this->urlGenerator->generate('app_profile_completion');
//        $response = new RedirectResponse($redirectUrl);
//        $response->headers->setCookie($languageCookie);
//
//        return $response;

        if ($route === 'app_login_callback' || $route === 'app_login') {
            $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
            $url = $targetPath ?: $this->urlGenerator->generate('app_home'); // or 'app_post_index' depending on your main route name

            $response = new RedirectResponse($url);
            $response->headers->setCookie($languageCookie);

            return $response;
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->ssoLogger->notice('authentication failure, clearing cookies', [
            'reason' => $exception->getMessage(),
            'ip' => $request->getClientIp()
        ]);

        $this->tokenStorage->setToken(null);
        $request->getSession()->remove('user');

        $response = new RedirectResponse($this->urlGenerator->generate('app_login'));

        $isSecure = $request->isSecure();
        $sameSite = $isSecure ? 'None' : 'Lax';

        $response->headers->clearCookie(
            name: 'pendoncete_jwt',
            path: '/',
            domain: $this->cookieDomain,
            secure: $isSecure,
            httpOnly: true,
            sameSite: $sameSite,
        );

        $response->headers->clearCookie(
            name: 'pendoncete_refresh',
            path: '/',
            domain: $this->cookieDomain,
            secure: $isSecure,
            httpOnly: true,
            sameSite: $sameSite,
        );

        return $response;
    }

    private function doIntrospect(string $jwt): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->authIntrospectUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                    'Host' => $this->authHost,
                    'Accept' => 'application/json',
                ],
            ]);

            $data = $response->toArray(false);

            return (!empty($data['active']) && !empty($data['user'])) ? $data['user'] : null;
        } catch (\Throwable $e) {
            $this->ssoLogger->error('introspection request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function doRefresh(string $refreshToken): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->authRefreshUrl, [
                'headers' => [
                    'Cookie' => sprintf('pendoncete_refresh=%s', $refreshToken),
                    'Host' => $this->authHost,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 204) {
                $setCookieHeaders = $response->getHeaders()['set-cookie'] ?? [];
                $newJwt = null;

                foreach ($setCookieHeaders as $header) {
                    if (preg_match('/pendoncete_jwt=([^;]+)/', $header, $matches)) {
                        $newJwt = $matches[1];
                        break;
                    }
                }

                if ($newJwt && ($userData = $this->doIntrospect($newJwt))) {
                    return [
                        'user' => $userData,
                        'cookies' => $setCookieHeaders
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->ssoLogger->error('refresh request failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function extractIdentifier(mixed $sessionData): string
    {
        if ($sessionData instanceof SessionUser) {
            return (string) $sessionData->getId();
        }

        if (is_array($sessionData)) {
            return (string) ($sessionData['id'] ?? $sessionData['sub'] ?? $sessionData['userId'] ?? '');
        }

        return '';
    }
}
