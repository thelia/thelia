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
use Thelia\Model\CartItem;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;

/**
 * Check a Checkout against its Product number
 *
 * @package Condition
 * @author  Franck Allimant <franck@cqfdev.fr>
 *
 */
class CartContainsCategories extends ConditionAbstract
{
    /** Condition 1st parameter : quantity */
    const CATEGORIES_LIST = 'categories';

    /**
     * @inheritdoc
     */
    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = [
            self::CATEGORIES_LIST => [
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
        return 'thelia.condition.cart_contains_categories';
    }

    /**
     * @inheritdoc
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->checkComparisonOperatorValue($operators, self::CATEGORIES_LIST);

        // Use default values if data is not defined.
        if (! isset($operators[self::CATEGORIES_LIST]) || ! isset($values[self::CATEGORIES_LIST])) {
            $operators[self::CATEGORIES_LIST] = Operators::IN;
            $values[self::CATEGORIES_LIST] = [];
        }

        // Be sure that the value is an array, make one if required
        if (! is_array($values[self::CATEGORIES_LIST])) {
            $values[self::CATEGORIES_LIST] = array($values[self::CATEGORIES_LIST]);
        }

        // Check that at least one category is selected
        if (empty($values[self::CATEGORIES_LIST])) {
            throw new InvalidConditionValueException(
                get_class(),
                self::CATEGORIES_LIST
            );
        }

        $this->operators = [ self::CATEGORIES_LIST => $operators[self::CATEGORIES_LIST] ];
        $this->values    = [ self::CATEGORIES_LIST => $values[self::CATEGORIES_LIST] ];

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
            $categories = $cartItem->getProduct()->getCategories();

            /** @var Category $category */
            foreach ($categories as $category) {
                if (! $this->conditionValidator->variableOpComparison(
                    $category->getId(),
                    $this->operators[self::CATEGORIES_LIST],
                    $this->values[self::CATEGORIES_LIST]
                )) {
                    // cart item doesn't match go to next cart item
                    continue 2;
                }
            }
            // cart item match
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
            'Cart contains categories condition',
            []
        );
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'The coupon applies if the cart contains at least one product of the selected categories',
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
            $this->operators[self::CATEGORIES_LIST]
        );

        $catStrList = '';

        $catIds = $this->values[self::CATEGORIES_LIST];

        if (null !== $catList = CategoryQuery::create()->findPks($catIds)) {
            /** @var Category $cat */
            foreach ($catList as $cat) {
                $catStrList .= $cat->setLocale($this->getCurrentLocale())->getTitle() . ', ';
            }

            $catStrList = rtrim($catStrList, ', ');
        }

        $toolTip = $this->translator->trans(
            'At least one of cart products categories is %op% <strong>%categories_list%</strong>',
            [
                '%categories_list%' => $catStrList,
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
            self::CATEGORIES_LIST => array(
                'availableOperators' => $this->availableOperators[self::CATEGORIES_LIST],
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
            'coupon/condition-fragments/cart-contains-categories-condition.html',
            [
                'operatorSelectHtml'    => $this->drawBackOfficeInputOperators(self::CATEGORIES_LIST),
                'categories_field_name' => self::CATEGORIES_LIST,
                'values'                => isset($this->values[self::CATEGORIES_LIST]) ? $this->values[self::CATEGORIES_LIST] : array()
            ]
        );
    }
}
