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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Api\Service\API\ResourceCacheInvalidationListener;
use Thelia\Core\Event\Brand\BrandEvent;
use Thelia\Core\Event\Content\ContentEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductUpdateEvent;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\Folder\FolderEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ToggleVisibilityEvent;
use Thelia\Core\Event\UpdateFilePositionEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\File\FileModelInterface;
use Thelia\Core\SharedCache\NullSharedCachePurger;
use Thelia\Core\SharedCache\SharedCacheInvalidationListener;
use Thelia\Core\SharedCache\SharedCacheInvalidator;
use Thelia\Core\SharedCache\SharedCacheKey;
use Thelia\Core\SharedCache\SharedCacheMode;
use Thelia\Core\SharedCache\SharedCachePurgeQueue;
use Thelia\Core\SharedCache\SharedCachePurgerInterface;
use Thelia\Domain\Media\ProductMediaOrder;
use Thelia\Model\Area;
use Thelia\Model\Brand;
use Thelia\Model\BrandDocument;
use Thelia\Model\BrandImage;
use Thelia\Model\Cart;
use Thelia\Model\Category;
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryImage;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentDocument;
use Thelia\Model\ContentImage;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupCategory;
use Thelia\Model\CustomerGroupPaymentModule;
use Thelia\Model\CustomerGroupProductPrice;
use Thelia\Model\Event\ChoiceFilterEvent;
use Thelia\Model\Event\ConfigEvent;
use Thelia\Model\Folder;
use Thelia\Model\FolderDocument;
use Thelia\Model\FolderImage;
use Thelia\Model\MetaData;
use Thelia\Model\Product;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\ProductVideo;
use Thelia\Model\RewritingUrl;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Unit\Core\SharedCache\RecordingSharedCachePurger;

final class SharedCacheInvalidationListenerTest extends IntegrationTestCase
{
    private RecordingSharedCachePurger $purger;

    private SharedCacheInvalidationListener $listener;

    private ResourceCache&MockObject $resourceCache;

    private ResourceCacheInvalidationListener $resourceCacheInvalidation;

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
        $queue = new SharedCachePurgeQueue($this->purger);
        $this->resourceCacheInvalidation = new ResourceCacheInvalidationListener($this->resourceCache);
        $this->listener = new SharedCacheInvalidationListener(new SharedCacheInvalidator($queue, $this->resourceCacheInvalidation), $queue);
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

    public function testACategoryDeletedFromTheBackOfficeIsSubscribedAsAWholeShopChange(): void
    {
        $subscribed = SharedCacheInvalidationListener::getSubscribedEvents();

        self::assertSame('onWholeShopChange', $subscribed[TheliaEvents::CATEGORY_DELETE][0]);
    }

    /**
     * The back office writes the filters of a category straight to their table, with no
     * catalogue event: the save still reaches the listener through Propel.
     */
    public function testSavingTheFiltersOfACategoryPurgesThePagesThatReadThem(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $feature = $factory->feature();

        $purger = new RecordingSharedCachePurger();
        static::getContainer()->set(SharedCachePurgerInterface::class, $purger);

        (new ChoiceFilter())
            ->setCategoryId((int) $category->getId())
            ->setFeatureId((int) $feature->getId())
            ->setVisible(1)
            ->setPosition(1)
            ->setType('checkbox')
            ->save($this->getPropelConnection());
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();

        self::assertCount(1, $purger->calls);
        self::assertContains('tfilters', $purger->calls[0]);
        self::assertContains('category-'.$category->getId(), $purger->calls[0]);
        self::assertNotContains(SharedCacheKey::ALL, $purger->calls[0]);
    }

    public function testTheFiltersOfATemplatePurgeEveryPageThatReadsFilters(): void
    {
        $this->listener->onChoiceFilterChange(new ChoiceFilterEvent((new ChoiceFilter())->setTemplateId(3)));
        $this->listener->flush();

        self::assertSame([['tfilters']], $this->purger->calls);
    }

    public function testADeletedFilterIsSubscribedLikeASavedOne(): void
    {
        $subscribed = SharedCacheInvalidationListener::getSubscribedEvents();

        foreach ([ChoiceFilterEvent::POST_SAVE, ChoiceFilterEvent::POST_DELETE] as $eventName) {
            self::assertArrayHasKey($eventName, $subscribed);
            self::assertSame('onChoiceFilterChange', $subscribed[$eventName][0]);
        }
    }

    public function testTheEndOfAMessageHandledByAWorkerPurgesWhatWasQueued(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->listener);

