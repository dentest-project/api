<?php

namespace App\Entity;

use App\Repository\StepRepository;
use App\Serializer\Groups;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: StepRepository::class)]
class Step
{
    const TYPE_GIVEN = 'given';
    const TYPE_WHEN = 'when';
    const TYPE_THEN = 'then';
    const EXTRA_PARAM_TYPE_NONE = 'none';
    const EXTRA_PARAM_TYPE_MULTILINE = 'multiline';
    const EXTRA_PARAM_TYPE_TABLE = 'table';

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadStep->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public string $id;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadStep->value])]
    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'steps')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public Project $project;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadStep->value])]
    #[ORM\Column(type: 'string', columnDefinition: 'step_type')]
    public string $type;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadStep->value])]
    #[ORM\Column(type: 'string', columnDefinition: 'step_extra_param_type')]
    public string $extraParamType;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadStep->value])]
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $extraParamTemplate;

    #[Serializer\Groups([Groups::ReadFeature->value, Groups::ReadStep->value])]
    #[ORM\OneToMany(mappedBy: 'step', targetEntity: StepPart::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['priority' => 'ASC'])]
    public iterable $parts = [];

    #[Serializer\Ignore]
    #[ORM\OneToMany(mappedBy: 'step', targetEntity: ScenarioStep::class, cascade: ['all'], orphanRemoval: true)]
    public iterable $scenarioSteps = [];

    #[Serializer\Groups([Groups::ReadStep->value])]
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    public iterable $tags = [];
}
