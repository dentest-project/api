<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use App\Serializer\Groups;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Index(columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Project
{
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_PRIVATE = 'private';

    #[Serializer\Groups([
        Groups::ListProjects->value,
        Groups::ReadFeature->value,
        Groups::ReadPath->value,
        Groups::ReadProject->value,
        Groups::ReadStep->value
    ])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public string $id;

    #[Serializer\Groups([
        Groups::ListProjects->value,
        Groups::ReadFeature->value,
        Groups::ReadPath->value,
        Groups::ReadProject->value
    ])]
    #[ORM\Column(type: 'string')]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    public string $title;

    #[Serializer\Groups([
        Groups::ListProjects->value,
        Groups::ReadFeature->value,
        Groups::ReadPath->value,
        Groups::ReadProject->value
    ])]
    #[ORM\Column(type: 'string', columnDefinition: 'project_visibility')]
    public string $visibility;

    #[Serializer\Groups([Groups::ListProjects->value, Groups::ReadProject->value])]
    #[ORM\OneToOne(inversedBy: 'project', targetEntity: Path::class, cascade: ['all'])]
    public Path $rootPath;

    #[Serializer\Groups([
        Groups::ListProjects->value,
        Groups::ReadFeature->value,
        Groups::ReadPath->value,
        Groups::ReadProject->value
    ])]
    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'projects')]
    public ?Organization $organization = null;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectUser::class, cascade: ['all'], orphanRemoval: true)]
    public iterable $users = [];

    #[Serializer\Groups([
        Groups::ReadFeature->value,
        Groups::ReadPath->value,
        Groups::ReadProject->value
    ])]
    public array $permissions = [];

    #[Serializer\Groups([
        Groups::ListProjects->value,
        Groups::ReadFeature->value,
        Groups::ReadPath->value,
        Groups::ReadProject->value,
        Groups::ReadStep->value
    ])]
    #[ORM\Column(type: 'string', length: 255)]
    public string $slug;

    #[Serializer\Ignore]
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Step::class, cascade: ['all'], orphanRemoval: true)]
    public iterable $steps = [];

    #[Serializer\Groups([Groups::ReadDomainModel->value])]
    #[Serializer\SerializedName('entities')]
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: DomainEntity::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    public iterable $domainEntities = [];

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->slug = Slugify::create()->slugify($this->title);
        $this->id = Uuid::v4()->toRfc4122();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->slug = Slugify::create()->slugify($this->title);
    }
}
