<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\DomainFixture;
use App\Validator\DomainFixtureValidationIssue;

class IncompatibleDomainSchemaException extends \RuntimeException
{
    public static function forFixture(DomainFixture $fixture, DomainFixtureValidationIssue $issue): self
    {
        $path = '' !== $issue->path ? sprintf(' at "%s"', $issue->path) : '';

        return new self(sprintf('Fixture "%s" is incompatible with the updated schema%s: %s', $fixture->name, $path, $issue->message));
    }
}
