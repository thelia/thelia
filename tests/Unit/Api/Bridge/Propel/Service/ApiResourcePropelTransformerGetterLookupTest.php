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

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\Service\TypeCasterService;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Tests\Unit\Api\Bridge\Propel\Service\Fixture\RecordingProduct;

/**
 * Writing a resource into its Propel model reads each property through the getter the resource
 * offers: getX(), isX(), or the property name itself for a boolean named after its question.
 * A property with no such getter reads as null, and a setter that cannot take null is not called.
 */
final class ApiResourcePropelTransformerGetterLookupTest extends TestCase
{
    public function testABooleanReadByAnIsGetterReachesTheModel(): void
    {
        $product = new RecordingProduct();

        $this->transformer()->resourceToModel($this->resource($product), new Post(denormalizationContext: []));

        self::assertSame(1, $product->getVisible());
    }

    public function testAPropertyWithoutAGetterIsLeftOutOfTheModel(): void
    {
        $model = new class extends RecordingProduct {
            public mixed $basisPoints = 'untouched';

            public function setBasisPoints(int $basisPoints): static
            {
                $this->basisPoints = $basisPoints;

                return $this;
            }
        };

        $this->transformer()->resourceToModel($this->resource($model), new Post(denormalizationContext: []));

        self::assertSame(
            'untouched',
            $model->basisPoints,
            'basisPoints() is not the getter of the property: the name only contains "is" followed by a capital.',
        );
    }

    private function transformer(): ApiResourcePropelTransformerService
    {
        return new ApiResourcePropelTransformerService(
            [],
            $this->createStub(ValidatorInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(TypeCasterService::class),
        );
    }

    /**
     * A resource shaped like the ones the core ships: a boolean read by isVisible(), and a
     * property whose only method shares its name.
     */
    private function resource(ActiveRecordInterface $model): PropelResourceInterface
    {
        return new class($model) implements PropelResourceInterface {
            public bool $visible = true;

            public ?int $basisPoints = 25;

            public function __construct(private readonly ActiveRecordInterface $model)
            {
            }

            public function isVisible(): bool
            {
                return $this->visible;
            }

            public function basisPoints(): int
            {
                return 25;
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
                return $this->model;
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
