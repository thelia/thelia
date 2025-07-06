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
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Condition AvailableForTotalAmount
 * Check if a Checkout total amount match criteria.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 */
class MatchForTotalAmount extends ConditionAbstract
{
    /** Condition 1st parameter : price */
    public const CART_TOTAL = 'price';

    /** Condition 1st parameter : currency */
    public const CART_CURRENCY = 'currency';

    public function __construct(FacadeInterface $facade)
    {
        // Define the allowed comparison operators
        $this->availableOperators = [
            self::CART_TOTAL => [
                Operators::INFERIOR,
                Operators::INFERIOR_OR_EQUAL,
                Operators::EQUAL,
                Operators::SUPERIOR_OR_EQUAL,
                Operators::SUPERIOR,
            ],
            self::CART_CURRENCY => [
                Operators::EQUAL,
            ],
        ];

        parent::__construct($facade);
    }

    public function getServiceId(): string
    {
        return 'thelia.condition.match_for_total_amount';
    }

    public function setValidatorsFromForm(array $operators, array $values): static
    {
        $this
            ->checkComparisonOperatorValue($operators, self::CART_TOTAL)
            ->checkComparisonOperatorValue($operators, self::CART_CURRENCY);

        $this->isPriceValid($values[self::CART_TOTAL]);

        $this->isCurrencyValid($values[self::CART_CURRENCY]);

        $this->operators = [
            self::CART_TOTAL => $operators[self::CART_TOTAL],
            self::CART_CURRENCY => $operators[self::CART_CURRENCY],
        ];
        $this->values = [
            self::CART_TOTAL => $values[self::CART_TOTAL],
            self::CART_CURRENCY => $values[self::CART_CURRENCY],
        ];

        return $this;
    }

    /**
     * Test if Customer meets conditions.
     */
    public function isMatching(): bool
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

    public function getName(): string
    {
        return $this->translator->trans(
            'Cart total amount',
            []
        );
    }

    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'Check the total Cart amount in the given currency',
            []
        );

        return $toolTip;
    }

    public function getSummary()
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::CART_TOTAL]
        );

        $toolTip = $this->translator->trans(
            'If cart total amount is <strong>%operator%</strong> %amount% %currency%',
            [
                '%operator%' => $i18nOperator,
                '%amount%' => $this->values[self::CART_TOTAL],
                '%currency%' => $this->values[self::CART_CURRENCY],
            ]
        );

        return $toolTip;
    }

    protected function generateInputs(): array
    {
        $currencies = CurrencyQuery::create()->filterByVisible(true)->find();

        $cleanedCurrencies = [];

        /** @var Currency $currency */
        foreach ($currencies as $currency) {
            $cleanedCurrencies[$currency->getCode()] = $currency->getSymbol();
        }

        return [
            self::CART_TOTAL => [
                'availableOperators' => $this->availableOperators[self::CART_TOTAL],
                'availableValues' => '',
                'value' => '',
                'selectedOperator' => '',
            ],
            self::CART_CURRENCY => [
                'availableOperators' => $this->availableOperators[self::CART_CURRENCY],
                'availableValues' => $cleanedCurrencies,
                'value' => '',
                'selectedOperator' => Operators::EQUAL,
            ],
        ];
    }

    public function drawBackOfficeInputs()
    {
        $labelPrice = $this->facade
            ->getTranslator()
            ->trans('Cart total amount is', []);

        $html = $this->drawBackOfficeBaseInputsText($labelPrice, self::CART_TOTAL);

        return $html;
    }

    protected function drawBackOfficeBaseInputsText($label, $inputKey)
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/cart-total-amount-condition.html',
            [
                'label' => $label,
                'inputKey' => $inputKey,
                'value' => $this->values[$inputKey] ?? '',
                'field_1_name' => self::CART_TOTAL,
                'field_2_name' => self::CART_CURRENCY,
                'operatorSelectHtml' => $this->drawBackOfficeInputOperators(self::CART_TOTAL),
                'currencySelectHtml' => $this->drawBackOfficeCurrencyInput(self::CART_CURRENCY),
            ]
        );
    }
}
