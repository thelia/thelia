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

use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Thelia\Api\Bridge\Propel\Loader\ClassMetaDataFactory;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Product as ProductResource;
use Thelia\Test\IntegrationTestCase;

/**
 * Which resources a module extends is known from the modules that are active, so
 * the addon attributes are aggregated into the parent metadata at runtime. A cache
 * warmer writes what the loaders alone produce: the addon is there as a bare
 * property name, without the groups that put it in a payload. Behind the
 * serializer metadata cache, that warmed entry answered first, and a shop deployed
 * the documented way — clear the cache, then warm it — served its API without the
 * addons a shop serving from a lazily built cache returns.
 */
final class ResourceAddonMetadataTest extends IntegrationTestCase
{
    /**
     * Nothing may cache the parent metadata in front of the aggregation, whatever
     * the state of that cache: this is the guard the payload assertion below cannot
     * make on its own, since it only reproduces on an already warmed cache.
     */
    public function testTheAddonAggregationAnswersInFrontOfTheMetadataCache(): void
    {
        self::assertInstanceOf(
            ClassMetaDataFactory::class,
            $this->getService(ClassMetadataFactoryInterface::class),
            'The addon aggregation must decorate the serializer metadata cache, not sit behind it.',
        );
    }

    public function testAnAddonAttributeCarriesTheGroupsOfTheAddon(): void
    {
        $addons = $this->getService(ApiResourcePropelTransformerService::class)
            ->getResourceAddonDefinitions(ProductResource::class);

        if ([] === $addons) {
            self::markTestSkipped('No active module extends the product resource here.');
        }

        $attributes = $this->getService(ClassMetadataFactoryInterface::class)
            ->getMetadataFor(ProductResource::class)
            ->getAttributesMetadata();

        foreach (array_keys($addons) as $addonShortName) {
            self::assertArrayHasKey($addonShortName, $attributes);
            self::assertNotSame(
                [],
                $attributes[$addonShortName]->getGroups(),
                'An addon attribute without groups is dropped from every grouped payload.',
            );
        }
    }
}
