<?php

declare(strict_types=1);

namespace App\Validator;

readonly class DomainFixtureValidationIssue
{
    public function __construct(
        public string $path,
        public string $message
    ) {}
}
