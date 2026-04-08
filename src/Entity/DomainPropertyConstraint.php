<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\DataType\DomainPropertyConstraintKind;
use App\Entity\DataType\DomainPropertyStringFormat;
use App\Serializer\Groups;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['property_id', 'kind'])]
#[ORM\HasLifecycleCallbacks]
class DomainPropertyConstraint
{
    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainProperty::class, inversedBy: 'constraints')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public DomainProperty $property;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Assert\NotNull]
    #[ORM\Column(type: 'string', length: 30, enumType: DomainPropertyConstraintKind::class)]
    public DomainPropertyConstraintKind $kind;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $stringValue = null;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $integerValue = null;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'decimal', precision: 20, scale: 6, nullable: true)]
    public ?string $decimalValue = null;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'string', length: 30, nullable: true, enumType: DomainPropertyStringFormat::class)]
    public ?DomainPropertyStringFormat $format = null;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->id = Uuid::v4()->toRfc4122();
    }
}
