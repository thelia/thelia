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
use Thelia\Model\Country;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;
use Thelia\Model\State;
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


    public function __construct()
    {
        $this->taxRuleQuery = new TaxRuleQuery();
    }

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

    public function getTaxAmountFromUntaxedPrice($untaxedPrice, &$taxCollection = null)
    {
        return $this->getTaxedPrice($untaxedPrice, $taxCollection) - $untaxedPrice;
    }

    public function getTaxAmountFromTaxedPrice($taxedPrice)
    {
        return $taxedPrice - $this->getUntaxedPrice($taxedPrice);
    }

    /**
     * @param      $untaxedPrice
     * @param null $taxCollection returns OrderProductTaxCollection
     * @param null $askedLocale
     *
     * @return int
     * @throws \Thelia\Exception\TaxEngineException
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
        foreach ($this->taxRulesCollection as $taxRule) {
            $position = (int) $taxRule->getTaxRuleCountryPosition();

            $taxType = $taxRule->getTypeInstance();

            if ($currentPosition !== $position) {
                $taxedPrice += $currentTax;
                $currentTax = 0;
                $currentPosition = $position;
            }

            $taxAmount = $taxType->calculate($this->product, $taxedPrice);
            $currentTax += $taxAmount;

            if (null !== $taxCollection) {
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

            $taxType = $taxRule->getTypeInstance();

            if ($currentPosition !== $position) {
                $untaxedPrice -= $currentFixTax;
                $untaxedPrice = $untaxedPrice / (1+$currentTaxFactor);
                $currentFixTax = 0;
                $currentTaxFactor = 0;
                $currentPosition = $position;
            }

            $currentFixTax += $taxType->fixAmountRetriever($this->product);
            $currentTaxFactor += $taxType->pricePercentRetriever();
        } while ($taxRule = $this->taxRulesCollection->getPrevious());

        $untaxedPrice -= $currentFixTax;
        $untaxedPrice = $untaxedPrice / (1+$currentTaxFactor);

        return $untaxedPrice;
    }
}
