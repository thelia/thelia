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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Core\Event\Brand\BrandEvent;
use Thelia\Core\Event\Content\ContentEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductUpdateEvent;
use Thelia\Core\Event\Folder\FolderEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ToggleVisibilityEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\SharedCache\NullSharedCachePurger;
use Thelia\Core\SharedCache\SharedCacheInvalidationListener;
use Thelia\Core\SharedCache\SharedCacheKey;
use Thelia\Core\SharedCache\SharedCacheMode;
use Thelia\Core\SharedCache\SharedCachePurgeQueue;
use Thelia\Core\SharedCache\SharedCachePurgerInterface;
use Thelia\Model\Area;
use Thelia\Model\Brand;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;
use Thelia\Model\Event\ConfigEvent;
use Thelia\Model\Folder;
use Thelia\Model\MetaData;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\RewritingUrl;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Unit\Core\SharedCache\RecordingSharedCachePurger;

final class SharedCacheInvalidationListenerTest extends IntegrationTestCase
{
    private RecordingSharedCachePurger $purger;

    private SharedCacheInvalidationListener $listener;

    private ResourceCache&MockObject $resourceCache;

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->purger = new RecordingSharedCachePurger();
        $this->resourceCache = $this->createMock(ResourceCache::class);
        $this->listener = new SharedCacheInvalidationListener(new SharedCachePurgeQueue($this->purger), $this->resourceCache);
    }

    public function testAProductChangePurgesThatProductAndWhatListsProducts(): void
    {
        $this->listener->onCatalogueChange(new ProductEvent((new Product())->setId(42)), TheliaEvents::PRODUCT_UPDATE);
        $this->listener->flush();

        self::assertSame([['product-42', 'products', 'product_sale_elements', 'product_images', 'product_sale_elements_product_image', 'product_videos', 'product_sale_elements_product_video', 'product_associations', 'tfilters']], $this->purger->calls);
    }

    public function testAFeatureValueChangePurgesThatProductOnly(): void
    {
        $this->listener->onCatalogueChange(new FeatureProductUpdateEvent(42, 3, 'blue'), TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE);
        $this->listener->flush();

        self::assertSame([SharedCacheKey::forChange('product', 42)], $this->purger->calls);
    }

    public function testAFeatureValueDeletionPurgesThatProductOnly(): void
    {
        $this->listener->onCatalogueChange(new FeatureProductDeleteEvent(42, 3), TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE);
        $this->listener->flush();

        self::assertSame([SharedCacheKey::forChange('product', 42)], $this->purger->calls);
    }

    public function testACloneIsPurgedAsTheProductItCreated(): void
    {
        $event = new ProductCloneEvent('CLONE-1', 'en_US', (new Product())->setId(42));
        $event->setClonedProduct((new Product())->setId(77));

        $this->listener->onCatalogueChange($event, TheliaEvents::PRODUCT_CLONE);
        $this->listener->flush();

        self::assertSame([SharedCacheKey::forChange('product', 77)], $this->purger->calls);
    }

    public function testAHiddenCombinationIsPurgedAsItsProduct(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $combination = $factory->productSaleElement($product);

        $this->listener->onCatalogueChange(
            new ProductSaleElementToggleVisibilityEvent((int) $combination->getId()),
            TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_TOGGLE_VISIBILITY,
        );
        $this->listener->flush();

        self::assertSame([SharedCacheKey::forChange('product', (int) $product->getId())], $this->purger->calls);
    }

    public function testAWriteOfTheShopConfigurationReachesTheListenerThroughPropel(): void
    {
        $purger = new RecordingSharedCachePurger();
        static::getContainer()->set(SharedCachePurgerInterface::class, $purger);

        ConfigQuery::write('shared_cache_probe', 'one');
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();

        self::assertSame([[SharedCacheKey::ALL]], $purger->calls);
    }

    public function testADeletedSettingIsSubscribedAsAWholeShopChange(): void
    {
        $subscribed = SharedCacheInvalidationListener::getSubscribedEvents();

        foreach ([ConfigEvent::POST_SAVE, ConfigEvent::POST_DELETE] as $eventName) {
            self::assertArrayHasKey($eventName, $subscribed);
            self::assertSame('onWholeShopChange', $subscribed[$eventName][0]);
        }
    }

    public function testAProductChangeLeavesTheOtherPagesAlone(): void
    {
        $this->listener->onCatalogueChange(new ProductEvent((new Product())->setId(42)), TheliaEvents::PRODUCT_UPDATE);
        $this->listener->flush();

        $purged = $this->purger->calls[0];
        self::assertNotContains('product-43', $purged);
        self::assertNotContains('categories', $purged);
        self::assertNotContains('contents', $purged);
        self::assertNotContains(SharedCacheKey::ALL, $purged);
    }

    public function testAnObjectIdIsEnoughToFindTheCategory(): void
    {
        $this->listener->onCatalogueChange(new UpdatePositionEvent(7, 1), TheliaEvents::CATEGORY_UPDATE_POSITION);
        $this->listener->flush();

        self::assertSame([['category-7', 'categories', 'products', 'tfilters']], $this->purger->calls);
    }

    public function testAVisibilityToggleIsFoundByItsObjectId(): void
    {
        $this->listener->onCatalogueChange(new ToggleVisibilityEvent(9), TheliaEvents::CONTENT_TOGGLE_VISIBILITY);
        $this->listener->flush();

        self::assertSame([['content-9', 'contents']], $this->purger->calls);
    }

    public function testABrandChangePurgesTheBrandAndTheProducts(): void
    {
        $this->listener->onCatalogueChange(new BrandEvent((new Brand())->setId(3)), TheliaEvents::BRAND_UPDATE);
        $this->listener->flush();

        self::assertSame([['brand-3', 'brands', 'products', 'tfilters']], $this->purger->calls);
    }

    public function testAContentChangePurgesTheContentAndItsLists(): void
    {
        $this->listener->onCatalogueChange(new ContentEvent((new Content())->setId(9)), TheliaEvents::CONTENT_UPDATE);
        $this->listener->flush();

        self::assertSame([['content-9', 'contents']], $this->purger->calls);
    }

    public function testAFolderChangePurgesTheFolderAndTheContents(): void
    {
        $this->listener->onCatalogueChange(new FolderEvent((new Folder())->setId(2)), TheliaEvents::FOLDER_UPDATE);
        $this->listener->flush();

        self::assertSame([['folder-2', 'folders', 'contents']], $this->purger->calls);
    }

    public function testAnEventThatNamesNoObjectPurgesTheWholeShop(): void
    {
        $this->listener->onCatalogueChange(new ProductEvent(), TheliaEvents::PRODUCT_UPDATE);
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    public function testClearingTheCachePurgesTheWholeShop(): void
    {
        $this->listener->onWholeShopChange();
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    public function testNothingIsSentBeforeTheRequestIsDone(): void
    {
        $this->listener->onCatalogueChange(new ProductEvent((new Product())->setId(42)), TheliaEvents::PRODUCT_UPDATE);

        self::assertSame([], $this->purger->calls);
    }

    public function testTheListenerIsWiredToTheCatalogueEventsAndToTheEndOfTheRequest(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');

        foreach ([TheliaEvents::PRODUCT_UPDATE, TheliaEvents::CATEGORY_TOGGLE_VISIBILITY, TheliaEvents::CACHE_CLEAR, TheliaEvents::SALE_UPDATE, KernelEvents::TERMINATE] as $eventName) {
            $listeners = array_filter(
                $dispatcher->getListeners($eventName),
                static fn (mixed $listener): bool => \is_array($listener) && $listener[0] instanceof SharedCacheInvalidationListener,
            );

            self::assertNotSame([], $listeners, $eventName);
        }
    }

    public function testTheDefaultPurgerSendsNothing(): void
    {
        self::assertInstanceOf(NullSharedCachePurger::class, static::getContainer()->get(SharedCachePurgerInterface::class));
    }

    public function testADefaultEventCarriesNoCatalogueObject(): void
    {
        $this->listener->onCatalogueChange(new DefaultActionEvent(), TheliaEvents::BRAND_UPDATE);
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    #[DataProvider('eventsThatCarryNoProductId')]
    public function testAnEventNamingAJunctionRowInsteadOfTheProductPurgesTheWholeShop(string $eventName): void
    {
        $subscribed = SharedCacheInvalidationListener::getSubscribedEvents();

        self::assertArrayHasKey($eventName, $subscribed);
        self::assertSame('onWholeShopChange', $subscribed[$eventName][0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function eventsThatCarryNoProductId(): iterable
    {
        yield 'content position' => [TheliaEvents::PRODUCT_UPDATE_CONTENT_POSITION];
        yield 'accessory position' => [TheliaEvents::PRODUCT_UPDATE_ACCESSORY_POSITION];
        yield 'association position' => [TheliaEvents::PRODUCT_UPDATE_ASSOCIATION_POSITION];
        yield 'combination position' => [TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_UPDATE_POSITION];
    }

    #[DataProvider('eventsThatChangeWhatEveryPageShows')]
    public function testAChangeThatReachesEveryPageIsSubscribedAsAWholeShopChange(string $eventName): void
    {
        $subscribed = SharedCacheInvalidationListener::getSubscribedEvents();

        self::assertArrayHasKey($eventName, $subscribed);
        self::assertSame('onWholeShopChange', $subscribed[$eventName][0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function eventsThatChangeWhatEveryPageShows(): iterable
    {
        yield 'tax rule' => [TheliaEvents::TAX_RULE_UPDATE];
        yield 'tax' => [TheliaEvents::TAX_UPDATE];
        yield 'currency rates' => [TheliaEvents::CURRENCY_UPDATE_RATES];
        yield 'default currency' => [TheliaEvents::CURRENCY_SET_DEFAULT];
        yield 'product video' => [TheliaEvents::PRODUCT_VIDEO_UPDATE];
        yield 'template feature' => [TheliaEvents::TEMPLATE_ADD_FEATURE];
        yield 'configuration' => [TheliaEvents::CONFIG_SETVALUE];
        yield 'language' => [TheliaEvents::LANG_TOGGLEACTIVE];
        yield 'import' => [TheliaEvents::IMPORT_SUCCESS];
        yield 'module activation' => [TheliaEvents::MODULE_TOGGLE_ACTIVATION];
        yield 'translation file written' => [TheliaEvents::TRANSLATION_WRITE_FILE];
        yield 'image saved' => [TheliaEvents::IMAGE_SAVE];
        yield 'image updated' => [TheliaEvents::IMAGE_UPDATE];
        yield 'image position' => [TheliaEvents::IMAGE_UPDATE_POSITION];
        yield 'image visibility' => [TheliaEvents::IMAGE_TOGGLE_VISIBILITY];
        yield 'image deleted' => [TheliaEvents::IMAGE_DELETE];
        yield 'document saved' => [TheliaEvents::DOCUMENT_SAVE];
        yield 'document updated' => [TheliaEvents::DOCUMENT_UPDATE];
        yield 'document position' => [TheliaEvents::DOCUMENT_UPDATE_POSITION];
        yield 'document visibility' => [TheliaEvents::DOCUMENT_TOGGLE_VISIBILITY];
        yield 'document deleted' => [TheliaEvents::DOCUMENT_DELETE];
        yield 'country created' => [TheliaEvents::COUNTRY_CREATE];
        yield 'country updated' => [TheliaEvents::COUNTRY_UPDATE];
        yield 'country deleted' => [TheliaEvents::COUNTRY_DELETE];
        yield 'default country' => [TheliaEvents::COUNTRY_TOGGLE_DEFAULT];
        yield 'country visibility' => [TheliaEvents::COUNTRY_TOGGLE_VISIBILITY];
        yield 'state created' => [TheliaEvents::STATE_CREATE];
        yield 'state updated' => [TheliaEvents::STATE_UPDATE];
        yield 'state deleted' => [TheliaEvents::STATE_DELETE];
        yield 'state visibility' => [TheliaEvents::STATE_TOGGLE_VISIBILITY];
        yield 'delivery area created' => [TheliaEvents::AREA_CREATE];
        yield 'delivery area updated' => [TheliaEvents::AREA_UPDATE];
        yield 'delivery area deleted' => [TheliaEvents::AREA_DELETE];
        yield 'country added to an area' => [TheliaEvents::AREA_ADD_COUNTRY];
        yield 'country removed from an area' => [TheliaEvents::AREA_REMOVE_COUNTRY];
        yield 'area postage' => [TheliaEvents::AREA_POSTAGE_UPDATE];
        yield 'module updated' => [TheliaEvents::MODULE_UPDATE];
        yield 'module position' => [TheliaEvents::MODULE_UPDATE_POSITION];
        yield 'module deleted' => [TheliaEvents::MODULE_DELETE];
        yield 'hook deactivated' => [TheliaEvents::HOOK_DEACTIVATION];
        yield 'hooks created for a module' => [TheliaEvents::HOOK_CREATE_ALL];
        yield 'feature added to every template' => [TheliaEvents::FEATURE_ADD_TO_ALL_TEMPLATES];
        yield 'feature removed from every template' => [TheliaEvents::FEATURE_REMOVE_FROM_ALL_TEMPLATES];
        yield 'attribute added to every template' => [TheliaEvents::ATTRIBUTE_ADD_TO_ALL_TEMPLATES];
        yield 'attribute removed from every template' => [TheliaEvents::ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES];
    }

    public function testAProductWrittenThroughTheApiPurgesThatProduct(): void
    {
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, (new Product())->setId(42), ResourcePersistedEvent::OPERATION_WRITE));
        $this->listener->flush();

        self::assertSame([['product-42', 'products', 'product_sale_elements', 'product_images', 'product_sale_elements_product_image', 'product_videos', 'product_sale_elements_product_video', 'product_associations', 'tfilters']], $this->purger->calls);
    }

    public function testADeletionThroughTheApiPurgesToo(): void
    {
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, (new Product())->setId(42), ResourcePersistedEvent::OPERATION_DELETE));
        $this->listener->flush();

        self::assertSame([SharedCacheKey::forChange('product', 42)], $this->purger->calls);
    }

    #[DataProvider('modelsWrittenThroughTheApiThatHaveNoFamily')]
    public function testAModelOutsideTheFivePagesFamiliesPurgesTheWholeShopWhenTheApiWritesIt(object $model): void
    {
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, $model, ResourcePersistedEvent::OPERATION_WRITE));
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function modelsWrittenThroughTheApiThatHaveNoFamily(): iterable
    {
        yield 'a delivery area' => [new Area()];
        yield 'a meta data' => [new MetaData()];
        yield 'a rewriting url' => [new RewritingUrl()];
    }

    public function testACatalogueModelWithoutAFamilyPurgesTheWholeShop(): void
    {
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, new ProductSaleElements(), ResourcePersistedEvent::OPERATION_WRITE));
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    public function testAWriteThroughTheApiOutsideTheCataloguePurgesNothing(): void
    {
        $this->resourceCache->expects(self::never())->method('clear');

        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, new Cart(), ResourcePersistedEvent::OPERATION_WRITE));
        $this->listener->flush();

        self::assertSame([], $this->purger->calls);
    }

    public function testAShopThatDoesNotShareLeavesTheResourceCacheToItsOwnListener(): void
    {
        $this->resourceCache->expects(self::never())->method('clear');

        $this->listener->onCatalogueChange(new ProductEvent((new Product())->setId(42)), TheliaEvents::PRODUCT_UPDATE);
        $this->listener->onWholeShopChange();
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, (new Product())->setId(42), ResourcePersistedEvent::OPERATION_WRITE));
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    public function testTheResourceCachePoolIsClearedWithEveryPurge(): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::ENABLED->value);
        $this->resourceCache->expects(self::exactly(3))->method('clear');

        $this->listener->onCatalogueChange(new ProductEvent((new Product())->setId(42)), TheliaEvents::PRODUCT_UPDATE);
        $this->listener->onWholeShopChange();
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, (new Product())->setId(42), ResourcePersistedEvent::OPERATION_WRITE));
    }

    public function testTheListenerHearsTheWritesOfTheApi(): void
    {
        self::assertArrayHasKey(ResourcePersistedEvent::class, SharedCacheInvalidationListener::getSubscribedEvents());
    }
}
