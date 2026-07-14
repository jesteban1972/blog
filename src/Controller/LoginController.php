<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/LoginController.php

namespace App\Controller;

use App\Service\JwtDecoderService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * this controller handles the initiation and completion of the Single Sign-On (SSO) process for one client
 * application within the pendoncete.org domain. it manages redirecting the user to the central auth server for login
 * and processes the subsequent callback to establish a local user session using the JWT set by the SSO server.
 */
class LoginController extends AbstractController
{
    /**
     * this constructor method injects environment configuration needed for the SSO handshake.
     *
     * @param LoggerInterface $ssoLogger logger for tracking token issuance and other security events.
     * @param string $appSecret the application's secret key, used to sign the redirect_uri (HMAC).
     * @param string $authBaseUrl the base URL of the central 'auth' SSO server (e.g., 'https://auth.pendoncete.org').
     * @param string $callbackUrl the full URL of the local /login/callback endpoint.
     */
    public function __construct(
        private LoggerInterface $ssoLogger,
        private string          $appSecret,
        private string          $authBaseUrl,
        private string          $callbackUrl,
    ) {}

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

        return $this->render('app/login.html.twig', [
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
}
