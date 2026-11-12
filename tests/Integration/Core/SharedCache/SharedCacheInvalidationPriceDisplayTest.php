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

namespace Thelia\Tests\Integration\Core\SharedCache;

use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Core\SharedCache\SharedCacheInvalidationListener;
use Thelia\Core\SharedCache\SharedCacheKey;
use Thelia\Core\SharedCache\SharedCachePurgerInterface;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Event\CatalogRestrictionEvent;
use Thelia\Model\Event\PriceListAssignmentEvent;
use Thelia\Model\Event\PriceListEvent;
use Thelia\Model\Event\PriceListItemEvent;
use Thelia\Model\Event\ProductEvent;
use Thelia\Model\PriceList;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;
use Thelia\Tests\Unit\Core\SharedCache\RecordingSharedCachePurger;

/**
 * What the visitors are shown decides every shared page: a price list, a line or an assignment of
 * one, a catalog restriction, the price display mode of the shop and the one a product states
 * purge the whole shop, however they are written (through Propel, as the back office writes them,
 * or through the API). A write of a product that leaves the mode alone purges nothing.
 */
final class SharedCacheInvalidationPriceDisplayTest extends IntegrationTestCase
{
    use PriceListFixtures;

    private FixtureFactory $factory;

    private RecordingSharedCachePurger $purger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->purger = new RecordingSharedCachePurger();
        static::getContainer()->set(SharedCachePurgerInterface::class, $this->purger);
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testThePriceListsAndTheRestrictionsAreSubscribedAsWholeShopChanges(): void
    {
        $subscribed = SharedCacheInvalidationListener::getSubscribedEvents();

        foreach ([PriceListEvent::POST_SAVE, PriceListEvent::POST_DELETE, PriceListItemEvent::POST_SAVE, PriceListItemEvent::POST_DELETE, PriceListAssignmentEvent::POST_SAVE, PriceListAssignmentEvent::POST_DELETE, CatalogRestrictionEvent::POST_SAVE, CatalogRestrictionEvent::POST_DELETE] as $eventName) {
            self::assertArrayHasKey($eventName, $subscribed);
            self::assertSame('onWholeShopChange', $subscribed[$eventName][0], $eventName);
        }
    }

    public function testAPriceListWrittenThroughPropelPurgesTheShop(): void
    {
        $list = $this->priceList();
        $this->flush();
        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);

        $this->purger->calls = [];
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $this->priceListItem($list, $pse, '9');
        $this->flush();
        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls, 'a line');

        $this->purger->calls = [];
        $this->assignToGroup($list, $this->factory->customerGroup());
        $this->flush();
        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls, 'an assignment');
    }

    public function testACatalogRestrictionWrittenThroughPropelPurgesTheShop(): void
    {
        $product = $this->pricedProduct();
        $this->flush();
        $this->purger->calls = [];

        (new CatalogRestriction())->setCustomerGroupId($this->factory->customerGroup()->getId())->setProductId($product->getId())->setMode('deny')->save($this->getPropelConnection());
        $this->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    public function testAPriceListOrARestrictionWrittenThroughTheApiPurgesTheShop(): void
    {
        $listener = static::getContainer()->get(SharedCacheInvalidationListener::class);
        $models = [
            (new PriceList())->setId(7),
            (new CatalogRestriction())->setId(7),
        ];

        foreach ($models as $model) {
            $this->purger->calls = [];
            $listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, $model, ResourcePersistedEvent::OPERATION_WRITE));
            $listener->flush();

            self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls, $model::class);
        }
    }

    public function testTheShopModeWrittenPurgesTheShop(): void
    {
        $this->flush();
        $this->purger->calls = [];

        ConfigQuery::write(PriceDisplayMode::CONFIG_KEY, PriceDisplayMode::LoggedIn->value, false);
        $this->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    public function testTheModeAProductStatesPurgesTheShopWhenItChanges(): void
    {
        $product = $this->pricedProduct();
        $this->flush();
        $this->purger->calls = [];

        $product->setPriceDisplayMode(PriceDisplayMode::OnRequest->value)->save($this->getPropelConnection());
        $this->flush();
        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls, 'set');

        $this->purger->calls = [];
        $product->setPriceDisplayMode(null)->save($this->getPropelConnection());
        $this->flush();
        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls, 'cleared');
    }

    public function testAWriteOfAProductThatLeavesTheModeAlonePurgesNothingHere(): void
    {
        $product = $this->pricedProduct();
        $this->flush();
        $this->purger->calls = [];

        $product->setPosition(((int) $product->getPosition()) + 5)->save($this->getPropelConnection());
        $this->flush();
        self::assertSame([], $this->purger->calls);

        // The mode written with the same value is no change either.
        $product->setPriceDisplayMode(PriceDisplayMode::OnRequest->value)->save($this->getPropelConnection());
        $this->flush();
        $this->purger->calls = [];
        $product->setPriceDisplayMode(PriceDisplayMode::OnRequest->value)->setPosition(((int) $product->getPosition()) + 1)->save($this->getPropelConnection());
        $this->flush();
        self::assertSame([], $this->purger->calls);
    }

    public function testTheModeOfAProductIsListenedToBeforeAndAfterTheWrite(): void
    {
        $subscribed = SharedCacheInvalidationListener::getSubscribedEvents();

        self::assertSame('rememberTheModeChange', $subscribed[ProductEvent::PRE_UPDATE][0]);
        self::assertSame('purgeOnModeChange', $subscribed[ProductEvent::POST_UPDATE][0]);
    }

    private function flush(): void
    {
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();
    }
}
