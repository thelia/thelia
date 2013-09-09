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
use Symfony\Component\Translation\Translator;
use Thelia\Constraint\ConstraintValidator;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Constraint\Validator\PriceParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Exception\InvalidRuleException;
use Thelia\Exception\InvalidRuleOperatorException;
use Thelia\Exception\InvalidRuleValueException;
use Thelia\Type\FloatType;

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
class AvailableForTotalAmountManager extends CouponRuleAbstract
{
    /** Rule 1st parameter : price */
    CONST INPUT1 = 'price';

    /** Rule 1st parameter : currency */
    CONST INPUT2 = 'currency';

    /** @var string Service Id from Resources/config.xml  */
    protected $serviceId = 'thelia.constraint.rule.available_for_total_amount';

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array(
        self::INPUT1 => array(
            Operators::INFERIOR,
            Operators::INFERIOR_OR_EQUAL,
            Operators::EQUAL,
            Operators::SUPERIOR_OR_EQUAL,
            Operators::SUPERIOR
        ),
        self::INPUT2 => array(
            Operators::EQUAL,
       )
    );

//    /** @var RuleValidator Price Validator */
//    protected $priceValidator = null;

//    /**
//     * Check if backoffice inputs are relevant or not
//     *
//     * @throws InvalidRuleOperatorException if Operator is not allowed
//     * @throws InvalidRuleValueException    if Value is not allowed
//     * @return bool
//     */
//    public function checkBackOfficeInput()
//    {
//        if (!isset($this->validators)
//            || empty($this->validators)
//            ||!isset($this->validators[self::PARAM1_PRICE])
//            ||!isset($this->validators[self::PARAM1_PRICE])
//        ) {
//            throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
//        }
//
//        /** @var RuleValidator $ruleValidator */
//        $ruleValidator = $this->validators[self::PARAM1_PRICE];
//        /** @var PriceParam $price */
//        $price = $ruleValidator->getParam();
//
//        if (!$price instanceof PriceParam) {
//            throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
//        }
//
//        $this->checkBackOfficeInputsOperators();
//
//        return $this->isPriceValid($price->getPrice(), $price->getCurrency());
//    }

//    /**
//     * Check if Checkout inputs are relevant or not
//     *
//     * @throws InvalidRuleValueException if Value is not allowed
//     * @return bool
//     */
//    public function checkCheckoutInput()
//    {
//        $currency = $this->adapter->getCheckoutCurrency();
//        if (empty($currency)) {
//            throw new InvalidRuleValueException(
//                get_class(), self::PARAM1_CURRENCY
//            );
//        }
//
//        $price = $this->adapter->getCartTotalPrice();
//        if (empty($price)) {
//            throw new InvalidRuleValueException(
//                get_class(), self::PARAM1_PRICE
//            );
//        }
//
//        $this->paramsToValidate = array(
//            self::PARAM1_PRICE => $this->adapter->getCartTotalPrice(),
//            self::PARAM1_CURRENCY => $this->adapter->getCheckoutCurrency()
//        );
//
//        return $this->isPriceValid($price, $currency);
//    }

