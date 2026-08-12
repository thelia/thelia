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

namespace Thelia\Domain\Taxation\TaxEngine;

use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Taxation\TaxEngine\Exception\TaxEngineException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;
use Thelia\Model\State;
use Thelia\Model\Tax;
use Thelia\Model\TaxI18n;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;
use Thelia\Tools\I18n;

/**
 * Class Calculator.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Franck Allimant <fallimant@openstudio.fr>
 * @author Vincent Lopes <vlopes@openstudio.fr>
 */
class Calculator implements TaxCalculatorInterface
{
    protected TaxRuleQuery $taxRuleQuery;
    protected ?ObjectCollection $taxRulesCollection = null;
    protected $product;
    protected $country;
    protected $state;

    /**
     * Tax factors already computed in this process, indexed on the order or the
     * cart they were computed for. A single request handles several of them —
     * the back-office order list, for one — and each has its own tax rates.
     *
     * @var \WeakMap<Order, float>|null
     */
    private static ?\WeakMap $orderTaxFactors = null;

    /**
     * @var \WeakMap<Cart, array<string, float>>|null
     */
    private static ?\WeakMap $cartTaxFactors = null;

    public function __construct()
    {
        $this->taxRuleQuery = new TaxRuleQuery();
    }

    /**
     * @deprecated since 3.0, use {@see computeUntaxedCartDiscount()} on the calculator
     *             handed out by TaxCalculatorFactoryInterface. A static cannot be replaced,
     *             so a custom tax engine never gets a say in this result.
     *
     * @throws PropelException
     */
    public static function getUntaxedCartDiscount(Cart $cart, Country $country, ?State $state = null): int|float
    {
        trigger_deprecation('thelia/core', '3.0', 'Calculator::getUntaxedCartDiscount() is deprecated, ask a TaxCalculatorFactoryInterface for a calculator and call computeUntaxedCartDiscount() on it.');

        return (new self())->computeUntaxedCartDiscount($cart, $country, $state);
    }

    /**
     * @deprecated since 3.0, use {@see computeUntaxedOrderDiscount()} on the calculator
     *             handed out by TaxCalculatorFactoryInterface
     *
     * @throws PropelException
     */
    public static function getUntaxedOrderDiscount(Order $order): int|float
    {
        trigger_deprecation('thelia/core', '3.0', 'Calculator::getUntaxedOrderDiscount() is deprecated, ask a TaxCalculatorFactoryInterface for a calculator and call computeUntaxedOrderDiscount() on it.');

        return (new self())->computeUntaxedOrderDiscount($order);
    }

    /**
     * @deprecated since 3.0, use {@see computeOrderTaxFactor()} on the calculator
     *             handed out by TaxCalculatorFactoryInterface
     *
     * @throws PropelException
     */
    public static function getOrderTaxFactor(Order $order): float
    {
        trigger_deprecation('thelia/core', '3.0', 'Calculator::getOrderTaxFactor() is deprecated, ask a TaxCalculatorFactoryInterface for a calculator and call computeOrderTaxFactor() on it.');

        return (new self())->computeOrderTaxFactor($order);
    }

    /**
     * @deprecated since 3.0, use {@see computeCartTaxFactor()} on the calculator
     *             handed out by TaxCalculatorFactoryInterface
     *
     * @throws PropelException
     */
    public static function getCartTaxFactor(Cart $cart, Country $country, ?State $state = null): float
    {
        trigger_deprecation('thelia/core', '3.0', 'Calculator::getCartTaxFactor() is deprecated, ask a TaxCalculatorFactoryInterface for a calculator and call computeCartTaxFactor() on it.');

        return (new self())->computeCartTaxFactor($cart, $country, $state);
    }

    /**
     * @throws PropelException
     */
    public function computeUntaxedCartDiscount(Cart $cart, Country $country, ?State $state = null): int|float
    {
        return $cart->getDiscount() / $this->computeCartTaxFactor($cart, $country, $state);
    }

    /**
     * @throws PropelException
     */
    public function computeUntaxedOrderDiscount(Order $order): int|float
    {
        return $order->getDiscount() / $this->computeOrderTaxFactor($order);
    }

    /**
     * @throws PropelException
     */
    public function computeOrderTaxFactor(Order $order): float
    {
        if (0.0 === (float) $order->getDiscount()) {
            return 1;
        }

        self::$orderTaxFactors ??= new \WeakMap();

        if (isset(self::$orderTaxFactors[$order])) {
            return self::$orderTaxFactors[$order];
        }

        // Find the average Tax rate (see computeCartTaxFactor())
        $orderTaxFactors = [];

        /** @var OrderProduct $orderProduct */
        foreach ($order->getOrderProducts() as $orderProduct) {
            /** @var \Thelia\Core\Template\Loop\OrderProductTax $orderProductTax */
            foreach ($orderProduct->getOrderProductTaxes() as $orderProductTax) {
                $orderTaxFactors[] = 1 + $orderProductTax->getAmount() / $orderProduct->getPrice();
            }
        }

        if ([] === $orderTaxFactors) {
            return 1;
        }

        return self::$orderTaxFactors[$order] = array_sum($orderTaxFactors) / \count($orderTaxFactors);
    }

