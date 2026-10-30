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

namespace Thelia\Tests\Integration\Domain\Catalog\Bundle;

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductBundleItem;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;

/**
 * The bundle of the plan: bike (1, required, 500, stock 7), helmet (2, required, 30, stock 5)
 * and lock (1, optional, 0 to 3, 20, stock 10), computed price, 10 % off.
 */
trait BuildsBundles
{
    private function component(FixtureFactory $factory, string $price, int $stock, array $overrides = []): Product
    {
        return $factory->product($factory->category(), $factory->taxRule(), Currency::getDefaultCurrency(), $overrides + [
            'basePrice' => (float) $price,
            'baseQuantity' => $stock,
        ]);
    }

    private function bundleProduct(FixtureFactory $factory, BundleCompositionService $service, BundlePriceMode $mode = BundlePriceMode::Computed, string $discount = '10', BundleDiscountType $type = BundleDiscountType::Percent, float $ownPrice = 999.0): Product
    {
        $bundle = $factory->product($factory->category(), $factory->taxRule(), Currency::getDefaultCurrency(), ['basePrice' => $ownPrice, 'baseQuantity' => 0, 'title' => 'Bundle']);

        return $service->setBundleSettings((int) $bundle->getId(), true, $mode, $discount, $type);
    }

    private function saleElementsOf(Product $product): ProductSaleElements
    {
        return $product->getDefaultSaleElements();
    }

    /**
     * @return array{bundle: Product, bike: ProductBundleItem, helmet: ProductBundleItem, lock: ProductBundleItem, bikeProduct: Product, helmetProduct: Product, lockProduct: Product}
     */
    private function exampleBundle(FixtureFactory $factory, BundleCompositionService $service, BundlePriceMode $mode = BundlePriceMode::Computed, string $discount = '10', BundleDiscountType $type = BundleDiscountType::Percent): array
    {
        $bikeProduct = $this->component($factory, '500', 7);
        $helmetProduct = $this->component($factory, '30', 5);
        $lockProduct = $this->component($factory, '20', 10);
        $bundle = $this->bundleProduct($factory, $service, $mode, $discount, $type);

        return [
            'bundle' => $bundle,
            'bike' => $service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($bikeProduct)->getId(), '1'),
            'helmet' => $service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($helmetProduct)->getId(), '2'),
            'lock' => $service->addItem((int) $bundle->getId(), (int) $this->saleElementsOf($lockProduct)->getId(), '1', '0', '3', false),
            'bikeProduct' => $bikeProduct,
            'helmetProduct' => $helmetProduct,
            'lockProduct' => $lockProduct,
        ];
    }
}
