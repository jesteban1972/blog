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

    public const DEFAULT_RESULTS_PER_PAGE = 25;

    /**
     * the primary key, sourced from the Authorization Center. it is NOT autoincremented.
     */
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * PERSISTED CLUSTER I: blog-specific UI preferences
     */
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

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CommunityComment::class, cascade: ['remove'])]
    private Collection $comments;

    /**
     * volatile in-memory properties hydrated from SSO data.
     * ordered to align with auth.users database schema.
     */
    private ?string $username = null;
    private ?string $email = null;
    private array $roles = [];
    private bool $isConsented = false;
    private string $uxLanguage = 'en';
    private ?int $resultsPerPage = null;
    private ?string $avatarHash = null;
    private ?string $displayName = null;
    private ?string $bio = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    // --- identity methods ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) ($this->email ?? $this->id);
    }

    // --- volatile in-memory setters/getters (sso hydrated) ---

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

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

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

    public function isConsented(): bool
    {
        return $this->isConsented;
    }

    public function getIsConsented(): bool
    {
        return $this->isConsented;
    }

    public function setIsConsented(bool $isConsented): self
    {
        $this->isConsented = $isConsented;

        return $this;
    }

    public function getUxLanguage(): string
    {
        return $this->uxLanguage;
    }

    public function setUxLanguage(string $uxLanguage): static
    {
        $this->uxLanguage = $uxLanguage;

        return $this;
    }

    public function getResultsPerPage(): int
    {
        return $this->resultsPerPage ?? self::DEFAULT_RESULTS_PER_PAGE;
    }

    public function setResultsPerPage(?int $resultsPerPage): static
    {
        $this->resultsPerPage = $resultsPerPage;

        return $this;
    }

    public function getAvatarHash(): ?string
    {
        return $this->avatarHash;
    }

    public function setAvatarHash(?string $avatarHash): static
    {
        $this->avatarHash = $avatarHash;

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

    // --- persisted local fields accessors ---

    public function getListsOrder(): ?int
    {
        return $this->listsOrder;
    }

    public function setListsOrder(?int $listsOrder): self
    {
        $this->listsOrder = $listsOrder;

        return $this;
    }

    public function preferMarkdown(): bool
    {
        return $this->preferMarkdown;
    }

    public function setPreferMarkdown(bool $preferMarkdown): self
    {
        $this->preferMarkdown = $preferMarkdown;

        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    // --- relationship accessors ---

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
     * @return Collection<int, CommunityComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(CommunityComment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setUser($this);
        }

        return $this;
    }

    public function removeComment(CommunityComment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getUser() === $this) {
                $comment->setUser(null);
            }
        }

        return $this;
    }

    // --- lifecycle callbacks ---

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function eraseCredentials(): void
    {
        // no local credentials stored
    }

    public function __toString(): string
    {
        return (string) ($this->displayName ?? $this->username ?? $this->email ?? $this->id);
    }
}
