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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\TaxEngine\TaxEngine;

/**
 * Allow to assist in getting relevant data on the current application state.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class BaseFacade implements FacadeInterface
{
    protected CouponFactory $couponFactory;
    protected ?Request $request;

    /**
     * Constructor.
     */
    public function __construct(
        protected SecurityContext $securityContext,
        protected TaxEngine $taxEngine,
        protected TranslatorInterface $translator,
        protected ParserResolver $parserResolver,
        protected RequestStack $requestStack,
        protected ConditionEvaluator $conditionEvaluator,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * Return a Cart a CouponManager can process.
     */
    public function getCart(): ?Cart
    {
        return $this->getRequest()->getSession()->getSessionCart($this->getDispatcher());
    }

    /**
     * Return an Address a CouponManager can process.
     */
    public function getDeliveryAddress(): Address
    {
        try {
            return AddressQuery::create()->findPk(
                $this->getRequest()->getSession()->getSessionCart($this->eventDispatcher)->getAddressDeliveryId()
            );
        } catch (\Exception $exception) {
            throw new \LogicException('Failed to get delivery address ('.$exception->getMessage().')', $exception->getCode(), $exception);
        }
    }

    /**
     * Return a Customer a CouponManager can process.
     */
    public function getCustomer(): ?Customer
    {
        return $this->securityContext->getCustomerUser();
    }

    /**
     * Return Checkout total price.
     */
    public function getCheckoutTotalPrice(): float
    {
        return $this->getRequest()->getSession()->getOrder()->getTotalAmount();
    }

    /**
     * Return Checkout total postage (only) price.
     */
    public function getCheckoutPostagePrice(): float
    {
        return (float) $this->getRequest()->getSession()->getOrder()->getPostage();
    }

    /**
     * Return Products total price.
     *
     * @param bool $withItemsInPromo if true, the discounted items are included in the total
     */
    public function getCartTotalPrice(bool $withItemsInPromo = true): float
    {
        $total = 0;

        $cartItems = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())?->getCartItems() ?? [];

        foreach ($cartItems as $cartItem) {
            if ($withItemsInPromo || !$cartItem->getPromo()) {
                $total += $cartItem->getTotalRealPrice();
            }
        }

        return (float) $total;
    }

    /**
     * @throws PropelException
     */
    public function getCartTotalTaxPrice(bool $withItemsInPromo = true): float
    {
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $cartItems = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())?->getCartItems() ?? [];

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
     */
    public function getCheckoutCurrency(): string
    {
        return $this->getRequest()->getSession()->getCurrency()->getCode();
    }

    /**
     * Return the number of Products in the Cart.
     */
    public function getNbArticlesInCart(): int
    {
        return \count($this->getRequest()->getSession()->getSessionCart($this->getDispatcher())?->getCartItems() ?? []);
    }

    public function getNbArticlesInCartIncludeQuantity(): int
    {
        $cartItems = $this->getCart()?->getCartItems() ?? [];
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
     */
    public function findOneCouponByCode(string $code): Coupon
    {
        return CouponQuery::create()->findOneByCode($code);
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
        return $this->parserResolver->getParserByCurrentRequest();
    }

    /**
     * Return the main currency
     * THe one used to set prices in BackOffice.
     */
    public function getMainCurrency(): Currency
    {
        return $this->getRequest()->getSession()->getCurrency();
    }

    /**
     * Return request.
     */
    public function getRequest(): Request
    {
        if (!$this->request instanceof \Symfony\Component\HttpFoundation\Request) {
            // If the request is not set, we try to get it from the RequestStack again.
            $this->request = $this->requestStack->getCurrentRequest();
            if (!$this->request instanceof \Symfony\Component\HttpFoundation\Request) {
                throw new \LogicException('Request is not set. Please ensure that the RequestStack is properly configured.');
            }
        }

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
    public function getAvailableCurrencies(): array
    {
        $currencies = CurrencyQuery::create();

        return $currencies->find();
    }

    /**
     * Return the event dispatcher,.
     */
    public function getDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * Add a coupon in session.
     */
    public function pushCouponInSession($couponCode): mixed
    {
        $consumedCoupons = $this->getRequest()->getSession()->getConsumedCoupons();

        if (!isset($consumedCoupons[$couponCode])) {
            // Prevent accumulation of the same Coupon on a Checkout
            $consumedCoupons[$couponCode] = $couponCode;

            return $this->getRequest()->getSession()->setConsumedCoupons($consumedCoupons);
        }

        return null;
    }
}
