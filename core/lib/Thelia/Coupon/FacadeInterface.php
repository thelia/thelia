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

namespace Thelia\Coupon;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\ParserInterface;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Coupon;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * Allow to assist in getting relevant data on the current application state.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
interface FacadeInterface
{
    /**
     * Return a Cart a CouponManager can process.
     */
    public function getCart(): ?Cart;

    /**
     * Return an Address a CouponManager can process.
     */
    public function getDeliveryAddress(): Address;

    /**
     * @return Country the delivery country
     */
    public function getDeliveryCountry(): Country;

    /**
     * Return an Customer a CouponManager can process.
     */
    public function getCustomer(): ?Customer;

    /**
     * Return Checkout total price.
     */
    public function getCheckoutTotalPrice(): float;

    /**
     * Return Products total price
     * CartTotalPrice = Checkout total - discount - postage.
     *
     * @param bool $withItemsInPromo true (default) if item in promotion should be included in the total, false otherwise
     */
    public function getCartTotalPrice(bool $withItemsInPromo = true): float;

    /**
     * Return Product total tax price.
     *
     * @param bool $withItemsInPromo true (default) if item in promotion should be included in the total, false otherwise
     */
    public function getCartTotalTaxPrice(bool $withItemsInPromo = true): float;

    /**
     * Return the Checkout currency EUR|USD.
     */
    public function getCheckoutCurrency(): string;

    /**
     * Return Checkout total postage (only) price.
     */
    public function getCheckoutPostagePrice(): float;

    /**
     * Return the number of Products in the Cart.
     */
    public function getNbArticlesInCart(): int;

    /**
     * Return the number of Products include quantity in the Cart.
     */
    public function getNbArticlesInCartIncludeQuantity(): int;

    /**
     * Find one Coupon in the database from its code.
     *
     * @param string $code Coupon code
     */
    public function findOneCouponByCode(string $code): Coupon;

    /**
     * Return platform TranslatorInterface.
     */
    public function getTranslator(): TranslatorInterface;

    /**
     * Return platform ParserInterface.
     */
    public function getParser(): ParserInterface;

    /**
     * Return the main currency
     * THe one used to set prices in BackOffice.
     */
    public function getMainCurrency(): Currency;

    /**
     * Return request.
     */
    public function getRequest(): Request;

    /**
     * Return Condition Evaluator.
     */
    public function getConditionEvaluator(): ConditionEvaluator;

    /**
     * Return all available currencies.
     *
     * @return array of Currency
     */
    public function getAvailableCurrencies(): array;

    /**
     * Return the event dispatcher,.
     */
    public function getDispatcher(): EventDispatcherInterface;

    /**
     * Add a coupon in session.
     */
    public function pushCouponInSession($couponCode): mixed;
}
