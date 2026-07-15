<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/LogoutController.php

namespace App\Controller;

use App\Service\CookieService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * this controller handles all aspects of the logout process for one client application within the pendoncete.org
 * domain, orchestrating the sequence of centralized token revocation (delegation to the SSO server) and immediate local
 * session cleanup. this dual approach ensures both security (token revocation) and a responsive user experience (local
 * log out).
 */
class LogoutController extends AbstractController
{
    /**
     * this constructor method injects necessary services and configuration for the logout sequence.
     *
     * @param CookieService $cookieService service responsible for generating expired cookies with correct
     * domain/security settings.
     * @param TokenStorageInterface $tokenStorage Symfony service used to clear the local security token.
     * @param LoggerInterface $ssoLogger logger
     * @param string $authBaseUrl the base URL of the central 'auth' SSO server (https://auth.pendoncete.org).
     * @param string $appSecret the app secret, needed for generating the signature.
     */
    public function __construct(
        private CookieService         $cookieService,
        private TokenStorageInterface $tokenStorage,
        private LoggerInterface       $ssoLogger,
        private string                $authBaseUrl,
        private string                $appSecret,
    ) {}

    /**
     * this method initiates the centralized SSO logout process (phase 1 of client logout). the client app delegates
     * the responsibility to the SSO server by redirecting the user to Authorization Center /logout endpoint. it passes
     * a redirect_uri URL and the user's refresh token (if present), allowing the Authorization Center to revoke the
     * token in the DB and clear the cross-domain cookies globally.
     *
     * @param Request $request used to determine the current host/scheme for the redirect_uri URL and to retrieve the
     * refresh token cookie.
     * @return Response a RedirectResponse to the Authorization Center logout endpoint.
     */
    // NOTE: route explicitly defined in config/routes.yaml to ensure it bypasses locale prefixes.
    // #[Route('~/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(Request $request): Response
    {
        $clientIp = $request->getClientIp();
        $redirectUri = $request->getSchemeAndHttpHost() . '/';

        // generate the signature:
        $sig = hash_hmac('sha256', $redirectUri, $this->appSecret);


        ////////////////////////////////////////////////////////////////////////
        /// log initiation of logout process

        $this->ssoLogger->info('local logout initiated; redirecting to SSO server', [
            'ip' => $clientIp,
            'redirect_uri' => $redirectUri,
            'has_refresh_token' => $request->cookies->has('pendoncete_refresh')
        ]);

        $authLogoutUrl = $this->authBaseUrl . '/logout?' . http_build_query([
                'redirect_uri' => $redirectUri,
                'sig' => $sig
            ]);


        ////////////////////////////////////////////////////////////////////////
        /// grab refresh token from cookie (if any)

        $refreshToken = $request->cookies->get('pendoncete_refresh');
        if ($refreshToken) {
            $authLogoutUrl .= '&refresh_token=' . urlencode($refreshToken);
        }


        ////////////////////////////////////////////////////////////////////////
        /// PRE-EMPTIVE local cleanup to prevent authenticator interception

        if ($request->hasSession()) {
            $request->getSession()->remove('user');
            $request->getSession()->invalidate();
        }
        $this->tokenStorage->setToken(null);


        ////////////////////////////////////////////////////////////////////////
        /// redirect to authLogoutUrl

        return $this->redirect($authLogoutUrl);
    }

    /**
     * this method performs the essential local session cleanup (phase 5 of broadcast logout). this endpoint is
     * designed to be called by the Authorization Center via hidden iframes or by a direct browser hit.
     *
     * logic:
     * 1. invalidates local PHP session and clears Symfony security token.
     * 2. expires JWT and Refresh cookies.
     * 3. IF called via iframe (SSO cascade): returns a '204 No Content' to prevent top-level navigation hijacks.
     * 4. IF called directly: renders 'logout_local.html.twig' for a standard redirect.
     *
     * @param Request $request the incoming request.
     * @return Response a 204 No Content (for iframes) or a rendered template (for direct hits).
     */
    // NOTE: route explicitly defined in config/routes.yaml to ensure it bypasses locale prefixes.
    // #[Route('~/logout_local', name: 'app_logout_local', methods: ['GET'])]
    public function logoutLocal(Request $request): Response
    {
        $clientIp = $request->getClientIp();

        ////////////////////////////////////////////////////////////////////////
        /// 1. log hit

        $this->ssoLogger->info('SSO logout_local called (iframe cleanup)', [
            'ip' => $clientIp,
            'referer' => $request->headers->get('referer'),
        ]);

        ////////////////////////////////////////////////////////////////////////
        /// 2. invalidate local session

        if ($request->hasSession()) {
            $request->getSession()->invalidate();
            $this->ssoLogger->debug('local PHP session invalidated', ['ip' => $clientIp]);
        }

        ////////////////////////////////////////////////////////////////////////
        /// 3. clear Symfony token

        $this->tokenStorage->setToken(null);

        ////////////////////////////////////////////////////////////////////////
        /// 4. expire JWT cookies

        [$expiredJwt, $expiredRefresh] = $this->cookieService->expireJwtAndRefreshCookies();

        ////////////////////////////////////////////////////////////////////////
        /// 5. log cleanup completion

        $this->ssoLogger->info('local session and cookies cleared successfully', [
            'ip' => $clientIp,
            'context' => 'sso_cascade',
        ]);

        ////////////////////////////////////////////////////////////////////////
        /// 6. generate unified broadcast response

        /**
         * we always render the 'logout_local' template. the template contains
         * the javascript "janitor" which shouts the logout event to other tabs
         * and then decides whether to redirect (if main window) or stay
         * silent (if inside an iframe).
         */

        $redirectUri = $this->generateUrl('app_logged_out', [], 0);
        $response = $this->render('app/logout_local.html.twig', [
            'redirectUri' => $redirectUri,
        ]);

        ////////////////////////////////////////////////////////////////////////
        /// 7. set cookies on the chosen response

        $response->headers->setCookie($expiredJwt);
        $response->headers->setCookie($expiredRefresh);

        ////////////////////////////////////////////////////////////////////////
        /// 8. calculate the origin of the Authorization Center (dynamic CSP frame-ancestors)

        $parsedAuthUrl = parse_url($this->authBaseUrl);
        if ($parsedAuthUrl && isset($parsedAuthUrl['scheme'], $parsedAuthUrl['host'])) {
            $authOrigin = $parsedAuthUrl['scheme'] . '://' . $parsedAuthUrl['host'];
            if (isset($parsedAuthUrl['port'])) {
                $authOrigin .= ':' . $parsedAuthUrl['port'];
            }

            $response->headers->set('Content-Security-Policy', sprintf("frame-ancestors 'self' %s", $authOrigin));
            $response->headers->remove('X-Frame-Options');
        }

        ////////////////////////////////////////////////////////////////////////
        /// 9. include additional necessary headers

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * this method is a simple redirect endpoint used as the final destination after the local cleanup process
     * (logoutLocal) completes. it ensures the user lands on a safe, unauthenticated page (app_home).
     *
     * @return Response a RedirectResponse to the application's home page.
     */
    // NOTE: route explicitly defined in config/routes.yaml to ensure it bypasses locale prefixes.
    // #[Route('~/logged_out', name: 'app_logged_out')]
    public function loggedOut(): Response
    {
        return $this->redirectToRoute('app_home');
    }
}
