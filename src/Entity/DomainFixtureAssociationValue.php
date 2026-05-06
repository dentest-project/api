<?php

declare(strict_types=1);

namespace App\Entity;

use App\Serializer\Groups;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['fixture_id', 'association_id', 'target_fixture_id'])]
#[ORM\HasLifecycleCallbacks]
class DomainFixtureAssociationValue
{
    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainFixture::class, inversedBy: 'associationValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public DomainFixture $fixture;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainAssociation::class)]
    #[ORM\JoinColumn(nullable: false)]
    public DomainAssociation $association;

    #[Serializer\Groups([Groups::ReadDomainFixture->value])]
    #[Serializer\MaxDepth(1)]
    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainFixture::class)]
    #[ORM\JoinColumn(nullable: false)]
    public DomainFixture $targetFixture;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->id = Uuid::v4()->toRfc4122();
    }
}
