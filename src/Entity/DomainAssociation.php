<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\DataType\DomainAssociationCardinality;
use App\Serializer\Groups;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['source_entity_id', 'source_name'])]
#[ORM\UniqueConstraint(columns: ['target_entity_id', 'target_name'])]
#[ORM\HasLifecycleCallbacks]
class DomainAssociation
{
    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainEntity::class, inversedBy: 'sourceAssociations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public DomainEntity $sourceEntity;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\Length(min: 1, max: 100, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public string $sourceName;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Assert\NotNull]
    #[ORM\Column(type: 'string', length: 30, enumType: DomainAssociationCardinality::class)]
    public DomainAssociationCardinality $sourceCardinality;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(0)]
    public int $sourcePosition = 0;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: DomainEntity::class, inversedBy: 'targetAssociations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public DomainEntity $targetEntity;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\Length(min: 1, max: 100, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public string $targetName;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Assert\NotNull]
    #[ORM\Column(type: 'string', length: 30, enumType: DomainAssociationCardinality::class)]
    public DomainAssociationCardinality $targetCardinality;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(0)]
    public int $targetPosition = 0;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Serializer\SerializedName('sourceEntity')]
    public function getSerializedSourceEntity(): ?array
    {
        if (!isset($this->sourceEntity)) {
            return null;
        }

        return [
            'id' => $this->sourceEntity->id,
            'name' => $this->sourceEntity->name
        ];
    }

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Serializer\SerializedName('targetEntity')]
    public function getSerializedTargetEntity(): ?array
    {
        if (!isset($this->targetEntity)) {
            return null;
        }

        return [
            'id' => $this->targetEntity->id,
            'name' => $this->targetEntity->name
        ];
    }

    #[Assert\Callback]
    public function validateProjectConsistency(ExecutionContextInterface $context): void
    {
        if (!isset($this->sourceEntity, $this->targetEntity)) {
            return;
        }

        if (null === $this->sourceEntity->id || null === $this->targetEntity->id) {
            $context->buildViolation('A domain association can only link persisted domain entities.')
                ->atPath('targetEntity')
                ->addViolation();

            return;
        }

        if ($this->sourceEntity->project !== $this->targetEntity->project) {
            $context->buildViolation('A domain association must link entities from the same project.')
                ->atPath('targetEntity')
                ->addViolation();
        }
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->id = Uuid::v4()->toRfc4122();
    }
}
