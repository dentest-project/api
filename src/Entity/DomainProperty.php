<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\DataType\DomainPropertyType;
use App\Serializer\Groups;
use App\Validator\ValidDomainProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['entity_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[ValidDomainProperty]
class DomainProperty
{
    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainEntity::class, inversedBy: 'properties')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public DomainEntity $entity;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public string $name;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'text')]
    public string $description = '';

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'integer')]
    #[Assert\GreaterThanOrEqual(0)]
    public int $position = 0;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Assert\NotNull]
    #[ORM\Column(type: 'string', length: 30, enumType: DomainPropertyType::class)]
    public DomainPropertyType $type;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    public bool $nullable = false;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'property', targetEntity: DomainPropertyConstraint::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['kind' => 'ASC'])]
    public Collection $constraints;

    public function __construct()
    {
        $this->constraints = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->id = Uuid::v4()->toRfc4122();
    }
}
