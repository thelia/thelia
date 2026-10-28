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

namespace Thelia\Tests\Unit\Domain\Catalog\Quantity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Quantity\NotPositiveQuantityException;
use Thelia\Domain\Catalog\Quantity\QuantityAdjustment;
use Thelia\Domain\Catalog\Quantity\QuantityPolicy;
use Thelia\Domain\Catalog\Quantity\QuantityRefusedException;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\UnreadableQuantityException;
use Thelia\Model\ProductSaleElements;

final class QuantityPolicyTest extends TestCase
{
    private QuantityPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new QuantityPolicy();
    }

    /**
     * @return iterable<string, array{QuantityRule, float, float}>
     */
    public static function acceptedQuantities(): iterable
    {
        yield 'twelve sold by six from two' => [self::bySix(), 12.0, 12.0];
        yield 'six, the smallest sold by six from two' => [self::bySix(), 6.0, 6.0];
        yield 'one and a quarter by quarters' => [self::byQuarters(), 1.25, 1.25];
        yield 'one, the minimum by quarters' => [self::byQuarters(), 1.0, 1.0];
        yield 'three by the piece' => [new QuantityRule(), 3.0, 3.0];
        yield 'one by the piece' => [new QuantityRule(), 1.0, 1.0];
        yield 'a tenth read from a float that is not one' => [new QuantityRule(minQuantity: 0.1, step: 0.1, decimals: 1), 0.1 + 0.2, 0.3];
        yield 'grams of a kilogram' => [new QuantityRule(minQuantity: 0.001, step: 0.001, decimals: 3), 1.234, 1.234];
        yield 'a large stock-like quantity' => [new QuantityRule(), 999999.0, 999999.0];
    }

    #[DataProvider('acceptedQuantities')]
    public function testAQuantityTheRulesAcceptIsAccepted(QuantityRule $rule, float $requested, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $this->policy->assertAcceptable($requested, $rule), 0.0000001);

        $decision = $this->policy->normalize($requested, $rule);
        self::assertFalse($decision->isAdjusted());
        self::assertFalse($decision->removesTheLine());
        self::assertSame('', $decision->message());
        self::assertSame('', $decision->refusalMessage());
    }

    /**
     * @return iterable<string, array{QuantityRule, float, QuantityAdjustment, string}>
     */
    public static function refusedQuantities(): iterable
    {
        yield 'seven sold by six' => [self::bySix(), 7.0, QuantityAdjustment::OffStep, 'Sold by 6: 6, 12, 18…'];
        yield 'thirteen sold by six' => [self::bySix(), 13.0, QuantityAdjustment::OffStep, 'Sold by 6: 6, 12, 18…'];
        yield 'one sold by six from two' => [self::bySix(), 1.0, QuantityAdjustment::BelowMinimum, 'The smallest quantity you can order is 6.'];
        yield 'two, the minimum but not a multiple' => [self::bySix(), 2.0, QuantityAdjustment::BelowMinimum, 'The smallest quantity you can order is 6.'];
        yield 'off the quarters' => [self::byQuarters(), 1.3, QuantityAdjustment::OffStep, 'Sold by 0.25: 1.00; 1.25; 1.50…'];
        yield 'a thousandth sold by hundredths' => [self::byQuarters(), 1.255, QuantityAdjustment::TooManyDecimals, 'The quantity cannot carry more than 2 decimal places.'];
        yield 'below the minimum by quarters' => [self::byQuarters(), 0.75, QuantityAdjustment::BelowMinimum, 'The smallest quantity you can order is 1.00.'];
        yield 'a half sold by the piece' => [new QuantityRule(), 1.5, QuantityAdjustment::TooManyDecimals, 'The quantity must be a whole number.'];
        yield 'a half below one by the piece' => [new QuantityRule(), 0.5, QuantityAdjustment::BelowMinimum, 'The smallest quantity you can order is 1.'];
        yield 'a minimum between two multiples' => [new QuantityRule(minQuantity: 7, step: 6), 11.0, QuantityAdjustment::BelowMinimum, 'The smallest quantity you can order is 12.'];
    }

    #[DataProvider('refusedQuantities')]
    public function testAQuantityTheRulesDoNotAcceptIsRefusedWithTheValidOnes(QuantityRule $rule, float $requested, QuantityAdjustment $adjustment, string $message): void
    {
        try {
            $this->policy->assertAcceptable($requested, $rule);
            self::fail('The quantity must be refused.');
        } catch (QuantityRefusedException $refusal) {
            self::assertSame($message, $refusal->getMessage());
            self::assertSame($adjustment, $refusal->decision->adjustment);
            self::assertSame($requested, $refusal->decision->requested);
            self::assertSame($rule, $refusal->decision->rule);
        }
    }

    /**
     * @return iterable<string, array{QuantityRule, float, ?float, QuantityAdjustment, string}>
     */
    public static function adjustedQuantities(): iterable
    {
        yield 'seven sold by six' => [self::bySix(), 7.0, 6.0, QuantityAdjustment::OffStep, 'The quantity was brought down to 6: sold by 6.'];
        yield 'seventeen sold by six' => [self::bySix(), 17.0, 12.0, QuantityAdjustment::OffStep, 'The quantity was brought down to 12: sold by 6.'];
        yield 'one sold by six from two' => [self::bySix(), 1.0, null, QuantityAdjustment::BelowMinimum, 'This line was removed: the smallest quantity you can order is 6.'];
        yield 'five sold by six from two' => [self::bySix(), 5.0, null, QuantityAdjustment::BelowMinimum, 'This line was removed: the smallest quantity you can order is 6.'];
        yield 'off the quarters' => [self::byQuarters(), 1.3, 1.25, QuantityAdjustment::OffStep, 'The quantity was brought down to 1.25: sold by 0.25.'];
        yield 'a thousandth sold by hundredths' => [self::byQuarters(), 1.255, 1.25, QuantityAdjustment::TooManyDecimals, 'The quantity was brought down to 1.25.'];
        yield 'thousandths off the quarters' => [self::byQuarters(), 1.499, 1.25, QuantityAdjustment::TooManyDecimals, 'The quantity was brought down to 1.25.'];
        yield 'a half sold by the piece' => [new QuantityRule(), 2.5, 2.0, QuantityAdjustment::TooManyDecimals, 'The quantity was brought down to 2.'];
        yield 'below the minimum by quarters' => [self::byQuarters(), 0.9, null, QuantityAdjustment::BelowMinimum, 'This line was removed: the smallest quantity you can order is 1.00.'];
    }

    #[DataProvider('adjustedQuantities')]
    public function testALineReadBackIsBroughtDownToTheValidMultipleBelowIt(QuantityRule $rule, float $requested, ?float $expected, QuantityAdjustment $adjustment, string $message): void
    {
        $decision = $this->policy->normalize($requested, $rule);

        self::assertTrue($decision->isAdjusted());
        self::assertSame($adjustment, $decision->adjustment);
        self::assertSame($requested, $decision->requested);
        self::assertSame(null === $expected, $decision->removesTheLine());
        if (null === $expected) {
            self::assertNull($decision->quantity);
        } else {
            self::assertEqualsWithDelta($expected, $decision->quantity, 0.0000001);
        }
        self::assertSame($message, $decision->message());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function quantitiesThatAreNotPositive(): iterable
    {
        yield 'zero' => [0.0];
        yield 'minus one' => [-1.0];
        yield 'minus a quarter' => [-0.25];
        yield 'less than the finest quantity stored' => [0.0000001];
        yield 'minus infinity' => [-\INF];
    }

    #[DataProvider('quantitiesThatAreNotPositive')]
    public function testAQuantityThatIsNotPositiveIsRefusedInBothModes(float $requested): void
    {
        try {
            $this->policy->assertAcceptable($requested, self::byQuarters());
            self::fail('The strict mode must refuse it.');
        } catch (NotPositiveQuantityException $exception) {
            self::assertSame('The quantity must be greater than zero.', $exception->getMessage());
        }

        $this->expectException(NotPositiveQuantityException::class);
        $this->policy->normalize($requested, self::byQuarters());
    }

    public function testAQuantityThatIsNotANumberOrBeyondAnyStockIsUnreadable(): void
    {
        foreach ([\NAN, \INF, 2_000_000_000.0] as $requested) {
            try {
                $this->policy->normalize($requested, new QuantityRule());
                self::fail('The quantity must be unreadable.');
            } catch (UnreadableQuantityException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheRulesAreReadFromTheCombination(): void
    {
        $combination = (new ProductSaleElements())->setMinQuantity('2.000000')->setQuantityStep('6.000000')->setQuantityDecimals(0);

        self::assertSame(12.0, $this->policy->assertAcceptable(12, $combination));
        self::assertEqualsWithDelta(6.0, $this->policy->normalize(7, $combination)->quantity, 0.0000001);
        $this->expectException(QuantityRefusedException::class);
        $this->policy->assertAcceptable(7, $combination);
    }

    /**
     * @return iterable<string, array{string, QuantityRule, float}>
     */
    public static function readableInputs(): iterable
    {
        yield 'a decimal point' => ['1.25', self::byQuarters(), 1.25];
        yield 'a decimal comma' => ['1,25', self::byQuarters(), 1.25];
        yield 'a whole number' => ['12', self::bySix(), 12.0];
        yield 'spaces around' => [" 6\u{00A0}", self::bySix(), 6.0];
        yield 'no leading digit' => [',5', self::byQuarters(), 0.5];
        yield 'a negative number, refused later as not positive' => ['-1', self::bySix(), -1.0];
        yield 'three decimals after a zero' => ['0,125', self::byQuarters(), 0.125];
        yield 'three decimals sold by the gram' => ['1,250', new QuantityRule(minQuantity: 0.001, step: 0.001, decimals: 3), 1.25];
        yield 'four decimals' => ['1.2345', self::byQuarters(), 1.2345];
    }

    #[DataProvider('readableInputs')]
    public function testATypedQuantityIsReadWithAPointOrAComma(string $input, QuantityRule $rule, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $this->policy->parse($input, $rule), 0.0000001);
    }

    /**
     * @return iterable<string, array{string, QuantityRule}>
     */
    public static function unreadableInputs(): iterable
    {
        yield 'a word' => ['six', self::bySix()];
        yield 'empty' => ['', self::bySix()];
        yield 'a separator alone' => [',', self::byQuarters()];
        yield 'a trailing separator' => ['1,', self::byQuarters()];
        yield 'two separators' => ['1.250,5', self::byQuarters()];
        yield 'a thousands separator or three decimals' => ['1.250', self::byQuarters()];
        yield 'a thousands comma or three decimals' => ['1,250', self::bySix()];
        yield 'a unit' => ['2 kg', self::byQuarters()];
        yield 'an exponent' => ['1e3', self::bySix()];
    }

    #[DataProvider('unreadableInputs')]
    public function testATypedQuantityThatCouldBeReadTwoWaysIsRefused(string $input, QuantityRule $rule): void
    {
        try {
            $this->policy->parse($input, $rule);
            self::fail('The input must be refused.');
        } catch (UnreadableQuantityException $exception) {
            self::assertSame($input, $exception->input);
            self::assertStringContainsString('"'.$input.'"', $exception->getMessage());
        }
    }

    private static function bySix(): QuantityRule
    {
        return new QuantityRule(minQuantity: 2, step: 6);
    }

    private static function byQuarters(): QuantityRule
    {
        return new QuantityRule(minQuantity: 1, step: 0.25, decimals: 2);
    }
}
