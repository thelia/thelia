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

use InvalidArgumentException;
use Thelia\Condition\Implementation\ConditionAbstract;
use Thelia\Condition\Operators;
use Thelia\Exception\InvalidConditionOperatorException;
use Thelia\Exception\InvalidConditionValueException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
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
            'Number of articles in cart',
            array(),
            'condition'
        );
    }

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip()
    {
        $i18nOperator = Operators::getI18n(
            $this->translator, $this->operators[self::INPUT1]
        );

        $toolTip = $this->translator->trans(
            'If cart products quantity is <strong>%operator%</strong> %quantity%',
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
        $name1 = $this->translator->trans(
            'Quantity',
            array(),
            'condition'
        );

        return array(
            self::INPUT1 => array(
                'title' => $name1,
                'availableOperators' => $this->availableOperators[self::INPUT1],
                'type' => 'text',
                'class' => 'form-control',
                'value' => '',
                'selectedOperator' => ''
            )
        );
    }
}
