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
use Thelia\Constraint\ConstraintValidator;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Constraint\Validator\ComparableInterface;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Exception\InvalidRuleException;
use Thelia\Exception\InvalidRuleOperatorException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Assist in writing a condition of whether the Rule is applied or not
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class CouponRuleAbstract implements CouponRuleInterface
{
//    /** Operator key in $validators */
//    CONST OPERATOR = 'operator';
//    /** Value key in $validators */
//    CONST VALUE = 'value';

    /** @var string Service Id from Resources/config.xml  */
    protected $serviceId = null;

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array();

    /** @var array Parameters validating parameters against */
    protected $validators = array();

//    /** @var array Parameters to be validated */
//    protected $paramsToValidate = array();

    /** @var  CouponAdapterInterface Provide necessary value from Thelia */
    protected $adapter = null;

    /** @var Translator Service Translator */
    protected $translator = null;

    /** @var array Operators set by Admin in BackOffice */
    protected $operators = array();

    /** @var array Values set by Admin in BackOffice */
    protected $values = array();

    /** @var ConstraintValidator Constaints validator */
    protected $constraintValidator = null;

    /**
     * Constructor
     *
     * @param CouponAdapterInterface $adapter Service adapter
     */
    function __construct(CouponAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->translator = $adapter->getTranslator();
        $this->constraintValidator = $adapter->getConstraintValidator();
    }

//    /**
//     * Check validator relevancy and store them
//     *
//     * @param array $validators Array of RuleValidator
//     *                          validating $paramsToValidate against
//     *
//     * @return $this
//     * @throws InvalidRuleException
//     */
//    protected function setValidators(array $validators)
//    {
//        foreach ($validators as $validator) {
//            if (!$validator instanceof RuleValidator) {
//                throw new InvalidRuleException(get_class());
//            }
//            if (!in_array($validator->getOperator(), $this->availableOperators)) {
//                throw new InvalidRuleOperatorException(
//                    get_class(),
//                    $validator->getOperator()
//                );
//            }
//        }
//        $this->validators = $validators;
//
//        return $this;
//    }



//    /**
//     * Check if the current Checkout matches this condition
//     *
//     * @return bool
//     */
//    public function isMatching()
//    {
//        $this->checkBackOfficeInput();
//        $this->checkCheckoutInput();
//
//        $isMatching = true;
//        /** @var $validator RuleValidator*/
//        foreach ($this->validators as $param => $validator) {
//            $a = $this->paramsToValidate[$param];
//            $operator = $validator->getOperator();
//            /** @var ComparableInterface, RuleParameterAbstract $b */
//            $b = $validator->getParam();
//
//            if (!Operators::isValid($a, $operator, $b)) {
//                $isMatching = false;
//            }
//        }
//
//        return $isMatching;
//
//    }

    /**
     * Return all available Operators for this Rule
     *
     * @return array Operators::CONST
     */
    public function getAvailableOperators()
    {
        return $this->availableOperators;
    }

//    /**
//     * Check if Operators set for this Rule in the BackOffice are legit
//     *
//     * @throws InvalidRuleOperatorException if Operator is not allowed
//     * @return bool
//     */
//    protected function checkBackOfficeInputsOperators()
//    {
//        /** @var RuleValidator $param */
//        foreach ($this->validators as $key => $param) {
//            $operator = $param->getOperator();
//            if (!isset($operator)
//                ||!in_array($operator, $this->availableOperators)
//            ) {
//                throw new InvalidRuleOperatorException(get_class(), $key);
//            }
//        }
//        return true;
//    }

//    /**
//     * Generate current Rule param to be validated from adapter
//     *
//     * @throws \Thelia\Exception\NotImplementedException
//     * @return $this
//     */
//    protected function setParametersToValidate()
//    {
//        throw new \Thelia\Exception\NotImplementedException();
//    }

    /**
     * Return all validators
     *
     * @return array
     */
    public function getValidators()
    {
        $this->validators = $this->generateInputs();

        $translatedInputs = array();
        foreach ($this->validators as $key => $validator) {
            $translatedOperators = array();
            foreach ($validator['availableOperators'] as $availableOperators) {
                $translatedOperators[$availableOperators] = Operators::getI18n(
                    $this->translator,
                    $availableOperators
                );
            }

            $validator['availableOperators'] = $translatedOperators;
            $translatedInputs[$key] = $validator;
        }
        $validators = array();
        $validators['inputs'] = $translatedInputs;
        $validators['setOperators'] = $this->operators;
        $validators['setValues'] = $this->values;

        return $validators;
    }

    /**
     * Generate inputs ready to be drawn
     *
     * @throws \Thelia\Exception\NotImplementedException
     * @return array
     */
    protected function generateInputs()
    {
        throw new \Thelia\Exception\NotImplementedException(
            'The generateInputs method must be implemented in ' . get_class()
        );
    }

    /**
     * Get Rule Service id
     *
     * @return string
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * Validate if Operator given is available for this Coupon
     *
     * @param string $operator           Operator to validate ex <
     * @param array  $availableOperators Available operators
     *
     * @return bool
     */
    protected function isOperatorLegit($operator, array $availableOperators)
    {
         return in_array($operator, $availableOperators);
    }

    /**
     * Return a serializable Rule
     *
     * @return SerializableRule
     */
    public function getSerializableRule()
    {
        $serializableRule = new SerializableRule();
        $serializableRule->ruleServiceId = $this->serviceId;
        $serializableRule->operators = $this->operators;

        $serializableRule->values = $this->values;

        return $serializableRule;
    }

}