    /**
     * Check validators relevancy and store them
     *
     * @param array $operators Operators the Admin set in BackOffice
     * @param array $values    Values the Admin set in BackOffice
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->setValidators(
            $operators[self::INPUT1],
            $values[self::INPUT1],
            $operators[self::INPUT2],
            $values[self::INPUT2]
        );

        return $this;
    }

    /**
     * Check validators relevancy and store them
     *
     * @param string $priceOperator    Price Operator ex <
     * @param float  $priceValue       Price set to meet condition
     * @param string $currencyOperator Currency Operator ex =
     * @param string $currencyValue    Currency set to meet condition
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    protected function setValidators($priceOperator, $priceValue, $currencyOperator, $currencyValue)
    {
        $isOperator1Legit = $this->isOperatorLegit(
            $priceOperator,
            $this->availableOperators[self::INPUT1]
        );
        if (!$isOperator1Legit) {
            throw new \InvalidArgumentException(
                'Operator for price field is not legit'
            );
        }

        $isOperator1Legit = $this->isOperatorLegit(
            $currencyOperator,
            $this->availableOperators[self::INPUT2]
        );
        if (!$isOperator1Legit) {
            throw new \InvalidArgumentException(
                'Operator for currency field is not legit'
            );
        }

        $floatType = new FloatType();
        if (!$floatType->isValid($priceValue) || $priceValue <= 0) {
            throw new \InvalidArgumentException(
                'Value for price field is not legit'
            );
        }

        // @todo check currency is legit or not

        $this->operators = array(
            self::INPUT1 => $priceOperator,
            self::INPUT2 => $currencyOperator,
        );
        $this->values = array(
            self::INPUT1 => $priceValue,
            self::INPUT2 => $currencyValue,
        );

        return $this;
    }

    /**
     * Test if Customer meets conditions
     *
     * @return bool
     */
    public function isMatching()
    {
        $isOperator1Legit = $this->isOperatorLegit(
            $this->operators[self::INPUT1],
            $this->availableOperators[self::INPUT1]
        );
        $isOperator2Legit = $this->isOperatorLegit(
            $this->operators[self::INPUT2],
            $this->availableOperators[self::INPUT2]
        );

        if (!$isOperator1Legit || !$isOperator2Legit) {
            return false;
        }

        $constrainValidator = new ConstraintValidator();
        $constraint1 =$constrainValidator->variableOpComparison(
            $this->adapter->getCartTotalPrice(),
            $this->operators[self::INPUT1],
            $this->values[self::INPUT1]
        );
        $constraint2 =$constrainValidator->variableOpComparison(
            $this->adapter->getCheckoutCurrency(),
            $this->operators[self::INPUT2],
            $this->values[self::INPUT2]
        );
        if ($constraint1 && $constraint2) {
            return true;
        }
        return false;
    }

//    /**
//     * Check if a price is valid
//     *
//     * @param float  $price    Price to check
//     * @param string $currency Price currency
//     *
//     * @throws InvalidRuleValueException if Value is not allowed
//     * @return bool
//     */
//    protected function isPriceValid($price, $currency)
//    {
//        $priceValidator = $this->priceValidator;
//
//        /** @var PriceParam $param */
//        $param = $priceValidator->getParam();
//        if ($currency == $param->getCurrency()) {
//            try {
//                $priceValidator->getParam()->compareTo($price);
//            } catch(\InvalidArgumentException $e) {
//                throw new InvalidRuleValueException(get_class(), self::PARAM1_PRICE);
//            }
//        } else {
//            throw new InvalidRuleValueException(get_class(), self::PARAM1_CURRENCY);
//        }
//
//        return true;
//    }

//    /**
//     * Generate current Rule param to be validated from adapter
//     *
//     * @return $this
//     */
//    protected function setParametersToValidate()
//    {
//        $this->paramsToValidate = array(
//            self::PARAM1_PRICE => $this->adapter->getCartTotalPrice(),
//            self::PARAM1_CURRENCY => $this->adapter->getCheckoutCurrency()
//        );
//
//        return $this;
//    }

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->adapter->get('thelia.translator')->trans(
            'Cart total amount',
            array(),
            'constraint'
        );
    }

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip()
    {
        $i18nOperator = Operators::getI18n(
            $this->translator, $this->operators[self::INPUT1]
        );

        $toolTip = $this->translator->trans(
            'If cart total amount is <strong>%operator%</strong> %amount% %currency%',
            array(
                '%operator%' => $i18nOperator,
                '%amount%' => $this->values[self::INPUT1],
                '%currency%' => $this->values[self::INPUT2]
            ),
            'constraint'
        );

        return $toolTip;
    }

//    /**
//     * Populate a Rule from a form admin
//     *
//     * @param array $operators Rule Operator set by the Admin
//     * @param array $values    Rule Values set by the Admin
//     *
//     * @throws \InvalidArgumentException
//     * @return $this
//     */
//    public function populateFromForm(array $operators, array $values)
//    {
//        if ($values[self::PARAM1_PRICE] === null
//            || $values[self::PARAM1_CURRENCY] === null
//        ) {
//            throw new \InvalidArgumentException(
//                'The Rule ' . get_class() . 'needs at least a quantity set (' . self::PARAM1_PRICE . ', ' . self::PARAM1_CURRENCY . ')'
//            );
//        }
//
//        $this->priceValidator = new RuleValidator(
//            $operators[self::PARAM1_PRICE],
//            new PriceParam(
//                $this->translator,
//                $values[self::PARAM1_PRICE],
//                $values[self::PARAM1_CURRENCY]
//            )
//        );
//
//        $this->validators = array(self::PARAM1_PRICE => $this->priceValidator);
//
//        return $this;
//    }





}