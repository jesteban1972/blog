<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Entity/User.php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * the 'shadow entity' representing a locally persisted user identity. architecturally, this entity stores only
 * local-specific metadata (blog specific fields, preferences, timestamps). identity validation and volatile
 * attributes (email, roles) are delegated to the Authorization Center and are hydrated into this object in-memory by
 * the SessionUserProvider during the 'Frankenstein' merger.
 */
#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface
{
    /**
     * PENDONCETE is used in several places, e.g. in mode showroom, to make the code more readable.
     */
    public const PENDONCETE = 1;

    /**
     * the primary Key, sourced from the Authorization Center. it is NOT autoincremented.
     */
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;


    /**
     * PERSISTED CLUSTER I: UI preferences
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $resultsPerPage = 25;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $listsOrder = 1;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $preferMarkdown = true;


    /**
     * PERSISTED CLUSTER II: lifecycle & auditing
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;


    /**
     * PERSISTED CLUSTER III: relationships
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Post::class, cascade: ['remove'])]
    private Collection $posts;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Comment::class, cascade: ['remove'])]
    private Collection $comments;


    /**
     * these volatile properties are NOT persisted in the local users table. they are populated in memory
     * from the session/auth database data.
     */
    private ?string $username = null;
    private ?string $email = null;
    private ?string $displayName = null;
    private ?string $bio = null;
    private array $roles = [];

    private string $uxLanguage = 'en';

    private bool $isConsented = false;


    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    // --- Identity Methods ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * this method is required by UserInterface. it returns the unique identity string. in this ecosystem, we prioritize
     * the email if hydrated, falling back to the integer ID provided by the SSO server.
     */
    public function getUserIdentifier(): string
    {
        return (string) ($this->email ?? $this->id);
    }

    // --- Shadow Properties (Non-Persisted / Hydrated from Auth DB) ---

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    /**
     * this method returns the roles granted to the user, merging volatile SSO roles with the mandatory local ROLE_USER.
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * Non-persisted getter for the language string (e.g., 'el', 'en')
     */
    public function getUxLanguage(): string
    {
        return $this->uxLanguage;
    }

    /**
     * Non-persisted setter for the language string
     */
    public function setUxLanguage(string $uxLanguage): static
    {
        $this->uxLanguage = $uxLanguage;
        return $this;
    }

    public function getIsConsented(): bool
    {
        return $this->isConsented;
    }

    /**
     * this acts as an alias (Symfony's authenticator and twig often look for "is[PropertyName]" for boolean values.
     */
    public function isConsented(): bool
    {
        return $this->getIsConsented();
    }

    public function setIsConsented(bool $isConsented): self
    {
        $this->isConsented = $isConsented;

        return $this;
    }

    // --- Persisted Local Fields accessors ---

    public function getResultsPerPage(): ?int { return $this->resultsPerPage; }
    public function setResultsPerPage(?int $resultsPerPage): self { $this->resultsPerPage = $resultsPerPage; return $this; }

    public function getListsOrder(): ?int { return $this->listsOrder; }
    public function setListsOrder(?int $listsOrder): self { $this->listsOrder = $listsOrder; return $this; }

    public function preferMarkdown(): bool { return $this->preferMarkdown; }
    public function setPreferMarkdown(bool $preferMarkdown): self { $this->preferMarkdown = $preferMarkdown; return $this; }

    public function getLastLogin(): ?\DateTimeInterface { return $this->lastLogin; }
    public function setLastLogin(?\DateTimeInterface $lastLogin): static { $this->lastLogin = $lastLogin; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    // --- Relationship Accessors ---

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setUser($this);
        }

        return $this;
    }

    public function removePost(Post $post): self
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getUser() === $this) {
                $post->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setUser($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getUser() === $this) {
                $comment->setUser(null);
            }
        }

        return $this;
    }

    // --- Lifecycle Callbacks ---

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // No local credentials stored.
    }

    public function __toString(): string
    {
        return (string) ($this->displayName ?? $this->username ?? $this->email ?? $this->id);
    }
}
