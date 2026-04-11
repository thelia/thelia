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

namespace Thelia\Tests\Unit\Condition;

use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\Currency;

/**
 * Unit coverage is limited to the `isMatching()` path because both
 * InvalidConditionValueException and InvalidConditionOperatorException
 * construct a Tlog instance in their constructor, which in turn reaches
 * into ConfigQuery (Propel). Those failure paths are exercised in the
 * integration layer where the database is available.
 */
final class MatchForTotalAmountTest extends FacadeBackedTestCase
{
    public function testServiceIdIsStable(): void
    {
        $condition = new MatchForTotalAmount($this->facadeWithEUR());

        self::assertSame('thelia.condition.match_for_total_amount', $condition->getServiceId());
    }

    public function testIsMatchingReturnsTrueWhenAmountAndCurrencyMatch(): void
    {
        $facade = $this->facadeWithEUR();
        $facade->method('getCartTotalTaxPrice')->willReturn(120.0);
        $facade->method('getCheckoutCurrency')->willReturn('EUR');

        $condition = $this->buildCondition($facade, Operators::SUPERIOR_OR_EQUAL, 100, 'EUR');

        self::assertTrue($condition->isMatching());
    }

    public function testIsMatchingReturnsFalseWhenAmountIsBelowThreshold(): void
    {
        $facade = $this->facadeWithEUR();
        $facade->method('getCartTotalTaxPrice')->willReturn(80.0);
        $facade->method('getCheckoutCurrency')->willReturn('EUR');

        $condition = $this->buildCondition($facade, Operators::SUPERIOR_OR_EQUAL, 100, 'EUR');

        self::assertFalse($condition->isMatching());
    }

    public function testIsMatchingReturnsFalseWhenCurrencyDiffers(): void
    {
        $facade = $this->facadeWithEUR();
        $facade->method('getCartTotalTaxPrice')->willReturn(500.0);
        $facade->method('getCheckoutCurrency')->willReturn('USD');

        $condition = $this->buildCondition($facade, Operators::SUPERIOR_OR_EQUAL, 100, 'EUR');

        self::assertFalse($condition->isMatching());
    }

    private function buildCondition(
        FacadeInterface $facade,
        string $priceOperator,
        float $price,
        string $currency,
    ): MatchForTotalAmount {
        $condition = new MatchForTotalAmount($facade);
        $condition->setValidatorsFromForm(
            [
                MatchForTotalAmount::CART_TOTAL => $priceOperator,
                MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => $price,
                MatchForTotalAmount::CART_CURRENCY => $currency,
            ],
        );

        return $condition;
    }

    private function facadeWithEUR(): FacadeInterface
    {
        $eur = $this->createMock(Currency::class);
        $eur->method('getCode')->willReturn('EUR');

        $facade = parent::makeFacade();
        $facade->method('getAvailableCurrencies')->willReturn([$eur]);

        return $facade;
    }
}
