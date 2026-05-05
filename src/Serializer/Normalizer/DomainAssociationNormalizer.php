<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\DomainAssociation;
use App\Entity\DomainEntity;
use App\Helper\ExtractSerializationGroupHelper;
use App\Serializer\Groups;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

readonly class DomainAssociationNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    public function __construct(
        private ObjectNormalizer $objectNormalizer
    ) {}

    public function normalize(mixed $object, string $format = null, array $context = [])
    {
        if (
            !$object instanceof DomainAssociation ||
            array_intersect(ExtractSerializationGroupHelper::extractGroup($context), [Groups::ReadDomainModel->value]) === []
        ) {
            return $this->objectNormalizer->normalize($object, $format, $context);
        }

        return [
            'id' => $object->id,
            'sourceEntity' => $this->normalizeEntityReference($object->sourceEntity ?? null),
            'sourceName' => $object->sourceName,
            'sourceCardinality' => $object->sourceCardinality->value,
            'sourcePosition' => $object->sourcePosition,
            'targetEntity' => $this->normalizeEntityReference($object->targetEntity ?? null),
            'targetName' => $object->targetName,
            'targetCardinality' => $object->targetCardinality->value,
            'targetPosition' => $object->targetPosition,
            'description' => $object->description,
        ];
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof DomainAssociation;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            DomainAssociation::class => true,
        ];
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->objectNormalizer->setSerializer($serializer);
    }

    /**
     * @return array{id: ?string, name: string}|null
     */
    private function normalizeEntityReference(?DomainEntity $entity): ?array
    {
        if (!$entity instanceof DomainEntity) {
            return null;
        }

        return [
            'id' => $entity->id,
            'name' => $entity->name,
        ];
    }
}
