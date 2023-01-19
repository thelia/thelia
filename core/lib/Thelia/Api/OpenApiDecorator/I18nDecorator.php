<?php

namespace Thelia\Api\OpenApiDecorator;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use Thelia\Api\Resource\I18n;

class I18nDecorator implements SchemaFactoryInterface
{
    public function __construct(
        private SchemaFactoryInterface $decorated
    ) {
    }

    public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
    {
        if ($className === I18n::class) {
            $schema = $schema ? clone $schema : new Schema();

        }

        return $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);
    }
}
