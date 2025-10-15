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

namespace Thelia\Domain\Order\Service;

use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order as ModelOrder;

readonly class OrderFactory
{
    public function createFromSessionOrder(
        ModelOrder $sessionOrder,
        CurrencyModel $currency,
        LangModel $language,
        CartModel $cart,
        UserInterface $customer,
    ): ModelOrder {
        $order = $sessionOrder->copy();

        $order
            ->setId(null)
            ->setRef(null)
            ->setNew(true);

        $order->resetModified(OrderTableMap::COL_CREATED_AT);
        $order->resetModified(OrderTableMap::COL_UPDATED_AT);
        $order->resetModified(OrderTableMap::COL_VERSION_CREATED_AT);

        $order->setCustomerId($customer->getId());
        $order->setCurrencyId($currency->getId());
        $order->setCurrencyRate($currency->getRate());
        $order->setLangId($language->getId());
        $order->setCartId($cart->getId());
        $order->setDiscount($cart->getDiscount());

        return $order;
    }
}
