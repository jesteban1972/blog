<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Entity/Post.php

namespace App\Entity;

use App\Enum\PostDiffusio;
use App\Enum\PostStatus;
use App\Repository\PostsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * representing a blog post written by a shadow user.
 */
#[ORM\Entity(repositoryClass: PostsRepository::class)]
#[ORM\Table(name: 'posts')]
#[ORM\Index(name: 'idx_post_created', fields: ['createdAt'])]
#[ORM\Index(name: 'idx_post_published', fields: ['publishedAt'])]
#[ORM\UniqueConstraint(name: 'uniq_post_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $rating = 0;

    #[ORM\Column(name: 'is_favorite', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isFavorite = false;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(type: Types::STRING, length: 2, options: ['default' => 'en'])]
    private string $language = 'en';

    #[ORM\Column(type: Types::INTEGER, nullable: true, enumType: PostDiffusio::class)]
    private ?PostDiffusio $diffusio = null;

    /**
     * PERSISTED CLUSTER: lifecycle & auditing
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    /**
     * PERSISTED CLUSTER: relationships
     */
    /**
     * @var Collection<int, CommunityComment>
     */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: CommunityComment::class, cascade: ['remove'])]
    private Collection $comments;

    /**
     * @var Collection<int, Copulatio>
     */
    #[ORM\OneToMany(
        mappedBy: 'post',
        targetEntity: Copulatio::class,
        cascade: ['persist']
    )]
    private Collection $copulationes;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->comments = new ArrayCollection();
        $this->copulationes = new ArrayCollection();
        $this->rating = 0;
        $this->isFavorite = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): self
    {
        if ($rating < 0 || $rating > 5) {
            throw new \InvalidArgumentException('rating must be an integer between 0 and 5.');
        }

        $this->rating = $rating;

        return $this;
    }

    public function getIsFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function setIsFavorite(bool $isFavorite): self
    {
        $this->isFavorite = $isFavorite;

        return $this;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getDiffusio(): ?PostDiffusio
    {
        return $this->diffusio;
    }

    public function setDiffusio(?PostDiffusio $diffusio): self
    {
        $this->diffusio = $diffusio;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getStatus(): PostStatus
    {
        return PostStatus::fromPost($this);
    }

    /**
     * returns true if the post is published and the publication date has passed.
     */
    public function isPublished(): bool
    {
        return $this->publishedAt !== null && $this->publishedAt <= new \DateTime();
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
            $comment->setPost($this);
        }

        return $this;
    }

    public function removeComment(CommunityComment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getPost() === $this) {
                $comment->setPost(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Copulatio>
     */
    public function getCopulationes(): Collection
    {
        return $this->copulationes;
    }

    public function setCopulationes(Collection $copulationes): self
    {
        $this->copulationes = $copulationes;

        return $this;
    }

    public function addCopulatio(Copulatio $copulatio): self
    {
        foreach ($this->copulationes as $existing) {
            if ($existing->getCategory() === $copulatio->getCategory() && null !== $copulatio->getCategory()) {
                return $this;
            }
        }

        if (!$this->copulationes->contains($copulatio)) {
            $this->copulationes->add($copulatio);
            $copulatio->setPost($this);
        }

        return $this;
    }

    public function removeCopulatio(Copulatio $copulatio): self
    {
        if ($this->copulationes->removeElement($copulatio)) {
            if ($copulatio->getPost() === $this) {
                $copulatio->setPost(null);
            }
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function __toString(): string
    {
        return (string) ($this->title ?? 'untitled post');
    }
}
