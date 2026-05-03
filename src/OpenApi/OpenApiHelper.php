<?php

namespace App\OpenApi;

use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

final class OpenApiHelper
{
    public static function getJsonRequestBodySchema(OA\RequestBody $requestBody): OA\Schema
    {
        $requestBody->content = Generator::UNDEFINED !== $requestBody->content ? $requestBody->content : [];

        if (!isset($requestBody->content['application/json'])) {
            $requestBody->content['application/json'] = new OA\MediaType([
                'mediaType' => 'application/json',
                '_context' => Util::createWeakContext($requestBody->_context),
            ]);
        }

        return Util::getChild($requestBody->content['application/json'], OA\Schema::class);
    }

    public static function getJsonResponseSchema(OA\Response $response): OA\Schema
    {
        $response->content = Generator::UNDEFINED !== $response->content ? $response->content : [];

        if (!isset($response->content['application/json'])) {
            $response->content['application/json'] = new OA\MediaType([
                'mediaType' => 'application/json',
                '_context' => Util::createWeakContext($response->_context),
            ]);
        }

        return Util::getChild($response->content['application/json'], OA\Schema::class);
    }

    public static function applyModelReference(OA\Schema $schema, string $modelRef, bool $collection = false): void
    {
        if ($collection) {
            $schema->type = 'array';
            $schema->items = new OA\Items([
                'ref' => $modelRef,
                '_context' => Util::createWeakContext($schema->_context),
            ]);

            return;
        }

        $schema->ref = $modelRef;
    }

    /**
     * @param array<string, mixed> $definition
     */
    public static function applySchemaDefinition(OA\Schema|OA\Property|OA\Items $schema, array $definition): void
    {
        foreach ($definition as $key => $value) {
            if (null === $value) {
                continue;
            }

            switch ($key) {
                case 'additionalProperties':
                    if (is_array($value)) {
                        $schema->additionalProperties = new OA\AdditionalProperties([
                            '_context' => Util::createWeakContext($schema->_context),
                        ]);
                        self::applySchemaDefinition($schema->additionalProperties, $value);
                    } else {
                        $schema->additionalProperties = $value;
                    }
                    break;
                case 'items':
                    $schema->items = new OA\Items([
                        '_context' => Util::createWeakContext($schema->_context),
                    ]);
                    self::applySchemaDefinition($schema->items, $value);
                    break;
                default:
                    $schema->{$key} = $value;
                    break;
            }
        }

        if ('array' === ($schema->type ?? null) && (!isset($schema->items) || Generator::UNDEFINED === $schema->items)) {
            $schema->items = new OA\Items([
                '_context' => Util::createWeakContext($schema->_context),
            ]);
        }
    }
}
