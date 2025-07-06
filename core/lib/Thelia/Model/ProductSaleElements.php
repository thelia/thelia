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

namespace Thelia\Model;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Base\ProductSaleElements as BaseProductSaleElements;
use Thelia\Model\Tools\ProductPriceTools;
use Thelia\TaxEngine\Calculator;

class ProductSaleElements extends BaseProductSaleElements
{
    /**
     * @param string $virtualColumnName
     * @param int    $discount
     *
     * @throws PropelException
     */
    public function getPrice($virtualColumnName = 'price_PRICE', $discount = 0): mixed
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);

            if ($discount > 0) {
                $amount *= (1 - ($discount / 100));
            }
        } catch (PropelException) {
            throw new PropelException(\sprintf('Virtual column `%s` does not exist in ProductSaleElements::getPrice', $virtualColumnName));
        }

        return $amount;
    }

    /**
     * @param string $virtualColumnName
     * @param int    $discount
     *
     * @throws PropelException
     */
    public function getPromoPrice($virtualColumnName = 'price_PROMO_PRICE', $discount = 0): mixed
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);

            if ($discount > 0) {
                $amount *= (1 - ($discount / 100));
            }
        } catch (PropelException) {
            throw new PropelException(\sprintf('Virtual column `%s` does not exist in ProductSaleElements::getPromoPrice', $virtualColumnName));
        }

        return $amount;
    }

    /**
     * @param string $virtualColumnName
     * @param int    $discount
     *
     * @throws PropelException
     *
     * @return int
     */
    public function getTaxedPrice(Country $country, $virtualColumnName = 'price_PRICE', $discount = 0)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPrice($virtualColumnName, $discount));
    }

    /**
     * @param string $virtualColumnName
     * @param int    $discount
     *
     * @throws PropelException
     *
     * @return int
     */
    public function getTaxedPromoPrice(Country $country, $virtualColumnName = 'price_PROMO_PRICE', $discount = 0)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPromoPrice($virtualColumnName, $discount));
    }

    /**
     * Get product prices for a specific currency.
     *
     * When the currency is not the default currency, the product prices for this currency is :
     * - calculated according to the product price of the default currency. It happens when no product price exists for
     *      the currency or when the `from_default_currency` flag is set to `true`
     * - set directly in the product price when `from_default_currency` is set to `false`
     *
     * @param int $discount
     *
     * @throws \RuntimeException
     * @throws PropelException
     *
     * @return ProductPriceTools
     */
    public function getPricesByCurrency(Currency $currency, $discount = 0)
    {
        $defaultCurrency = Currency::getDefaultCurrency();

        $productPrice = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($this->getId())
            ->filterByCurrencyId($currency->getId())
            ->findOne();

        if (null === $productPrice || $productPrice->getFromDefaultCurrency()) {
            // need to calculate the prices based on the product prices for the default currency
            $productPrice = ProductPriceQuery::create()
                ->filterByProductSaleElementsId($this->getId())
                ->filterByCurrencyId($defaultCurrency->getId())
                ->findOne();
            if (null !== $productPrice) {
                $price = $productPrice->getPrice() * $currency->getRate() / $defaultCurrency->getRate();
                $promoPrice = $productPrice->getPromoPrice() * $currency->getRate() / $defaultCurrency->getRate();
            } else {
                throw new \RuntimeException('Cannot find product prices for currency id: `'.$currency->getId().'`');
            }
        } else {
            $price = $productPrice->getPrice();
            $promoPrice = $productPrice->getPromoPrice();
        }

        if ($discount > 0) {
            $price *= (1 - ($discount / 100));
            $promoPrice *= (1 - ($discount / 100));
        }

        return new ProductPriceTools($price, $promoPrice);
    }
}
