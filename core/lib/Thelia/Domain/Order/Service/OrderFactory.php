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
use Thelia\Domain\Cart\Exception\CartPricesChangedException;
use Thelia\Domain\Cart\Service\CartPriceGuard;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateCalendar;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\DeliverySlotQuery;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order as ModelOrder;

readonly class OrderFactory
{
    public function __construct(
        private DeliveryDateCalendar $deliveryDateCalendar,
        private SaleUnitContext $saleUnitContext,
        private CustomerGroupResolver $customerGroupResolver,
        private CartPriceGuard $cartPriceGuard,
        private CustomerDiscountRate $customerDiscountRate,
    ) {
    }

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
        $this->snapshotPricing($order, $cart, $currency, $customer);
        // Stamped by the server from the resolved context, never by the client: the
        // order carries the sale unit it was placed on for good, whatever the session
        // does afterwards. NULL is a national (single-shop) order.
        $order->setSaleUnitId($this->saleUnitContext->getSaleUnit()?->getId());

        // The note for whoever receives the parcel, copied off the cart the way every other
        // wording the order needs to keep is. The wrapping itself is not copied here: it
        // becomes a line of the order, built beside the product lines.
        $order->setGiftMessage($cart->getGiftMessage());

        $this->copyDeliveryDate($cart, $order);

        return $order;
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
     * Copies on the order the customer group, its discount rate, its way of showing
     * prices and the discount of the customer, the ones the lines are priced with: they
     * stay readable once the customer, or the group, changes.
     *
     * The buyer is the customer of the cart, read back from the database by
     * {@see CustomerGroupResolver}, never the session copy, which keeps the group the
     * customer had at sign-in. The lines are not priced here: the checkout settles them
     * before it computes the coupon, the postage and the fingerprint, and refuses to go on
     * when one moved. A line that still does not match the group the order is recorded with
     * means a placement that did not go through the checkout, and it is refused the same way,
     * so the lines and the snapshot never tell two stories.
     *
     * @throws CartPricesChangedException when a line is not at the price its buyer is owed
     */
    private function snapshotPricing(ModelOrder $order, CartModel $cart, CurrencyModel $currency, UserInterface $customer): void
    {
        $buyer = $this->cartPriceGuard->buyerOf($cart, $customer);

        $this->cartPriceGuard->refuseIfStale($cart, $currency, $buyer);

        $customerGroup = $this->customerGroupResolver->groupOf($buyer);

        $order->setCustomerDiscountRate(self::rate($this->customerDiscountRate->rateOf($buyer)));
        $order->setCustomerGroupId($customerGroup->getId());
        $order->setCustomerGroupDiscountRate(self::rate($customerGroup->effectiveDiscountRate()));
        $order->setCustomerGroupDisplayTaxedPrice(1 === (int) $customerGroup->getDisplayTaxedPrice() ? 1 : 0);
    }

    /**
     * A rate as the DECIMAL(16,6) columns of the order store it.
     */
    private static function rate(float $rate): string
    {
        return number_format($rate, 6, '.', '');
    }
}
