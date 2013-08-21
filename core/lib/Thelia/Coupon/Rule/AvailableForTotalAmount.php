<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Coupon\Rule;

use Symfony\Component\Intl\Exception\NotImplementedException;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Coupon\Parameter\PriceParam;
use Thelia\Exception\InvalidRuleOperatorException;
use Thelia\Exception\InvalidRuleValueException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Rule AvailableForTotalAmount
 * Check if a Checkout total amount match criteria
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForTotalAmount extends CouponRuleAbstract
{
    /** Rule 1st parameter : price */
    CONST PARAM1_PRICE = 'price';

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array(
        Operators::INFERIOR,
        Operators::EQUAL,
        Operators::SUPERIOR,
    );

    /** @var PriceParam Price Validator */
    protected $priceValidator = null;

    /**
     * Constructor
     *
     * @param array $validators Parameters validating $paramsToValidate against
     * @param array $validated  Parameters to be paramsToValidate
     */
    public function __construct(array $validators, array $validated = null)
    {
        parent::__construct($validators, $validated);

        $this->priceValidator = $validators[self::PARAM1_PRICE][self::VALUE];
    }


    /**
     * Check if backoffice inputs are relevant or not
     *
     * @throws InvalidRuleOperatorException if Operator is not allowed
     * @throws InvalidRuleValueException    if Value is not allowed
     * @return bool
     */
    public function checkBackOfficeInput()
    {
        if (!isset($this->validators)
            || empty($this->validators)
            ||!isset($this->validators[self::PARAM1_PRICE])
            ||!isset($this->validators[self::PARAM1_PRICE][self::VALUE])
            ||!$this->validators[self::PARAM1_PRICE][self::VALUE] instanceof PriceParam
        ) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
        }

        $this->checkBackOfficeInputsOperators();

        /** @var PriceParam $price */
        $price = $this->validators[self::PARAM1_PRICE][self::VALUE];

        return $this->isPriceValid($price->getPrice());
    }

    /**
     * Check if Checkout inputs are relevant or not
     *
     * @throws InvalidRuleValueException if Value is not allowed
     * @return bool
     */
    public function checkCheckoutInput()
    {
        if (!isset($this->paramsToValidate)
            || empty($this->paramsToValidate)
            ||!isset($this->paramsToValidate[self::PARAM1_PRICE])
        ) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
        }

        $quantity = $this->paramsToValidate[self::PARAM1_PRICE];

        return $this->isPriceValid($quantity);
    }

    /**
     * Check if a price is valid
     *
     * @param int $price Price to check
     *
     * @throws InvalidRuleValueException if Value is not allowed
     * @return bool
     */
    protected function isPriceValid($price)
    {
        $priceValidator = $this->priceValidator;
        try {
            $priceValidator->compareTo($price);
        } catch(\InvalidArgumentException $e) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
        }

        return true;
    }

    /**
     * Generate current Rule validator from adapter
     *
     * @param CouponAdapterInterface $adapter allowing to gather
     *                               all necessary Thelia variables
     *
     * @throws \Symfony\Component\Intl\Exception\NotImplementedException
     * @return $this
     */
    protected function setValidators(CouponAdapterInterface $adapter)
    {
        $adapter->getRule($this);
    }

    /**
     * Generate current Rule param to be validated from adapter
     *
     * @param CouponAdapterInterface $adapter allowing to gather
     *                               all necessary Thelia variables
     *
     * @return $this
     */
    protected function setParametersToValidate(CouponAdapterInterface $adapter)
    {
        $this->paramsToValidate = array(
            self::PARAM1_PRICE => $adapter->getCheckoutTotalPrice()
        );

        return $this;
    }


}