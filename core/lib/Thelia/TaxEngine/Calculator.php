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

namespace Thelia\TaxEngine;

use Thelia\Exception\TaxEngineException;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;
use Thelia\Model\State;
use Thelia\Model\Tax;
use Thelia\Model\TaxI18n;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;
use Thelia\Tools\I18n;

/**
 * Class Calculator
 * @package Thelia\TaxEngine
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Calculator
{
    /**
     * @var TaxRuleQuery
     */
    protected $taxRuleQuery = null;

    /**
     * @var null|\Propel\Runtime\Collection\ObjectCollection
     */
    protected $taxRulesCollection = null;

    protected $product = null;
    protected $country = null;
    protected $state = null;

    /** @var float */
    protected $applicableDiscountTaxFactor;

    public function __construct($applicableDiscountTaxFactor = 1.0)
    {
        $this->taxRuleQuery = new TaxRuleQuery();

        $this->applicableDiscountTaxFactor = $applicableDiscountTaxFactor;
    }

    /**
     * @param Cart $cart
     * @param Country $country
     * @param State|null $state
     * @return Calculator
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function createFromCart(Cart $cart, Country $country, State $state = null)
    {
        // We have to calculate the taxes on the discounted product unit prices. As the discount is global,
        // we should apply a factor to all tax calculation, in order to dispatch discount tax on all
        // products in the cart.

        // Get the cart total without the discount
        $cartTotal = $cart->getTaxedAmount($country, false, $state);
        // Remove the discount (disount icludes taxes)
        $discountedTotal = $cartTotal - $cart->getDiscount();

        // Get the factor applicable to all tax calculation
        $discountTaxFactor = 1 - $discountedTotal / $cartTotal;

        // Get the cart total with discount
        return new Calculator($discountTaxFactor);
    }

    /**
     * @return float
     */
    public function getApplicableDiscountTaxFactor()
    {
        return $this->applicableDiscountTaxFactor;
    }

    /**
     * @param int $applicableDiscountTaxFactor
     * @return $this
     */
    public function setApplicableDiscountTaxFactor($applicableDiscountTaxFactor)
    {
        $this->applicableDiscountTaxFactor = $applicableDiscountTaxFactor;
        return $this;
    }

    /**
     * @param Product $product
     * @param Country $country
     * @param State|null $state
     * @return $this
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function load(Product $product, Country $country, State $state = null)
    {
        $this->product = null;
        $this->country = null;
        $this->state = null;

        $this->taxRulesCollection = null;

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
     * @param TaxRule $taxRule
     * @param Country $country
     * @param Product $product
     * @param State|null $state
     * @return $this
     */
    public function loadTaxRule(TaxRule $taxRule, Country $country, Product $product, State $state = null)
    {
        $this->product = null;
        $this->country = null;
        $this->taxRulesCollection = null;

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
     * @param TaxRule $taxRule
     * @param Country $country
     * @param State|null $state
     * @return $this
     * @since 2.4
     */
    public function loadTaxRuleWithoutProduct(TaxRule $taxRule, Country $country, State $state = null)
    {
        $this->product = null;
        $this->country = null;
        $this->taxRulesCollection = null;

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
     * @return float
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTaxAmountFromUntaxedPrice($untaxedPrice, &$taxCollection = null)
    {
        return $this->getTaxedPrice($untaxedPrice, $taxCollection) - $untaxedPrice;
    }

    /**
     * @param $taxedPrice
     * @return float
     */
    public function getTaxAmountFromTaxedPrice($taxedPrice)
    {
        // We have to round the tax amout here (sum of rounded values methods) to prevent the small total amount
        // differences which may occur when rounding the sum of unrouded values.
        return round($taxedPrice - $this->getUntaxedPrice($taxedPrice), 2);
    }

    /**
     * @param float $untaxedPrice
     * @param OrderProductTaxCollection|null $taxCollection returns OrderProductTaxCollection
     * @param string|null $askedLocale
     * @param bool $ignoreTaxWhereDiscountIsNotApplicable
     * @return float
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTaxedPrice($untaxedPrice, &$taxCollection = null, $askedLocale = null, $ignoreTaxWhereDiscountIsNotApplicable = false)
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

            if ($taxType->isDiscountFactorApplicable()) {
                $taxAmount *= $this->applicableDiscountTaxFactor;
            } elseif ($ignoreTaxWhereDiscountIsNotApplicable) {
                continue;
            }

            // We have to round the tax amout here (sum of rounded values methods) to prevent the small total amount
            // differences which may occur when rounding the sum of unrouded values.
            $taxAmount = round($taxAmount, 2);

            $currentTax += $taxAmount;

            if (null !== $taxCollection) {
                /** @var TaxI18n $taxI18n */
                $taxI18n = I18n::forceI18nRetrieving($askedLocale, 'Tax', $taxRule->getId());
                $orderProductTax = new OrderProductTax();
                $orderProductTax->setTitle($taxI18n->getTitle());
                $orderProductTax->setDescription($taxI18n->getDescription());
                $orderProductTax->setAmount($taxAmount);
                $taxCollection->addTax($orderProductTax);
            }
        }

        $taxedPrice += $currentTax;

        return $taxedPrice;
    }

    /**
     * @param $taxedPrice
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
                $untaxedPrice = $untaxedPrice / (1 + $currentTaxFactor);
                $currentFixTax = 0;
                $currentTaxFactor = 0;
                $currentPosition = $position;
            }

            $fixedAmount = $taxType->fixAmountRetriever($this->product);
            $ratioAmount = $taxType->pricePercentRetriever();

            if ($taxType->isDiscountFactorApplicable()) {
                $fixedAmount /= $this->applicableDiscountTaxFactor;
                $ratioAmount /= $this->applicableDiscountTaxFactor;
            }

            $currentFixTax += $fixedAmount;
            $currentTaxFactor += $ratioAmount;
        } while ($taxRule = $this->taxRulesCollection->getPrevious());

        $untaxedPrice -= $currentFixTax;
        $untaxedPrice = $untaxedPrice / (1 + $currentTaxFactor);

        return $untaxedPrice;
    }
}
