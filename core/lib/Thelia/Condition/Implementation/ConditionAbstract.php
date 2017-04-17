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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;
use Thelia\Condition\SerializableCondition;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionOperatorException;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Model\CurrencyQuery;
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
    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = [];

    /** @var array Parameters validating parameters against */
    protected $validators = [];

    /** @var  FacadeInterface Provide necessary value from Thelia */
    protected $facade = null;

    /** @var Translator Service Translator */
    protected $translator = null;

    /** @var array Operators set by Admin in BackOffice */
    protected $operators = [];

    /** @var array Values set by Admin in BackOffice */
    protected $values = [];

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
     * @param array  $operatorList  the list of comparison operator values, as entered in the condition parameter form
     * @param string $parameterName the name of the parameter to check
     *
     * @return $this
     *
     * @throws \Thelia\Exception\InvalidConditionOperatorException if the operator value is not in the allowed value
     */
    protected function checkComparisonOperatorValue($operatorList, $parameterName)
    {
        $isOperator1Legit = $this->isOperatorLegit(
            $operatorList[$parameterName],
            $this->availableOperators[$parameterName]
        );

        if (!$isOperator1Legit) {
            throw new InvalidConditionOperatorException(
                get_class(),
                $parameterName
            );
        }

        return $this;
    }

    /**
     * Return all validators
     *
     * @return array
     */
    public function getValidators()
    {
        $this->validators = $this->generateInputs();

        $translatedInputs = [];

        foreach ($this->validators as $key => $validator) {
            $translatedOperators = [];

            foreach ($validator['availableOperators'] as $availableOperators) {
                $translatedOperators[$availableOperators] = Operators::getI18n(
                    $this->translator,
                    $availableOperators
                );
            }

            $validator['availableOperators'] = $translatedOperators;

            $translatedInputs[$key] = $validator;
        }

        $validators = [
            'inputs'       => $translatedInputs,
            'setOperators' => $this->operators,
            'setValues'    => $this->values
        ];

        return $validators;
    }

    /**
     * Generate inputs ready to be drawn.
     *
     * TODO: what these "inputs ready to be drawn" is not clear.
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
        throw new \Thelia\Exception\NotImplementedException(
            'The getServiceId method must be implemented in ' . get_class()
        );
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
        $serializableCondition->conditionServiceId = $this->getServiceId();
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
                get_class(),
                'currency'
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
                get_class(),
                'price'
            );
        }

        return true;
    }

    /**
     * Draw the operator input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions
     *
     * @param string $inputKey Input key (ex: self::INPUT1)
     *
     * @return string HTML string
     */
    protected function drawBackOfficeInputOperators($inputKey)
    {
        $html = '';

        $inputs = $this->getValidators();

        if (isset($inputs['inputs'][$inputKey])) {
            $html = $this->facade->getParser()->render(
                'coupon/condition-fragments/condition-selector.html',
                [
                    'operators' => $inputs['inputs'][$inputKey]['availableOperators'],
                    'value'     => isset($this->operators[$inputKey]) ? $this->operators[$inputKey] : '',
                    'inputKey'  => $inputKey
                ]
            );
        }

        return $html;
    }

    /**
     * Draw the base input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions
     *
     * @param string $label    I18n input label
     * @param string $inputKey Input key (ex: self::INPUT1)
     *
     * @return string HTML string
     */
    protected function drawBackOfficeBaseInputsText($label, $inputKey)
    {
        $operatorSelectHtml = $this->drawBackOfficeInputOperators($inputKey);

        $currentValue = '';
        if (isset($this->values) && isset($this->values[$inputKey])) {
            $currentValue = $this->values[$inputKey];
        }

        return $this->facade->getParser()->render(
            'coupon/conditions-fragments/base-input-text.html',
            [
                'label' => $label,
                'inputKey' => $inputKey,
                'currentValue' => $currentValue,
                'operatorSelectHtml' => $operatorSelectHtml
            ]
        );
    }

    /**
     * Draw the quantity input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions
     *
     * @param string $inputKey Input key (ex: self::INPUT1)
     * @param int    $max      Maximum selectable
     * @param int    $min      Minimum selectable
     *
     * @return string HTML string
     */
    protected function drawBackOfficeInputQuantityValues($inputKey, $max = 10, $min = 0)
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/quantity-selector.html',
            [
                'min'      => $min,
                'max'      => $max,
                'value'    => isset($this->values[$inputKey]) ? $this->values[$inputKey] : '',
                'inputKey' => $inputKey
            ]
        );
    }

    /**
     * Draw the currency input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions
     *
     * @param string $inputKey Input key (ex: self::INPUT1)
     *
     * @return string HTML string
     */
    protected function drawBackOfficeCurrencyInput($inputKey)
    {
        $currencies = CurrencyQuery::create()->find();

        $cleanedCurrencies = [];

        /** @var Currency $currency */
        foreach ($currencies as $currency) {
            $cleanedCurrencies[$currency->getCode()] = $currency->getSymbol();
        }

        return $this->facade->getParser()->render(
            'coupon/condition-fragments/currency-selector.html',
            [
                'currencies' => $cleanedCurrencies,
                'value'      => isset($this->values[$inputKey]) ? $this->values[$inputKey] : '',
                'inputKey'   => $inputKey
            ]
        );
    }

    /**
     * A helper to het the current locale.
     *
     * @return string the current locale.
     */
    protected function getCurrentLocale()
    {
        return $this->facade->getRequest()->getSession()->getLang()->getLocale();
    }
}
