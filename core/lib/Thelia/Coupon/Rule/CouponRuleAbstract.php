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
use Thelia\Coupon\Parameter\ComparableInterface;
use Thelia\Exception\InvalidRuleOperatorException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Assist in writing a condition of whether the Rule is applied or not
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class CouponRuleAbstract implements CouponRuleInterface
{
    /** Operator key in $validators */
    CONST OPERATOR = 'operator';
    /** Value key in $validators */
    CONST VALUE = 'value';

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array();

    /** @var array Parameters validating parameters against */
    protected $validators = array();

    /** @var array Parameters to be validated */
    protected $paramsToValidate = array();

    /**
     * Constructor
     * Ex:
     *     Param 1 :
     *     $validators['price']['operator'] = Operators::INFERIOR
     *                         ['value'] = new IntegerParam(10)
     *
     *     Param 2 :
     *     $paramsToValidate['price'] = 9
     *
     * @param array $validators Parameters validating $paramsToValidate against
     * @param array $validated  Parameters to be paramsToValidate
     */
    public function __construct(array $validators, array $validated = null)
    {
        $this->validators = $validators;
        $this->paramsToValidate = $validated;
    }

    /**
     * Check if the current Checkout matches this condition
     *
     * @return bool
     */
    public function isMatching()
    {
        $this->checkBackOfficeInput();
        $this->checkCheckoutInput();

        $isMatching = true;
        foreach ($this->validators as $param => $validator) {
            $a = $this->paramsToValidate[$param];
            $operator = $validator[self::OPERATOR];
            /** @var ComparableInterface $b */
            $b = $validator[self::VALUE];

            if (!Operators::isValidAccordingToOperator($a, $operator, $b)) {
                $isMatching = false;
            }
        }

        return $isMatching;

    }

    /**
     * Return all available Operators for this Rule
     *
     * @return array Operators::CONST
     */
    public function getAvailableOperators()
    {
        return $this->availableOperators;
    }

    /**
     * Check if Operators set for this Rule in the BackOffice are legit
     *
     * @throws InvalidRuleOperatorException if Operator is not allowed
     * @return bool
     */
    protected function checkBackOfficeInputsOperators()
    {
        foreach ($this->validators as $key => $param) {
            if (!isset($param[self::OPERATOR])
                ||!in_array($param[self::OPERATOR], $this->availableOperators)
            ) {
                throw new InvalidRuleOperatorException(get_class(), $key);
            }
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
        throw new NotImplementedException(
            'CouponRuleInterface::setValidators needs to be implemented'
        );
    }

    /**
     * Generate current Rule param to be validated from adapter
     *
     * @param CouponAdapterInterface $adapter allowing to gather
     *                               all necessary Thelia variables
     *
     * @throws \Symfony\Component\Intl\Exception\NotImplementedException
     * @return $this
     */
    protected function setParametersToValidate(CouponAdapterInterface $adapter)
    {
        throw new NotImplementedException(
            'CouponRuleInterface::setValidators needs to be implemented'
        );
    }
}