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

namespace Thelia\Domain\Subscription;

use Thelia\Domain\Pricing\CurrencyConverter;
use Thelia\Domain\Subscription\Exception\SubscriptionPlanPriceMissingException;
use Thelia\Model\Currency;
use Thelia\Model\SubscriptionPlan;
use Thelia\Model\SubscriptionPlanPrice;
use Thelia\Model\SubscriptionPlanPriceQuery;

/**
 * What one installment of a plan costs, for one unit and without taxes, in a currency.
 *
 * Read the way a sale element price is (ProductSaleElements::getPricesByCurrency()): the
 * price typed in that currency, or, when there is none or it is flagged as converted
 * (from_default_currency), the price in the default currency converted at the rate of
 * the currency.
 */
final readonly class SubscriptionPlanPricing
{
    public function __construct(
        private CurrencyConverter $currencyConverter = new CurrencyConverter(),
    ) {
    }

    /**
     * @return string the price as the DECIMAL(16,6) columns hold it
     *
     * @throws SubscriptionPlanPriceMissingException
     */
    public function priceOf(SubscriptionPlan $plan, Currency $currency): string
    {
        $price = $this->priceRow($plan, (int) $currency->getId());

        if ($price instanceof SubscriptionPlanPrice && 1 !== (int) $price->getFromDefaultCurrency()) {
            return self::decimal((float) $price->getPrice());
        }

        $defaultCurrency = Currency::getDefaultCurrency();
        $defaultPrice = $this->priceRow($plan, (int) $defaultCurrency->getId());
        $converted = null === $defaultPrice ? null : $this->currencyConverter->fromDefaultCurrency((float) $defaultPrice->getPrice(), $currency);

        if (null === $converted) {
            throw new SubscriptionPlanPriceMissingException((int) $plan->getId(), (string) $currency->getCode());
        }

        return self::decimal($converted);
    }

    private function priceRow(SubscriptionPlan $plan, int $currencyId): ?SubscriptionPlanPrice
    {
        return SubscriptionPlanPriceQuery::create()
            ->filterBySubscriptionPlanId($plan->getId())
            ->filterByCurrencyId($currencyId)
            ->findOne();
    }

    private static function decimal(float $amount): string
    {
        return number_format(round($amount, 6), 6, '.', '');
    }
}
