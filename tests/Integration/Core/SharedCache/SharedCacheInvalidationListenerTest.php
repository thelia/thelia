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

use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\Brand\BrandEvent;
use Thelia\Core\Event\Content\ContentEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\Folder\FolderEvent;
use Thelia\Core\Event\Product\ProductEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ToggleVisibilityEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\SharedCache\NullSharedCachePurger;
use Thelia\Core\SharedCache\SharedCacheInvalidationListener;
use Thelia\Core\SharedCache\SharedCacheKey;
use Thelia\Core\SharedCache\SharedCachePurgeQueue;
use Thelia\Core\SharedCache\SharedCachePurgerInterface;
use Thelia\Model\Brand;
use Thelia\Model\Content;
use Thelia\Model\Folder;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Unit\Core\SharedCache\RecordingSharedCachePurger;

final class SharedCacheInvalidationListenerTest extends IntegrationTestCase
{
    private RecordingSharedCachePurger $purger;

    private SharedCacheInvalidationListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purger = new RecordingSharedCachePurger();
        $this->listener = new SharedCacheInvalidationListener(new SharedCachePurgeQueue($this->purger));
    }

    public function testAProductChangePurgesThatProductAndWhatListsProducts(): void
    {
        $this->listener->onCatalogueChange(new ProductEvent((new Product())->setId(42)), TheliaEvents::PRODUCT_UPDATE);
        $this->listener->flush();

        self::assertSame([['product-42', 'products', 'product_sale_elements', 'product_images', 'product_sale_elements_product_image']], $this->purger->calls);
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

        self::assertSame([['category-7', 'categories', 'products']], $this->purger->calls);
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

        self::assertSame([['brand-3', 'brands', 'products']], $this->purger->calls);
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
}
