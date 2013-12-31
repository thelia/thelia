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

namespace Thelia\Condition\Implementation;

use Symfony\Component\Intl\Exception\NotImplementedException;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;
use Thelia\Condition\SerializableCondition;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Model\Currency;
use Thelia\Type\FloatType;

/**
 * Assist in writing a condition of whether the Condition is applied or not
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class ConditionAbstract implements ConditionInterface
{

    /** @var string Service Id from Resources/config.xml  */
    protected $serviceId = null;

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array();

    /** @var array Parameters validating parameters against */
    protected $validators = array();

    /** @var  FacadeInterface Provide necessary value from Thelia */
    protected $facade = null;

    /** @var Translator Service Translator */
    protected $translator = null;

    /** @var array Operators set by Admin in BackOffice */
    protected $operators = array();

    /** @var array Values set by Admin in BackOffice */
    protected $values = array();

    /** @var ConditionEvaluator Conditions validator */
    protected $conditionValidator = null;

    /**
     * Constructor
     *
     * @param FacadeInterface $facade Service Facade
     */
    public function __construct(FacadeInterface $facade)
    {
        $this->facade = $facade;
        $this->translator = $facade->getTranslator();
        $this->conditionValidator = $facade->getConditionEvaluator();
    }

    /**
     * Return all available Operators for this Condition
     *
     * @return array Operators::CONST
     */
    public function getAvailableOperators()
    {
        return $this->availableOperators;
    }

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
     * Get ConditionManager Service id
     *
     * @return string
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * Validate if Operator given is available for this Condition
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
     * Return a serializable Condition
     *
     * @return SerializableCondition
     */
    public function getSerializableCondition()
    {
        $serializableCondition = new SerializableCondition();
        $serializableCondition->conditionServiceId = $this->serviceId;
        $serializableCondition->operators = $this->operators;

        $serializableCondition->values = $this->values;

        return $serializableCondition;
    }


    /**
     * Check if currency if valid or not
     *
     * @param string $currencyValue Currency EUR|USD|..
     *
     * @return bool
     * @throws \Thelia\Exception\InvalidConditionValueException
     */
    protected function isCurrencyValid($currencyValue)
    {
        $availableCurrencies = $this->facade->getAvailableCurrencies();
        /** @var Currency $currency */
        $currencyFound = false;
        foreach ($availableCurrencies as $currency) {
            if ($currencyValue == $currency->getCode()) {
                $currencyFound = true;
            }
        }
        if (!$currencyFound) {
            throw new InvalidConditionValueException(
                get_class(), 'currency'
            );
        }

        return true;
    }

    /**
     * Check if price is valid
     *
     * @param float $priceValue Price value to check
     *
     * @return bool
     * @throws \Thelia\Exception\InvalidConditionValueException
     */
    protected function isPriceValid($priceValue)
    {
        $floatType = new FloatType();
        if (!$floatType->isValid($priceValue) || $priceValue <= 0) {
            throw new InvalidConditionValueException(
                get_class(), 'price'
            );
        }

        return true;
    }

}