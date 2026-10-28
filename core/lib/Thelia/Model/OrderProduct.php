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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Event\Order\OrderProductEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\OrderProduct as BaseOrderProduct;

class OrderProduct extends BaseOrderProduct
{
    /**
     * A good taken off the catalogue. What every line of every order placed before this
     * column existed is, and what the column defaults to.
     */
    public const LINE_TYPE_PRODUCT = 'product';

    /**
     * A service the shop invoiced beside the goods — a gift wrapping, today. It is priced
     * and taxed like a product so that the invoice, the credit note and the accounting
     * export need no special case, and it is marked so that everything which picks,
     * ships, weighs or counts stock can leave it alone.
     */
    public const LINE_TYPE_SERVICE = 'service';

    protected ?int $cartItemId = null;

    /**
     * Whether this line stands for something to pick and put in a parcel.
     *
     * The reading to branch on rather than a comparison against a string: a line type this
     * version does not know is something to handle, not something to skip, so anything
     * that is not explicitly a service reads as goods.
     */
    public function isProductLine(): bool
    {
        return self::LINE_TYPE_SERVICE !== $this->getLineType();
    }

    public function isServiceLine(): bool
    {
        return self::LINE_TYPE_SERVICE === $this->getLineType();
    }

    /**
     * @return $this
     */
    public function setCartItemId(?int $cartItemId)
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    public function getCartItemId(): ?int
    {
        return $this->cartItemId;
    }

    /**
     * The tax of one unit of the line, at the precision it is stored with: the sum of its tax
     * rows, read on the promotional price when the line was bought on promotion.
     *
     * @throws PropelException
     */
    public function getUnitTax(bool $onPromo): float
    {
        $tax = 0.0;

        foreach ($this->getOrderProductTaxes() as $orderProductTax) {
            $tax += (float) ($onPromo ? $orderProductTax->getPromoAmount() : $orderProductTax->getAmount());
        }

        return $tax;
    }

    /**
     * The taxed price of one unit, built the way the rounding mode the order was invoiced with
     * builds it, the one the order loop and the order total read.
     *
     * Under sum of roundings the price and its tax are each cut to the cent before they are
     * added. Under rounding of sums, and for the orders that predate the rounding modes, they
     * are added as they are stored.
     *
     * @param bool       $onPromo whether to read the promotional price and the promotional tax
     * @param float|null $unitTax the tax of one unit when the caller already holds it (the order
     *                            product loop sums it in SQL); read from the tax rows otherwise
     *
     * @throws PropelException
     */
    public function getTaxedUnitPrice(bool $onPromo, ?float $unitTax = null): float
    {
        $unitPrice = (float) ($onPromo ? $this->getPromoPrice() : $this->getPrice());
        $unitTax ??= $this->getUnitTax($onPromo);

        $orderId = (int) $this->getOrderId();

        if (ConfigQuery::isOrderWithLegacyRounding($orderId) || ConfigQuery::isRoundingModeRoundingOfSums($orderId)) {
            return $unitPrice + $unitTax;
        }

        return round($unitPrice, 2) + round($unitTax, 2);
    }

    /**
     * A unit amount with the precision the order total was built from.
     *
     * Under sum of roundings the total multiplies unit amounts already rounded to the cent,
     * so the cent is what the line was charged on. Under rounding of sums the total keeps the
     * stored precision, and a unit price cut to the cent would no longer multiply up to the
     * total the customer paid, which is the whole point of a price per gram or per millilitre.
     * What the order API publishes as a unit amount, and what a theme that holds the model
     * reads instead of rounding it again.
     */
    public function roundUnitAmount(float $amount): float
    {
        return ConfigQuery::isRoundingModeRoundingOfSums($this->getOrderId()) ? $amount : round($amount, 2);
    }

