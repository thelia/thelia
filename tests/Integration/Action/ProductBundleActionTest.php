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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Product\ProductBundleItemEvent;
use Thelia\Core\Event\Product\ProductBundleItemPositionEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Model\Product;
use Thelia\Model\ProductBundleItemQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\RecordsStatements;

/**
 * The events of the back office and of the catalogue that carry a bundle.
 */
final class ProductBundleActionTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use RecordsStatements;

    private BundleCompositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(BundleCompositionService::class);
    }

    public function testTheBundleSettingsAreWrittenByTheUpdateEvent(): void
    {
        $product = $this->component($this->factory, '10', 1);

        $event = $this->updateEvent($product)
            ->setIsBundle(true)
            ->setBundlePriceMode(BundlePriceMode::Computed)
            ->setBundleDiscount('15')
            ->setBundleDiscountType(BundleDiscountType::Amount);
        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE);

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertSame(1, $reloaded?->getIsBundle());
        self::assertSame('computed', $reloaded?->getBundlePriceMode());
        self::assertSame('15.000000', $reloaded?->getBundleDiscount());
        self::assertSame('amount', $reloaded?->getBundleDiscountType());
    }

    public function testTheUpdateEventHoldsTheProductBeforeItsFirstPlainRead(): void
    {
        $product = $this->component($this->factory, '10', 1);
        $event = $this->updateEvent($product)->setIsBundle(true);

        $statements = $this->statementsDuring(fn () => $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE));

        $firstLock = $this->firstStatementMatching($statements, '/FROM `product` WHERE `id` = \d+ FOR UPDATE/');
        $firstTranslationRead = $this->firstStatementMatching($statements, '/FROM `product_i18n`/');

        self::assertNotNull($firstLock, 'The row of the product is held.');
        self::assertNotNull($firstTranslationRead);
        self::assertLessThan($firstTranslationRead, $firstLock, 'The picture of the transaction is taken after the row is held.');
    }

    public function testTurningAProductIntoABundleTakesNoSharedLockOnItsSaleElements(): void
    {
        $product = $this->component($this->factory, '10', 1);

        $statements = $this->statementsDuring(fn () => $this->service->setBundleSettings((int) $product->getId(), true));

        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/LOCK IN SHARE MODE|FOR SHARE/i', $statement))));
        self::assertSame(1, ProductQuery::create()->findPk($product->getId())?->getIsBundle());
    }

    public function testAnItemIsAddedUnderTheLocksOfThePackAndOfItsComponentTheSmallerIdFirst(): void
    {
        // The component is created before the pack, then the other way round: the smaller id is held first each time.
        $component = $this->component($this->factory, '10', 4);
        $bundle = $this->bundleProduct($this->factory, $this->service);
        $laterComponent = $this->component($this->factory, '10', 4);

        foreach ([[$bundle, $component], [$bundle, $laterComponent]] as [$pack, $taken]) {
            $statements = $this->statementsDuring(fn () => $this->service->addItem((int) $pack->getId(), (int) $this->saleElementsOf($taken)->getId()));

            $locked = [];

            foreach ($statements as $statement) {
                if (1 === preg_match('/FROM `product` WHERE `id` = (\d+) FOR UPDATE/', $statement, $matches)) {
                    $locked[] = (int) $matches[1];
                }
            }

            $expected = array_unique([(int) $pack->getId(), (int) $taken->getId()]);
            sort($expected);

            self::assertSame($expected, $locked, 'Both rows, the smaller id first.');
        }
    }

    public function testAnUpdateThatDoesNotCarryTheSettingsLeavesThemIntact(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service, BundlePriceMode::Computed, '15', BundleDiscountType::Amount);

        $this->dispatcher->dispatch($this->updateEvent($bundle), TheliaEvents::PRODUCT_UPDATE);

        $reloaded = ProductQuery::create()->findPk($bundle->getId());
        self::assertSame(1, $reloaded?->getIsBundle());
        self::assertSame('computed', $reloaded?->getBundlePriceMode());
        self::assertSame('15.000000', $reloaded?->getBundleDiscount());
        self::assertSame('amount', $reloaded?->getBundleDiscountType());
    }

    public function testTheUpdateEventRefusesWhatTheServiceRefuses(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);

        $this->dispatcher->dispatch($this->updateEvent($example['bikeProduct'])->setIsBundle(true), TheliaEvents::PRODUCT_UPDATE);
    }

    public function testTheComponentEventsWriteThroughTheService(): void
    {
        $bundle = $this->bundleProduct($this->factory, $this->service);
        $component = $this->component($this->factory, '10', 4);
        $saleElementsId = (int) $this->saleElementsOf($component)->getId();

        $added = $this->dispatch(ProductBundleItemEvent::add((int) $bundle->getId(), $saleElementsId, '2', '1', '4', false), TheliaEvents::PRODUCT_BUNDLE_ITEM_ADD);
        $itemId = (int) $added->getItem()?->getId();
        self::assertGreaterThan(0, $itemId);

        $this->dispatch(ProductBundleItemEvent::update($itemId, $saleElementsId, '3', '1', '4', false), TheliaEvents::PRODUCT_BUNDLE_ITEM_UPDATE);
        self::assertSame('3.000000', ProductBundleItemQuery::create()->findPk($itemId)?->getQuantity());

        $this->dispatch(new ProductBundleItemPositionEvent($itemId, 5), TheliaEvents::PRODUCT_BUNDLE_ITEM_UPDATE_POSITION);
        self::assertSame(5, ProductBundleItemQuery::create()->findPk($itemId)?->getPosition());

        $this->dispatch(ProductBundleItemEvent::delete($itemId), TheliaEvents::PRODUCT_BUNDLE_ITEM_DELETE);
        self::assertNull(ProductBundleItemQuery::create()->findPk($itemId));
    }

    public function testTheUpdateEventRefusesAnotherSaleElement(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $other = $this->component($this->factory, '15', 5);

        $this->expectException(BundleDefinitionException::class);

        $this->dispatch(ProductBundleItemEvent::update((int) $example['lock']->getId(), (int) $this->saleElementsOf($other)->getId(), '1', '0', '3', false), TheliaEvents::PRODUCT_BUNDLE_ITEM_UPDATE);
    }

    public function testTheComponentEventsRefuseABundleInsideABundle(): void
    {
        $inner = $this->bundleProduct($this->factory, $this->service);
        $outer = $this->bundleProduct($this->factory, $this->service);

        $this->expectException(BundleDefinitionException::class);

        $this->dispatch(ProductBundleItemEvent::add((int) $outer->getId(), (int) $this->saleElementsOf($inner)->getId()), TheliaEvents::PRODUCT_BUNDLE_ITEM_ADD);
    }

    public function testACloneOfABundleHoldsTheSameSettingsAndComponents(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service, BundlePriceMode::Computed, '12', BundleDiscountType::Percent);

        $event = new ProductCloneEvent($example['bundle']->getRef().'-CLONE', 'en_US', $example['bundle']);
        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        $clone = ProductQuery::create()->findPk($event->getClonedProduct()->getId());
        self::assertSame(1, $clone?->getIsBundle());
        self::assertSame('computed', $clone?->getBundlePriceMode());
        self::assertSame('12.000000', $clone?->getBundleDiscount());
        self::assertSame('percent', $clone?->getBundleDiscountType());

        $copies = ProductBundleItemQuery::create()->filterByBundleProductId($clone->getId())->orderByPosition()->find();
        self::assertCount(3, $copies);
        self::assertSame($example['bike']->getProductSaleElementsId(), $copies[0]->getProductSaleElementsId());
        self::assertSame($example['helmet']->getProductSaleElementsId(), $copies[1]->getProductSaleElementsId());
        self::assertSame(3, ProductBundleItemQuery::create()->filterByBundleProductId($example['bundle']->getId())->count(), 'The source keeps its own components.');
    }

    /**
     * @param list<string> $statements
     */
    private function firstStatementMatching(array $statements, string $pattern): ?int
    {
        foreach ($statements as $position => $statement) {
            if (1 === preg_match($pattern, $statement)) {
                return $position;
            }
        }

        return null;
    }

    private function updateEvent(Product $product): ProductUpdateEvent
    {
        $event = new ProductUpdateEvent((int) $product->getId());
        $event
            ->setLocale('en_US')
            ->setRef($product->getRef())
            ->setTitle('Bundle probe')
            ->setDefaultCategory($product->getDefaultCategoryId())
            ->setVisible(true)
            ->setVirtual(false);

        return $event;
    }
}
