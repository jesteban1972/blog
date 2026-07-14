<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Security/SessionUser.php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * this class is a stateless Data Transfer Object (DTO) representing the user's identity during the SSO session. it
 * serves as the local security identity for users authenticated via the global SSO server. by implementing
 * UserInterface, it allows the application to integrate external identities into the Symfony Security component without
 * requiring a local database record. it acts as the precursor to the 'App\Entity\User' during the promotion lifecycle.
 */
class SessionUser implements UserInterface
{
    private ?int $id;
    private ?string $username;
    private ?string $email;
    private array $roles;

    private string $uxLanguage = 'en';

    private bool $isConsented = false;

    /**
     * this constructor hydrates the DTO using the claims array sourced from the local Symfony session. it maps the
     * validated identity data (id, username, email) provided by the Authorization Center. if roles are missing from the
     * SSO claims, they default to 'ROLE_PENDONCETE_USER' to ensure basic access.
     *
     * @param array $data the SSO claims containing 'userId', 'username', 'email', 'roles' and 'uxLanguage'.
     */
    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->email = $data['email'] ?? null;

        // if server provided roles use them, otherwise *default to* your app role:
        $this->roles = !empty($data['roles']) ? $data['roles'] : ['ROLE_USER'];

        $this->uxLanguage = $data['uxLanguage'] ?? 'en';
        $this->isConsented = $data['isConsented'];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUxLanguage(): string
    {
        return $this->uxLanguage;
    }

    public function isConsented(): bool
    {
        return $this->isConsented;
    }

    /**
     * methods getPassword(), getSalt(), eraseCredentials()
     * these methods are part of the Symfony\Component\Security\Core\User\UserInterface contract and are intentionally
     * empty or return null. this signifies that the SessionUser is a *stateless* identity wrapper and does not store or
     * handle any local credentials (passwords or salt), delegating all credential management to the external 'auth'
     * server.
     */

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
        // nothing to erase
    }

    /**
     * this method, required by Symfony 5.3+, returns the primary identifier used by the Symfony Security system. it is
     * consistent with your SSO setup, the user's email is used as the canonical user identifier.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * this method exports the DTO properties back into an array for session persistence. this allows the
     * SessionUserProvider to re-instantiate the object on subsequent requests using the same claim structure.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'roles' => $this->roles,
            'uxLanguage' => $this->uxLanguage,
            'isConsented' => $this->isConsented,
        ];
    }

    /**
     * this method determines if the current DTO is logically equivalent to the provided user. if the provided user is
     * an instance of App\Entity\User, this returns false, triggering the security token to switch from this DTO to the
     * persistent entity.
     *
     * @inheritdoc this method is called internally by Symfony's security listeners to detect changes between the
     *   session-stored user and the refreshed user.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        // if we are comparing a SessionUser with a persistent User (or vice versa),
        // they are NOT equal, which triggers the 'promotion' refresh.
        if (!$user instanceof self) {
            return false;
        }

        return $this->getUserIdentifier() === $user->getUserIdentifier()
            && $this->isConsented() === $user->isConsented(); // the users are no longer equal if the consent status changed in the SSO session
    }
}
