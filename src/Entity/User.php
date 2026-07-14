<?php
declare(strict_types=1);
// file ~/Sites/annales/src/Entity/User.php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * the 'shadow entity' representing a locally persisted user identity. architecturally, this entity stores only
 * local-specific metadata (annales specific fields, preferences, timestamps). identity validation and volatile
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
     * PERSISTED CLUSTER I: annales-specific fields
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthdate = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $defaultGenre = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $description1 = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $description2 = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $description3 = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $description4 = null;


    /**
     * PERSISTED CLUSTER II: UI preferences
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $resultsPerPage = 25;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $listsOrder = 1;

    /**
     * PERSISTED CLUSTER III: lifecycle & auditing
     */

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;


    /**
     * these volatile properties are NOT persisted in pendoncete.users table. they are populated in memory from the
     * SessionUser data.
     */
    private ?string $username = null;
    private ?string $email = null;
    private array $roles = [];

    private string $uxLanguage = 'en';

    private bool $isConsented = false;

    /**
     * RELATIONSHIPS: Locally anchored to the integer ID.
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Locus::class, orphanRemoval: true)]
    private Collection $loca;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Praxis::class, orphanRemoval: true)]
    private Collection $practica;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Amor::class, orphanRemoval: true)]
    private Collection $amores;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Copulatio::class, orphanRemoval: true)]
    private Collection $copulationes;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Kind::class, orphanRemoval: true)]
    private Collection $kinds;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Country::class, orphanRemoval: true)]
    private Collection $countries;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        $this->loca = new ArrayCollection();
        $this->practica = new ArrayCollection();
        $this->amores = new ArrayCollection();
        $this->copulationes = new ArrayCollection();
        $this->kinds = new ArrayCollection();
        $this->countries = new ArrayCollection();
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

    // --- Shadow Properties (Non-Persisted) ---

    /**
     * this method is the non-persisted setter for the username claim.
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * this method is the non-persisted setter for the email claim.
     */
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
        // simply point it to the getter we verified earlier
        return $this->getIsConsented();
    }

    public function setIsConsented(bool $isConsented): self
    {
        $this->isConsented = $isConsented;

        return $this;
    }

    // --- Persisted Local Fields accessors ---

    public function getBirthdate(): ?\DateTimeInterface { return $this->birthdate; }
    public function setBirthdate(?\DateTimeInterface $birthdate): self { $this->birthdate = $birthdate; return $this; }

    public function getDefaultGenre(): ?int { return $this->defaultGenre; }
    public function setDefaultGenre(?int $defaultGenre): self { $this->defaultGenre = $defaultGenre; return $this; }

    public function getDescription1(): ?string { return $this->description1; }
    public function setDescription1(?string $description1): self { $this->description1 = $description1; return $this; }

    public function getDescription2(): ?string { return $this->description2; }
    public function setDescription2(?string $description2): self { $this->description2 = $description2; return $this; }

    public function getDescription3(): ?string { return $this->description3; }
    public function setDescription3(?string $description3): self { $this->description3 = $description3; return $this; }

    public function getDescription4(): ?string { return $this->description4; }
    public function setDescription4(?string $description4): self { $this->description4 = $description4; return $this; }

    public function getResultsPerPage(): ?int { return $this->resultsPerPage; }
    public function setResultsPerPage(?int $resultsPerPage): self { $this->resultsPerPage = $resultsPerPage; return $this; }

    public function getListsOrder(): ?int { return $this->listsOrder; }
    public function setListsOrder(?int $listsOrder): self { $this->listsOrder = $listsOrder; return $this; }

    public function getLastLogin(): ?\DateTimeInterface { return $this->lastLogin; }
    public function setLastLogin(?\DateTimeInterface $lastLogin): static { $this->lastLogin = $lastLogin; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    // --- Lifecycle Callbacks ---

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // --- Relationship Accessors ---

    /** @return Collection<int, Locus> */
    public function getLoca(): Collection { return $this->loca; }
    public function addLoca(Locus $loca): self { if (!$this->loca->contains($loca)) { $this->loca[] = $loca; $loca->setUser($this); } return $this; }
    public function removeLoca(Locus $loca): self { if ($this->loca->removeElement($loca)) { if ($loca->getUser() === $this) $loca->setUser(null); } return $this; }

    /** @return Collection<int, Praxis> */
    public function getPractica(): Collection { return $this->practica; }
    public function addPractica(Praxis $practica): self { if (!$this->practica->contains($practica)) { $this->practica[] = $practica; $practica->setUser($this); } return $this; }
    public function removePractica(Praxis $practica): self { if ($this->practica->removeElement($practica)) { if ($practica->getUser() === $this) $practica->setUser(null); } return $this; }

    /** @return Collection<int, Amor> */
    public function getAmores(): Collection { return $this->amores; }
    public function addAmor(Amor $amor): self { if (!$this->amores->contains($amor)) { $this->amores[] = $amor; $amor->setUser($this); } return $this; }
    public function removeAmor(Amor $amor): self { if ($this->amores->removeElement($amor)) { if ($amor->getUser() === $this) $amor->setUser(null); } return $this; }

    /** @return Collection<int, Copulatio> */
    public function getCopulationes(): Collection { return $this->copulationes; }
    public function addCopulatio(Copulatio $copulatio): self { if (!$this->copulationes->contains($copulatio)) { $this->copulationes[] = $copulatio; $copulatio->setUser($this); } return $this; }
    public function removeCopulatio(Copulatio $copulatio): self { if ($this->copulationes->removeElement($copulatio)) { if ($copulatio->getUser() === $this) $copulatio->setUser(null); } return $this; }

    /** @return Collection<int, Kind> */
    public function getKinds(): Collection { return $this->kinds; }
    public function addKind(Kind $kind): self { if (!$this->kinds->contains($kind)) { $this->kinds[] = $kind; $kind->setUser($this); } return $this; }
    public function removeKind(Kind $kind): self { if ($this->kinds->removeElement($kind)) { if ($kind->getUser() === $this) $kind->setUser(null); } return $this; }

    /** @return Collection<int, Country> */
    public function getCountries(): Collection { return $this->countries; }
    public function addCountry(Country $country): self { if (!$this->countries->contains($country)) { $this->countries[] = $country; $country->setUser($this); } return $this; }
    public function removeCountry(Country $country): self { if ($this->countries->removeElement($country)) { if ($country->getUser() === $this) $country->setUser(null); } return $this; }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // No local credentials stored.
    }

    public function __toString(): string
    {
        return (string) ($this->email ?? $this->username ?? $this->id);
    }
}
