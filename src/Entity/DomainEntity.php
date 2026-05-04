<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DomainEntityRepository;
use App\Serializer\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: DomainEntityRepository::class)]
#[ORM\UniqueConstraint(columns: ['project_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
class DomainEntity
{
    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'domainEntities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Project $project;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public string $name;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[ORM\Column(type: 'text')]
    public string $description = '';

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'entity', targetEntity: DomainProperty::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    public iterable $properties;

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Serializer\MaxDepth(1)]
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'sourceEntity', targetEntity: DomainAssociation::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['sourcePosition' => 'ASC', 'sourceName' => 'ASC'])]
    public iterable $associations;

    public function __construct()
    {
        $this->properties = new ArrayCollection();
        $this->associations = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateMemberNames(ExecutionContextInterface $context): void
    {
        $memberNames = [];

        foreach ($this->properties as $index => $property) {
            if (!isset($property->name)) {
                continue;
            }

            $this->validateMemberName($property->name, sprintf('properties[%d].name', $index), $memberNames, $context);
        }

        foreach ($this->associations as $index => $association) {
            if (!isset($association->sourceName)) {
                continue;
            }

            $this->validateMemberName($association->sourceName, sprintf('associations[%d].sourceName', $index), $memberNames, $context);
        }

    }

    private function validateMemberName(
        string $memberName,
        string $path,
        array &$memberNames,
        ExecutionContextInterface $context
    ): void {
        if (isset($memberNames[$memberName])) {
            $context->buildViolation('A member name must be unique within a domain entity.')
                ->atPath($path)
                ->addViolation();

            return;
        }

        $memberNames[$memberName] = true;
    }

    /**
     * @return list<DomainAssociation>
     */
    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Serializer\MaxDepth(1)]
    public function getTargetAssociations(): array
    {
        if (!isset($this->project, $this->project->domainEntities)) {
            return [];
        }

        $targetAssociations = [];

        foreach ($this->project->domainEntities as $domainEntity) {
            if (!isset($domainEntity->associations)) {
                continue;
            }

            foreach ($domainEntity->associations as $association) {
                if ($this->isTargetAssociation($association)) {
                    $targetAssociations[] = $association;
                }
            }
        }

        usort(
            $targetAssociations,
            static function (DomainAssociation $left, DomainAssociation $right): int {
                $positionComparison = $left->targetPosition <=> $right->targetPosition;

                if (0 !== $positionComparison) {
                    return $positionComparison;
                }

                return $left->targetName <=> $right->targetName;
            }
        );

        return $targetAssociations;
    }

    private function isTargetAssociation(DomainAssociation $association): bool
    {
        if (!isset($association->targetEntity)) {
            return false;
        }

        if ($association->targetEntity === $this) {
            return true;
        }

        if (!isset($association->targetEntity->id, $this->id)) {
            return false;
        }

        return $association->targetEntity->id === $this->id;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->id = Uuid::v4()->toRfc4122();
    }
}
