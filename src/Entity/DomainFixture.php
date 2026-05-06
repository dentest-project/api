<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DomainFixtureRepository;
use App\Serializer\Groups;
use App\Validator\ValidDomainFixture;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DomainFixtureRepository::class)]
#[ORM\UniqueConstraint(columns: ['entity_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[ValidDomainFixture]
class DomainFixture
{
    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Serializer\Ignore]
    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Project $project;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainEntity::class)]
    #[ORM\JoinColumn(nullable: false)]
    public DomainEntity $entity;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public string $name;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[Serializer\MaxDepth(1)]
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'fixture', targetEntity: DomainFixturePropertyValue::class, cascade: ['all'], orphanRemoval: true)]
    public iterable $propertyValues;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[Serializer\MaxDepth(1)]
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'fixture', targetEntity: DomainFixtureAssociationValue::class, cascade: ['all'], orphanRemoval: true)]
    public iterable $associationValues;

    public function __construct()
    {
        $this->propertyValues = new ArrayCollection();
        $this->associationValues = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->id = Uuid::v4()->toRfc4122();
    }
}
