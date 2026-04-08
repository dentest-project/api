<?php

namespace App\SummaryGeneration\SummaryPromptBuilder;

class SummarySystemPromptBuilder
{
    public function build(int $minimumSentences, int $maximumSentences): string
    {
        return sprintf(
<<<PROMPT
Summarize the subject in %d-%d sentences.
You are allowed, and encouraged, to produce clear paragraphs to organize your ideas, separated by double line breaks.
You are encouraged to use markdown helpers to highlight some concepts.
If the whole text is longer than 10 sentences, you are strongly encouraged to produce a clear Markdown document, with accurate sectioning.
The concepts you outline need to be structured and streamlined in a way a human can easily process them.
In your sentences, reuse the names of the concepts and entities you find in the features. It is very important for ubiquitous language concerns. Your are allowed, and encouraged, to explain what they are.
Do NOT output Gherkin or any headings or bullets.
Avoid starting lines with Gherkin keywords like Feature, Scenario, Outline, Background, Given, When, Then, And, or But.
Do NOT use technical words.
Do NOT describe the subject as a test.
Describe it in natural language for a human who does not understand technical specifics.
The summary must be provided in the main language that is used to describe the features.
PROMPT,
            $minimumSentences,
            $maximumSentences
        );
    }
}
