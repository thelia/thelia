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
use Thelia\Model\ProductQuery;
use Thelia\Model\CartItem;
use Thelia\Model\Product;

/**
 * Check a Checkout against its Product number
 *
 * @package Condition
 * @author  Franck Allimant <franck@cqfdev.fr>
 *
 */
class CartContainsProducts extends ConditionAbstract
{
    /** Condition 1st parameter : quantity */
    const PRODUCTS_LIST = 'products';

    /**
     * @inheritdoc
     */
    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = [
            self::PRODUCTS_LIST => [
                Operators::IN,
                Operators::OUT
            ]
        ];

        parent::__construct($facade);
    }

    /**
     * @inheritdoc
     */
    public function getServiceId()
    {
        return 'thelia.condition.cart_contains_products';
    }

    /**
     * @inheritdoc
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->checkComparisonOperatorValue($operators, self::PRODUCTS_LIST);

        // Use default values if data is not defined.
        if (! isset($operators[self::PRODUCTS_LIST]) || ! isset($values[self::PRODUCTS_LIST])) {
            $operators[self::PRODUCTS_LIST] = Operators::IN;
            $values[self::PRODUCTS_LIST] = [];
        }

        // Be sure that the value is an array, make one if required
        if (! is_array($values[self::PRODUCTS_LIST])) {
            $values[self::PRODUCTS_LIST] = array($values[self::PRODUCTS_LIST]);
        }

        // Check that at least one product is selected
        if (empty($values[self::PRODUCTS_LIST])) {
            throw new InvalidConditionValueException(
                get_class(),
                self::PRODUCTS_LIST
            );
        }

        $this->operators = [ self::PRODUCTS_LIST => $operators[self::PRODUCTS_LIST] ];
        $this->values    = [ self::PRODUCTS_LIST => $values[self::PRODUCTS_LIST] ];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isMatching()
    {
        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if ($this->conditionValidator->variableOpComparison(
                $cartItem->getProduct()->getId(),
                $this->operators[self::PRODUCTS_LIST],
                $this->values[self::PRODUCTS_LIST]
            )) {
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
            'Cart contains specific products',
            []
        );
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'The coupon applies if the cart contains at least one product of the specified product list',
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
            $this->operators[self::PRODUCTS_LIST]
        );

        $prodStrList = '';

        $prodIds = $this->values[self::PRODUCTS_LIST];

        if (null !== $prodList = ProductQuery::create()->findPks($prodIds)) {
            /** @var Product $prod */
            foreach ($prodList as $prod) {
                $prodStrList .= $prod->setLocale($this->getCurrentLocale())->getTitle() . ', ';
            }

            $prodStrList = rtrim($prodStrList, ', ');
        }

        $toolTip = $this->translator->trans(
            'Cart contains at least a product %op% <strong>%products_list%</strong>',
            [
                '%products_list%' => $prodStrList,
                '%op%' => $i18nOperator
            ]
        );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    protected function generateInputs()
    {
        return array(
            self::PRODUCTS_LIST => array(
                'availableOperators' => $this->availableOperators[self::PRODUCTS_LIST],
                'value' => '',
                'selectedOperator' => Operators::IN
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/cart-contains-products-condition.html',
            [
                'operatorSelectHtml'    => $this->drawBackOfficeInputOperators(self::PRODUCTS_LIST),
                'products_field_name' => self::PRODUCTS_LIST,
                'values'                => isset($this->values[self::PRODUCTS_LIST]) ? $this->values[self::PRODUCTS_LIST] : array()
            ]
        );
    }
}
