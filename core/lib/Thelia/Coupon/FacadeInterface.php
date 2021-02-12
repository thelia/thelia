<?php

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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\EventDispatcher\EventDispatcher;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Model\Country;
use Thelia\Model\Coupon;
use Thelia\TaxEngine\TaxEngine;

/**
 * Allow to assist in getting relevant data on the current application state.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
interface FacadeInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        SecurityContext $securityContext,
        TaxEngine $taxEngine,
        TranslatorInterface $translator,
        ParserInterface $parser,
        RequestStack $requestStack,
        ConditionEvaluator $conditionEvaluator,
        EventDispatcher $eventDispatcher
    );

    /**
     * Return a Cart a CouponManager can process.
     *
     * @return \Thelia\Model\Cart
     */
    public function getCart();

    /**
     * Return an Address a CouponManager can process.
     *
     * @return \Thelia\Model\Address
     */
    public function getDeliveryAddress();

    /**
     * @return Country the delivery country
     */
    public function getDeliveryCountry();

    /**
     * Return an Customer a CouponManager can process.
     *
     * @return \Thelia\Model\Customer
     */
    public function getCustomer();

    /**
     * Return Checkout total price.
     *
     * @return float
     */
    public function getCheckoutTotalPrice();

    /**
     * Return Products total price
     * CartTotalPrice = Checkout total - discount - postage.
     *
     * @param bool $withItemsInPromo true (default) if item in promotion should be included in the total, false otherwise
     *
     * @return float
     */
    public function getCartTotalPrice($withItemsInPromo = true);

    /**
     * Return Product total tax price.
     *
     * @param bool $withItemsInPromo true (default) if item in promotion should be included in the total, false otherwise
     *
     * @return float
     */
    public function getCartTotalTaxPrice($withItemsInPromo = true);

    /**
     * Return the Checkout currency EUR|USD.
     *
     * @return string
     */
    public function getCheckoutCurrency();

    /**
     * Return Checkout total postage (only) price.
     *
     * @return float
     */
    public function getCheckoutPostagePrice();

    /**
     * Return the number of Products in the Cart.
     *
     * @return int
     */
    public function getNbArticlesInCart();

    /**
     * Return the number of Products include quantity in the Cart.
     *
     * @return int
     */
    public function getNbArticlesInCartIncludeQuantity();

    /**
     * Find one Coupon in the database from its code.
     *
     * @param string $code Coupon code
     *
     * @return Coupon
     */
    public function findOneCouponByCode($code);

    /**
     * Return platform TranslatorInterface.
     *
     * @return TranslatorInterface
     */
    public function getTranslator();

    /**
     * Return platform ParserInterface.
     *
     * @return ParserInterface
     */
    public function getParser();

    /**
     * Return the main currency
     * THe one used to set prices in BackOffice.
     *
     * @return string
     */
    public function getMainCurrency();

    /**
     * Return request.
     *
     * @return Request
     */
    public function getRequest();

    /**
     * Return Condition Evaluator.
     *
     * @return ConditionEvaluator
     */
    public function getConditionEvaluator();

    /**
     * Return all available currencies.
     *
     * @return array of Currency
     */
    public function getAvailableCurrencies();

    /**
     * Return the event dispatcher,.
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher();

    /**
     * Add a coupon in session.
     *
     * @param $couponCode
     *
     * @return mixed|void
     */
    public function pushCouponInSession($couponCode);
}
