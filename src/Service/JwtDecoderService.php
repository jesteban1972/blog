<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Service/JwtDecoderService.php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;

/**
 * this is the JWT Decoder Service for the client application 'annales'. it is critical for the Single Sign-On (SSO)
 * security model: it is the responsible for reading the access token ('pendoncete_jwt') issued by the central 'auth'
 * application:
 *    i) it verifies the token's authenticity using the **SSO server's public key (RS256 algorithm)** * read from
 *       disk. if the signature is valid, it guarantees the claims were set by the trusted Identity Provider.
 *    ii) it exposes methods to decode the token and retrieve the user's authenticated claims (user_id, username, roles,
 *       etc.) for local session establishment.
 */
class JwtDecoderService
{
    private string $publicKey;

    /**
     * this constructor method initializes the decoder by reading the SSO server's public key from the file system.
     * this key is essential for verifying the RSA signature of the incoming JWT. verification confirms the token
     * originated from the trusted 'auth' server and has not been tampered with.
     *
     * @param LoggerInterface $ssoLogger dedicated logger for tracking SSO handshakes.
     * @param string $publicKeyPath the absolute path to the public.pem file, which contains the SSO server's public key.
     * @throws \InvalidArgumentException if the public key file is not found.
     */
    public function __construct(
        private LoggerInterface $ssoLogger,
        private string $publicKeyPath,
    )
    {
        // read key contents from file
        if (!file_exists($publicKeyPath)) {
            throw new \InvalidArgumentException(sprintf('public key not found at %s', $publicKeyPath));
        }

        $this->publicKey = file_get_contents($publicKeyPath);
    }

    /**
     * this method decodes and verifies the signature of a raw JWT string using the stored public key and the RS256
     * algorithm. if the token is successfully verified, its claims (payload) are returned as an array. if the signature
     * is invalid, the token has expired (exp), or any other JWT validation fails, an exception is caught, and the
     * method returns null, signifying an invalid token for local authentication.
     *
     * @param string $token the raw JWT access token string (e.g., 'pendoncete_jwt' cookie value).
     * @return array|null the token payload (claims) or null on failure (e.g., invalid signature, expiration).
     */
    public function decode(string $token): ?array
    {
        try {

            /**
             * we allow a 60-second leeway to account for clock skew between the Auth server
             * and the client applications. this is especially critical in Docker environments
             * where container clocks might drift by a few milliseconds, which otherwise
             * triggers 'BeforeValidException' (nbf) or 'ExpiredException' (exp).
             */

            JWT::$leeway = 60;

            return (array) JWT::decode($token, new Key($this->publicKey, 'RS256'));

        } catch (\Throwable $e) {

            $this->ssoLogger->error(sprintf('SSO JWT decode failure: %s at %s', $e->getMessage(), $e->getFile()));
            return null;
        }
    }
}
