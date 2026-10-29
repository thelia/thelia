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

    /**
     * The line of a product bundle, as the buyer was charged for it: the price, the tax and
     * the quantity of the pack. It stands for the pack as a whole and holds no stock of its
     * own, nothing to pick and nothing to ship: its components, the lines that name it as
     * their `parent`, are what leaves the warehouse.
     */
    public const LINE_TYPE_BUNDLE = 'bundle';

    /**
     * A component of a bundle: what the pack is made of for this order. Priced at zero and
     * carrying no tax, since the bundle line holds the price; it carries the sale element,
     * the quantity (the quantity of the component times the quantity of the pack) and the
     * stock movements, and it is what is picked and shipped.
     */
    public const LINE_TYPE_BUNDLE_COMPONENT = 'bundle_component';

    protected ?int $cartItemId = null;

    /**
     * Whether this line is goods taken off the catalogue, as opposed to a service.
     *
     * The reading to branch on rather than a comparison against a string: a line type this
     * version does not know is something to handle, not something to skip, so anything
     * that is not explicitly a service reads as goods. That holds for the two lines of a
     * bundle as well: the pack is goods the shop sold, and its components are goods that
     * left the stock. What each of them does with it is told apart by the readings below.
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
     * The line of the pack itself. Its sale element is the one of the bundle product, which
     * keeps no stock: nothing may be taken from it or given back to it, and nothing ships.
     */
    public function isBundleLine(): bool
    {
        return self::LINE_TYPE_BUNDLE === $this->getLineType();
    }

    public function isBundleComponentLine(): bool
    {
        return self::LINE_TYPE_BUNDLE_COMPONENT === $this->getLineType();
    }

    /**
     * Whether this line is something to pick and put in a parcel: goods, and the components
     * of a bundle, but neither a service nor the line of the pack that groups them.
     */
    public function isShippableLine(): bool
    {
        return $this->isProductLine() && !$this->isBundleLine();
    }

    /**
     * Whether the sale element of this line is the one the stock moves on: false for the
     * line of a bundle, whose sale element holds no stock and whose components carry the
     * movements instead.
     */
    public function movesStockOfItsOwn(): bool
    {
        return !$this->isBundleLine();
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
