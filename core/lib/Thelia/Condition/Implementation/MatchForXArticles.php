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
use Thelia\Exception\InvalidConditionValueException;

/**
 * Check a Checkout against its Product number
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 *
 */
class MatchForXArticles extends ConditionAbstract
{
    /** Condition 1st parameter : quantity */
    const CART_QUANTITY = 'quantity';

    /**
     * @inheritdoc
     */
    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = array(
            self::CART_QUANTITY => array(
                Operators::INFERIOR,
                Operators::INFERIOR_OR_EQUAL,
                Operators::EQUAL,
                Operators::SUPERIOR_OR_EQUAL,
                Operators::SUPERIOR
            )
        );

        parent::__construct($facade);
    }

    /**
     * @inheritdoc
     */
    public function getServiceId()
    {
        return 'thelia.condition.match_for_x_articles';
    }

    /**
     * @inheritdoc
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->checkComparisonOperatorValue($operators, self::CART_QUANTITY);

        if (intval($values[self::CART_QUANTITY]) <= 0) {
            throw new InvalidConditionValueException(
                get_class(),
                'quantity'
            );
        }

        $this->operators = [
            self::CART_QUANTITY => $operators[self::CART_QUANTITY]
        ];

        $this->values = [
            self::CART_QUANTITY => $values[self::CART_QUANTITY]
        ];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isMatching()
    {
        $condition1 = $this->conditionValidator->variableOpComparison(
            $this->facade->getNbArticlesInCart(),
            $this->operators[self::CART_QUANTITY],
            $this->values[self::CART_QUANTITY]
        );

        if ($condition1) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->translator->trans(
            'Cart item count',
            []
        );
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'The cart item count should match the condition',
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
            $this->operators[self::CART_QUANTITY]
        );

        $toolTip = $this->translator->trans(
            'If cart item count is <strong>%operator%</strong> %quantity%',
            array(
                '%operator%' => $i18nOperator,
                '%quantity%' => $this->values[self::CART_QUANTITY]
            )
        );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    protected function generateInputs()
    {
        return array(
            self::CART_QUANTITY => array(
                'availableOperators' => $this->availableOperators[self::CART_QUANTITY],
                'value' => '',
                'selectedOperator' => ''
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        $labelQuantity = $this->facade
            ->getTranslator()
            ->trans('Cart item count is');

        $html = $this->drawBackOfficeBaseInputsText($labelQuantity, self::CART_QUANTITY);

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function drawBackOfficeBaseInputsText($label, $inputKey)
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/cart-item-count-condition.html',
            [
                'label'              => $label,
                'operatorSelectHtml' => $this->drawBackOfficeInputOperators($inputKey),
                'quantitySelectHtml' => $this->drawBackOfficeInputQuantityValues($inputKey, 20, 1)
            ]
        );
    }
}
