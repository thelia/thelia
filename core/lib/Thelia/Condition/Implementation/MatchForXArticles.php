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

use Thelia\Condition\Operators;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionValueException;

/**
 * Check a Checkout against its Product number.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 */
class MatchForXArticles extends ConditionAbstract
{
    /** Condition 1st parameter : quantity */
    public const CART_QUANTITY = 'quantity';

    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = [
            self::CART_QUANTITY => [
                Operators::INFERIOR,
                Operators::INFERIOR_OR_EQUAL,
                Operators::EQUAL,
                Operators::SUPERIOR_OR_EQUAL,
                Operators::SUPERIOR,
            ],
        ];

        parent::__construct($facade);
    }

    public function getServiceId(): string
    {
        return 'thelia.condition.match_for_x_articles';
    }

    public function setValidatorsFromForm(array $operators, array $values): static
    {
        $this->checkComparisonOperatorValue($operators, self::CART_QUANTITY);

        if ((int) $values[self::CART_QUANTITY] <= 0) {
            throw new InvalidConditionValueException(
                self::class,
                'quantity'
            );
        }

        $this->operators = [
            self::CART_QUANTITY => $operators[self::CART_QUANTITY],
        ];

        $this->values = [
            self::CART_QUANTITY => $values[self::CART_QUANTITY],
        ];

        return $this;
    }

    public function isMatching()
    {
        return $this->conditionValidator->variableOpComparison(
            $this->facade->getNbArticlesInCart(),
            $this->operators[self::CART_QUANTITY],
            $this->values[self::CART_QUANTITY]
        );
    }

    public function getName(): string
    {
        return $this->translator->trans(
            'Cart item count',
            []
        );
    }

    public function getToolTip(): string
    {
        return $this->translator->trans(
            'The cart item count should match the condition',
            []
        );
    }

    public function getSummary(): string
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::CART_QUANTITY]
        );

        return $this->translator->trans(
            'If cart item count is <strong>%operator%</strong> %quantity%',
            [
                '%operator%' => $i18nOperator,
                '%quantity%' => $this->values[self::CART_QUANTITY],
            ]
        );
    }

    protected function generateInputs(): array
    {
        return [
            self::CART_QUANTITY => [
                'availableOperators' => $this->availableOperators[self::CART_QUANTITY],
                'value' => '',
                'selectedOperator' => '',
            ],
        ];
    }

    public function drawBackOfficeInputs()
    {
        $labelQuantity = $this->facade
            ->getTranslator()
            ->trans('Cart item count is');

        return $this->drawBackOfficeBaseInputsText($labelQuantity, self::CART_QUANTITY);
    }

    protected function drawBackOfficeBaseInputsText($label, $inputKey)
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/cart-item-count-condition.html',
            [
                'label' => $label,
                'operatorSelectHtml' => $this->drawBackOfficeInputOperators($inputKey),
                'quantitySelectHtml' => $this->drawBackOfficeInputQuantityValues($inputKey, 20, 1),
            ]
        );
    }
}
