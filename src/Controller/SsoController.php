<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/SsoController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Service\JwtDecoderService;
use Psr\Log\LoggerInterface;
use App\Service\CookieService;

/**
 * this controller manages the entire authentication lifecycle for a client application
 * within the pendoncete.org SSO ecosystem.
 *
 * it consolidates all credential-delegating entry points, callback processing, and session cleanup sequences:
 *  i) signup & login initiation: delegates user registration and authentication to the central auth server
 *   by generating HMAC-signed return URLs.
 *  ii) callback handling: processes successful logins from the SSO server, validating incoming JWTs
 *   and establishing local session state.
 *  iii)  logout orchestration: manages global token revocation at the authorization center,
 *   pre-emptively clears local security state, and executes background iframe cleanup cascades.
 */

class SsoController extends AbstractController
{
    /**
     * this constructor method injects environment configuration needed for the SSO handshake.
     *
     * @param CookieService $cookieService service responsible for generating expired cookies with correct
     *  domain/security settings.
     * @param TokenStorageInterface $tokenStorage Symfony service used to clear the local security token.
     * @param LoggerInterface $ssoLogger logger for tracking token issuance and other security events.
     * @param string $appSecret the application's secret key, used to sign the redirect_uri (HMAC).
     * @param string $authBaseUrl the base URL of the central 'auth' SSO server (e.g., 'https://auth.pendoncete.org').
     * @param string $callbackUrl the full URL of the local /login/callback endpoint.
     */
    public function __construct(
        private CookieService $cookieService,
        private TokenStorageInterface $tokenStorage,
        private LoggerInterface $ssoLogger,
        private string $appSecret,
        private string $authBaseUrl,
        private string $callbackUrl,
    ) {}

    #[Route('/signup', name: 'app_signup', methods: ['GET', 'POST'])]
    public function signup(): Response
    {
        ////////////////////////////////////////////////////////////////////////
        /// generate signature to send to auth server

        $authUrl = $this->authBaseUrl . '/signup';
        $redirectUri = $this->callbackUrl;
        $sig = hash_hmac('sha256', $redirectUri, $this->appSecret);

        ////////////////////////////////////////////////////////////////////////
        /// render template

        return $this->render('auth_signup.html.twig', [
            'authUrl' => $authUrl,
            'redirectUri' => $redirectUri,
            'sig' => $sig,
        ]);
    }

    /**
     * this method initiates the SSO login process (phase 1). it generates an HMAC signature based on the intended
     * redirect_uri (the local callback URL), and renders a template which links the user to the SSO server's /login
     * endpoint, passing the signed redirect_uri to guarantee a safe return path.
     *
     * @param Request $request the incoming request, used to access and invalidate the local session.
     * @return Response a rendered template containing the redirect link to the SSO server.
     */
    // NOTE: route explicitly defined in config/routes.yaml to ensure it bypasses locale prefixes.
    // #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        $clientIp = $request->getClientIp();


        ////////////////////////////////////////////////////////////////////////
        /// log initialization of login process

        $this->ssoLogger->info('SSO login initiation started (phase 1)', [
            'ip' => $clientIp,
            'auth_url' => $this->authBaseUrl . '/login',
            'callback_url' => $this->callbackUrl,
        ]);


        ////////////////////////////////////////////////////////////////////////
        /// generate signature to send to auth server

        $authUrl = $this->authBaseUrl . '/login';
        $redirectUri = $this->callbackUrl;
        $sig = hash_hmac('sha256', $redirectUri, $this->appSecret);


        ////////////////////////////////////////////////////////////////////////
        /// render template

        return $this->render('app/auth_login.html.twig', [
            'authUrl' => $authUrl,
            'redirectUri' => $redirectUri,
            'sig' => $sig,
        ]);
    }

    /**
     * this method handles the successful return redirect from the SSO server (phase 4). it is the core SSO integration
     * point: it retrieves the 'pendoncete_jwt' cookie, validates its signature via JwtDecoder, extracts the user claims
     * (id, username, etc.), and stores this information in the local session ('user'). this session data is then
     * used by the local security provider to fully authenticate the user for the current and future requests.
     *
     * @param Request $request the incoming request, used to access cookies and the session.
     * @param JwtDecoderService $jwtDecoder the service used to decode and verify the JWT cookie signature.
     * @return Response a RedirectResponse, either to the intended URL or the application home page.
     */
    // NOTE: route explicitly defined in config/routes.yaml to ensure it bypasses locale prefixes.
    // #[Route('/login/callback', name: 'app_login_callback')]
    public function callback(Request $request, JwtDecoderService $jwtDecoder): Response
    {
        $clientIp = $request->getClientIp();

        ////////////////////////////////////////////////////////////////////////
        /// 1) get JWT cookie from auth server

        $jwt = $request->cookies->get('pendoncete_jwt');

        if (!$jwt) { // JWT missing: redirect back to auth server login

            $this->ssoLogger->warning('SSO callback failed: JWT cookie missing', [
                'ip' => $clientIp,
                'referer' => $request->headers->get('referer')
            ]);

            return $this->redirect(
                $this->authBaseUrl . '/login?redirect_uri=' . urlencode($request->getUri())
            );
        }


        ////////////////////////////////////////////////////////////////////////
        /// 2) decode JWT

        $userData = $jwtDecoder->decode($jwt);
        if (!$userData) { // JWT invalid: redirect back to auth server login

            $this->ssoLogger->error('SSO callback failed: JWT decoding/signature verification failed', [
                'ip' => $clientIp,
                'jwt_excerpt' => substr((string)$jwt, 0, 15) . '...'
            ]);

            return $this->redirect(
                $this->authBaseUrl . '/login?redirect_uri=' . urlencode($request->getUri())
            );
        }


        ////////////////////////////////////////////////////////////////////////
        /// 3) store user info in local session

        $session = $request->getSession();
        $session->set('user', [
            'id' => $userData['id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'roles' => $userData['roles'],
        ]);

        $this->ssoLogger->info('SSO local session established', [
            'ip' => $clientIp,
            'user_id' => $userData['id'],
            'email' => $userData['email'],
            'has_refresh_cookie' => $request->cookies->has('pendoncete_refresh'),
        ]);


        ////////////////////////////////////////////////////////////////////////
        /// 4) store refresh token in session, so that /refresh route can be used locally

        if (isset($userData['refresh_token'])) {
            $session->set('refresh_token', $userData['refresh_token']);
        }


        ////////////////////////////////////////////////////////////////////////
        /// 5) redirect to intended URL or home

        $intended = $session->get('intended_url');

        $this->ssoLogger->debug('redirecting user after callback', [
            'target' => $intended ?? 'app_home'
        ]);

        if ($intended) {
            $session->remove('intended_url');
            return $this->redirect($intended);
        }

        return $this->redirectToRoute('app_home');
    }

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
    // #[Route('/logout', name: 'app_logout', methods: ['GET'])]
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
    // #[Route('/logout_local', name: 'app_logout_local', methods: ['GET'])]
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
    // #[Route('/logged_out', name: 'app_logged_out')]
    public function loggedOut(): Response
    {
        return $this->redirectToRoute('app_home');
    }
}
