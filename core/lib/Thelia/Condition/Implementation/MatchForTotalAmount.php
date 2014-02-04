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


use Thelia\Condition\Operators;
use Thelia\Exception\InvalidConditionOperatorException;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Condition AvailableForTotalAmount
 * Check if a Checkout total amount match criteria
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForTotalAmount extends ConditionAbstract
{
    /** Condition 1st parameter : price */
    CONST INPUT1 = 'price';

    /** Condition 1st parameter : currency */
    CONST INPUT2 = 'currency';

    /** @var string Service Id from Resources/config.xml  */
    protected $serviceId = 'thelia.condition.match_for_total_amount';

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
     * @throws \Thelia\Exception\InvalidConditionOperatorException
     * @return $this
     */
    protected function setValidators($priceOperator, $priceValue, $currencyOperator, $currencyValue)
    {
        $isOperator1Legit = $this->isOperatorLegit(
            $priceOperator,
            $this->availableOperators[self::INPUT1]
        );
        if (!$isOperator1Legit) {
            throw new InvalidConditionOperatorException(
                get_class(), 'price'
            );
        }

        $isOperator1Legit = $this->isOperatorLegit(
            $currencyOperator,
            $this->availableOperators[self::INPUT2]
        );
        if (!$isOperator1Legit) {
            throw new InvalidConditionOperatorException(
                get_class(), 'price'
            );
        }

        $this->isPriceValid($priceValue);

        $this->isCurrencyValid($currencyValue);

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
        $condition1 = $this->conditionValidator->variableOpComparison(
            $this->facade->getCartTotalPrice(),
            $this->operators[self::INPUT1],
            $this->values[self::INPUT1]
        );
        $condition2 = $this->conditionValidator->variableOpComparison(
            $this->facade->getCheckoutCurrency(),
            $this->operators[self::INPUT2],
            $this->values[self::INPUT2]
        );
        if ($condition1 && $condition2) {
            return true;
        }

        return false;
    }

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->translator->trans(
            'By cart total amount',
            array(),
            'condition'
        );
    }

    /**
     * Get I18n tooltip
     * Explain in detail what the Condition checks
     *
     * @return string
     */
    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'Check the total Cart amount in the given currency',
            array(),
            'condition'
        );

        return $toolTip;
    }

    /**
     * Get I18n summary
     * Explain briefly the condition with given values
     *
     * @return string
     */
    public function getSummary()
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
            'condition'
        );

        return $toolTip;
    }

    /**
     * Generate inputs ready to be drawn
     *
     * @return array
     */
    protected function generateInputs()
    {
        $currencies = CurrencyQuery::create()->find();
        $cleanedCurrencies = array();
        /** @var Currency $currency */
        foreach ($currencies as $currency) {
            $cleanedCurrencies[$currency->getCode()] = $currency->getSymbol();
        }

        return array(
            self::INPUT1 => array(
                'availableOperators' => $this->availableOperators[self::INPUT1],
                'availableValues' => '',
                'value' => '',
                'selectedOperator' => ''
            ),
            self::INPUT2 => array(
                'availableOperators' => $this->availableOperators[self::INPUT2],
                'availableValues' => $cleanedCurrencies,
                'value' => '',
                'selectedOperator' => Operators::EQUAL
            )
        );
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs()
    {
        $labelPrice = $this->facade
            ->getTranslator()
            ->trans('Price', array(), 'condition');

        $html = $this->drawBackOfficeBaseInputsText($labelPrice, self::INPUT1);

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
        $operatorSelectHtml = $this->drawBackOfficeInputOperators(self::INPUT1);
        $currencySelectHtml = $this->drawBackOfficeCurrencyInput(self::INPUT2);
        $selectedAmount = '';
        if (isset($this->values) && isset($this->values[$inputKey])) {
            $selectedAmount = $this->values[$inputKey];
        }

        $html = '
            <label for="operator">' . $label . '</label>
            <div class="row">
                <div class="col-lg-6">
                    ' . $operatorSelectHtml . '
                </div>
                <div class="input-group col-lg-3">
                    <input type="text" class="form-control" id="' . self::INPUT1 . '-value" name="' . self::INPUT1 . '[value]" value="' . $selectedAmount . '">
                </div>
                <div class="input-group col-lg-3">
                    <input type="hidden" id="' . self::INPUT2 . '-operator" name="' . self::INPUT2 . '[operator]" value="==" />
                    ' . $currencySelectHtml . '
                </div>
            </div>
        ';

        return $html;
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
        $optionHtml = '';

        $currencies = CurrencyQuery::create()->find();
        $cleanedCurrencies = array();
        /** @var Currency $currency */
        foreach ($currencies as $currency) {
            $cleanedCurrencies[$currency->getCode()] = $currency->getSymbol();
        }

        foreach ($cleanedCurrencies as $key => $cleanedCurrency) {
            $selected = '';
            if (isset($this->values) && isset($this->values[$inputKey]) && $this->values[$inputKey] == $key) {
                $selected = ' selected="selected"';
            }
            $optionHtml .= '<option value="' . $key . '" ' . $selected . '>' . $cleanedCurrency . '</option>';
        }

        $selectHtml = '
            <select class="form-control" id="' . $inputKey . '-value" name="' . $inputKey . '[value]">
                ' . $optionHtml . '
            </select>
        ';

        return $selectHtml;
    }

}
