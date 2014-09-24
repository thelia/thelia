<?php

namespace Thelia\Model;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Base\ProductSaleElements as BaseProductSaleElements;
use Thelia\Model\Tools\ProductPriceTools;
use Thelia\TaxEngine\Calculator;

class ProductSaleElements extends BaseProductSaleElements
{
    public function getPrice($virtualColumnName = 'price_PRICE', $discount = 0)
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);

            if ($discount > 0) {
                $amount = $amount * (1-($discount/100));
            }
        } catch (PropelException $e) {
            throw new PropelException("Virtual column `$virtualColumnName` does not exist in ProductSaleElements::getPrice");
        }

        return $amount;
    }

    public function getPromoPrice($virtualColumnName = 'price_PROMO_PRICE', $discount = 0)
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);

            if ($discount > 0) {
                $amount = $amount * (1-($discount/100));
            }
        } catch (PropelException $e) {
            throw new PropelException("Virtual column `$virtualColumnName` does not exist in ProductSaleElements::getPromoPrice");
        }

        return $amount;
    }

    public function getTaxedPrice(Country $country, $virtualColumnName = 'price_PRICE', $discount = 0)
    {
        $taxCalculator = new Calculator();

        return round($taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPrice($virtualColumnName, $discount)), 2);
    }

    public function getTaxedPromoPrice(Country $country, $virtualColumnName = 'price_PROMO_PRICE', $discount = 0)
    {
        $taxCalculator = new Calculator();

        return round($taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPromoPrice($virtualColumnName, $discount)), 2);
    }

    /**
     * Get product prices for a specific currency.
     *
     * When the currency is not the default currency, the product prices for this currency is :
     * - calculated according to the product price of the default currency. It happens when no product price exists for
     *      the currency or when the `from_default_currency` flag is set to `true`
     * - set directly in the product price when `from_default_currency` is set to `false`
     *
     * @param  Currency          $currency
     * @return ProductPriceTools
     * @throws \RuntimeException
     */
    public function getPricesByCurrency(Currency $currency, $discount = 0)
    {
        $defaultCurrency = Currency::getDefaultCurrency();

        $productPrice = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($this->getId())
            ->filterByCurrencyId($currency->getId())
            ->findOne();

        $price = 0.0;
        $promoPrice = 0.0;

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
                throw new \RuntimeException('Cannot find product prices for currency id: `' . $currency->getId() . '`');
            }
        } else {
            $price = $productPrice->getPrice();
            $promoPrice = $productPrice->getPromoPrice();
        }

        if ($discount > 0) {
            $price = $price * (1-($discount/100));
            $promoPrice = $promoPrice * (1-($discount/100));
        }

        $productPriceTools = new ProductPriceTools($price, $promoPrice);

        return $productPriceTools;
    }
}