    /**
     * @throws PropelException
     */
    public function computeCartTaxFactor(Cart $cart, Country $country, ?State $state = null): float
    {
        if (0.0 === (float) $cart->getDiscount()) {
            return 1;
        }

        $cartItems = $cart->getCartItems();

        // The factor depends on the destination and on the products in the cart,
        // both of which change while a request is being served.
        $key = implode(':', [$country->getId(), $state?->getId()]);

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            $key .= ':'.$cartItem->getProductId();
        }

        self::$cartTaxFactors ??= new \WeakMap();

        $cartFactors = self::$cartTaxFactors[$cart] ?? [];

        if (isset($cartFactors[$key])) {
            return $cartFactors[$key];
        }

        // Get the average of tax factor to apply it to the discount
        $cartTaxFactors = [];

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            $taxRulesCollection = TaxRuleQuery::create()->getTaxCalculatorCollection($cartItem->getProduct()->getTaxRule(), $country, $state);

            /** @var TaxRule $taxRule */
            foreach ($taxRulesCollection as $taxRule) {
                $cartTaxFactors[] = 1 + $taxRule->getTypeInstance()->pricePercentRetriever();
            }
        }

        // Nothing taxable to spread the discount over: a cart still carrying the
        // discount of a coupon after its last item was removed, or a destination
        // no tax rule applies to. Leave the discount as it is, like an order does.
        if ([] === $cartTaxFactors) {
            return 1;
        }

        $cartFactor = array_sum($cartTaxFactors) / \count($cartTaxFactors);

        $cartFactors[$key] = $cartFactor;
        self::$cartTaxFactors[$cart] = $cartFactors;

        return $cartFactor;
    }

    /**
     * @return $this
     *
     * @throws PropelException
     */
    public function load(Product $product, Country $country, ?State $state = null): static
    {
        if (null === $product->getId()) {
            throw new TaxEngineException('Product id is empty in Calculator::load', TaxEngineException::UNDEFINED_PRODUCT);
        }

        if (null === $country->getId()) {
            throw new TaxEngineException('Country id is empty in Calculator::load', TaxEngineException::UNDEFINED_COUNTRY);
        }

        $this->product = $product;
        $this->country = $country;
        $this->state = $state;

        $this->taxRulesCollection = $this->taxRuleQuery->getTaxCalculatorCollection($product->getTaxRule(), $country, $state);

        return $this;
    }

    /**
     * @return $this
     *
     * @throws PropelException
     */
    public function loadTaxRule(TaxRule $taxRule, Country $country, Product $product, ?State $state = null): static
    {
        if (null === $taxRule->getId()) {
            throw new TaxEngineException('TaxRule id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_TAX_RULE);
        }

        if (null === $country->getId()) {
            throw new TaxEngineException('Country id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_COUNTRY);
        }

        if (null === $product->getId()) {
            throw new TaxEngineException('Product id is empty in Calculator::load', TaxEngineException::UNDEFINED_PRODUCT);
        }

        $this->country = $country;
        $this->product = $product;
        $this->state = $state;

        $this->taxRulesCollection = $this->taxRuleQuery->getTaxCalculatorCollection($taxRule, $country, $state);

        return $this;
    }

    /**
     * @return $this
     *
     * @throws PropelException
     */
    public function loadTaxRuleWithoutCountry(TaxRule $taxRule, Product $product): static
    {
        $this->product = null;
        $this->taxRulesCollection = null;

        if (null === $taxRule->getId()) {
            throw new TaxEngineException('TaxRule id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_TAX_RULE);
        }

        if (null === $product->getId()) {
            throw new TaxEngineException('Product id is empty in Calculator::load', TaxEngineException::UNDEFINED_PRODUCT);
        }

        $this->product = $product;

        $this->taxRulesCollection = $this->taxRuleQuery->getTaxCalculatorCollection($taxRule);

        return $this;
    }

    /**
     * @return $this
     *
     * @throws PropelException
     */
    public function loadTaxRuleWithoutProduct(TaxRule $taxRule, Country $country, ?State $state = null): static
    {
        if (null === $taxRule->getId()) {
            throw new TaxEngineException('TaxRule id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_TAX_RULE);
        }

        if (null === $country->getId()) {
            throw new TaxEngineException('Country id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_COUNTRY);
        }

        $this->country = $country;
        $this->product = new Product();
        $this->state = $state;

        $this->taxRulesCollection = $this->taxRuleQuery->getTaxCalculatorCollection($taxRule, $country, $state);

        return $this;
    }

    /**
     * @return float
     *
     * @throws PropelException
     */
    public function getTaxAmountFromUntaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null): int|float
    {
        return $this->getTaxedPrice($untaxedPrice, $taxCollection) - $untaxedPrice;
    }

    /**
     * @return float
     */
    public function getTaxAmountFromTaxedPrice($taxedPrice): int|float
    {
        return $taxedPrice - $this->getUntaxedPrice($taxedPrice);
    }

    /**
     * @param OrderProductTaxCollection|null $taxCollection returns OrderProductTaxCollection
     *
     * @return float
     *
     * @throws PropelException
     */
    public function getTaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null, ?string $askedLocale = null): int|float
    {
        if (!$this->taxRulesCollection instanceof ObjectCollection) {
            throw new TaxEngineException('Tax rules collection is empty in Calculator::getTaxedPrice', TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION);
        }

        if (null === $this->product) {
            throw new TaxEngineException('Product is empty in Calculator::getTaxedPrice', TaxEngineException::UNDEFINED_PRODUCT);
        }

        if (false === $untaxedPrice) {
            throw new TaxEngineException('BAD AMOUNT FORMAT', TaxEngineException::BAD_AMOUNT_FORMAT);
        }

        $taxedPrice = $untaxedPrice;
        $currentPosition = 1;
        $currentTax = 0;

        if ($taxCollection instanceof OrderProductTaxCollection) {
            $taxCollection = new OrderProductTaxCollection();
        }

        /** @var Tax $taxRule */
        foreach ($this->taxRulesCollection as $taxRule) {
            $position = (int) $taxRule->getTaxRuleCountryPosition();

            /** @var BaseTaxType $taxType */
            $taxType = $taxRule->getTypeInstance();

            if ($currentPosition !== $position) {
                $taxedPrice += $currentTax;
                $currentTax = 0;
                $currentPosition = $position;
            }

            $taxAmount = $taxType->calculate($this->product, $taxedPrice);
            $currentTax += $taxAmount;

            if ($taxCollection instanceof OrderProductTaxCollection) {
                /** @var TaxI18n $taxI18n */
                $taxI18n = I18n::forceI18nRetrieving($askedLocale, 'Tax', $taxRule->getId());

                $orderProductTax = (new OrderProductTax())
                    ->setTitle($taxI18n->getTitle())
                    ->setDescription($taxI18n->getDescription())
                    ->setAmount((string) $taxAmount);

                $taxCollection->addTax($orderProductTax);
            }
        }

        return $taxedPrice + $currentTax;
    }

    /**
     * @return float|int|number
     */
    public function getUntaxedPrice($taxedPrice): int|float
    {
        if (!$this->taxRulesCollection instanceof ObjectCollection) {
            throw new TaxEngineException('Tax rules collection is empty in Calculator::getTaxAmount', TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION);
        }

        if (null === $this->product) {
            throw new TaxEngineException('Product is empty in Calculator::getTaxedPrice', TaxEngineException::UNDEFINED_PRODUCT);
        }

        if (false === filter_var($taxedPrice, \FILTER_VALIDATE_FLOAT)) {
            throw new TaxEngineException('BAD AMOUNT FORMAT', TaxEngineException::BAD_AMOUNT_FORMAT);
        }

        $taxRule = $this->taxRulesCollection->getLast();

        if (null === $taxRule) {
            throw new TaxEngineException('Tax rules collection got no tax ', TaxEngineException::NO_TAX_IN_TAX_RULES_COLLECTION);
        }

        $untaxedPrice = $taxedPrice;
        $currentPosition = (int) $taxRule->getTaxRuleCountryPosition();
        $currentFixTax = 0;
        $currentTaxFactor = 0;

        do {
            $position = (int) $taxRule->getTaxRuleCountryPosition();

            /** @var BaseTaxType $taxType */
            $taxType = $taxRule->getTypeInstance();

            if ($currentPosition !== $position) {
                $untaxedPrice -= $currentFixTax;
                $untaxedPrice /= (1 + $currentTaxFactor);
                $currentFixTax = 0;
                $currentTaxFactor = 0;
                $currentPosition = $position;
            }

            $currentFixTax += $taxType->fixAmountRetriever($this->product);
            $currentTaxFactor += $taxType->pricePercentRetriever();
        } while ($taxRule = $this->taxRulesCollection->getPrevious());

        $untaxedPrice -= $currentFixTax;

        return $untaxedPrice / (1 + $currentTaxFactor);
    }
}
