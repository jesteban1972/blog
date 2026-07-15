<?php
declare(strict_types=1);
// file: ~/Sites/blog/src/Security/AuthBridgeAuthenticator.php

/**
 * the logic of this class is standardized across the pendoncete.org domain. while the core SSO handshake is universal,
 * the specific immune routes and redirection targets are governed by local configuration (services.yaml)
 */

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
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

    /**
     * the extracted domain name (e.g., 'auth.pendoncete.org'), injected into the HTTP 'Host' header during API calls.
     * it allows the internal Apache server in the 'auth-php' container to identify which VirtualHost should handle
     * the request.
     * @var string
     */
    private string $authHost;

    /**
     * TODO: add suitable comment
     */
    private string $centralBaseUrl;

    /**
     * @param EntityManagerInterface $entityManager
     * @param HttpClientInterface $httpClient for server-to-server introspection and refresh calls.
     * @param TokenStorageInterface $tokenStorage to clear the security token during authentication failure.
     * @param UrlGeneratorInterface $urlGenerator to generate redirects for login and wizard routes.
     * @param LoggerInterface $ssoLogger dedicated logger for tracking SSO handshakes.
     * @param string $cookieDomain the shared domain ('.pendoncete.org') for cookie management.
     * @param array $immuneRoutes route names exempt from promotion checks to prevent redirection loops.
     */
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

        // build the central app base URL (e.g., https://pendoncete.localhost)
        // by stripping the leading dot from your cookieDomain (e.g. '.pendoncete.localhost')
        $this->centralBaseUrl = 'https://' . ltrim($this->cookieDomain, '.');
    }

    /**
     * this method determines if the authenticator should handle the request. it triggers if a local session exists OR
     * if SSO cookies are present in the browser.
     *
     * @param Request $request
     * @return bool|null
     */
    public function supports(Request $request): ?bool
    {
        // if we are logging out, we don't need to authenticate anything:
        if ($request->attributes->get('_route') === 'app_logout_local') {
            return false;
        }

        return $request->cookies->has('pendoncete_jwt')
            || $request->cookies->has('pendoncete_refresh')
            || $request->getSession()->has('user'); // TODO: add cookie pendoncete_ux_language?
    }

    /**
     * this method executes the core authentication logic. it prioritizes existing session data, then falls back to
     * background JWT introspection, and finally attempts a silent refresh before failing.
     *
     * @param Request $request
     * @return SelfValidatingPassport
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $session = $request->getSession();
        $sessionData = $session->get('user');

        // get fresh JWT data, if available:
        $jwt = $request->cookies->get('pendoncete_jwt');
        $userData = $jwt ? $this->doIntrospect($jwt) : null;


        ////////////////////////////////////////////////////////////////////////
        /// 1. handling of existing local session

        if ($sessionData) {

            // if we have fresh data from the JWT, update the session to stay in sync:
            if ($userData) {
                $session->set('user', $userData);
                $sessionData = $userData;
            }

            // we determine the identifier from the session data (object or array)
            if ($sessionData instanceof SessionUser) {

                $identifier = (string)$sessionData->getId();

            } elseif (is_array($sessionData)) {

                // defensive check for both 'id', 'sub' and 'user_id' keys: // TODO: is this correct?
                $identifier = (string) ($sessionData['id'] ?? $sessionData['sub'] ?? $sessionData['userId'] ?? '');

            } else {

                $this->ssoLogger->error('invalid session user type', ['type' => get_debug_type($sessionData)]);

                throw new AuthenticationException('invalid session user type.');
            }

            if (empty($identifier)) {

                $this->ssoLogger->error('invalid empty identifier given');

                throw new AuthenticationException('session user identifier is missing.');
            }

            return new SelfValidatingPassport(new UserBadge($identifier));
        }


        ////////////////////////////////////////////////////////////////////////
        /// 2. global JWT cookie validation (SSO Introspection)

        $jwt = $request->cookies->get('pendoncete_jwt');
        $refreshToken = $request->cookies->get('pendoncete_refresh');
        $userData = $jwt ? $this->doIntrospect($jwt) : null;


        ////////////////////////////////////////////////////////////////////////
        /// 3. silent refresh

        /**
         * if JWT failed or is missing, but we have a refresh token, we try to renew the session
         */

        if (!$userData && $refreshToken) {

            $this->ssoLogger->info('JWT expired or missing, attempting silent refresh', [
                'ip' => $request->getClientIp()
            ]);

            $refreshResult = $this->doRefresh($refreshToken);

            if ($refreshResult) {

                $userData = $refreshResult['user'];

                // store new raw headers to drop them into browser in onAuthenticationSuccess:
                $request->attributes->set('_sso_new_cookies', $refreshResult['cookies']);
            }
        }


        ////////////////////////////////////////////////////////////////////////
        /// 4. final validation and session hydration

        if (!$userData) {

            $this->ssoLogger->warning('authentication failed (no valid JWT or refresh token)', [
                'ip' => $request->getClientIp()
            ]);

            throw new AuthenticationException('SSO session expired.');
        }

        // capture the id from the Authorization Center payload: // TODO: is this right?
        $identifier = (string)($userData['id'] ?? $userData['sub'] ?? $userData['userId'] ?? '');

        if (empty($identifier)) {

            $this->ssoLogger->error('SSO payload missing identifier keys (id, sub, or user_id)', [
                'payload' => $userData,
            ]);

            throw new AuthenticationException('invalid SSO response structure.');
        }

        $this->ssoLogger->info('authentication successful, hydrating session', [
            'user' => $userData['email'] ?? '(unknown)',
            'id' => $identifier,
        ]);

        // the SessionUserProvider will use the raw data from the session to build either a SessionUser,
        // or hydrate an App\Entity\User:
        $session->set('user', $userData);

        return new SelfValidatingPassport(new UserBadge($identifier));
    }

    /**
     * internal helper for JWT introspection against the Auth Center.
     *
     * @param string $jwt the token to validate.
     * @return array|null the user claims if active, null otherwise.
     */
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

    /**
     * internal helper for silent refresh call.
     *
     * @param string $refreshToken the token used to request new credentials.
     * @return array|null contains 'user' claims and 'cookies' headers.
     */
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

                // extract new JWT from Set-Cookie headers to perform final introspection:
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

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        /** @var User|SessionUser $user */
        $user = $token->getUser();
        $session = $request->getSession();

        $route = $request->attributes->get('_route');
        $newCookies = $request->attributes->get('_sso_new_cookies');
        $userData = $session->get('user');

        ////////////////////////////////////////////////////////////////////////////////
        /// 1. update last login for persistent entities

        if ($user instanceof User) {

            $user->setLastLogin(new \DateTime());

            // persist the change to the shadow table:
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 2. synchronize the language state (session and cookie)

        /**
         * to maintain a consistent experience across the SSO domain, we prioritize the user's
         * current browser intent (cookie) over the stored DB preference. this allows for
         * immediate UI updates that later propagate back to the persistent identity.
         */

        $cookieLocale = $request->cookies->get('pendoncete_ux_language');
        $dbLocale = $user->getUxLanguage() ?: 'en';

        // prioritize the cookie if it exists; otherwise fall back to DB setting:
        $targetLocale = $cookieLocale ?: $dbLocale;

        // sync the session and request immediately for the current execution:
        $session->set('_locale', $targetLocale);
        $request->setLocale($targetLocale);

        // if the browser intent differs from the DB, we promote the new language to the persistent entity:
        if ($cookieLocale && $cookieLocale !== $dbLocale && $user instanceof User) {
            $user->setUxLanguage($cookieLocale);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        // prepare the cookie for broadcasting to the entire .pendoncete.org domain:
        $languageCookie = Cookie::create(
            name: 'pendoncete_ux_language',
            value: $targetLocale,
            expire: time() + 31536000, // 1 year
            path: '/',
            domain: $this->cookieDomain,
            secure: $request->isSecure(),
            httpOnly: false, // JS must see it for the LanguageBar UI
            sameSite: Cookie::SAMESITE_LAX
        );

        ////////////////////////////////////////////////////////////////////////////////
        /// 3. map the JWT claim isConsented to the volatile entity property

        if ($user instanceof User && isset($userData['isConsented'])) {

            // olim: $user->setIsConsented((bool)$userData['isConsented']);

            // ONLY update if the local DB thinks they haven't consented.
            // Never let the JWT "un-consent" a user who is already verified locally.
            if (!$user->isConsented() && (bool)$userData['isConsented'] === true) {
                $user->setIsConsented(true);
            }
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 4. handle response generation (silent refresh and cookie seeding)

        /**
         * we issue a redirect response to "force" the browser to persist state ONLY if:
         * a) a silent refresh occurred (new JWT/Refresh cookies must be dropped).
         *
         * we no longer redirect for language mismatches, as the targetLocale is already
         * synchronized with the cookie intent in Section 2.
         */
        if ($newCookies) {

            $this->ssoLogger->info('SSO silent refresh detected; issuing redirect to sync security cookies.', [
                'target_locale' => $targetLocale
            ]);

            $response = new RedirectResponse($request->getUri());

            // ensure the language state is stamped into the browser:
            $response->headers->setCookie($languageCookie);

            // drop the new SSO JWT/Refresh cookies, if they were generated during a silent refresh:
            foreach ($newCookies as $cookieString) {
                $response->headers->set('Set-Cookie', $cookieString, false);
            }

            return $response;
        }

        ////////////////////////////////////////////////////////////////////////////////
        // 5. immunity check to avoid redirection loops

        if (
            in_array($route, $this->immuneRoutes) ||
            $request->isXmlHttpRequest() ||
            $request->headers->has('X-Live-Component-Request')
        ) {
            return null;
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 6. gatekeeper for mandatory promotion & validation

        /**
         * in the blog app, we do not enforce local DB promotion or consent checks.
         * if the user is authenticated via the global SSO, they are allowed access.
         */
        if ($route === 'app_login_callback' || $route === 'app_login') {

            // try to find where they were going before being kicked to login
            $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
            $url = $targetPath ?: $this->urlGenerator->generate('app_home');

            $response = new RedirectResponse($url);

            // ensure the language state is stamped into the browser before redirecting:
            $response->headers->setCookie($languageCookie);

            return $response;
        }

        // allow the request to proceed normally to any blog page
        return null;
    }

    /**
     * this method determines if the request is secure (HTTPS). this is critical for setting the correct cookie
     * attributes during cleanup.
     */
    private function isSecure(Request $request): bool
    {
        return $request->isSecure();
    }

    /**
     * this method returns the appropriate SameSite attribute based on the protocol. for SSO cross-domain
     * interactions over HTTPS, 'None' is required.
     */
    private function getSameSite(Request $request): string
    {
        return $this->isSecure($request) ? 'None' : 'Lax';
    }

    /**
     * this method handles cases where authentication fails (e.g., expired token). it cleans up the local session,
     * clears the local security token, and issues expired cookie commands to the browser. finally, it redirects the
     * user to the local login initiation point.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        ////////////////////////////////////////////////////////////////////////////////
        /// log the failure

        $this->ssoLogger->notice('authentication failure, clearing cookies', [
            'reason' => $exception->getMessage(),
            'ip' => $request->getClientIp()
        ]);


        ////////////////////////////////////////////////////////////////////////////////
        /// clear the token and prepare response

        $this->tokenStorage->setToken(null);
        $request->getSession()->remove('user');

        $response = new RedirectResponse($this->urlGenerator->generate('app_login'));


        ////////////////////////////////////////////////////////////////////////////////
        /// delete JWT & refresh cookies safely

        $isSecure = $this->isSecure($request);
        $sameSite = $this->getSameSite($request);

        /**
         * Achtung! we use named arguments to ensure cross-version compatibility. by explicitly naming
         * 'sameSite', we bypass the positional argument count differences between Symfony 6.1 (annales) and
         * 7.x. (pendoncete, auth...).
         *
         * we omit the 'partitioned' parameter entirely as it is optional and defaults to false, keeping the
         * code verbatim across all our client applications.
         */

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
}
