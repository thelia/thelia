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
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryEstimate;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateCalendar;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryTimeEstimator;
use Thelia\Log\ThrowableTrace;
use Thelia\Log\Tlog;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\DeliverySlotQuery;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order as ModelOrder;

readonly class OrderFactory
{
    public function __construct(
        private DeliveryDateCalendar $deliveryDateCalendar,
        private DeliveryTimeEstimator $deliveryTimeEstimator,
    ) {
    }

    public function createFromSessionOrder(
        ModelOrder $sessionOrder,
        CurrencyModel $currency,
        LangModel $language,
        CartModel $cart,
        UserInterface $customer,
        ?DeliveryEstimate $deliveryEstimate = null,
    ): ModelOrder {
        $order = $sessionOrder->copy();

        $order
            ->setId(null)
            ->setRef(null)
            ->deferRefGeneration()
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
        $order->setCustomerDiscountRate($this->resolveCustomerDiscountRate($customer));

        // The note for whoever receives the parcel, copied off the cart the way every other
        // wording the order needs to keep is. The wrapping itself is not copied here: it
        // becomes a line of the order, built beside the product lines.
        $order->setGiftMessage($cart->getGiftMessage());

        $this->copyDeliveryDate($cart, $order);
        $this->keepDeliveryEstimate($order, $deliveryEstimate);

        return $order;
    }

    /**
     * The interval the carrier delays give for this cart now, to be kept on the order. It is
     * counted before the order transaction opens: it only reads settings, and an estimate
     * that fails must not run inside the transaction that writes the order. An order paid
     * after the cutoff hour gets the next day, which is the day it will really be prepared.
     */
    public function estimateDeliveryOf(ModelOrder $sessionOrder, CartModel $cart): ?DeliveryEstimate
    {
        $moduleId = $sessionOrder->getDeliveryModuleId();

        if (null === $moduleId) {
            return null;
        }

        // Read here, outside the guard of the estimator: a failure still leaves the order
        // placed without an estimate.
        try {
            $module = ModuleQuery::create()->findPk($moduleId);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->addError(\sprintf('The carrier #%d of the order could not be read for a delivery estimate', $moduleId)."\n".ThrowableTrace::describe($throwable));

            return null;
        }

        return null === $module ? null : $this->deliveryTimeEstimator->estimateForCart($module, $cart);
    }

    /**
     * Kept as it was counted, whatever the delays become; none for an order with a picked day.
     */
    private function keepDeliveryEstimate(ModelOrder $order, ?DeliveryEstimate $deliveryEstimate): void
    {
        $estimate = null === $order->getDeliveryDate() ? $deliveryEstimate : null;

        $order
            ->setEstimatedDeliveryFrom($estimate?->earliestDay())
            ->setEstimatedDeliveryTo($estimate?->latestDay());
    }

    /**
     * The day and the slot the buyer picked, with the hours of the slot: the order keeps
     * saying 9:00 to 11:00 once the slot is edited or deleted from the carrier settings.
     *
     * Only for a carrier that still offers dates. A day left on the cart from a carrier the
     * buyer then swapped is dropped by the cart itself; this covers a merchant turning the
     * dates off while the cart was sitting there.
     */
    private function copyDeliveryDate(CartModel $cart, ModelOrder $order): void
    {
        $date = $cart->getDeliveryDate('Y-m-d');
        $module = null === $date || null === $cart->getDeliveryModuleId() ? null : ModuleQuery::create()->findPk($cart->getDeliveryModuleId());

        if (null === $module || DeliveryDateChoiceMode::None === $this->deliveryDateCalendar->choiceModeOf($module)) {
            return;
        }

        $order->setDeliveryDate($date);

        $slot = null === $cart->getDeliverySlotId() ? null : DeliverySlotQuery::create()->findPk($cart->getDeliverySlotId());

        if (null !== $slot) {
            $order
                ->setDeliverySlotId($slot->getId())
                ->setDeliverySlotStart($slot->getStartTime('H:i:s'))
                ->setDeliverySlotEnd($slot->getEndTime('H:i:s'));
        }
    }

    /**
     * The customer discount is baked into the cart item prices, so the rate itself
     * has to be copied on the order to stay readable once the customer changes it.
     */
    private function resolveCustomerDiscountRate(UserInterface $customer): string
    {
        if (!$customer instanceof CustomerModel) {
            return '0.000000';
        }

        return $customer->getDiscount() ?? '0.000000';
    }
}
