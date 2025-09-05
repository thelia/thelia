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

use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\CartItem;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * Check a Checkout against its Product number.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class CartContainsProducts extends ConditionAbstract
{
    /** Condition 1st parameter : quantity */
    public const PRODUCTS_LIST = 'products';

    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = [
            self::PRODUCTS_LIST => [
                Operators::IN,
                Operators::OUT,
            ],
        ];

        parent::__construct($facade);
    }

    public function getServiceId(): string
    {
        return 'thelia.condition.cart_contains_products';
    }

    public function setValidatorsFromForm(array $operators, array $values): static
    {
        $this->checkComparisonOperatorValue($operators, self::PRODUCTS_LIST);

        // Use default values if data is not defined.
        if (!isset($operators[self::PRODUCTS_LIST]) || !isset($values[self::PRODUCTS_LIST])) {
            $operators[self::PRODUCTS_LIST] = Operators::IN;
            $values[self::PRODUCTS_LIST] = [];
        }

        // Be sure that the value is an array, make one if required
        if (!\is_array($values[self::PRODUCTS_LIST])) {
            $values[self::PRODUCTS_LIST] = [$values[self::PRODUCTS_LIST]];
        }

        // Check that at least one product is selected
        if (empty($values[self::PRODUCTS_LIST])) {
            throw new InvalidConditionValueException(self::class, self::PRODUCTS_LIST);
        }

        $this->operators = [self::PRODUCTS_LIST => $operators[self::PRODUCTS_LIST]];
        $this->values = [self::PRODUCTS_LIST => $values[self::PRODUCTS_LIST]];

        return $this;
    }

    public function isMatching(): bool
    {
        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if ($this->conditionValidator->variableOpComparison(
                $cartItem->getProduct()->getId(),
                $this->operators[self::PRODUCTS_LIST],
                $this->values[self::PRODUCTS_LIST],
            )) {
                return true;
            }
        }

        return false;
    }

    public function getName(): string
    {
        return $this->translator->trans(
            'Cart contains specific products',
            [],
        );
    }

    public function getToolTip(): string
    {
        return $this->translator->trans(
            'The coupon applies if the cart contains at least one product of the specified product list',
            [],
        );
    }

    public function getSummary(): string
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::PRODUCTS_LIST],
        );

        $prodStrList = '';

        $prodIds = $this->values[self::PRODUCTS_LIST];

        if (null !== $prodList = ProductQuery::create()->findPks($prodIds)) {
            /** @var Product $prod */
            foreach ($prodList as $prod) {
                $prodStrList .= $prod->setLocale($this->getCurrentLocale())->getTitle().', ';
            }

            $prodStrList = rtrim($prodStrList, ', ');
        }

        return $this->translator->trans(
            'Cart contains at least a product %op% <strong>%products_list%</strong>',
            [
                '%products_list%' => $prodStrList,
                '%op%' => $i18nOperator,
            ],
        );
    }

    protected function generateInputs(): array
    {
        return [
            self::PRODUCTS_LIST => [
                'availableOperators' => $this->availableOperators[self::PRODUCTS_LIST],
                'value' => '',
                'selectedOperator' => Operators::IN,
            ],
        ];
    }

    public function drawBackOfficeInputs(): string
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/cart-contains-products-condition.html',
            [
                'operatorSelectHtml' => $this->drawBackOfficeInputOperators(self::PRODUCTS_LIST),
                'products_field_name' => self::PRODUCTS_LIST,
                'values' => $this->values[self::PRODUCTS_LIST] ?? [],
            ],
        );
    }
}
