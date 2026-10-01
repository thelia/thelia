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

namespace Thelia\Tests\Integration\Api;

use ApiPlatform\Metadata\Resource\Factory\CachedResourceMetadataCollectionFactory;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * The resource metadata is built once per cache warmup, so a deprecation raised
 * while building it lands in the deprecation log of every shop, once per resource
 * and per operation. This builds the metadata of every core resource behind the
 * metadata cache, which would otherwise answer from a previous run, and fails on
 * any deprecation API Platform raises on the way.
 */
final class ResourceMetadataDeprecationTest extends IntegrationTestCase
{
    private const string CORE_RESOURCE_NAMESPACE = 'Thelia\\Api\\Resource\\';

    public function testBuildingTheCoreResourceMetadataRaisesNoApiPlatformDeprecation(): void
    {
        $resourceClasses = array_filter(
            iterator_to_array($this->getService(ResourceNameCollectionFactoryInterface::class)->create()),
            static fn (string $resourceClass): bool => str_starts_with($resourceClass, self::CORE_RESOURCE_NAMESPACE),
        );
        self::assertNotSame([], $resourceClasses, 'No core API resource found.');

        $metadataFactory = $this->getService(ResourceMetadataCollectionFactoryInterface::class);
        if ($metadataFactory instanceof CachedResourceMetadataCollectionFactory) {
            $metadataFactory = (new \ReflectionProperty(CachedResourceMetadataCollectionFactory::class, 'decorated'))->getValue($metadataFactory);
        }
        self::assertInstanceOf(ResourceMetadataCollectionFactoryInterface::class, $metadataFactory);

        $deprecations = [];

        set_error_handler(
            static function (int $level, string $message) use (&$deprecations): bool {
                if (str_starts_with($message, 'Since api-platform/')) {
                    $deprecations[$message] = true;
                }

                return true;
            },
            \E_USER_DEPRECATED,
        );

        try {
            foreach ($resourceClasses as $resourceClass) {
                $metadataFactory->create($resourceClass);
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([], array_keys($deprecations));
    }
}
