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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleProductsQueryEvent;
use Thelia\Core\Event\Sale\SaleToggleActivityEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A module narrows the products a sale applies to in SQL, through the query the sale hands it.
 */
final class SaleProductsQueryEventTest extends ActionIntegrationTestCase
{
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->factory->currency();
    }

    public function testTheEventCarriesTheSaleAndItsSelectionBeforeItIsRead(): void
    {
        $product = $this->catalogProduct();
        $sale = $this->createSale();
        $received = [];
        $listener = static function (SaleProductsQueryEvent $event) use (&$received): void {
            $received = [
                'saleId' => $event->getSale()->getId(),
                'productIds' => array_map(
                    static fn ($saleProduct): int => $saleProduct->getProductId(),
                    iterator_to_array($event->getQuery()->find()),
                ),
            ];
        };

        $this->dispatcher->addListener(TheliaEvents::SALE_PRODUCTS_QUERY, $listener);

        try {
            $this->updateSale($sale, [$product]);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::SALE_PRODUCTS_QUERY, $listener);
        }

        self::assertSame($sale->getId(), $received['saleId']);
        self::assertSame([$product->getId()], $received['productIds'], 'the query is already filtered by the sale');
    }

    public function testAProductKeptOutByAListenerIsNotPutOnSale(): void
    {
        $kept = $this->catalogProduct();
        $excluded = $this->catalogProduct();
        $sale = $this->createSale();

        $this->withExclusion($excluded, fn () => $this->updateSale($sale, [$kept, $excluded]));

        self::assertSame(1, (int) $this->defaultPseFor($kept)->getPromo());
        self::assertSame(90.0, $this->promoPriceOf($this->defaultPseFor($kept)), 'the product left in the selection is discounted');
        self::assertSame(0, (int) $this->defaultPseFor($excluded)->getPromo());
        self::assertSame(100.0, $this->promoPriceOf($this->defaultPseFor($excluded)), 'the excluded product keeps its price');
    }

    public function testAProductExcludedAfterBeingOnSaleLosesItsPromoStatus(): void
    {
        $kept = $this->catalogProduct();
        $excluded = $this->catalogProduct();
        $sale = $this->createSale();

        $this->updateSale($sale, [$kept, $excluded]);
        self::assertSame(1, (int) $this->defaultPseFor($excluded)->getPromo(), 'without a listener the whole selection is on sale');

        $this->withExclusion($excluded, fn () => $this->updateSale($sale, [$kept, $excluded]));

        self::assertSame(0, (int) $this->defaultPseFor($excluded)->getPromo());
        self::assertSame(1, (int) $this->defaultPseFor($kept)->getPromo());
    }

    public function testAProductKeptOutWithItsOwnPromotionKeepsItWhenTheListenerAsks(): void
    {
        $kept = $this->catalogProduct();
        $clearance = $this->catalogProduct();
        $this->putOnPromotion($clearance, 80.0);
        $sale = $this->createSale();

        $this->withExclusion($clearance, fn () => $this->updateSale($sale, [$kept, $clearance]), keepPromoStatus: true);

        self::assertSame(1, (int) $this->defaultPseFor($kept)->getPromo());
        self::assertSame(90.0, $this->promoPriceOf($this->defaultPseFor($kept)));
        self::assertSame(1, (int) $this->defaultPseFor($clearance)->getPromo(), 'the excluded product keeps its own promotion');
        self::assertSame(80.0, $this->promoPriceOf($this->defaultPseFor($clearance)), 'and its own promo price');

        $activeSale = SaleQuery::create()->findPk($sale->getId());
        self::assertTrue((bool) $activeSale?->getActive());
        $this->withExclusion($clearance, fn () => $this->dispatch(new SaleToggleActivityEvent($activeSale), TheliaEvents::SALE_TOGGLE_ACTIVITY), keepPromoStatus: true);

        self::assertSame(0, (int) $this->defaultPseFor($kept)->getPromo(), 'the sale is over for the product it selects');
        self::assertSame(1, (int) $this->defaultPseFor($clearance)->getPromo(), 'not for the product it never selected');
    }

    private function putOnPromotion(Product $product, float $promoPrice): void
    {
        $pse = $this->defaultPseFor($product);
        $pse->setPromo(1)->save();
        ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->filterByCurrencyId($this->currency->getId())
            ->findOne()
            ->setPromoPrice((string) $promoPrice)
            ->save();
    }

    private function withExclusion(Product $excluded, callable $action, bool $keepPromoStatus = false): void
    {
        $listener = static function (SaleProductsQueryEvent $event) use ($excluded, $keepPromoStatus): void {
            $event->getQuery()->filterByProductId($excluded->getId(), Criteria::NOT_EQUAL);

            if ($keepPromoStatus) {
                $event->keepExcludedProductsPromoStatus();
            }
        };

        $this->dispatcher->addListener(TheliaEvents::SALE_PRODUCTS_QUERY, $listener);

        try {
            $action();
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::SALE_PRODUCTS_QUERY, $listener);
        }
    }

    private function createSale(): Sale
    {
        return $this->dispatch(
            (new SaleCreateEvent())->setLocale('en_US')->setTitle('Operation')->setSaleLabel('OP'),
            TheliaEvents::SALE_CREATE,
        )->getSale();
    }

    /**
     * @param list<Product> $products
     */
    private function updateSale(Sale $sale, array $products): void
    {
        $event = new SaleUpdateEvent($sale->getId());
        $event
            ->setLocale('en_US')
            ->setTitle('Operation')
            ->setSaleLabel('OP')
            ->setActive(true)
            ->setStartDate(new \DateTime('-1 day'))
            ->setEndDate(new \DateTime('+1 day'))
            ->setPriceOffsetType(Sale::OFFSET_TYPE_PERCENTAGE)
            ->setDisplayInitialPrice(true)
            ->setChapo('')
            ->setDescription('')
            ->setPostscriptum('')
            ->setPriceOffsets([$this->currency->getId() => '10'])
            ->setProducts(array_map(static fn (Product $product): int => $product->getId(), $products))
            ->setProductAttributes([])
            ->setAudienceMode(Sale::AUDIENCE_MODE_PUBLIC)
            ->setHideProducts(false)
            ->setCountdownMode(Sale::COUNTDOWN_MODE_NONE)
            ->setCountdownLeadHours(null)
            ->setCustomerIds([]);

        $this->dispatch($event, TheliaEvents::SALE_UPDATE);
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0],
        );
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
    }

    private function promoPriceOf(ProductSaleElements $pse): float
    {
        return (float) ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->filterByCurrencyId($this->currency->getId())
            ->findOne()
            ->getPromoPrice();
    }
}
