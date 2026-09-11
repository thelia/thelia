<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Service;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;

/**
 * Reading a resource property back before it is written to the Propel model.
 *
 * A boolean named after the question it answers carries its getter in its own
 * name: $isOffered is read by isOffered(), never by getIsOffered(). Missing that
 * getter made the transformer read null, and a resource that answered "false"
 * was written to the model as if it had said nothing.
 */
final class ApiResourcePropelTransformerPropertyValueTest extends TestCase
{
    public function testABooleanNamedAfterItsQuestionIsReadFromTheGetterOfTheSameName(): void
    {
        $resource = $this->resource();

        self::assertFalse(
            $this->propertyValue($resource, 'isOffered'),
            'A false answered by isOffered() must reach the model as false, not as null.',
        );
    }

    public function testAPropertyThatMerelyStartsWithIsKeepsItsRegularGetter(): void
    {
        $resource = $this->resource();

        self::assertSame('FR', $this->propertyValue($resource, 'isocode'));
    }

    private function propertyValue(PropelResourceInterface $resource, string $property): mixed
    {
        $service = (new \ReflectionClass(ApiResourcePropelTransformerService::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod(ApiResourcePropelTransformerService::class, 'getPropertyValue');

        return $method->invoke($service, $resource, new \ReflectionProperty($resource, $property));
    }

    /**
     * A resource shaped like the ones the core ships: a question-named boolean whose
     * getter is the property name, and a plain property whose name only happens to
     * start with "is".
     */
    private function resource(): PropelResourceInterface
    {
        return new class implements PropelResourceInterface {
            public bool $isOffered = false;

            public string $isocode = 'FR';

            public function isOffered(): bool
            {
                return $this->isOffered;
            }

            public function getIsocode(): string
            {
                return $this->isocode;
            }

            public function __get(string $property)
            {
                return null;
            }

            public function setPropelModel(ActiveRecordInterface $propelModel): self
            {
                return $this;
            }

            public function getPropelModel(): ?ActiveRecordInterface
            {
                return null;
            }

            public function getResourceAddons(): array
            {
                return [];
            }

            public function getResourceAddon(string $addonName): ?ResourceAddonInterface
            {
                return null;
            }

            public function setResourceAddon(string $addonName, ?ResourceAddonInterface $addon): self
            {
                return $this;
            }

            public static function getPropelRelatedTableMap(): ?TableMap
            {
                return null;
            }
        };
    }
}
