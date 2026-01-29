<?php

namespace App\Entity;

use App\Repository\FeatureRepository;
use App\Serializer\Groups;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FeatureRepository::class)]
#[ORM\UniqueConstraint(columns: ['slug', 'path_id'])]
#[ORM\HasLifecycleCallbacks]
class Feature
{
    public const FEATURE_STATUS_DRAFT = 'draft';
    public const FEATURE_STATUS_READY_TO_DEV = 'ready_to_dev';
    public const FEATURE_STATUS_LIVE = 'live';

    #[Serializer\Groups([
        Groups::ListFeatures->value,
        Groups::ReadFeature->value,
        Groups::ReadPath->value
    ])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public ?string $id = null;

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\ManyToOne(targetEntity: Path::class, inversedBy: 'features')]
    public Path $path;

    #[Serializer\Groups([Groups::ListFeatures->value, Groups::ReadFeature->value, Groups::ReadPath->value])]
    #[ORM\Column(type: 'string')]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public string $title;

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\Column(type: 'string')]
    #[Assert\Length(max: 1024, normalizer: 'trim')]
    public string $description = "As an <actor>\nI want to <action>\nSo that <consequence>";

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\Column(type: 'text')]
    #[Assert\Length(max: 10000, normalizer: 'trim')]
    public string $summary = '';

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\OneToMany(mappedBy: 'feature', targetEntity: Scenario::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['priority' => 'ASC'])]
    public iterable $scenarios = [];

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadPath->value])]
    #[ORM\Column(type: 'string', length: 255)]
    public string $slug;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadPath->value])]
    #[ORM\Column(type: 'string', columnDefinition: 'feature_status')]
    public string $status = self::FEATURE_STATUS_DRAFT;

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    public iterable $tags = [];

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\OneToMany(mappedBy: 'feature', targetEntity: Issue::class, cascade: ['all'], orphanRemoval: true)]
    public iterable $issues = [];

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadPath->value])]
    public ?Project $rootProject = null;

    public function getRootPath(): string
    {
        $rootPath = sprintf('/%s', $this->slug);

        $path = $this->path;
        while ($path->project === null) {
            $rootPath = sprintf('/%s%s', $path->slug, $rootPath);
            $path = $path->parent;
        }

        return $rootPath;
    }

    public function getDisplayRootPath(): string
    {
        $rootPath = sprintf('%s', $this->title);

        $path = $this->path;
        while ($path->project === null) {
            $rootPath = sprintf('%s / %s', $path->path, $rootPath);
            $path = $path->parent;
        }

        return $rootPath;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->slug = Slugify::create()->slugify($this->title);
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->slug = Slugify::create()->slugify($this->title);
    }
}
