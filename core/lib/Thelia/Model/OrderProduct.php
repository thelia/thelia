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
    protected int $cartItemId;

    /**
     * @return $this
     */
    public function setCartItemId($cartItemId)
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    public function getCartItemId()
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
