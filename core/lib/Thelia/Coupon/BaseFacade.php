<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Coupon;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\EventDispatcher\EventDispatcher;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Model\AddressQuery;
use Thelia\Model\Country;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\TaxEngine\TaxEngine;

/**
 * Allow to assist in getting relevant data on the current application state
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class BaseFacade implements FacadeInterface
{
    /**
     * @var SecurityContext
     */
    protected $securityContext;
    /**
     * @var TaxEngine
     */
    protected $taxEngine;
    /**
     * @var CouponFactory
     */
    protected $couponFactory;
    /**
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * @var ParserInterface
     */
    protected $parser;
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var ConditionEvaluator
     */
    protected $conditionEvaluator;
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * Constructor
     *
     * @param SecurityContext $securityContext
     * @param TaxEngine $taxEngine
     * @param TranslatorInterface $translator
     * @param ParserInterface $parser
     * @param RequestStack $requestStack
     * @param ConditionEvaluator $conditionEvaluator
     * @param EventDispatcher $eventDispatcher
     */
    public function __construct(
        SecurityContext $securityContext,
        TaxEngine $taxEngine,
        TranslatorInterface $translator,
        ParserInterface $parser,
        RequestStack $requestStack,
        ConditionEvaluator $conditionEvaluator,
        EventDispatcher $eventDispatcher
    )
    {
        $this->securityContext = $securityContext;
        $this->taxEngine = $taxEngine;
        $this->translator = $translator;
        $this->parser = $parser;
        $this->request = $requestStack->getCurrentRequest();
        $this->conditionEvaluator = $conditionEvaluator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Return a Cart a CouponManager can process
     *
     * @return \Thelia\Model\Cart
     */
    public function getCart()
    {
        return $this->getRequest()->getSession()->getSessionCart($this->getDispatcher());
    }

    /**
     * Return an Address a CouponManager can process
     *
     * @return \Thelia\Model\Address
     */
    public function getDeliveryAddress()
    {
        try {
            return AddressQuery::create()->findPk(
                $this->getRequest()->getSession()->getOrder()->getChoosenDeliveryAddress()
            );
        } catch (\Exception $ex) {
            throw new \LogicException("Failed to get delivery address (" . $ex->getMessage() . ")");
        }
    }

    /**
     * Return an Customer a CouponManager can process
     *
     * @return \Thelia\Model\Customer
     */
    public function getCustomer()
    {
        return $this->securityContext->getCustomerUser();
    }

    /**
     * Return Checkout total price
     *
     * @return float
     */
    public function getCheckoutTotalPrice()
    {
        return $this->getRequest()->getSession()->getOrder()->getTotalAmount();
    }

    /**
     * Return Checkout total postage (only) price
     *
     * @return float
     */
    public function getCheckoutPostagePrice()
    {
        return $this->getRequest()->getSession()->getOrder()->getPostage();
    }

    /**
     * Return Products total price
     *
     * @param bool $withItemsInPromo if true, the discounted items are included in the total
     *
     * @return float
     */
    public function getCartTotalPrice($withItemsInPromo = true)
    {
        $total = 0;

        $cartItems = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getCartItems();

        foreach ($cartItems as $cartItem) {
            if ($withItemsInPromo || ! $cartItem->getPromo()) {
                $total += $cartItem->getTotalRealPrice();
            }
        }

        return $total;
    }

    /**
     * @param bool $withItemsInPromo
     * @return float|int
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getCartTotalTaxPrice($withItemsInPromo = true)
    {
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $cartItems = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getCartItems();

        $total = 0;

        foreach ($cartItems as $cartItem) {
            if ($withItemsInPromo || ! $cartItem->getPromo()) {
                $total += $cartItem->getTotalRealTaxedPrice($taxCountry);
            }
        }

        return $total;
    }

    /**
     * @return Country the delivery country
     */
    public function getDeliveryCountry()
    {
        return $this->taxEngine->getDeliveryCountry();
    }

    /**
     * Return the Checkout currency EUR|USD
     *
     * @return string
     */
    public function getCheckoutCurrency()
    {
        return $this->getRequest()->getSession()->getCurrency()->getCode();
    }

    /**
     * Return the number of Products in the Cart
     *
     * @return int
     */
    public function getNbArticlesInCart()
    {
        return \count($this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getCartItems());
    }

    public function getNbArticlesInCartIncludeQuantity()
    {
        $cartItems = $this->getCart()->getCartItems();
        $quantity = 0;

        foreach ($cartItems as $cartItem) {
            $quantity += $cartItem->getQuantity();
        }

        return $quantity;
    }

    /**
     * Find one Coupon in the database from its code
     *
     * @param string $code Coupon code
     *
     * @return Coupon
     */
    public function findOneCouponByCode($code)
    {
        $couponQuery = CouponQuery::create();

        return $couponQuery->findOneByCode($code);
    }

    /**
     * Return platform TranslatorInterface
     *
     * @return TranslatorInterface
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Return platform Parser
     *
     * @return ParserInterface
     */
    public function getParser()
    {
        if ($this->parser == null) {
            // Define the current back-office template that should be used
            $this->parser->setTemplateDefinition(
                $this->parser->getTemplateHelper()->getActiveAdminTemplate()
            );
        }

        return $this->parser;
    }

    /**
     * Return the main currency
     * THe one used to set prices in BackOffice
     *
     * @return string
     */
    public function getMainCurrency()
    {
        return $this->getRequest()->getSession()->getCurrency();
    }

    /**
     * Return request
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Return Constraint Validator
     *
     * @return ConditionEvaluator
     */
    public function getConditionEvaluator()
    {
        return $this->conditionEvaluator;
    }

    /**
     * Return all available currencies
     *
     * @return array of Currency
     */
    public function getAvailableCurrencies()
    {
        $currencies = CurrencyQuery::create();

        return $currencies->find();
    }

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Add a coupon in session
     * @param $couponCode
     * @return mixed|void
     */
    public function pushCouponInSession($couponCode)
    {
        $consumedCoupons = $this->getRequest()->getSession()->getConsumedCoupons();

        if (!isset($consumedCoupons) || !$consumedCoupons) {
            $consumedCoupons = array();
        }

        if (!isset($consumedCoupons[$couponCode])) {
            // Prevent accumulation of the same Coupon on a Checkout
            $consumedCoupons[$couponCode] = $couponCode;

            $this->getRequest()->getSession()->setConsumedCoupons($consumedCoupons);
        }
    }
}