        // An import run by messenger:consume ends neither a request nor a command.
        foreach ([
            new WorkerMessageHandledEvent(new Envelope(new \stdClass()), 'async_heavy'),
            new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async_heavy', new \RuntimeException('the import failed')),
        ] as $endOfTheMessage) {
            $this->purger->calls = [];
            $this->listener->onCatalogueChange(new ProductEvent((new Product())->setId(42)), TheliaEvents::PRODUCT_UPDATE);
            self::assertSame([], $this->purger->calls, 'nothing is purged while the message runs');

            $dispatcher->dispatch($endOfTheMessage);

            self::assertNotSame([], $this->purger->calls, 'the purge is sent once the message is over: '.$endOfTheMessage::class);
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
        yield 'template feature' => [TheliaEvents::TEMPLATE_ADD_FEATURE];
        yield 'configuration' => [TheliaEvents::CONFIG_SETVALUE];
        yield 'language' => [TheliaEvents::LANG_TOGGLEACTIVE];
        yield 'import' => [TheliaEvents::IMPORT_SUCCESS];
        yield 'import begun, even if it never succeeds' => [TheliaEvents::IMPORT_BEGIN];
        yield 'module activation' => [TheliaEvents::MODULE_TOGGLE_ACTIVATION];
        yield 'translation file written' => [TheliaEvents::TRANSLATION_WRITE_FILE];
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function writesToWhatACustomerGroupSeesOrPays(): iterable
    {
        foreach (ResourceCacheInvalidationListener::CUSTOMER_GROUP_WRITES as $eventName) {
            yield $eventName => [$eventName];
        }
    }

    /**
     * What a group is shut out of, owes and discounts decides the pages of the visitors of
     * the default group: a write to those tables purges the shop, whoever wrote it.
     */
    #[DataProvider('writesToWhatACustomerGroupSeesOrPays')]
    public function testAWriteToWhatACustomerGroupSeesOrPaysPurgesTheWholeShop(string $eventName): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->listener);

        $dispatcher->dispatch(new Event(), $eventName);
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    public function testTheEightWritesAreTheFourTablesSavedAndDeleted(): void
    {
        self::assertSame(
            [
                'propel.post.save.customer_group',
                'propel.post.delete.customer_group',
                'propel.post.save.customer_group_category',
                'propel.post.delete.customer_group_category',
                'propel.post.save.customer_group_product_price',
                'propel.post.delete.customer_group_product_price',
                'propel.post.save.customer_group_payment_module',
                'propel.post.delete.customer_group_payment_module',
            ],
            ResourceCacheInvalidationListener::CUSTOMER_GROUP_WRITES,
        );
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function customerGroupModelsWrittenThroughTheApi(): iterable
    {
        yield 'a group' => [new CustomerGroup()];
        yield 'a category of a group' => [new CustomerGroupCategory()];
        yield 'a price of a group' => [new CustomerGroupProductPrice()];
        yield 'a payment module of a group' => [new CustomerGroupPaymentModule()];
    }

    #[DataProvider('customerGroupModelsWrittenThroughTheApi')]
    public function testACustomerGroupModelWrittenThroughTheApiPurgesTheWholeShop(object $model): void
    {
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, $model, ResourcePersistedEvent::OPERATION_WRITE));
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
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

