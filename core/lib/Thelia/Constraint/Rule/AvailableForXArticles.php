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

use Thelia\Constraint\Validator\QuantityParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Exception\InvalidRuleValueException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Check a Checkout against its Product number
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForXArticles extends CouponRuleAbstract
{
    /** Rule 1st parameter : price */
    CONST PARAM1_QUANTITY = 'quantity';

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array(
        Operators::INFERIOR,
        Operators::EQUAL,
        Operators::SUPERIOR,
    );

    /** @var RuleValidator Quantity Validator */
    protected $quantityValidator = null;

    /**
     * Constructor
     *
     * @param CouponAdapterInterface $adapter    allowing to gather
     *                                           all necessary Thelia variables
     * @param array                  $validators Array of RuleValidator
     *                                           validating $paramsToValidate against
     *
     * @throws InvalidRuleException
     */
    public function __construct(CouponAdapterInterface $adapter, array $validators)
    {
        parent::__construct($adapter, $validators);

        if (isset($validators[self::PARAM1_QUANTITY])
            && $validators[self::PARAM1_QUANTITY] instanceof RuleValidator
        ) {
            $this->quantityValidator = $validators[self::PARAM1_QUANTITY];
        } else {
            throw new InvalidRuleException(get_class());
        }

        $this->adapter = $adapter;
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
            ||!isset($this->validators[self::PARAM1_QUANTITY])
            ||!isset($this->validators[self::PARAM1_QUANTITY])
        ) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_QUANTITY);
        }

        /** @var RuleValidator $ruleValidator */
        $ruleValidator = $this->validators[self::PARAM1_QUANTITY];
        /** @var QuantityParam $quantity */
        $quantity = $ruleValidator->getParam();

        if (!$quantity instanceof QuantityParam) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_QUANTITY);
        }

        $this->checkBackOfficeInputsOperators();

        return $this->isQuantityValid($quantity->getInteger());
    }

    /**
     * Generate current Rule param to be validated from adapter
     *
     * @param CouponAdapterInterface $adapter allowing to gather
     *                               all necessary Thelia variables
     *
     * @return $this
     */
    protected function setParametersToValidate()
    {
        $this->paramsToValidate = array(
            self::PARAM1_QUANTITY => $this->adapter->getCartTotalPrice()
        );

        return $this;
    }

    /**
     * Check if Checkout inputs are relevant or not
     *
     * @return bool
     */
    public function checkCheckoutInput()
    {
        // TODO: Implement checkCheckoutInput() method.
    }

    /**
     * Check if a quantity is valid
     *
     * @param float $quantity Quantity to check
     *
     * @throws InvalidRuleValueException if Value is not allowed
     * @return bool
     */
    protected function isQuantityValid($quantity)
    {
        $quantityValidator = $this->quantityValidator;
        try {
            $quantityValidator->getParam()->compareTo($quantity);
        } catch(\InvalidArgumentException $e) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_QUANTITY);
        }

        return true;
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
            ->trans('Number of articles in cart', null, 'constraint');
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
                'If cart products quantity is %operator% %quantity%',
                array(
                    '%operator%' => $i18nOperator,
                    '%quantity%' => $this->quantityValidator->getParam()->getInteger(),
                ),
                'constraint'
            );

        return $toolTip;
    }

}