<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Entity/AuthUser.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * this entity points to the 'users' table in the 'auth' database.
 */
#[ORM\Entity]
#[ORM\Table(name: 'auth.users')] // the prefix tells mysql to switch databases
class AuthUser
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $username = null;

    public function getId(): ?int { return $this->id; }
    public function getUsername(): ?string { return $this->username; }
}
