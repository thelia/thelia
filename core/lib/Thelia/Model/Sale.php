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

use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Model\Base\Sale as BaseSale;
use Thelia\Model\Tools\UrlRewritingTrait;

class Sale extends BaseSale
{
    use UrlRewritingTrait;

    /**
     * An operation is a page of the shop: the one its countdown is shown on, and the
     * only address a private drop can be linked from. The view name is what the
     * rewriting router puts in `_view` and what `sale_id` is then read against.
     */
    public function getRewrittenUrlViewName(): string
    {
        return 'sale';
    }

    /** The price offsets types, either amount or percentage. */
    public const OFFSET_TYPE_PERCENTAGE = 10;

    public const OFFSET_TYPE_AMOUNT = 20;

    /** Who the operation is open to. */
    public const AUDIENCE_MODE_PUBLIC = 0;

    public const AUDIENCE_MODE_CUSTOMERS = 1;

    /** Customer groups are US #122: the mode exists, nothing reads the groups yet. */
    public const AUDIENCE_MODE_CUSTOMER_GROUPS = 2;

    /** When the countdown to the end of the operation is shown. */
    public const COUNTDOWN_MODE_NONE = 0;

    public const COUNTDOWN_MODE_LEAD_HOURS = 1;

    public const COUNTDOWN_MODE_FROM_OPENING = 2;

    /**
     * Whether the operation is open to a named audience rather than to everyone.
     *
     * This is the one question the price chain asks: a reserved operation never
     * writes a public promo flag or promo price, its discount is resolved for the
     * customer who is entitled to it instead.
     */
    public function isReserved(): bool
    {
        return self::AUDIENCE_MODE_PUBLIC !== $this->getAudienceMode();
    }

    /**
     * Whether the countdown to the end of the operation shows at the given instant.
     *
     * A countdown counts down to something, so an operation with no end date never
     * shows one, whatever its mode says. The lead-hours mode needs a number of hours
     * for the same reason: without it there is no threshold to compare against, and a
     * misconfigured row must not read as "start counting now".
     *
     * An operation that has not opened yet shows nothing either, in either mode: its
     * products are not on offer, so a countdown next to them would be counting down
     * to the end of something that has not begun. A window shorter than the lead time
     * is what makes the two disagree, and the start date is the one that wins.
     */
    public function shouldDisplayCountdown(\DateTimeInterface $now): bool
    {
        if (self::COUNTDOWN_MODE_NONE === $this->getCountdownMode()) {
            return false;
        }

        if (!$this->hasEndDate()) {
            return false;
        }

        $startDate = $this->getStartDate();

        if (null !== $startDate && $startDate > $now) {
            return false;
        }

        if (self::COUNTDOWN_MODE_FROM_OPENING === $this->getCountdownMode()) {
            return true;
        }

        $leadHours = $this->getCountdownLeadHours();

        if (self::COUNTDOWN_MODE_LEAD_HOURS !== $this->getCountdownMode() || null === $leadHours) {
            return false;
        }

        $threshold = (clone $this->getEndDate())->modify(\sprintf('-%d hours', $leadHours));

        return $now >= $threshold;
    }

    /**
     * @return bool true if the sale has an end date, false otherwise
     */
    public function hasStartDate(): bool
    {
        return null !== $this->getStartDate();
    }

    /**
     * @return bool true if the sale has a begin date, false otherwise
     */
    public function hasEndDate(): bool
    {
        return null !== $this->getEndDate();
    }

    /**
     * Get the price offsets for each of the currencies.
     *
     * @return array an array of (currency ID => offset value)
     */
    public function getPriceOffsets(): array
    {
        $currencyOffsets = SaleOffsetCurrencyQuery::create()->filterBySaleId($this->getId())->find();

        $offsetList = [];

        /** @var SaleOffsetCurrency $currencyOffset */
        foreach ($currencyOffsets as $currencyOffset) {
            $offsetList[$currencyOffset->getCurrencyId()] = $currencyOffset->getPriceOffsetValue();
        }

        return $offsetList;
    }

    /**
     * Return the products included in this sale.
     *
     * @return SaleProduct[]
     */
    public function getSaleProductList(): ObjectCollection
    {
        return SaleProductQuery::create()
            ->filterBySaleId($this->getId())
            ->groupByProductId()
            ->find();
    }

    /**
     * Return the selected attributes values for each of the selected products.
     *
     * @return array an array of (product ID => array of attribute availability ID)
     */
    public function getSaleProductsAttributeList(): array
    {
        $saleProducts = SaleProductQuery::create()->filterBySaleId($this->getId())->orderByProductId()->find();

        $selectedAttributes = [];

        $currentProduct = false;

        /** @var SaleProduct $saleProduct */
        foreach ($saleProducts as $saleProduct) {
            if ($currentProduct !== $saleProduct->getProductId()) {
                $currentProduct = $saleProduct->getProductId();

                $selectedAttributes[$currentProduct] = [];
            }

            $selectedAttributes[$currentProduct][] = $saleProduct->getAttributeAvId();
        }

        return $selectedAttributes;
    }
}
