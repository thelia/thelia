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

use Thelia\Constraint\Validator\CustomerParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Exception\InvalidRuleException;
use Thelia\Exception\InvalidRuleOperatorException;
use Thelia\Exception\InvalidRuleValueException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForCustomer extends CouponRuleAbstract
{

    /** Rule 1st parameter : customer id */
    CONST PARAM1 = 'customerId';

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array(
        Operators::EQUAL,
    );

    /** @var RuleValidator Customer Validator */
    protected $customerValidator = null;

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
            ||!isset($this->validators[self::PARAM1])
            ||!isset($this->validators[self::PARAM1])
        ) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1);
        }

        /** @var RuleValidator $ruleValidator */
        $ruleValidator = $this->validators[self::PARAM1];
        /** @var CustomerParam $customer */
        $customer = $ruleValidator->getParam();

        if (!$customer instanceof CustomerParam) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1);
        }

        $this->checkBackOfficeInputsOperators();

        return $this->isCustomerValid($customer->getInteger());
    }

    /**
     * Generate current Rule param to be validated from adapter
     *
     * @return $this
     */
    protected function setParametersToValidate()
    {
        $this->paramsToValidate = array(
            self::PARAM1 => $this->adapter->getCustomer()->getId()
        );

        return $this;
    }

    /**
     * Check if Checkout inputs are relevant or not
     *
     * @throws \Thelia\Exception\InvalidRuleValueException
     * @return bool
     */
    public function checkCheckoutInput()
    {
        if (!isset($this->paramsToValidate)
            || empty($this->paramsToValidate)
            ||!isset($this->paramsToValidate[self::PARAM1])
        ) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1);
        }

        $customerId = $this->paramsToValidate[self::PARAM1];

        return $this->isCustomerValid($customerId);
    }

    /**
     * Check if a Customer is valid
     *
     * @param int $customerId Customer to check
     *
     * @throws InvalidRuleValueException if Value is not allowed
     * @return bool
     */
    protected function isCustomerValid($customerId)
    {
        $customerValidator = $this->customerValidator;
        try {
            $customerValidator->getParam()->compareTo($customerId);
        } catch(\InvalidArgumentException $e) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1);
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
            ->trans('Customer', null, 'constraint');
    }

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip()
    {
        /** @var CustomerParam $param */
        $param = $this->customerValidator->getParam();
        $toolTip = $this->adapter
            ->getTranslator()
            ->trans(
                'If customer is %fistname% %lastname% (%email%)',
                array(
                    '%fistname%' => $param->getFirstName(),
                    '%lastname%' => $param->getLastName(),
                    '%email%' => $param->getEmail(),
                ),
                'constraint'
            );

        return $toolTip;
    }


}