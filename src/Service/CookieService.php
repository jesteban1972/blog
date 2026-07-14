<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Service/CookieService.php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * this service is responsible for the lifecycle management of SSO-related cookies within the pendoncete.org ecosystem.
 * it handles the generation of secure, cross-subdomain Cookie objects, the invalidation of credentials during logout
 * sequences, and the reliable discovery of the active JWT token.
 *
 * by abstracting token retrieval, this service bridges the gap between the incoming browser state (cookies) and the
 * volatile authentication state (refreshed tokens in request attributes), ensuring that client-side components receive
 * valid credentials even during a "silent refresh" cycle.
 */
class CookieService
{
    /**
     * this constructor method initializes the service with the common cookie domain.
     *
     * @param RequestStack $requestStack used to access the current request for token discovery.
     * @param string $cookieDomain the domain to which the cookies will be scoped (e.g., '.pendoncete.org' or
     *    '.pendoncete.localhost'). this is critical for sharing cookies across all subdomains in the SSO ecosystem.
     */
    public function __construct(
        private RequestStack $requestStack,
        private string $cookieDomain
    ) {}

    /**
     * this method determines the required 'Secure' and 'SameSite' attributes based on the environment. cookies are set
     * as insecure (Secure=false) for local development domains (localhost, 127.0.0.1) where HTTPS is typically
     * unavailable. HTTPS is mandatory for production SSO cookies.
     *
     * @return bool true if the environment requires Secure=true (i.e., not a local development setup).
     */
    private function isSecure(): bool
    {
        return !(
            str_contains($this->cookieDomain, 'localhost') ||
            str_contains($this->cookieDomain, '127.0.0.1')
        );
    }

    /**
     * this method returns specially constructed Cookie objects designed to immediately expire the 'pendoncete_jwt'
     * (Access Token) and 'pendoncete_refresh' (Refresh Token) cookies in the browser. it is used when the client
     * application performs a local logout cleanup, as part of the broadcast logout sequence or /logout-local endpoint.
     * the method correctly adjusts the SameSite policy ('None' or 'Lax') based on the 'Secure' flag determined by
     * isSecure() for cross-subdomain compatibility.
     *
     * @return array an array containing the two expired Symfony\Component\HttpFoundation\Cookie objects.
     */
    public function expireJwtAndRefreshCookies(): array
    {
        $isSecure = $this->isSecure();
        $sameSite = $isSecure ? 'None' : 'Lax';
        $expiredAt = new \DateTimeImmutable('-1 day');

        $jwtCookie = Cookie::create(
            'pendoncete_jwt',
            '',
            $expiredAt,
            '/',
            $this->cookieDomain,
            $isSecure,
            true,
            false,
            $sameSite
        );

        $refreshCookie = Cookie::create(
            'pendoncete_refresh',
            '',
            $expiredAt,
            '/',
            $this->cookieDomain,
            $isSecure,
            true,
            false,
            $sameSite
        );

        return [$jwtCookie, $refreshCookie];
    }

    /**
     * this method retrieves the current JWT token by inspecting both the incoming request cookies and the internal
     * request attributes. this dual-source approach is essential for the "silent refresh" mechanism:
     *    1. it first checks the browser-provided 'pendoncete_jwt' cookie.
     *    2. if the cookie is missing or expired, it searches the '_sso_new_cookies' attribute, where the
     *       AuthBridgeAuthenticator stores newly minted tokens during a background refresh cycle before they are
     *       officially persisted to the browser via the Response headers.
     *
     * @return string|null the raw JWT string if discovered, or null if the SSO session is unauthenticated.
     */
    public function getJwt(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }


        ////////////////////////////////////////////////////////////////////////
        /// 1. primary discovery: incoming browser cookies

        $jwt = $request->cookies->get('pendoncete_jwt');
        if ($jwt) return $jwt;


        ////////////////////////////////////////////////////////////////////////
        /// 2. secondary discovery: volatile refresh state

        /**
         * if the token was just refreshed during this request cycle, it resides in the attributes bag, waiting to be
         * dispatched in a Set-Cookie header.
         */

        $newCookies = $request->attributes->get('_sso_new_cookies', []);
        foreach ($newCookies as $cookieString) {
            if (preg_match('/pendoncete_jwt=([^;]+)/', (string)$cookieString, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
