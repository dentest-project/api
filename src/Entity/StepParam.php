<?php

namespace App\Entity;

use App\Serializer\Groups;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', columnDefinition: 'param_type')]
#[ORM\DiscriminatorMap([
    StepParamType::Inline->value => InlineStepParam::class,
    StepParamType::Multiline->value => MultilineStepParam::class,
    StepParamType::Table->value => TableStepParam::class
])]
abstract class StepParam
{
    #[Serializer\Groups([Groups::ReadFeature->value])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public string $id;

    #[ORM\ManyToOne(targetEntity: ScenarioStep::class, inversedBy: 'params')]
    public ScenarioStep $step;

    #[Serializer\Groups([Groups::ReadFeature->value])]
    public function getType(): StepParamType
    {
        return match (static::class) {
            InlineStepParam::class => StepParamType::Inline,
            MultilineStepParam::class => StepParamType::Multiline,
            TableStepParam::class => StepParamType::Table,
        };
    }
}
