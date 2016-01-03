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

use Thelia\Condition\Operators;
use Thelia\Coupon\FacadeInterface;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Condition AvailableForTotalAmount
 * Check if a Checkout total amount match criteria
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 *
 */
class MatchForTotalAmount extends ConditionAbstract
{
    /** Condition 1st parameter : price */
    const CART_TOTAL = 'price';

    /** Condition 1st parameter : currency */
    const CART_CURRENCY = 'currency';

    public function __construct(FacadeInterface $facade)
    {
        // Define the allowed comparison operators
        $this->availableOperators = [
            self::CART_TOTAL => [
                Operators::INFERIOR,
                Operators::INFERIOR_OR_EQUAL,
                Operators::EQUAL,
                Operators::SUPERIOR_OR_EQUAL,
                Operators::SUPERIOR
            ],
            self::CART_CURRENCY => [
                Operators::EQUAL,
            ]
        ];

        parent::__construct($facade);
    }

    /**
     * @inheritdoc
     */
    public function getServiceId()
    {
        return 'thelia.condition.match_for_total_amount';
    }

    /**
     * @inheritdoc
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this
            ->checkComparisonOperatorValue($operators, self::CART_TOTAL)
            ->checkComparisonOperatorValue($operators, self::CART_CURRENCY);

        $this->isPriceValid($values[self::CART_TOTAL]);

        $this->isCurrencyValid($values[self::CART_CURRENCY]);

        $this->operators = array(
            self::CART_TOTAL => $operators[self::CART_TOTAL],
            self::CART_CURRENCY => $operators[self::CART_CURRENCY],
        );
        $this->values = array(
            self::CART_TOTAL => $values[self::CART_TOTAL],
            self::CART_CURRENCY => $values[self::CART_CURRENCY],
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
            $this->facade->getCartTotalTaxPrice(),
            $this->operators[self::CART_TOTAL],
            $this->values[self::CART_TOTAL]
        );

        if ($condition1) {
            $condition2 = $this->conditionValidator->variableOpComparison(
                $this->facade->getCheckoutCurrency(),
                $this->operators[self::CART_CURRENCY],
                $this->values[self::CART_CURRENCY]
            );

            if ($condition2) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->translator->trans(
            'Cart total amount',
            []
        );
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'Check the total Cart amount in the given currency',
            []
        );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function getSummary()
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::CART_TOTAL]
        );

        $toolTip = $this->translator->trans(
            'If cart total amount is <strong>%operator%</strong> %amount% %currency%',
            array(
                '%operator%' => $i18nOperator,
                '%amount%' => $this->values[self::CART_TOTAL],
                '%currency%' => $this->values[self::CART_CURRENCY]
            )
        );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    protected function generateInputs()
    {
        $currencies = CurrencyQuery::create()->filterByVisible(true)->find();

        $cleanedCurrencies = [];

        /** @var Currency $currency */
        foreach ($currencies as $currency) {
            $cleanedCurrencies[$currency->getCode()] = $currency->getSymbol();
        }

        return array(
            self::CART_TOTAL => array(
                'availableOperators' => $this->availableOperators[self::CART_TOTAL],
                'availableValues' => '',
                'value' => '',
                'selectedOperator' => ''
            ),
            self::CART_CURRENCY => array(
                'availableOperators' => $this->availableOperators[self::CART_CURRENCY],
                'availableValues' => $cleanedCurrencies,
                'value' => '',
                'selectedOperator' => Operators::EQUAL
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        $labelPrice = $this->facade
            ->getTranslator()
            ->trans('Cart total amount is', []);

        $html = $this->drawBackOfficeBaseInputsText($labelPrice, self::CART_TOTAL);

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function drawBackOfficeBaseInputsText($label, $inputKey)
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/cart-total-amount-condition.html',
            [
                'label' => $label,
                'inputKey' => $inputKey,
                'value' => isset($this->values[$inputKey]) ? $this->values[$inputKey] : '',
                'field_1_name' => self::CART_TOTAL,
                'field_2_name' => self::CART_CURRENCY,
                'operatorSelectHtml' => $this->drawBackOfficeInputOperators(self::CART_TOTAL),
                'currencySelectHtml' => $this->drawBackOfficeCurrencyInput(self::CART_CURRENCY),
            ]
        );
    }
}
