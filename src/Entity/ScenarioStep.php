<?php

namespace App\Entity;

use App\Serializer\Groups;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;

#[ORM\Entity]
class ScenarioStep
{
    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public string $id;

    #[ORM\ManyToOne(targetEntity: Scenario::class, inversedBy: 'steps')]
    public Scenario $scenario;

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\Column(type: 'string', columnDefinition: 'step_adverb')]
    public ScenarioStepAdverb $adverb;

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\ManyToOne(targetEntity: Step::class, inversedBy: 'scenarioSteps')]
    public ?Step $step = null;

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\OneToMany(mappedBy: 'step', targetEntity: StepParam::class, cascade: ['all'], orphanRemoval: true)]
    public iterable $params = [];

    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\Column(type: 'integer')]
    public int $priority;
}