    public function testACategoryDeletedThroughTheApiPurgesTheWholeShop(): void
    {
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, (new Category())->setId(42), ResourcePersistedEvent::OPERATION_DELETE));
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls, 'its deletion cascades on the lists of the groups: what a restricted group sees changes across the shop');
    }

    public function testACategoryUpdatedThroughTheApiStillPurgesThatCategory(): void
    {
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, (new Category())->setId(42), ResourcePersistedEvent::OPERATION_WRITE));
        $this->listener->flush();

        self::assertSame([SharedCacheKey::forChange('category', 42)], $this->purger->calls);
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

    public function testTheResourceCachePoolIsClearedOnceTheRequestEndsNotWhilstTheWriteStillRuns(): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::ENABLED->value);
        $cleared = 0;
        $this->resourceCache->method('clear')->willReturnCallback(static function () use (&$cleared): void {
            ++$cleared;
        });

        $this->listener->onCatalogueChange(new ProductEvent((new Product())->setId(42)), TheliaEvents::PRODUCT_UPDATE);
        $this->listener->onWholeShopChange();
        $this->listener->onResourceChange(new ResourcePersistedEvent(\stdClass::class, (new Product())->setId(42), ResourcePersistedEvent::OPERATION_WRITE));

        // The write is not committed yet: a pool flushed now is refilled with the rows as they were.
        self::assertSame(0, $cleared);

        $this->resourceCacheInvalidation->flush();

        self::assertSame(1, $cleared, 'three purges, one flush, once the request ends');
    }

    public function testTheListenerHearsTheWritesOfTheApi(): void
    {
        self::assertArrayHasKey(ResourcePersistedEvent::class, SharedCacheInvalidationListener::getSubscribedEvents());
    }

    /**
     * @param \Closure(): object $event
     */
    #[DataProvider('mediumActions')]
    public function testAMediumActionPurgesTheObjectTheMediumBelongsTo(\Closure $event, string $eventName, string $family): void
    {
        $this->listener->onMediumChange($event());
        $this->listener->flush();

        self::assertSame([SharedCacheKey::forChange($family, 42)], $this->purger->calls, $eventName);
    }

    /**
     * @return iterable<string, array{\Closure(): object, string, string}>
     */
    public static function mediumActions(): iterable
    {
        yield 'image of a product saved' => [static fn (): object => (new FileCreateOrUpdateEvent(42))->setModel((new ProductImage())->setProductId(42)), TheliaEvents::IMAGE_SAVE, 'product'];
        yield 'image of a category updated' => [static fn (): object => (new FileCreateOrUpdateEvent(42))->setModel((new CategoryImage())->setCategoryId(42)), TheliaEvents::IMAGE_UPDATE, 'category'];
        yield 'image of a content deleted' => [static fn (): object => new FileDeleteEvent((new ContentImage())->setContentId(42)), TheliaEvents::IMAGE_DELETE, 'content'];
        yield 'image of a folder saved' => [static fn (): object => (new FileCreateOrUpdateEvent(42))->setModel((new FolderImage())->setFolderId(42)), TheliaEvents::IMAGE_SAVE, 'folder'];
        yield 'image of a brand saved' => [static fn (): object => (new FileCreateOrUpdateEvent(42))->setModel((new BrandImage())->setBrandId(42)), TheliaEvents::IMAGE_SAVE, 'brand'];
        yield 'document of a product deleted' => [static fn (): object => new FileDeleteEvent((new ProductDocument())->setProductId(42)), TheliaEvents::DOCUMENT_DELETE, 'product'];
        yield 'document of a category saved' => [static fn (): object => (new FileCreateOrUpdateEvent(42))->setModel((new CategoryDocument())->setCategoryId(42)), TheliaEvents::DOCUMENT_SAVE, 'category'];
        yield 'document of a content updated' => [static fn (): object => (new FileCreateOrUpdateEvent(42))->setModel((new ContentDocument())->setContentId(42)), TheliaEvents::DOCUMENT_UPDATE, 'content'];
        yield 'document of a folder deleted' => [static fn (): object => new FileDeleteEvent((new FolderDocument())->setFolderId(42)), TheliaEvents::DOCUMENT_DELETE, 'folder'];
        yield 'document of a brand updated' => [static fn (): object => (new FileCreateOrUpdateEvent(42))->setModel((new BrandDocument())->setBrandId(42)), TheliaEvents::DOCUMENT_UPDATE, 'brand'];
        yield 'video of a product deleted' => [static fn (): object => new FileDeleteEvent((new ProductVideo())->setProductId(42)), TheliaEvents::PRODUCT_VIDEO_DELETE, 'product'];
    }

    public function testAMediumMovedOrHiddenIsFoundByItsId(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $document = (new ProductDocument())->setProductId((int) $product->getId())->setFile('shared-cache-probe.pdf');
        $document->save($this->getPropelConnection());

        $this->listener->onMediumChange(new UpdateFilePositionEvent(ProductDocumentQuery::create(), (int) $document->getId(), UpdatePositionEvent::POSITION_ABSOLUTE, 1));
        $this->listener->onMediumChange(new FileToggleVisibilityEvent(ProductDocumentQuery::create(), (int) $document->getId()));
        $this->listener->flush();

        self::assertSame([SharedCacheKey::forChange('product', (int) $product->getId())], $this->purger->calls);
    }

    /**
     * The medium of a module is never asked for its parent: HeaderHighlights answers with
     * the medium itself, which breaks the return type of the interface.
     */
    public function testAMediumOfAModuleStillPurgesTheWholeShop(): void
    {
        $medium = $this->createMock(FileModelInterface::class);
        $medium->method('getParentFileModel')->willThrowException(new \TypeError('Return value must be of type FileModelParentInterface'));
        $medium->method('getParentId')->willReturn(42);

        $this->listener->onMediumChange((new FileCreateOrUpdateEvent(42))->setModel($medium));
        $this->listener->onMediumChange(new FileCreateOrUpdateEvent(42));
        $this->listener->flush();

        self::assertSame([[SharedCacheKey::ALL]], $this->purger->calls);
    }

    #[DataProvider('mediumEventNames')]
    public function testEveryMediumActionIsSubscribedAsAMediumChange(string $eventName): void
    {
        $subscribed = SharedCacheInvalidationListener::getSubscribedEvents();

        self::assertArrayHasKey($eventName, $subscribed);
        self::assertSame('onMediumChange', $subscribed[$eventName][0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mediumEventNames(): iterable
    {
        foreach ([
            TheliaEvents::IMAGE_SAVE, TheliaEvents::IMAGE_UPDATE, TheliaEvents::IMAGE_UPDATE_POSITION, TheliaEvents::IMAGE_TOGGLE_VISIBILITY, TheliaEvents::IMAGE_DELETE,
            TheliaEvents::DOCUMENT_SAVE, TheliaEvents::DOCUMENT_UPDATE, TheliaEvents::DOCUMENT_UPDATE_POSITION, TheliaEvents::DOCUMENT_TOGGLE_VISIBILITY, TheliaEvents::DOCUMENT_DELETE,
            TheliaEvents::PRODUCT_VIDEO_CREATE, TheliaEvents::PRODUCT_VIDEO_UPDATE, TheliaEvents::PRODUCT_VIDEO_DELETE, TheliaEvents::PRODUCT_VIDEO_UPDATE_POSITION, TheliaEvents::PRODUCT_VIDEO_TOGGLE_VISIBILITY,
        ] as $eventName) {
            yield $eventName => [$eventName];
        }
    }

    public function testTheDeletionOfACombinationPurgesItsProductOnly(): void
    {
        $purger = $this->recordThroughTheContainer();
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $combination = $factory->productSaleElement($product);
        $this->flushTheContainerListener($purger);

        static::getContainer()->get('event_dispatcher')->dispatch(
            new ProductSaleElementDeleteEvent((int) $combination->getId(), (int) $currency->getId()),
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
        );
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();

        self::assertNull(ProductSaleElementsQuery::create()->findPk($combination->getId()));
        self::assertSame([SharedCacheKey::forChange('product', (int) $product->getId())], $purger->calls);
    }

    public function testMovingACategoryInTheTreePurgesThatCategory(): void
    {
        $purger = $this->recordThroughTheContainer();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $newParent = $factory->category();
        $this->flushTheContainerListener($purger);

        $category->setParent((int) $newParent->getId())->save($this->getPropelConnection());
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();

        self::assertSame([SharedCacheKey::forChange('category', (int) $category->getId())], $purger->calls);
    }

    public function testAttachingAnImageToACombinationPurgesItsProduct(): void
    {
        $purger = $this->recordThroughTheContainer();
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $combination = $factory->productSaleElement($product);
        $image = (new ProductImage())->setProductId((int) $product->getId())->setFile('shared-cache-probe.png');
        $image->save($this->getPropelConnection());
        $this->flushTheContainerListener($purger);

        $association = (new ProductSaleElementsProductImage())
            ->setProductSaleElementsId((int) $combination->getId())
            ->setProductImageId((int) $image->getId());
        $association->save($this->getPropelConnection());
        $association->delete($this->getPropelConnection());
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();

        self::assertSame([SharedCacheKey::forChange('product', (int) $product->getId())], $purger->calls);
    }

    public function testReorderingTheMediaOfAProductPurgesThatProduct(): void
    {
        $purger = $this->recordThroughTheContainer();
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $first = (new ProductImage())->setProductId((int) $product->getId())->setFile('shared-cache-probe-1.png');
        $first->save($this->getPropelConnection());
        $second = (new ProductImage())->setProductId((int) $product->getId())->setFile('shared-cache-probe-2.png');
        $second->save($this->getPropelConnection());
        $this->flushTheContainerListener($purger);

        (new ProductMediaOrder())->reorder((int) $product->getId(), [
            ['type' => ProductMediaOrder::TYPE_IMAGE, 'id' => (int) $second->getId()],
            ['type' => ProductMediaOrder::TYPE_IMAGE, 'id' => (int) $first->getId()],
        ]);
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();

        self::assertSame([SharedCacheKey::forChange('product', (int) $product->getId())], $purger->calls);
    }

    public function testTheQuickEditOfTheTitleOfADocumentPurgesItsObject(): void
    {
        $purger = $this->recordThroughTheContainer();
        $factory = $this->createFixtureFactory();
        $folder = $factory->folder();
        $document = (new FolderDocument())->setFolderId((int) $folder->getId())->setFile('shared-cache-probe.pdf');
        $document->save($this->getPropelConnection());
        $this->flushTheContainerListener($purger);

        $document->setLocale('en_US')->setTitle('Renamed');
        $document->save($this->getPropelConnection());
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();

        self::assertSame([SharedCacheKey::forChange('folder', (int) $folder->getId())], $purger->calls);
    }

    private function recordThroughTheContainer(): RecordingSharedCachePurger
    {
        $purger = new RecordingSharedCachePurger();
        static::getContainer()->set(SharedCachePurgerInterface::class, $purger);

        return $purger;
    }

    /**
     * Sends what the fixtures purged, so the test only sees what its own write purges.
     */
    private function flushTheContainerListener(RecordingSharedCachePurger $purger): void
    {
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();
        $purger->calls = [];
    }
}
