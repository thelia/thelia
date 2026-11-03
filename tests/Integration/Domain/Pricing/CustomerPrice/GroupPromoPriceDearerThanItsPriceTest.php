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

namespace Thelia\Tests\Integration\Domain\Pricing\CustomerPrice;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Model\Currency;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * CustomerPriceContext: "the cheaper of the two wins" and "a promo price equal to the price is
 * no promotion", and the catalog promotion dearer than the discounted base "is then none".
 * A promo price the group has of its own follows the same rule: it is never charged above the
 * price it is shown against, and never shown as a promotion dearer than its crossed-out price.
 */
final class GroupPromoPriceDearerThanItsPriceTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetRequestMemos();
    }

    protected function tearDown(): void
    {
        $this->resetRequestMemos();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{float, ?float, float, float}>
     */
    public static function groupPricesWithADearerPromo(): iterable
    {
        // catalog 100, group 15 %: the promotion is shown against 85
        yield 'promo of the group only, above the discounted catalog price' => [15.0, null, 90.0, 85.0];
        // price of the group 80: the promotion is shown against 80
        yield 'price and promo of the group, the promo above the price' => [0.0, 80.0, 95.0, 80.0];
    }

    #[DataProvider('groupPricesWithADearerPromo')]
    public function testAPromoPriceOfTheGroupDearerThanThePriceItIsShownAgainstIsNoPromotion(
        float $groupDiscount,
        ?float $groupPrice,
        float $groupPromoPrice,
        float $expectedCharged,
    ): void {
        $currency = Currency::getDefaultCurrency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['basePrice' => 100.0, 'baseQuantity' => 100]);
        $pse = $product->getDefaultSaleElements();
        $group = $this->factory->customerGroup(discount: $groupDiscount);
        $this->factory->customerGroupProductPrice($group, $pse, $currency, $groupPrice, $groupPromoPrice);
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $this->resetRequestMemos();

        $price = $this->getService(CustomerPriceContextInterface::class)
            ->resolve([$pse->getId()], $currency, $member)[$pse->getId()];

        self::assertEqualsWithDelta($expectedCharged, $price->chargedPrice(), 0.000001, 'The member is charged the dearer promotion instead of the price it is shown against.');
        self::assertFalse($price->isPromo && $price->untaxedPromoPrice >= $price->untaxedPrice, 'A promotion dearer than its crossed-out price is shown.');
    }

    private function resetRequestMemos(): void
    {
        foreach ([
            CatalogActivityProbe::class,
            CustomerGroupResolver::class,
            CustomerDiscountRate::class,
            CustomerPriceContext::class,
            EffectivePriceCatalog::class,
        ] as $service) {
            $this->getService($service)->reset();
        }
    }
}
