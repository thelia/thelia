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
