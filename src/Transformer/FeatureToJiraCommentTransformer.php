<?php

declare(strict_types=1);

namespace App\Transformer;

use App\Entity\Feature;
use App\Entity\InlineStepParam;
use App\Entity\MultilineStepParam;
use App\Entity\Scenario;
use App\Entity\ScenarioStep;
use App\Entity\ScenarioStepAdverb;
use App\Entity\ScenarioType;
use App\Entity\StepParamType;
use App\Entity\StepPart;
use App\Entity\TableStepParam;
use Doctrine\Common\Collections\Collection;

class FeatureToJiraCommentTransformer
{
    public function transform(Feature $feature): string
    {
        return sprintf(
            "%s\n%s",
            $this->getDescription($feature),
            $this->getScenarios($feature)
        );
    }

    private function getDescription(Feature $feature): string
    {
        return sprintf(
            "{panel:bgColor=#e3fcef}\n%s\n{panel}",
            implode(
                "\n",
                array_map(
                    static fn (string $line) => "*$line*",
                    explode("\n", $feature->description)
                )
            )
        );
    }

    private function getScenarios(Feature $feature): string
    {
        $scenarios = $feature->scenarios instanceof Collection ? $feature->scenarios->toArray() : $feature->scenarios;

        return implode("\n\n", array_map(fn (Scenario $scenario) => $this->getScenario($scenario), $scenarios));
    }

    private function getScenario(Scenario $scenario): string
    {
        return sprintf(
            "%s\n%s%s\n{panel}",
            $this->getScenarioHeadline($scenario),
            $this->getSteps($scenario),
            $this->getExamples($scenario)
        );
    }

    private function getScenarioHeadline(Scenario $scenario): string
    {
        if ($scenario->type === ScenarioType::Background) {
            return "{panel:bgColor=#eae6ff}\n*Background:*\n\n";
        }

        return sprintf("{panel:bgColor=#deebff}\n*%s: %s*\n\n", $scenario->type === ScenarioType::Regular ? 'Scenario' : 'Scenario outline', $scenario->title);
    }

    private function getSteps(Scenario $scenario): string
    {
        $steps = $scenario->steps instanceof Collection ? $scenario->steps->toArray() : $scenario->steps;

        return implode("\n", array_map(fn (ScenarioStep $step) => $this->getStep($step), $steps));
    }

    private function getStep(ScenarioStep $step): string
    {
        return sprintf('%s %s%s', $this->getStepAdverb($step), $this->getStepSentence($step), $this->getExtraParam($step));
    }

    private function getStepAdverb(ScenarioStep $step): string
    {
        return match ($step->adverb) {
            ScenarioStepAdverb::Given => '*Given*',
            ScenarioStepAdverb::When => '*When*',
            ScenarioStepAdverb::Then => '*Then*',
            ScenarioStepAdverb::And => '*And*',
            ScenarioStepAdverb::But => '*But*',
        };
    }

    private function getStepSentence(ScenarioStep $step): string
    {
        $parts = $step->step->parts instanceof Collection ? $step->step->parts->toArray() : $step->step->parts;

        return implode(' ', array_map(fn (StepPart $part): string => $this->getStepPart($step, $part), $parts));
    }

    private function getStepPart(ScenarioStep $step, StepPart $part): string
    {
        return $part->type === StepPart::TYPE_SENTENCE ? $part->content : $this->getInlineParam($step, $part);
    }

    private function getInlineParam(ScenarioStep $step, StepPart $part): string
    {
        foreach ($step->params as $param) {
            if (!$param instanceof InlineStepParam) {
                continue;
            }

            if ($param->stepPart->id === $part->id) {
                return sprintf('_%s_', $param->content);
            }
        }

        return '';
    }

    private function getExtraParam(ScenarioStep $step): string
    {
        if ($step->step->extraParamType === StepParamType::None) {
            return '';
        }

        return $step->step->extraParamType === StepParamType::Multiline ? $this->getMultilineExtraParam($step) : $this->getTableExtraParam($step);
    }

    private function getMultilineExtraParam(ScenarioStep $step): string
    {
        $str = '';
        foreach ($step->params as $param) {
            if ($param instanceof MultilineStepParam) {
                $str = $param->content;
            }
        }

        return sprintf(
            "\n%s",
            implode(
                "\n",
                array_map(
                    static fn (string $line) => sprintf('{{%s}}', $line),
                    explode("\n", $str)
                )
            )
        );
    }

    private function getTableExtraParam(ScenarioStep $step): string
    {
        foreach ($step->params as $param) {
            if ($param instanceof TableStepParam) {
                return sprintf("\n%s", $this->getTable($param->content));
            }
        }

        return '';
    }

    private function getTable(array $table): string
    {
        $columnsLengths = $this->getColumnsLengths($table);

        return implode("\n", array_map(fn (array $row): string => $this->getTableRow($row, $columnsLengths), $table));
    }

    private function getTableRow(array $row, array $columnsLengths): string
    {
        $out = [];

        foreach ($row as $columnId => $column) {
            $out[] = $this->getTableColumn($columnsLengths, $column, $columnId);
        }

        return sprintf("{{|%s|}}", implode('|', $out));
    }

    private function getTableColumn(array $columnsLengths, string $column, int $columnId): string
    {
        return sprintf(' %s%s ', $column, str_pad('', $columnsLengths[$columnId] - mb_strlen($column)));
    }

    private function getColumnsLengths(array $table): array
    {
        if (count($table) === 0) {
            return [];
        }

        $nbColumns = count($table[0]);
        $lengths = array_fill(0, $nbColumns, 0);

        foreach ($table as $row) {
            foreach ($row as $columnId => $column) {
                $length = mb_strlen($column);

                if (!array_key_exists($columnId, $lengths)) {
                    var_dump($table);
                    var_dump($lengths);
                    var_dump($columnId);
                    die;
                }

                if ($length > $lengths[$columnId]) {
                    $lengths[$columnId] = $length;
                }
            }
        }

        return $lengths;
    }

    private function getExamples(Scenario $scenario): string
    {
        if ($scenario->type !== ScenarioType::Outline) {
            return '';
        }

        return sprintf(
            "\n+Examples:+\n\n%s",
            $this->getTable(
                array_merge(
                    [array_keys($scenario->examples)],
                    $this->turnExamplesValuesIntoRows(
                        count(array_keys($scenario->examples)),
                        array_values($scenario->examples)
                    )
                )
            )
        );
    }

    private function turnExamplesValuesIntoRows(int $length, array $values): array
    {
        $amountOfRows = count($values[0]);
        $out = [];

        for ($i = 0 ; $i < $amountOfRows ; $i++) {
            $row = [];

            for ($j = 0 ; $j < $length ; $j++) {
                $row[] = $values[$j][$i];
            }

            $out[] = $row;
        }

        return $out;
    }
}
