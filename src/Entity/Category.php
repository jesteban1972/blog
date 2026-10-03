<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Entity/Category.php

namespace App\Entity;

use App\Repository\CategoriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * representing a category for grouping blog posts.
 *
 * architectural translation suggestion: explicit Locale Entity / one-to-many translation:
 * create a separate CategoryTranslation entity:
 * - Category: id, slug, parent_id
 * - CategoryTranslation: id, category_id, locale (e.g., 'en', 'es'), name, description
 * this pattern scales infinitely to new languages (Arabic, French, German, Catalan, etc.)
 * without table schema alterations.
 * (Libraries like Gedmo Translatable or KnpLabs DoctrineBehaviors automate this pattern).
 */
#[ORM\Entity(repositoryClass: CategoriesRepository::class)]
#[ORM\Table(name: 'categories')]
#[ORM\UniqueConstraint(name: 'uniq_category_slug', columns: ['slug'])]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::STRING, length: 510, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'self_meaning', type: Types::TEXT, nullable: true)]
    private ?string $selfMeaning = null;

    /**
     * PERSISTED CLUSTER: relationships
     */
    /**
     * @var Collection<int, Copulatio>
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Copulatio::class)]
    private Collection $copulationes;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    public function __construct()
    {
        $this->copulationes = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getSelfMeaning(): ?string
    {
        return $this->selfMeaning;
    }

    public function setSelfMeaning(?string $selfMeaning): static
    {
        $this->selfMeaning = $selfMeaning;

        return $this;
    }

    /**
     * @return Collection<int, Copulatio>
     */
    public function getCopulationes(): Collection
    {
        return $this->copulationes;
    }

    public function addCopulatio(Copulatio $copulatio): self
    {
        if (!$this->copulationes->contains($copulatio)) {
            $this->copulationes->add($copulatio);
            $copulatio->setCategory($this);
        }

        return $this;
    }

    public function removeCopulatio(Copulatio $copulatio): self
    {
        if ($this->copulationes->removeElement($copulatio)) {
            if ($copulatio->getCategory() === $this) {
                $copulatio->setCategory(null);
            }
        }

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->name ?? 'new category');
    }
}
