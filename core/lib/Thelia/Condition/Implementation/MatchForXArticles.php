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
use Thelia\Exception\InvalidConditionOperatorException;
use Thelia\Exception\InvalidConditionValueException;

/**
 * Check a Checkout against its Product number
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForXArticles extends ConditionAbstract
{
    /** Condition 1st parameter : quantity */
    CONST INPUT1 = 'quantity';

    /** @var string Service Id from Resources/config.xml  */
    protected $serviceId = 'thelia.condition.match_for_x_articles';

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array(
        self::INPUT1 => array(
            Operators::INFERIOR,
            Operators::INFERIOR_OR_EQUAL,
            Operators::EQUAL,
            Operators::SUPERIOR_OR_EQUAL,
            Operators::SUPERIOR
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
            $values[self::INPUT1]
        );

        return $this;
    }

    /**
     * Check validators relevancy and store them
     *
     * @param string $quantityOperator Quantity Operator ex <
     * @param int    $quantityValue    Quantity set to meet condition
     *
     * @throws \Thelia\Exception\InvalidConditionValueException
     * @throws \Thelia\Exception\InvalidConditionOperatorException
     * @return $this
     */
    protected function setValidators($quantityOperator, $quantityValue)
    {
        $isOperator1Legit = $this->isOperatorLegit(
            $quantityOperator,
            $this->availableOperators[self::INPUT1]
        );
        if (!$isOperator1Legit) {
            throw new InvalidConditionOperatorException(
                get_class(), 'quantity'
            );
        }

        if ((int) $quantityValue <= 0) {
            throw new InvalidConditionValueException(
                get_class(), 'quantity'
            );
        }

        $this->operators = array(
            self::INPUT1 => $quantityOperator,
        );
        $this->values = array(
            self::INPUT1 => $quantityValue,
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
            $this->facade->getNbArticlesInCart(),
            $this->operators[self::INPUT1],
            $this->values[self::INPUT1]
        );

        if ($condition1) {
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
            'Cart item count condition',
            [],
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
            'The cart item count should match the condition',
            [],
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
            'If cart item count is <strong>%operator%</strong> %quantity%',
            array(
                '%operator%' => $i18nOperator,
                '%quantity%' => $this->values[self::INPUT1]
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
        return array(
            self::INPUT1 => array(
                'availableOperators' => $this->availableOperators[self::INPUT1],
                'value' => '',
                'selectedOperator' => ''
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
        $labelQuantity = $this->facade
            ->getTranslator()
            ->trans('Cart item count is', [], 'condition');

        $html = $this->drawBackOfficeBaseInputsText($labelQuantity, self::INPUT1);

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
        return $this->facade->getParser()->render('coupon/condition-fragments/cart-item-count-condition.html', [
                'label'              => $label,
                'operatorSelectHtml' => $this->drawBackOfficeInputOperators($inputKey),
                'quantitySelectHtml' => $this->drawBackOfficeInputQuantityValues($inputKey, 20, 1)
            ]
        );
    }
}