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

namespace Thelia\Constraint\Rule;

use Symfony\Component\Intl\Exception\NotImplementedException;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Constraint\Validator\PriceParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Exception\InvalidRuleException;
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
 * @package Constraint
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

    /** @var RuleValidator Price Validator */
    protected $priceValidator = null;

    /**
     * Constructor
     *
     * @param CouponAdapterInterface $adapter    allowing to gather
     *                                           all necessary Thelia variables
     * @param array                  $validators Array of RuleValidator
     *                                           validating $paramsToValidate against
     *
     * @throws \Thelia\Exception\InvalidRuleException
     */
    public function __construct(CouponAdapterInterface $adapter, array $validators)
    {
        parent::__construct($adapter, $validators);

        if (isset($validators[self::PARAM1_PRICE])
            && $validators[self::PARAM1_PRICE] instanceof RuleValidator
        ) {
            $this->priceValidator = $validators[self::PARAM1_PRICE];
        } else {
            throw new InvalidRuleException(get_class());
        }
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
            ||!isset($this->validators[self::PARAM1_PRICE])
        ) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
        }

        /** @var RuleValidator $ruleValidator */
        $ruleValidator = $this->validators[self::PARAM1_PRICE];
        /** @var PriceParam $price */
        $price = $ruleValidator->getParam();

        if (!$price instanceof PriceParam) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
        }

        $this->checkBackOfficeInputsOperators();

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

        $price = $this->paramsToValidate[self::PARAM1_PRICE];

        return $this->isPriceValid($price);
    }

    /**
     * Check if a price is valid
     *
     * @param float $price Price to check
     *
     * @throws InvalidRuleValueException if Value is not allowed
     * @return bool
     */
    protected function isPriceValid($price)
    {
        $priceValidator = $this->priceValidator;
        try {
            $priceValidator->getParam()->compareTo($price);
        } catch(\InvalidArgumentException $e) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
        }

        return true;
    }

    /**
     * Generate current Rule param to be validated from adapter
     *
     * @return $this
     */
    protected function setParametersToValidate()
    {
        $this->paramsToValidate = array(
            self::PARAM1_PRICE => $this->adapter->getCartTotalPrice()
        );

        return $this;
    }

    /**
     * Return all validators
     * Serialization purpose
     *
     * @return array
     */
    public function getValidators()
    {
        return $this->validators;
    }

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->adapter
            ->getTranslator()
            ->trans('Cart total amount', null, 'constraint');
    }

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip()
    {
        $i18nOperator = Operators::getI18n(
            $this->adapter, $this->priceValidator->getOperator()
        );

        $toolTip = $this->adapter
            ->getTranslator()
            ->trans(
                'If cart total amount is %operator% %amount% %currency%',
                array(
                    '%operator%' => $i18nOperator,
                    '%amount%' => $this->priceValidator->getParam()->getPrice(),
                    '%currency%' => $this->priceValidator->getParam()->getCurrency()
                ),
                'constraint'
            );

        return $toolTip;
    }

}