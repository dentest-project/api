<?php

namespace App\Entity;

use App\Repository\PathRepository;
use App\Serializer\Groups;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PathRepository::class)]
#[ORM\UniqueConstraint(columns: ['slug', 'parent_id'])]
#[ORM\HasLifecycleCallbacks]
class Path
{
    private const DEFAULT_PATH_SLUG = 'root';

    #[Serializer\Groups([Groups::ListProjects->value, Groups::ReadFeature->value, Groups::ReadPath->value, Groups::ReadProject->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadPath->value])]
    #[ORM\OneToOne(mappedBy: 'rootPath', targetEntity: Project::class)]
    public ?Project $project = null;

    #[Serializer\Groups([
        Groups::ReadFeature->value,
        Groups::ReadPath->value,
        Groups::ReadProject->value
    ])]
    #[ORM\Column(type: 'string')]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public string $path;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadPath->value])]
    #[ORM\ManyToOne(targetEntity: Path::class, inversedBy: 'children')]
    public ?Path $parent = null;

    #[Serializer\Groups([Groups::ReadPath->value])]
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Path::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['path' => 'ASC'])]
    public iterable $children = [];

    #[Serializer\Groups([Groups::ReadPath->value])]
    #[ORM\OneToMany(mappedBy: 'path', targetEntity: Feature::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['title' => 'ASC'])]
    public iterable $features = [];

    #[Serializer\Groups([Groups::ReadPath->value, Groups::ReadProject->value])]
    #[ORM\Column(type: 'text')]
    public string $summary = '';

    #[Serializer\Groups([Groups::ListProjects->value, Groups::ReadFeature->value, Groups::ReadPath->value, Groups::ReadProject->value])]
    #[ORM\Column(type: 'string', length: 255)]
    public string $slug;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadPath->value])]
    public ?Project $rootProject = null;

    public function getDisplayPath(): string
    {
        $parts = [];
        $currentPath = $this;

        while ($currentPath !== null) {
            $parts[] = $currentPath->path;

            if ($currentPath->project !== null) {
                break;
            }

            $currentPath = $currentPath->parent;
        }

        return implode(' / ', array_reverse($parts));
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->slug = Slugify::create()->slugify($this->path) ? : self::DEFAULT_PATH_SLUG;
        $this->id = Uuid::v4()->toRfc4122();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->slug = Slugify::create()->slugify($this->path) ? : self::DEFAULT_PATH_SLUG;
    }
}
