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

namespace Thelia\TaxEngine;

use Thelia\Exception\TaxEngineException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\Order;
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
class Calculator
{
    /**
     * @var TaxRuleQuery
     */
    protected $taxRuleQuery;

    /**
     * @var \Propel\Runtime\Collection\ObjectCollection|null
     */
    protected $taxRulesCollection;

    protected $product;
    protected $country;
    protected $state;

    public function __construct()
    {
        $this->taxRuleQuery = new TaxRuleQuery();
    }

    /**
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getUntaxedCartDiscount(Cart $cart, Country $country, State $state = null)
    {
        return $cart->getDiscount() / self::getCartTaxFactor($cart, $country, $state);
    }

    /**
     * @return float|int
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */

    /**
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getUntaxedOrderDiscount(Order $order)
    {
        return $order->getDiscount() / self::getOrderTaxFactor($order);
    }

    /**
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getOrderTaxFactor(Order $order)
    {
        // Cache the result in a local variable
        static $orderTaxFactor;

        if (null === $orderTaxFactor) {
            if ((float) $order->getDiscount() === 0.0) {
                return 1;
            }

            // Find the average Tax rate (see \Thelia\TaxEngine\Calculator::getCartTaxFactor())
            $orderTaxFactors = [];

            /** @var \Thelia\Model\OrderProduct $orderProduct */
            foreach ($order->getOrderProducts() as $orderProduct) {
                /** @var \Thelia\Core\Template\Loop\OrderProductTax $orderProductTax */
                foreach ($orderProduct->getOrderProductTaxes() as $orderProductTax) {
                    $orderTaxFactors[] = 1 + $orderProductTax->getAmount() / $orderProduct->getPrice();
                }
            }

            if (0 === $orderTaxfactorCount = \count($orderTaxFactors)) {
                return 1;
            }

            $orderTaxFactor = array_sum($orderTaxFactors) / \count($orderTaxFactors);
        }

        return $orderTaxFactor;
    }

    /**
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getCartTaxFactor(Cart $cart, Country $country, State $state = null)
    {
        // Cache the result in a local variable
        static $cartFactor;

        if (null === $cartFactor) {
            if ((float) $cart->getDiscount() === 0.0) {
                return 1;
            }

            $cartItems = $cart->getCartItems();

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

            $cartFactor = array_sum($cartTaxFactors) / \count($cartTaxFactors);
        }

        return $cartFactor;
    }

    /**
     * @return $this
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function load(Product $product, Country $country, State $state = null)
    {
        if ($product->getId() === null) {
            throw new TaxEngineException('Product id is empty in Calculator::load', TaxEngineException::UNDEFINED_PRODUCT);
        }
        if ($country->getId() === null) {
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
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function loadTaxRule(TaxRule $taxRule, Country $country, Product $product, State $state = null)
    {
        if ($taxRule->getId() === null) {
            throw new TaxEngineException('TaxRule id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_TAX_RULE);
        }
        if ($country->getId() === null) {
            throw new TaxEngineException('Country id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_COUNTRY);
        }
        if ($product->getId() === null) {
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
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function loadTaxRuleWithoutCountry(TaxRule $taxRule, Product $product)
    {
        $this->product = null;
        $this->taxRulesCollection = null;

        if ($taxRule->getId() === null) {
            throw new TaxEngineException('TaxRule id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_TAX_RULE);
        }
        if ($product->getId() === null) {
            throw new TaxEngineException('Product id is empty in Calculator::load', TaxEngineException::UNDEFINED_PRODUCT);
        }

        $this->product = $product;

        $this->taxRulesCollection = $this->taxRuleQuery->getTaxCalculatorCollection($taxRule);

        return $this;
    }

    /**
     * @return $this
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @since 2.4
     */
    public function loadTaxRuleWithoutProduct(TaxRule $taxRule, Country $country, State $state = null)
    {
        if ($taxRule->getId() === null) {
            throw new TaxEngineException('TaxRule id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_TAX_RULE);
        }
        if ($country->getId() === null) {
            throw new TaxEngineException('Country id is empty in Calculator::loadTaxRule', TaxEngineException::UNDEFINED_COUNTRY);
        }

        $this->country = $country;
        $this->product = new Product();
        $this->state = $state;

        $this->taxRulesCollection = $this->taxRuleQuery->getTaxCalculatorCollection($taxRule, $country, $state);

        return $this;
    }

    /**
     * @param $untaxedPrice
     * @param null $taxCollection
     *
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTaxAmountFromUntaxedPrice($untaxedPrice, &$taxCollection = null)
    {
        return $this->getTaxedPrice($untaxedPrice, $taxCollection) - $untaxedPrice;
    }

    /**
     * @param $taxedPrice
     *
     * @return float
     */
    public function getTaxAmountFromTaxedPrice($taxedPrice)
    {
        return $taxedPrice - $this->getUntaxedPrice($taxedPrice);
    }

    /**
     * @param float                          $untaxedPrice
     * @param OrderProductTaxCollection|null $taxCollection returns OrderProductTaxCollection
     * @param string|null                    $askedLocale
     *
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTaxedPrice($untaxedPrice, &$taxCollection = null, $askedLocale = null)
    {
        if (null === $this->taxRulesCollection) {
            throw new TaxEngineException('Tax rules collection is empty in Calculator::getTaxedPrice', TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION);
        }

        if (null === $this->product) {
            throw new TaxEngineException('Product is empty in Calculator::getTaxedPrice', TaxEngineException::UNDEFINED_PRODUCT);
        }

        if (false === filter_var($untaxedPrice, FILTER_VALIDATE_FLOAT)) {
            throw new TaxEngineException('BAD AMOUNT FORMAT', TaxEngineException::BAD_AMOUNT_FORMAT);
        }

        $taxedPrice = $untaxedPrice;
        $currentPosition = 1;
        $currentTax = 0;

        if (null !== $taxCollection) {
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

            if (null !== $taxCollection) {
                /** @var TaxI18n $taxI18n */
                $taxI18n = I18n::forceI18nRetrieving($askedLocale, 'Tax', $taxRule->getId());

                $orderProductTax = (new OrderProductTax())
                    ->setTitle($taxI18n->getTitle())
                    ->setDescription($taxI18n->getDescription())
                    ->setAmount($taxAmount)
                ;

                $taxCollection->addTax($orderProductTax);
            }
        }

        $taxedPrice += $currentTax;

        return $taxedPrice;
    }

    /**
     * @param $taxedPrice
     *
     * @return float|int|number
     */
    public function getUntaxedPrice($taxedPrice)
    {
        if (null === $this->taxRulesCollection) {
            throw new TaxEngineException('Tax rules collection is empty in Calculator::getTaxAmount', TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION);
        }

        if (null === $this->product) {
            throw new TaxEngineException('Product is empty in Calculator::getTaxedPrice', TaxEngineException::UNDEFINED_PRODUCT);
        }

        if (false === filter_var($taxedPrice, FILTER_VALIDATE_FLOAT)) {
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
        $untaxedPrice /= (1 + $currentTaxFactor);

        return $untaxedPrice;
    }
}
