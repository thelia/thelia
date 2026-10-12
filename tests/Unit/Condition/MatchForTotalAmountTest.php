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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Condition\Exception\InvalidConditionOperatorException;
use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\Currency;
use Thelia\Tests\Support\Log\SilencedTlog;

/**
 * A cart total condition compares the cart total with a strictly positive amount in
 * a currency the shop sells. The exceptions rejecting a bad form log an error, so
 * the logger reads a configuration sending it nowhere instead of the database.
 */
final class MatchForTotalAmountTest extends FacadeBackedTestCase
{
    use SilencedTlog;

    protected function setUp(): void
    {
        $this->silenceTlog();
    }

    protected function tearDown(): void
    {
        $this->restoreTlog();
    }

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

    public function testAcceptsStringPriceComingFromTheForm(): void
    {
        $facade = $this->facadeWithEUR();
        $facade->method('getCartTotalTaxPrice')->willReturn(120.0);
        $facade->method('getCheckoutCurrency')->willReturn('EUR');

        // Back-office form values reach the condition as strings.
        $condition = new MatchForTotalAmount($facade);
        $condition->setValidatorsFromForm(
            [
                MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR_OR_EQUAL,
                MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => '100',
                MatchForTotalAmount::CART_CURRENCY => 'EUR',
            ],
        );

        self::assertTrue($condition->isMatching());
    }

    public function testACartBelowTheAmountMatchesTheLessThanOperator(): void
    {
        $facade = $this->facadeWithEUR();
        $facade->method('getCartTotalTaxPrice')->willReturn(49.99);
        $facade->method('getCheckoutCurrency')->willReturn('EUR');

        $condition = $this->buildCondition($facade, Operators::INFERIOR, 50, 'EUR');

        self::assertTrue($condition->isMatching());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function operatorsThatCompareNothing(): iterable
    {
        yield 'a list operator on the amount' => [Operators::IN, Operators::EQUAL];
        yield 'an ordering operator on the currency' => [Operators::SUPERIOR, Operators::SUPERIOR];
    }

    #[DataProvider('operatorsThatCompareNothing')]
    public function testAnOperatorTheConditionDoesNotOfferIsRejected(string $priceOperator, string $currencyOperator): void
    {
        $this->expectException(InvalidConditionOperatorException::class);
        $this->expectExceptionMessage('Invalid Operator for Condition '.MatchForTotalAmount::class.' on parameter ');

        (new MatchForTotalAmount($this->facadeWithEUR()))->setValidatorsFromForm(
            [
                MatchForTotalAmount::CART_TOTAL => $priceOperator,
                MatchForTotalAmount::CART_CURRENCY => $currencyOperator,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => '100',
                MatchForTotalAmount::CART_CURRENCY => 'EUR',
            ],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function amountsThatAreNotAThreshold(): iterable
    {
        yield 'zero' => ['0'];
        yield 'a negative amount' => ['-10'];
    }

    #[DataProvider('amountsThatAreNotAThreshold')]
    public function testAnAmountThatIsNotStrictlyPositiveIsRejected(string $amount): void
    {
        $this->expectException(InvalidConditionValueException::class);
        $this->expectExceptionMessage('Invalid Parameter for Condition '.MatchForTotalAmount::class.' on parameter price');

        (new MatchForTotalAmount($this->facadeWithEUR()))->setValidatorsFromForm(
            [
                MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR_OR_EQUAL,
                MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => $amount,
                MatchForTotalAmount::CART_CURRENCY => 'EUR',
            ],
        );
    }

    public function testACurrencyTheShopDoesNotSellIsRejected(): void
    {
        $this->expectException(InvalidConditionValueException::class);
        $this->expectExceptionMessage('Invalid Parameter for Condition '.MatchForTotalAmount::class.' on parameter currency');

        $this->buildCondition($this->facadeWithEUR(), Operators::SUPERIOR_OR_EQUAL, 100, 'USD');
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
