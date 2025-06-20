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

use Exception;
use LogicException;
use Thelia\Model\Cart;
use Thelia\Model\Address;
use Thelia\Model\Customer;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionEvaluator;
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
 * Allow to assist in getting relevant data on the current application state.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class BaseFacade implements FacadeInterface
{


    /**
     * @var CouponFactory
     */
    protected $couponFactory;



    protected ?\Symfony\Component\HttpFoundation\Request $request;



    /**
     * Constructor.
     */
    public function __construct(
        protected SecurityContext $securityContext,
        protected TaxEngine $taxEngine,
        protected TranslatorInterface $translator,
        protected ParserInterface $parser,
        RequestStack $requestStack,
        protected ConditionEvaluator $conditionEvaluator,
        protected EventDispatcherInterface $eventDispatcher
    ) {
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * Return a Cart a CouponManager can process.
     *
     * @return Cart
     */
    public function getCart()
    {
        return $this->getRequest()->getSession()->getSessionCart($this->getDispatcher());
    }

    /**
     * Return an Address a CouponManager can process.
     *
     * @return Address
     */
    public function getDeliveryAddress()
    {
        try {
            return AddressQuery::create()->findPk(
                $this->getRequest()->getSession()->getOrder()->getChoosenDeliveryAddress()
            );
        } catch (Exception $exception) {
            throw new LogicException('Failed to get delivery address ('.$exception->getMessage().')', $exception->getCode(), $exception);
        }
    }

    /**
     * Return an Customer a CouponManager can process.
     *
     * @return Customer
     */
    public function getCustomer(): mixed
    {
        return $this->securityContext->getCustomerUser();
    }

    /**
     * Return Checkout total price.
     *
     * @return float
     */
    public function getCheckoutTotalPrice()
    {
        return $this->getRequest()->getSession()->getOrder()->getTotalAmount();
    }

    /**
     * Return Checkout total postage (only) price.
     *
     * @return float
     */
    public function getCheckoutPostagePrice()
    {
        return $this->getRequest()->getSession()->getOrder()->getPostage();
    }

    /**
     * Return Products total price.
     *
     * @param bool $withItemsInPromo if true, the discounted items are included in the total
     *
     * @return float
     */
    public function getCartTotalPrice($withItemsInPromo = true): int|float
    {
        $total = 0;

        $cartItems = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getCartItems();

        foreach ($cartItems as $cartItem) {
            if ($withItemsInPromo || !$cartItem->getPromo()) {
                $total += $cartItem->getTotalRealPrice();
            }
        }

        return $total;
    }

    /**
     * @param bool $withItemsInPromo
     *
     * @throws PropelException
     */
    public function getCartTotalTaxPrice($withItemsInPromo = true): int|float
    {
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $cartItems = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getCartItems();

        $total = 0;

        foreach ($cartItems as $cartItem) {
            if ($withItemsInPromo || !$cartItem->getPromo()) {
                $total += $cartItem->getTotalRealTaxedPrice($taxCountry);
            }
        }

        return $total;
    }

    /**
     * @return Country the delivery country
     */
    public function getDeliveryCountry(): Country
    {
        return $this->taxEngine->getDeliveryCountry();
    }

    /**
     * Return the Checkout currency EUR|USD.
     *
     * @return string
     */
    public function getCheckoutCurrency()
    {
        return $this->getRequest()->getSession()->getCurrency()->getCode();
    }

    /**
     * Return the number of Products in the Cart.
     */
    public function getNbArticlesInCart(): int
    {
        return \count($this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getCartItems());
    }

    public function getNbArticlesInCartIncludeQuantity(): int|float
    {
        $cartItems = $this->getCart()->getCartItems();
        $quantity = 0;

        foreach ($cartItems as $cartItem) {
            $quantity += $cartItem->getQuantity();
        }

        return $quantity;
    }

    /**
     * Find one Coupon in the database from its code.
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
     * Return platform TranslatorInterface.
     */
    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    /**
     * Return platform Parser.
     */
    public function getParser(): ParserInterface
    {
        if ($this->parser instanceof ParserInterface) {
            // Define the current back-office template that should be used
            $this->parser->setTemplateDefinition(
                $this->parser->getTemplateHelper()->getActiveAdminTemplate()
            );
        }

        return $this->parser;
    }

    /**
     * Return the main currency
     * THe one used to set prices in BackOffice.
     *
     * @return string
     */
    public function getMainCurrency()
    {
        return $this->getRequest()->getSession()->getCurrency();
    }

    /**
     * Return request.
     *
     * @return Request
     */
    public function getRequest(): ?\Symfony\Component\HttpFoundation\Request
    {
        return $this->request;
    }

    /**
     * Return Constraint Validator.
     */
    public function getConditionEvaluator(): ConditionEvaluator
    {
        return $this->conditionEvaluator;
    }

    /**
     * Return all available currencies.
     *
     * @return array of Currency
     */
    public function getAvailableCurrencies()
    {
        $currencies = CurrencyQuery::create();

        return $currencies->find();
    }

    /**
     * Return the event dispatcher,.
     *
     * @return EventDispatcher
     */
    public function getDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * Add a coupon in session.
     */
    public function pushCouponInSession($couponCode): void
    {
        $consumedCoupons = $this->getRequest()->getSession()->getConsumedCoupons();

        if (!isset($consumedCoupons[$couponCode])) {
            // Prevent accumulation of the same Coupon on a Checkout
            $consumedCoupons[$couponCode] = $couponCode;

            $this->getRequest()->getSession()->setConsumedCoupons($consumedCoupons);
        }
    }
}
