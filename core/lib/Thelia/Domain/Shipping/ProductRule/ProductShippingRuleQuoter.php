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

namespace Thelia\Domain\Shipping\ProductRule;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;

/**
 * Applies the shipping rules of the products to what a carrier quotes.
 *
 * The carrier prices the cart without the offered lines, then the surcharges are added
 * on top, taxed under the rule the carrier applies to its own postage. A cart whose
 * products carry no rule goes through untouched, so its postage does not move.
 */
final readonly class ProductShippingRuleQuoter
{
    public function __construct(
        private CartShippingRules $cartShippingRules,
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param callable(Cart): ?OrderPostage $askCarrier the carrier quote for the cart it is given
     */
    public function quote(Module $module, Cart $cart, Country $country, ?State $state, callable $askCarrier): ?OrderPostage
    {
        $rules = $this->cartShippingRules->for($cart);

        if (!$rules->changesPostage()) {
            return $askCarrier($cart);
        }

        $postage = $this->askOnTheShippedLines($rules, $cart, $askCarrier);

        if (!$postage instanceof OrderPostage) {
            return $postage;
        }

        if ($rules->isEntirelyFree()) {
            $postage->setAmount(0.0);
            $postage->setAmountTax(0.0);
            $postage->setUntaxedAmount(0.0);
        }

        $this->addSurcharge($postage, $this->surchargeFor($rules, $module, $cart, $country));

        return $postage;
    }

    /**
     * The same rules on the options a carrier lists, which it prices on its own.
     *
     * @param callable(Cart): list<DeliveryModuleOption> $askCarrier
     *
     * @return list<DeliveryModuleOption>
     */
    public function quoteOptions(Module $module, Cart $cart, Country $country, callable $askCarrier): array
    {
        $rules = $this->cartShippingRules->for($cart);

        if (!$rules->changesPostage()) {
            return $askCarrier($cart);
        }

        $options = $this->askOnTheShippedLines($rules, $cart, $askCarrier);
        $surcharge = $this->surchargeFor($rules, $module, $cart, $country);

        foreach ($options as $option) {
            if (!$option instanceof DeliveryModuleOption || null === $option->getPostage()) {
                continue;
            }

            $postage = $rules->isEntirelyFree()
                ? new OrderPostage(0.0, 0.0)
                : new OrderPostage($option->getPostage(), $option->getPostageTax() ?? 0.0);

            if (!$rules->isEntirelyFree() && null !== $option->getPostageUntaxed()) {
                $postage->setUntaxedAmount($option->getPostageUntaxed());
            }

            $this->addSurcharge($postage, $surcharge);

            $option
                ->setPostage($postage->getAmount())
                ->setPostageTax($postage->getAmountTax())
                ->setPostageUntaxed($postage->getUntaxedAmount());
        }

        return $options;
    }

    /**
     * What the buyer still pays for shipping when a coupon offers the postage: the
     * surcharges, which a free-shipping coupon does not waive. Null when there are none.
     */
    public function surchargeLeftByAFreeShippingCoupon(Module $module, Cart $cart, Country $country): ?OrderPostage
    {
        $surcharge = $this->surchargeFor($this->cartShippingRules->for($cart), $module, $cart, $country);

        if (!$surcharge instanceof OrderPostage) {
            return null;
        }

        $postage = new OrderPostage(0.0, 0.0, $surcharge->getTaxRuleTitle());
        $postage->setUntaxedAmount(0.0);
        $this->addSurcharge($postage, $surcharge);

        return $postage;
    }

    /**
     * @template T
     *
     * @param callable(Cart): T $askCarrier
     *
     * @return T
     */
    private function askOnTheShippedLines(CartShippingRuleSet $rules, Cart $cart, callable $askCarrier): mixed
    {
        // Every line offered: the carrier is still asked whether it takes the cart,
        // on the whole of it, and its price is then dropped.
        if (!$rules->hasFreeLines() || $rules->isEntirelyFree()) {
            return $askCarrier($cart);
        }

        $shippedCart = $this->withoutLines($cart, $rules->freeCartItemIds());

        return ShippingCartScope::within($shippedCart, static fn (): mixed => $askCarrier($shippedCart));
    }

    /**
     * An unsaved copy of the cart holding only the lines the carrier is paid for.
     *
     * @param list<int> $cartItemIds
     */
    private function withoutLines(Cart $cart, array $cartItemIds): Cart
    {
        $shippedCart = $cart->copy();

        foreach ($cart->getCartItems() as $cartItem) {
            if (\in_array((int) $cartItem->getId(), $cartItemIds, true)) {
                continue;
            }

            $shippedCart->addCartItem($cartItem->copy());
        }

        return $shippedCart;
    }

    private function surchargeFor(CartShippingRuleSet $rules, Module $module, Cart $cart, Country $country): ?OrderPostage
    {
        if ($rules->untaxedSurcharge() <= 0) {
            return null;
        }

        $untaxedSurcharge = round($this->toCartCurrency($rules->untaxedSurcharge(), $cart), 2);
        $carrier = $module->getDeliveryModuleInstance($this->container);

        // Taxed the way the carrier taxes its postage, when it says how.
        if (method_exists($carrier, 'buildOrderPostage')) {
            $surcharge = $carrier->buildOrderPostage($untaxedSurcharge, $country, Lang::getDefaultLanguage()->getLocale());

            if ($surcharge instanceof OrderPostage) {
                $surcharge->setUntaxedAmount($untaxedSurcharge);

                return $surcharge;
            }
        }

        $surcharge = new OrderPostage($untaxedSurcharge, 0.0);
        $surcharge->setUntaxedAmount($untaxedSurcharge);

        return $surcharge;
    }

    private function addSurcharge(OrderPostage $postage, ?OrderPostage $surcharge): void
    {
        if (!$surcharge instanceof OrderPostage) {
            return;
        }

        $untaxedPostage = round((float) $postage->getUntaxedAmount(), 2);

        $postage->setAmount(round((float) $postage->getAmount() + (float) $surcharge->getAmount(), 2));
        $postage->setAmountTax((float) $postage->getAmountTax() + (float) $surcharge->getAmountTax());
        $postage->setUntaxedAmount(round($untaxedPostage + (float) $surcharge->getUntaxedAmount(), 2));

        if (null === $postage->getTaxRuleTitle()) {
            $postage->setTaxRuleTitle($surcharge->getTaxRuleTitle());
        }
    }

    private function toCartCurrency(float $amount, Cart $cart): float
    {
        $currency = $cart->getCurrency();
        $defaultCurrency = Currency::getDefaultCurrency();

        if (null === $currency || $currency->getId() === $defaultCurrency->getId()) {
            return $amount;
        }

        return $amount * $currency->getRate() / $defaultCurrency->getRate();
    }
}