    /**
     * What the line is worth, for a quantity of it, built the way the rounding mode the order
     * was invoiced with builds the order total: the sum of these lines is what
     * {@see Order::getTotalAmount()} totals, and what the order product loop publishes as
     * TOTAL_PRICE, TOTAL_TAXED_PRICE and their promotional counterparts.
     *
     * The tax of a line is the gap between its taxed and its untaxed total under rounding of
     * sums, so that the three always add up; under sum of roundings and for the orders that
     * predate the rounding modes it is the unit tax times the quantity. `untaxed`, `taxed` and
     * `tax` are the totals of the line; `unitTax` and `taxedUnitPrice` the amounts of one unit,
     * as the loop publishes them.
     *
     * @param bool       $onPromo  whether to read the promotional price and the promotional tax
     * @param float|null $quantity the quantity to total, the whole line by default (a shipment
     *                             carries a part of it)
     * @param float|null $unitTax  the tax of one unit when the caller already holds it (the
     *                             order product loop sums it in SQL), read from the tax rows otherwise
     *
     * @return array{untaxed: float, taxed: float, tax: float, unitTax: float, taxedUnitPrice: float}
     *
     * @throws PropelException
     */
    public function getLineTotals(bool $onPromo, ?float $quantity = null, ?float $unitTax = null): array
    {
        $quantity ??= (float) $this->getQuantity();
        $unitPrice = (float) ($onPromo ? $this->getPromoPrice() : $this->getPrice());
        $unitTax = (float) ($unitTax ?? $this->getUnitTax($onPromo));
        $orderId = (int) $this->getOrderId();

        // To prevent price changes in pre-2.4 orders, the legacy calculation is kept.
        if (ConfigQuery::isOrderWithLegacyRounding($orderId)) {
            $taxedUnitPrice = $this->getTaxedUnitPrice($onPromo, $unitTax);

            return [
                'untaxed' => $unitPrice * $quantity,
                'taxed' => round($taxedUnitPrice, 2) * $quantity,
                'tax' => round($unitTax * $quantity, 2),
                'unitTax' => $unitTax,
                'taxedUnitPrice' => $taxedUnitPrice,
            ];
        }

        // The quantity multiplies the unit amount at full precision, and only the line total
        // is rounded, as CartItem::getTotalTaxedPrice() does under this mode. Unit amounts stay
        // unrounded: a price per gram or per millilitre is nothing once cut to the cent.
        if (ConfigQuery::isRoundingModeRoundingOfSums($orderId)) {
            $taxedUnitPrice = $this->getTaxedUnitPrice($onPromo, $unitTax);
            $untaxed = round($unitPrice * $quantity, 2);
            $taxed = round($taxedUnitPrice * $quantity, 2);

            return [
                'untaxed' => $untaxed,
                'taxed' => $taxed,
                // Rounding the tax of the line on its own would leave the three totals
                // disagreeing by a cent, and an invoice whose lines and whose footer state
                // different amounts. Order::getTotalAmount() reads the tax of an order as the
                // gap between its taxed and its untaxed total; a line reads it the same way.
                'tax' => round($taxed - $untaxed, 2),
                'unitTax' => $unitTax,
                'taxedUnitPrice' => $taxedUnitPrice,
            ];
        }

        // Sum of roundings: the unit price and its tax are cut to the cent, then multiplied.
        $unitTax = round($unitTax, 2);
        $taxedUnitPrice = $this->getTaxedUnitPrice($onPromo, $unitTax);

        return [
            'untaxed' => round($unitPrice, 2) * $quantity,
            'taxed' => $taxedUnitPrice * $quantity,
            'tax' => $unitTax * $quantity,
            'unitTax' => $unitTax,
            'taxedUnitPrice' => $taxedUnitPrice,
        ];
    }

    /**
     * What the line was charged, for its whole quantity: the promotional price when the line
     * was bought on promotion (the order product loop's REAL_TOTAL_PRICE and
     * REAL_TOTAL_TAXED_PRICE). The one reading of a service line (a gift wrapping) total.
     *
     * @return array{untaxed: float, taxed: float, tax: float, unitTax: float, taxedUnitPrice: float}
     *
     * @throws PropelException
     */
    public function getChargedLineTotals(): array
    {
        return $this->getLineTotals((bool) $this->getWasInPromo());
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        if (
            $con instanceof ConnectionInterface
            && method_exists($con, 'getEventDispatcher')
            && null !== $con->getEventDispatcher()
        ) {
            $con->getEventDispatcher()->dispatch(
                (new OrderProductEvent($this->getOrder(), null))
                    ->setCartItemId($this->cartItemId),
                TheliaEvents::ORDER_PRODUCT_BEFORE_CREATE,
            );
        }

        return true;
    }

    public function postInsert(?ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        if (
            $con instanceof ConnectionInterface
            && method_exists($con, 'getEventDispatcher')
            && null !== $con->getEventDispatcher()
        ) {
            $con->getEventDispatcher()->dispatch(
                (new OrderProductEvent($this->getOrder(), $this->getId()))
                    ->setCartItemId($this->cartItemId),
                TheliaEvents::ORDER_PRODUCT_AFTER_CREATE,
            );
        }
    }
}
