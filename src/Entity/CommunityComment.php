<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Entity/CommunityComment.php

namespace App\Entity;

use App\Repository\CommunityCommentsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunityCommentsRepository::class)]
#[ORM\Table(name: 'community_comments')]
#[ORM\Index(name: 'idx_comment_post', columns: ['post_id'])]
class CommunityComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AuthUser::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?AuthUser $authUser = null;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'your comment cannot be empty.')]
    private ?string $content = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getAuthUser(): ?AuthUser { return $this->authUser; }

    public function setAuthUser(?AuthUser $authUser): self
    {
        $this->authUser = $authUser;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->authUser ? $this->authUser->getId() : null;
    }

    public function getPost(): ?Post { return $this->post; }

    public function setPost(?Post $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function getParent(): ?self { return $this->parent; }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function getContent(): ?string { return $this->content; }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
