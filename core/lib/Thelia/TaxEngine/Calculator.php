<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\TaxEngine;

use Thelia\Exception\TaxEngineException;
use Thelia\Model\Country;
use Thelia\Model\Product;
use Thelia\Model\TaxRuleQuery;

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

    public function __construct()
    {
        $this->taxRuleQuery = new TaxRuleQuery();
    }

    public function load(Product $product, Country $country)
    {
        $this->product = null;
        $this->country = null;
        $this->taxRulesCollection = null;

        if($product->getId() === null) {
            throw new TaxEngineException('Product id is empty in Calculator::load', TaxEngineException::UNDEFINED_PRODUCT);
        }
        if($country->getId() === null) {
            throw new TaxEngineException('Country id is empty in Calculator::load', TaxEngineException::UNDEFINED_COUNTRY);
        }

        $this->product = $product;
        $this->country = $country;

        $this->taxRulesCollection = $this->taxRuleQuery->getTaxCalculatorCollection($product, $country);

        return $this;
    }

    public function getTaxAmount($untaxedPrice)
    {
        if(null === $this->taxRulesCollection) {
            throw new TaxEngineException('Tax rules collection is empty in Calculator::getTaxAmount', TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION);
        }

        if(false === filter_var($untaxedPrice, FILTER_VALIDATE_FLOAT)) {
            throw new TaxEngineException('BAD AMOUNT FORMAT', TaxEngineException::BAD_AMOUNT_FORMAT);
        }

        $taxedPrice = $untaxedPrice;
        $currentPosition = 1;
        $currentTax = 0;

        foreach($this->taxRulesCollection as $taxRule) {
            $position = (int)$taxRule->getTaxRuleCountryPosition();

            $taxType = $taxRule->getTypeInstance();
            $taxType->loadRequirements( $taxRule->getRequirements() );

            if($currentPosition !== $position) {
                $taxedPrice += $currentTax;
                $currentTax = 0;
                $currentPosition = $position;
            }

            $currentTax += $taxType->calculate($taxedPrice);
        }

        $taxedPrice += $currentTax;

        return $taxedPrice;
    }

    public function getTaxedPrice($untaxedPrice)
    {
        return $untaxedPrice + $this->getTaxAmount($untaxedPrice);
    }
}
