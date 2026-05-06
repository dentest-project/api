<?php

declare(strict_types=1);

namespace App\Entity;

use App\Serializer\Groups;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['fixture_id', 'property_id'])]
#[ORM\HasLifecycleCallbacks]
class DomainFixturePropertyValue
{
    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainFixture::class, inversedBy: 'propertyValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public DomainFixture $fixture;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainProperty::class)]
    #[ORM\JoinColumn(nullable: false)]
    public DomainProperty $property;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $stringValue = null;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $integerValue = null;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $decimalValue = null;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[ORM\Column(type: 'boolean', nullable: true)]
    public ?bool $booleanValue = null;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->id = Uuid::v4()->toRfc4122();
    }
}
