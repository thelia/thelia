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

use Thelia\Core\Event\Sale\SaleActiveStatusCheckEvent;
use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleDeleteEvent;
use Thelia\Core\Event\Sale\SaleToggleActivityEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleCustomerQuery;
use Thelia\Model\SaleQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A reserved operation is a promise made to named customers, not a catalog price.
 * It must never write `product_sale_elements.promo` nor `product_price.promo_price`:
 * those two are what every visitor sees, and a shared product would leak the
 * reserved discount to the whole shop — or, worse, lose the public discount it
 * already had.
 */
final class ReservedSaleActionTest extends ActionIntegrationTestCase
{
    public function testAnActiveReservedOperationWritesNoPublicPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->factory->customer($this->factory->customerTitle());

        // Product::create() writes promo_price = price, so "untouched" is the base price.
        $untouchedPromoPrice = $this->promoPriceOf($this->defaultPseFor($product), $currency);
        self::assertSame(100.0, $untouchedPromoPrice);

        $sale = $this->createSale();
        $this->updateSale($sale, [
            'active' => true,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$customer->getId()],
        ]);

        $pse = $this->defaultPseFor($product);
        self::assertSame(0, (int) $pse->getPromo(), 'a reserved operation leaves the public promo flag alone');
        self::assertSame(
            $untouchedPromoPrice,
            $this->promoPriceOf($pse, $currency),
            'a reserved operation writes no public promo price',
        );
    }

    /**
     * The reset of `promo` in updateProductsSaleStatus covers every sale element of
     * every product of the operation, not just the ones it discounts. Running it for
     * a reserved operation would wipe the public discount of a product both
     * operations happen to include.
     */
    public function testAReservedOperationDoesNotWipeThePublicPromoOfASharedProduct(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);

        $publicSale = $this->createSale();
        $this->updateSale($publicSale, [
            'active' => true,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
        ]);

        $pse = $this->defaultPseFor($product);
        self::assertSame(1, (int) $pse->getPromo(), 'the public operation is the one that flags the sale element');
        $publicPromoPrice = $this->promoPriceOf($pse, $currency);
        self::assertGreaterThan(0.0, $publicPromoPrice);

        $reservedSale = $this->createSale();
        $this->updateSale($reservedSale, [
            'active' => true,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '50'],
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$this->factory->customer($this->factory->customerTitle())->getId()],
        ]);

        $pse = $this->defaultPseFor($product);
        self::assertSame(1, (int) $pse->getPromo(), 'the public promo flag survives the reserved operation');
        self::assertSame(
            $publicPromoPrice,
            $this->promoPriceOf($pse, $currency),
            'the public promo price survives the reserved operation',
        );
    }

    /**
     * An operation that was public wrote public flags. Making it reserved has to take
     * them back, otherwise the discount stays visible to everyone for good.
     */
    public function testTurningAPublicOperationIntoAReservedOneClearsThePublicFlags(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);

        $sale = $this->createSale();
        $this->updateSale($sale, [
            'active' => true,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
        ]);

        self::assertSame(1, (int) $this->defaultPseFor($product)->getPromo());

        $this->updateSale($sale, [
            'active' => true,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$this->factory->customer($this->factory->customerTitle())->getId()],
        ]);

        self::assertSame(
            0,
            (int) $this->defaultPseFor($product)->getPromo(),
            'the promo flag the operation wrote while it was public is taken back',
        );
    }

    public function testUpdatePersistsTheAudienceAndCountdownFields(): void
    {
        $sale = $this->createSale();

        $this->updateSale($sale, [
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
            'countdownMode' => Sale::COUNTDOWN_MODE_LEAD_HOURS,
            'countdownLeadHours' => 48,
        ]);

        $reloaded = SaleQuery::create()->findPk($sale->getId());
        self::assertSame(Sale::AUDIENCE_MODE_CUSTOMERS, $reloaded->getAudienceMode());
        self::assertTrue($reloaded->getHideProducts());
        self::assertSame(Sale::COUNTDOWN_MODE_LEAD_HOURS, $reloaded->getCountdownMode());
        self::assertSame(48, $reloaded->getCountdownLeadHours());
        self::assertTrue($reloaded->isReserved());
    }

    public function testUpdateResynchronisesTheTargetedCustomers(): void
    {
        $first = $this->factory->customer($this->factory->customerTitle());
        $second = $this->factory->customer($this->factory->customerTitle());
        $sale = $this->createSale();

        $this->updateSale($sale, [
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$first->getId(), $second->getId()],
        ]);

        self::assertSame([$first->getId(), $second->getId()], $this->targetedCustomerIds($sale));

        // Removing one from the selection removes the row, it does not just stop reading it.
        $this->updateSale($sale, [
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$second->getId()],
        ]);

        self::assertSame([$second->getId()], $this->targetedCustomerIds($sale));
    }

    /**
     * An operation open to everyone has no audience to keep: leaving the rows behind
     * would make it reserved again the moment someone flips the mode back.
     */
    public function testGoingBackToAPublicAudienceEmptiesTheTargeting(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $sale = $this->createSale();

        $this->updateSale($sale, [
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$customer->getId()],
        ]);
        self::assertCount(1, $this->targetedCustomerIds($sale));

        $this->updateSale($sale, [
            'audienceMode' => Sale::AUDIENCE_MODE_PUBLIC,
            'customerIds' => [$customer->getId()],
        ]);

        self::assertSame([], $this->targetedCustomerIds($sale));
    }

    /**
     * The mirror of the switch above: an operation that opens up to everyone starts
     * writing the public flags it never wrote while it was reserved.
     */
    public function testTurningAReservedOperationIntoAPublicOneWritesThePublicFlags(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);

        $sale = $this->createSale();
        $this->updateSale($sale, [
            'active' => true,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$this->factory->customer($this->factory->customerTitle())->getId()],
        ]);

        self::assertSame(0, (int) $this->defaultPseFor($product)->getPromo());

        $this->updateSale($sale, [
            'active' => true,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
            'audienceMode' => Sale::AUDIENCE_MODE_PUBLIC,
        ]);

        $pse = $this->defaultPseFor($product);
        self::assertSame(1, (int) $pse->getPromo());
        self::assertSame(90.0, $this->promoPriceOf($pse, $currency));
    }

    public function testDeletingAReservedOperationWritesNoPublicPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);

        $sale = $this->createSale();
        $this->updateSale($sale, [
            'active' => true,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$this->factory->customer($this->factory->customerTitle())->getId()],
        ]);

        $this->dispatch(new SaleDeleteEvent($sale->getId()), TheliaEvents::SALE_DELETE);

        self::assertNull(SaleQuery::create()->findPk($sale->getId()));
        $pse = $this->defaultPseFor($product);
        self::assertSame(0, (int) $pse->getPromo());
        self::assertSame(100.0, $this->promoPriceOf($pse, $currency));
    }

    public function testTogglingAReservedOperationWritesNoPublicPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);

        $sale = $this->createSale();
        $this->updateSale($sale, [
            'active' => false,
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$this->factory->customer($this->factory->customerTitle())->getId()],
        ]);

        $this->dispatch(
            new SaleToggleActivityEvent(SaleQuery::create()->findPk($sale->getId())),
            TheliaEvents::SALE_TOGGLE_ACTIVITY,
        );

        self::assertSame(1, (int) SaleQuery::create()->findPk($sale->getId())->getActive());

        $pse = $this->defaultPseFor($product);
        self::assertSame(0, (int) $pse->getPromo());
        self::assertSame(100.0, $this->promoPriceOf($pse, $currency), 'the catalog promo price is left as it was');
    }

    /**
     * The scheduled command opens and closes operations outside any HTTP request.
     * A reserved one still changes its `active` flag — that is what makes its price
     * resolvable — but writes no price of its own.
     */
    public function testCheckSaleActivationOpensAndClosesAReservedOperationWithoutWritingPrices(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);

        $sale = $this->createSale();
        $this->updateSale($sale, [
            'active' => false,
            'startDate' => new \DateTime('-2 days'),
            'endDate' => new \DateTime('+2 days'),
            'products' => [$product->getId()],
            'priceOffsets' => [$currency->getId() => '10'],
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$this->factory->customer($this->factory->customerTitle())->getId()],
        ]);

        $this->dispatch(new SaleActiveStatusCheckEvent(), TheliaEvents::CHECK_SALE_ACTIVATION_EVENT);

        self::assertSame(1, (int) SaleQuery::create()->findPk($sale->getId())->getActive(), 'the operation opens');
        $pse = $this->defaultPseFor($product);
        self::assertSame(0, (int) $pse->getPromo());
        self::assertSame(100.0, $this->promoPriceOf($pse, $currency));

        // Its end date is now in the past: the next run closes it.
        SaleQuery::create()->findPk($sale->getId())
            ->setEndDate(new \DateTime('-1 hour'))
            ->save();

        $this->dispatch(new SaleActiveStatusCheckEvent(), TheliaEvents::CHECK_SALE_ACTIVATION_EVENT);

        self::assertSame(0, (int) SaleQuery::create()->findPk($sale->getId())->getActive(), 'the operation closes');
        $pse = $this->defaultPseFor($product);
        self::assertSame(0, (int) $pse->getPromo());
        self::assertSame(100.0, $this->promoPriceOf($pse, $currency));
    }

    private function createSale(): Sale
    {
        return $this->dispatch(
            (new SaleCreateEvent())->setLocale('en_US')->setTitle('Operation')->setSaleLabel('OP'),
            TheliaEvents::SALE_CREATE,
        )->getSale();
    }

    private function updateSale(Sale $sale, array $overrides = []): SaleUpdateEvent
    {
        $event = new SaleUpdateEvent($sale->getId());
        $event
            ->setLocale('en_US')
            ->setTitle($overrides['title'] ?? 'Operation')
            ->setSaleLabel($overrides['saleLabel'] ?? 'OP')
            ->setActive($overrides['active'] ?? true)
            ->setStartDate($overrides['startDate'] ?? new \DateTime('-1 day'))
            ->setEndDate($overrides['endDate'] ?? new \DateTime('+1 day'))
            ->setPriceOffsetType($overrides['priceOffsetType'] ?? Sale::OFFSET_TYPE_PERCENTAGE)
            ->setDisplayInitialPrice(true)
            ->setChapo('')
            ->setDescription('')
            ->setPostscriptum('')
            ->setPriceOffsets($overrides['priceOffsets'] ?? [])
            ->setProducts($overrides['products'] ?? [])
            ->setProductAttributes($overrides['productAttributes'] ?? [])
            ->setAudienceMode($overrides['audienceMode'] ?? Sale::AUDIENCE_MODE_PUBLIC)
            ->setHideProducts($overrides['hideProducts'] ?? false)
            ->setCountdownMode($overrides['countdownMode'] ?? Sale::COUNTDOWN_MODE_NONE)
            ->setCountdownLeadHours($overrides['countdownLeadHours'] ?? null)
            ->setCustomerIds($overrides['customerIds'] ?? []);

        return $this->dispatch($event, TheliaEvents::SALE_UPDATE);
    }

    private function catalogProduct(Currency $currency): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
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

    private function promoPriceOf(ProductSaleElements $pse, Currency $currency): float
    {
        return (float) ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->filterByCurrencyId($currency->getId())
            ->findOne()
            ->getPromoPrice();
    }

    /**
     * @return list<int>
     */
    private function targetedCustomerIds(Sale $sale): array
    {
        return array_map(
            static fn ($saleCustomer): int => $saleCustomer->getCustomerId(),
            iterator_to_array(
                SaleCustomerQuery::create()
                    ->filterBySaleId($sale->getId())
                    ->orderByCustomerId()
                    ->find(),
            ),
        );
    }
}
