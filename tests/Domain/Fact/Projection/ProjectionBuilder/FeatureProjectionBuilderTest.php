<?php

declare(strict_types=1);

namespace App\Tests\Domain\Fact\Projection\ProjectionBuilder;

use App\Domain\Fact\Event\EventMetadata;
use App\Domain\Fact\Event\FeatureCreated;
use App\Domain\Fact\Event\FeatureMoved;
use App\Domain\Fact\Projection\EventProcessor\Feature\FeatureCreatedEventProcessor;
use App\Domain\Fact\Projection\ProjectionBuilder\FeatureProjectionBuilder;
use App\Entity\Feature;
use App\Tests\Double\Gateway\Domain\Fact\Event\InMemoryEventGateway;
use DateTimeImmutable;
use DirectoryIterator;
use PHPUnit\Framework\TestCase;

class FeatureProjectionBuilderTest extends TestCase
{
    public function testItBuildsProjectionByReorderingEvents(): void
    {
        InMemoryEventGateway::$events = [
            new FeatureMoved(
                new EventMetadata(
                    'id',
                    DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-01-02 00:00:00'),
                    'feature-1',
                    null,
                ),
                'path-2'
            ),
            new FeatureCreated(
                new EventMetadata(
                    'id',
                    DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-01-01 00:00:00'),
                    'feature-1',
                    null,
                ),
                'path-1',
                'My cool feature',
                'This is an extra cool feature',
            ),
        ];

        $result = $this->getFullFeatureProjectionBuilder()->build('feature-1');

        $this->assertInstanceOf(Feature::class, $result);
        $this->assertEquals('feature-1', $result->id);
        $this->assertEquals('path-2', $result->path->id);
        $this->assertEquals('My cool feature', $result->title);
        $this->assertEquals('This is an extra cool feature', $result->description);
        $this->assertEquals('my-cool-feature', $result->slug);
    }

    public function testItIgnoresWhenNoProcessorExistsForEvent(): void
    {
        InMemoryEventGateway::$events = [
            new FeatureMoved(
                new EventMetadata(
                    'id',
                    DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-01-02 00:00:00'),
                    'feature-1',
                    null,
                ),
                'path-2'
            ),
            new FeatureCreated(
                new EventMetadata(
                    'id',
                    DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-01-01 00:00:00'),
                    'feature-1',
                    null,
                ),
                'path-1',
                'My cool feature',
                'This is an extra cool feature',
            ),
        ];

        $result = (new FeatureProjectionBuilder(
            [new FeatureCreatedEventProcessor()],
            new InMemoryEventGateway(),
        ))->build('feature-1');

        $this->assertEquals('path-1', $result->path->id);
    }

    private function getAllFeatureProcessors(): array
    {
        static $processors;

        if ($processors) {
            return $processors;
        }

        $classes = [];
        $dir = __DIR__ . '/../../../../../src/Domain/Fact/Projection/EventProcessor/Feature';
        $ns = 'App\\Domain\\Fact\\Projection\\EventProcessor\\Feature';

        $it = new DirectoryIterator($dir);
        /** @var \SplFileInfo $f */
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $classes[] = $ns . '\\' . substr($f->getBasename(), 0, - strlen($f->getExtension()) - 1);
            }
        }

        $processors = array_map(
            static fn (string $className) => new $className(),
            $classes
        );

        return $processors;
    }

    private function getFullFeatureProjectionBuilder(): FeatureProjectionBuilder
    {
        return new FeatureProjectionBuilder(
            $this->getAllFeatureProcessors(),
            new InMemoryEventGateway(),
        );
    }
}
