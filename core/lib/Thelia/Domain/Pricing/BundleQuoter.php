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

namespace Thelia\Domain\Pricing;

use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRule;

/**
 * The price of a composition of a bundle, for a page: the price of the line the composition
 * would make in the cart of this customer (BundleEffectivePriceSource, then the discounts of the
 * group and of the customer by CustomerPriceContextInterface::resolveBundlePrices(), as the
 * CartLinePricer applies them), taxed by the tax rule of the bundle product for the delivery
 * country the visitor is shown, the way the prices of a product are.
 *
 * Nothing is computed here that the cart does not compute: a theme shows the figures, it does
 * not add, subtract or tax them. Every figure is given with and without its taxes, and
 * `displaysTaxedPrices` says which of the two the group of the customer is shown.
 */
final readonly class BundleQuoter
{
    public function __construct(
        private BundleEffectivePriceSource $bundleEffectivePriceSource,
        private TaxEngine $taxEngine,
        private CustomerPriceContextInterface $customerPriceContext,
    ) {
    }

    /**
     * @param Country|null $country the country the prices are taxed for; the delivery country of the visitor when null
     *
     * @return BundleQuote|null null for a bundle that is not priced by its components (its sale element is priced like any product's), or a composition the bundle cannot hold
     */
    public function quote(
        int $bundleProductId,
        int $saleElementsId,
        BundleComposition $composition,
        Currency $currency,
        ?Customer $customer = null,
        ?Country $country = null,
    ): ?BundleQuote {
        $effective = $this->bundleEffectivePriceSource->forLines([0 => [$bundleProductId, $saleElementsId, $composition]], $currency)[0] ?? null;

        if (null === $effective) {
            return null;
        }

        // The group and the customer discounts, in the one resolution the cart line is priced by.
        $resolved = $this->customerPriceContext->resolveBundlePrices([0 => $effective], $customer)[0];
        $settled = new SettledPrice(
            $resolved->untaxedPrice,
            $resolved->chargedPrice(),
            $resolved->isPromo,
            $resolved->displayInitialPrice,
            true,
        );

        $product = ProductQuery::create()->findPk($bundleProductId);
        $country ??= $this->taxEngine->getDeliveryCountry();
        $taxed = fn (float $untaxed): float => $this->taxed($product, $country, $untaxed);

        $saving = $settled->untaxedSaving();

        return new BundleQuote(
            $settled->untaxedPromoPrice,
            $taxed($settled->untaxedPromoPrice),
            null === $saving ? null : $settled->untaxedPrice,
            null === $saving ? null : $taxed($settled->untaxedPrice),
            $saving,
            $settled->taxedSaving($taxed),
            $this->customerPriceContext->displaysTaxedPrices($customer),
        );
    }

    private function taxed(?Product $product, Country $country, float $untaxed): float
    {
        // No tax rule means no tax applies: the taxed price is the untaxed one.
        if (!$product instanceof Product || !$product->getTaxRule() instanceof TaxRule) {
            return $untaxed;
        }

        return (float) $product->getTaxedPrice($country, $untaxed);
    }
}
