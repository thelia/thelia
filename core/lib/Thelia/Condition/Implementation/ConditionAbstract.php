<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Condition\Implementation;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;
use Thelia\Condition\SerializableCondition;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionOperatorException;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Exception\NotImplementedException;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Type\FloatType;

/**
 * Assist in writing a condition of whether the Condition is applied or not.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
abstract class ConditionAbstract implements ConditionInterface
{
    /** @var array Available Operators (Operators::CONST) */
    protected array $availableOperators = [];

    /** @var array Parameters validating parameters against */
    protected array $validators = [];

    /** @var Translator Service Translator */
    protected Translator $translator;

    /** @var array Operators set by Admin in BackOffice */
    protected array $operators = [];

    /** @var array Values set by Admin in BackOffice */
    protected array $values = [];

    /** @var ConditionEvaluator Conditions validator */
    protected ConditionEvaluator $conditionValidator;

    /**
     * Constructor.
     *
     * @param FacadeInterface $facade Service Facade
     */
    public function __construct(protected FacadeInterface $facade)
    {
        $this->translator = $this->facade->getTranslator();
        $this->conditionValidator = $this->facade->getConditionEvaluator();
    }

    /**
     * @param array  $operatorList  the list of comparison operator values, as entered in the condition parameter form
     * @param string $parameterName the name of the parameter to check
     *
     * @return $this
     *
     * @throws InvalidConditionOperatorException if the operator value is not in the allowed value
     */
    protected function checkComparisonOperatorValue(array $operatorList, string $parameterName)
    {
        $isOperator1Legit = $this->isOperatorLegit(
            $operatorList[$parameterName],
            $this->availableOperators[$parameterName],
        );

        if (!$isOperator1Legit) {
            throw new InvalidConditionOperatorException(self::class, $parameterName);
        }

        return $this;
    }

    /**
     * Return all validators.
     */
    public function getValidators(): array
    {
        $this->validators = $this->generateInputs();

        $translatedInputs = [];

        foreach ($this->validators as $key => $validator) {
            $translatedOperators = [];

            foreach ($validator['availableOperators'] as $availableOperators) {
                $translatedOperators[$availableOperators] = Operators::getI18n(
                    $this->translator,
                    $availableOperators,
                );
            }

            $validator['availableOperators'] = $translatedOperators;

            $translatedInputs[$key] = $validator;
        }

        return [
            'inputs' => $translatedInputs,
            'setOperators' => $this->operators,
            'setValues' => $this->values,
        ];
    }

    /**
     * Generate inputs ready to be drawn.
     *
     * TODO: what these "inputs ready to be drawn" is not clear.
     *
     * @throws NotImplementedException
     */
    protected function generateInputs(): array
    {
        throw new NotImplementedException('The generateInputs method must be implemented in '.self::class);
    }

    public function getServiceId(): string
    {
        return static::class;
    }

    /**
     * Validate if Operator given is available for this Condition.
     *
     * @param string $operator           Operator to validate ex <
     * @param array  $availableOperators Available operators
     */
    protected function isOperatorLegit(string $operator, array $availableOperators): bool
    {
        return \in_array($operator, $availableOperators, true);
    }

    /**
     * Return a serializable Condition.
     */
    public function getSerializableCondition(): SerializableCondition
    {
        $serializableCondition = new SerializableCondition();
        $serializableCondition->conditionServiceId = $this->getServiceId();
        $serializableCondition->operators = $this->operators;

        $serializableCondition->values = $this->values;

        return $serializableCondition;
    }

    /**
     * Check if currency if valid or not.
     *
     * @param string $currencyValue Currency EUR|USD|..
     *
     * @throws InvalidConditionValueException
     */
    protected function isCurrencyValid(string $currencyValue): bool
    {
        $availableCurrencies = $this->facade->getAvailableCurrencies();
        /** @var Currency $currency */
        $currencyFound = false;

        foreach ($availableCurrencies as $currency) {
            if ($currencyValue === $currency->getCode()) {
                $currencyFound = true;
            }
        }

        if (!$currencyFound) {
            throw new InvalidConditionValueException(self::class, 'currency');
        }

        return true;
    }

    /**
     * Check if price is valid.
     *
     * @param float $priceValue Price value to check
     *
     * @throws InvalidConditionValueException
     */
    protected function isPriceValid(float $priceValue): bool
    {
        $floatType = new FloatType();

        if (!$floatType->isValid($priceValue) || $priceValue <= 0) {
            throw new InvalidConditionValueException(self::class, 'price');
        }

        return true;
    }

    /**
     * Draw the operator input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions.
     *
     * @param string $inputKey Input key (ex: self::INPUT1)
     *
     * @return string HTML string
     */
    protected function drawBackOfficeInputOperators(string $inputKey): string
    {
        $html = '';

        $inputs = $this->getValidators();

        if (isset($inputs['inputs'][$inputKey])) {
            $html = $this->facade->getParser()->render(
                'coupon/condition-fragments/condition-selector.html',
                [
                    'operators' => $inputs['inputs'][$inputKey]['availableOperators'],
                    'value' => $this->operators[$inputKey] ?? '',
                    'inputKey' => $inputKey,
                ],
            );
        }

        return $html;
    }

    /**
     * Draw the base input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions.
     *
     * @param string $label    I18n input label
     * @param string $inputKey Input key (ex: self::INPUT1)
     *
     * @return string HTML string
     */
    protected function drawBackOfficeBaseInputsText(string $label, string $inputKey): string
    {
        $operatorSelectHtml = $this->drawBackOfficeInputOperators($inputKey);

        $currentValue = '';

        if (null !== $this->values && isset($this->values[$inputKey])) {
            $currentValue = $this->values[$inputKey];
        }

        return $this->facade->getParser()->render(
            'coupon/conditions-fragments/base-input-text.html',
            [
                'label' => $label,
                'inputKey' => $inputKey,
                'currentValue' => $currentValue,
                'operatorSelectHtml' => $operatorSelectHtml,
            ],
        );
    }

    /**
     * Draw the quantity input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions.
     *
     * @param string $inputKey Input key (ex: self::INPUT1)
     * @param int    $max      Maximum selectable
     * @param int    $min      Minimum selectable
     *
     * @return string HTML string
     */
    protected function drawBackOfficeInputQuantityValues(string $inputKey, int $max = 10, int $min = 0): string
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/quantity-selector.html',
            [
                'min' => $min,
                'max' => $max,
                'value' => $this->values[$inputKey] ?? '',
                'inputKey' => $inputKey,
            ],
        );
    }

    /**
     * Draw the currency input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions.
     *
     * @param string $inputKey Input key (ex: self::INPUT1)
     *
     * @return string HTML string
     */
    protected function drawBackOfficeCurrencyInput(string $inputKey): string
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
                'value' => $this->values[$inputKey] ?? '',
                'inputKey' => $inputKey,
            ],
        );
    }

    /**
     * A helper to het the current locale.
     *
     * @return string the current locale
     */
    protected function getCurrentLocale(): string
    {
        return $this->facade->getRequest()->getSession()->getLang()->getLocale();
    }
}
