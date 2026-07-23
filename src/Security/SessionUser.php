<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Security/SessionUser.php

/**
 * this is the consolidated, universal blueprint file that can be dropped verbatim
 * into every client application.
 */

namespace App\Security;

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * this class is a stateless Data Transfer Object (DTO) representing the user's identity during the SSO session. it
 * serves as the local security identity for users authenticated via the global SSO server. by implementing
 * UserInterface and EquatableInterface, it allows the application to integrate external identities into the Symfony Security
 * component without requiring a local database record. it acts as the precursor to 'App\Entity\User' during promotion.
 */
class SessionUser implements UserInterface, EquatableInterface
{
    private ?int $id;
    private ?string $username;
    private ?string $email;
    private array $roles;
    private string $uxLanguage = 'en';
    private bool $isConsented = false;

    /**
     * hydrates the DTO using the claims array sourced from the local Symfony session.
     *
     * @param array $data the SSO claims containing 'id', 'username', 'email', 'roles', 'uxLanguage', 'isConsented'.
     * @param string $defaultRole fallback role specific to the client application (e.g., 'ROLE_USER').
     */
    public function __construct(array $data, string $defaultRole = 'ROLE_USER')
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : null;
        $this->username = $data['username'] ?? null;
        $this->email = $data['email'] ?? null;

        // use claims roles if available; otherwise fall back to application default
        $this->roles = !empty($data['roles']) ? $data['roles'] : [$defaultRole];

        $this->uxLanguage = $data['uxLanguage'] ?? 'en';
        $this->isConsented = (bool) ($data['isConsented'] ?? false);
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
        // stateless object; no local credentials stored
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

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

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        return $this->getUserIdentifier() === $user->getUserIdentifier()
            && $this->isConsented() === $user->isConsented();
    }
}
