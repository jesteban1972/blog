<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Entity/Copulatio.php

namespace App\Entity;

use App\Repository\CopulationesRepository; // TODO: Undefined class 'CopulationesRepository'
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CopulationesRepository::class)]
#[ORM\Table(name: 'copulationes')]
class Copulatio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'copulationes')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'copulationes')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Category $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function __toString(): string
    {
        return json_encode([
            'id' => $this->getId(),
            'post' => $this->getPost()?->getId(),
            'category' => $this->getCategory()?->getId(),
        ]);
    }
}
