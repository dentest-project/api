<?php

namespace App\SummaryGeneration\Prompt;

class SummarySystemPromptBuilder
{
    public function build(int $minimumSentences, int $maximumSentences): string
    {
        return sprintf(
<<<PROMPT
Summarize the subject in %d-%d sentences as a plain paragraph.
Do NOT output Gherkin or any headings or bullets.
Avoid starting lines with Gherkin keywords like Feature, Scenario, Outline, Background, Given, When, Then, And, or But.
Do NOT use technical words.
Do NOT describe the subject as a test.
Describe it in natural language for a human who does not understand technical specifics.
PROMPT,
            $minimumSentences,
            $maximumSentences
        );
    }
}
