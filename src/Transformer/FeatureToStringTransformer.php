<?php

namespace App\Transformer;

use App\Entity\Feature;
use App\Entity\InlineStepParam;
use App\Entity\MultilineStepParam;
use App\Entity\Scenario;
use App\Entity\ScenarioStep;
use App\Entity\Step;
use App\Entity\StepPart;
use App\Entity\TableStepParam;
use Doctrine\Common\Collections\Collection;

class FeatureToStringTransformer
{
    public function transform(Feature $feature): string
    {
        return sprintf(
            "%s\n%s\n%s",
            $this->getTitle($feature),
            $this->getDescription($feature),
            $this->getScenarios($feature)
        );
    }

    private function getTitle(Feature $feature): string
    {
        return sprintf('Feature: %s', $feature->title);
    }

    private function getDescription(Feature $feature): string
    {
        return implode("\n", array_map(fn (string $row): string => sprintf('  %s', $row), explode("\n", $feature->description)));
    }

    private function getScenarios(Feature $feature): string
    {
        $scenarios = $feature->scenarios instanceof Collection ? $feature->scenarios->toArray() : $feature->scenarios;

        return implode("\n\n", array_map(fn (Scenario $scenario) => $this->getScenario($scenario), $scenarios));
    }

    private function getScenario(Scenario $scenario): string
    {
        return sprintf(
            "%s\n%s%s",
            $this->getScenarioHeadline($scenario),
            $this->getSteps($scenario),
            $this->getExamples($scenario)
        );
    }

    private function getScenarioHeadline(Scenario $scenario): string
    {
        if ($scenario->type === Scenario::TYPE_BACKGROUND) {
            return '  Background:';
        }

        return sprintf('  %s: %s', $scenario->type === Scenario::TYPE_REGULAR ? 'Scenario' : 'Scenario outline', $scenario->title);
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
        switch ($step->adverb) {
            case ScenarioStep::ADVERB_GIVEN:
                return '    Given';
            case ScenarioStep::ADVERB_WHEN:
                return '    When';
            case ScenarioStep::ADVERB_THEN:
                return '    Then';
            case ScenarioStep::ADVERB_AND:
                return '    And';
            case ScenarioStep::ADVERB_BUT:
                return '    But';
            default:
                return '    ';
        }
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
                continue 1;
            }

            if ($param->stepPart->id === $part->id) {
                return $param->content;
            }
        }

        return '';
    }

    private function getExtraParam(ScenarioStep $step): string
    {
        if ($step->step->extraParamType === Step::EXTRA_PARAM_TYPE_NONE) {
            return '';
        }

        return $step->step->extraParamType === Step::EXTRA_PARAM_TYPE_MULTILINE ? $this->getMultilineExtraParam($step) : $this->getTableExtraParam($step);
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
            "\n    \"\"\"\n%s\n    \"\"\"",
            implode("\n", array_map(fn (string $row) => sprintf('    %s', $row), explode("\n", $str)))
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

        return sprintf("    |%s|", implode('|', $out));
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
                if ($length > $lengths[$columnId]) {
                    $lengths[$columnId] = $length;
                }
            }
        }

        return $lengths;
    }

    private function getExamples(Scenario $scenario): string
    {
        if ($scenario->type !== Scenario::TYPE_OUTLINE) {
            return '';
        }

        return sprintf("\n    Examples:\n%s", $this->getTable(array_merge([array_keys($scenario->examples)], array_values($scenario->examples))));
    }
}
