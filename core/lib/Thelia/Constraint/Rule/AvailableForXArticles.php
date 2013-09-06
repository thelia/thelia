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

use InvalidArgumentException;
use Symfony\Component\Translation\Translator;
use Thelia\Constraint\Validator\QuantityParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Exception\InvalidRuleValueException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Check a Checkout against its Product number
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForXArticles extends CouponRuleAbstract
{
    /** Rule 1st parameter : quantity */
    CONST PARAM1_QUANTITY = 'quantity';

    /** @var string Service Id from Resources/config.xml  */
    protected $serviceId = 'thelia.constraint.rule.available_for_x_articles';

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array(
        Operators::INFERIOR,
        Operators::EQUAL,
        Operators::SUPERIOR,
    );

    /** @var QuantityParam Quantity Validator */
    protected $quantityValidator = null;

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
            ||!isset($this->validators[self::PARAM1_QUANTITY])
            ||!isset($this->validators[self::PARAM1_QUANTITY])
        ) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_QUANTITY);
        }

        /** @var RuleValidator $ruleValidator */
        $ruleValidator = $this->validators[self::PARAM1_QUANTITY];
        /** @var QuantityParam $quantity */
        $quantity = $ruleValidator->getParam();

        if (!$quantity instanceof QuantityParam) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_QUANTITY);
        }

        $this->checkBackOfficeInputsOperators();

        return $this->isQuantityValid($quantity->getInteger());
    }

    /**
     * Generate current Rule param to be validated from adapter
     *
     * @param CouponAdapterInterface $adapter allowing to gather
     *                               all necessary Thelia variables
     *
     * @return $this
     */
    protected function setParametersToValidate()
    {
        $this->paramsToValidate = array(
            self::PARAM1_QUANTITY => $this->adapter->getNbArticlesInCart()
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
            ||!isset($this->paramsToValidate[self::PARAM1_QUANTITY])
        ) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_QUANTITY);
        }

        $price = $this->paramsToValidate[self::PARAM1_QUANTITY];

        return $this->isQuantityValid($price);
    }

    /**
     * Check if a quantity is valid
     *
     * @param int $quantity Quantity to check
     *
     * @throws InvalidRuleValueException if Value is not allowed
     * @return bool
     */
    protected function isQuantityValid($quantity)
    {
        $quantityValidator = $this->quantityValidator;
        try {
            $quantityValidator->getParam()->compareTo($quantity);
        } catch(InvalidArgumentException $e) {
            throw new InvalidRuleValueException(get_class(), self::PARAM1_QUANTITY);
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
        return $this->translator->trans(
            'Number of articles in cart',
            array(),
            'constraint'
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
            $this->translator, $this->priceValidator->getOperator()
        );

        $toolTip = $this->translator->trans(
            'If cart products quantity is <strong>%operator%</strong> %quantity%',
            array(
                '%operator%' => $i18nOperator,
                '%quantity%' => $this->quantityValidator->getParam()->getInteger(),
            ),
            'constraint'
        );

        return $toolTip;
    }

    /**
     * Populate a Rule from a form admin
     *
     * @param array $operators Rule Operator set by the Admin
     * @param array $values    Rule Values set by the Admin
     *
     * @throws InvalidArgumentException
     * @return $this
     */
    public function populateFromForm(array $operators, array $values)
    {
        if ($values[self::PARAM1_QUANTITY] === null) {
            throw new InvalidArgumentException(
                'The Rule ' . get_class() . 'needs at least a quantity set (' . self::PARAM1_QUANTITY. ')'
            );
        }

        $this->quantityValidator = new RuleValidator(
            $operators[self::PARAM1_QUANTITY],
            new QuantityParam(
                $this->adapter,
                $values[self::PARAM1_QUANTITY]
            )
        );

        $this->validators = array(self::PARAM1_QUANTITY => $this->quantityValidator);

        return $this;
    }

    /**
     * Return a serializable Rule
     *
     * @return SerializableRule
     */
    public function getSerializableRule()
    {
        $serializableRule = new SerializableRule();
        $serializableRule->ruleServiceId = $this->serviceId;
        $serializableRule->operators = array(
            self::PARAM1_QUANTITY => $this->quantityValidator->getOperator()
        );

        $serializableRule->values = array(
            self::PARAM1_QUANTITY => $this->quantityValidator->getInteger()
        );

        return $serializableRule;
    }

}