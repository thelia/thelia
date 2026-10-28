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
use Thelia\Domain\Catalog\Quantity\InvalidQuantityRuleException;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\UnitCode;
use Thelia\Model\ProductSaleElements;

final class QuantityRuleTest extends TestCase
{
    public function testTheDefaultsSellByThePieceFromOne(): void
    {
        $rule = new QuantityRule();

        self::assertSame(1.0, $rule->minQuantity);
        self::assertSame(1.0, $rule->step);
        self::assertSame(0, $rule->decimals);
        self::assertFalse($rule->isSoldByMeasure());
        self::assertFalse($rule->hasReferencePrice());
        self::assertEqualsWithDelta(1.0, $rule->smallestQuantity(), 0.0000001);
    }

    /**
     * @return iterable<string, array{QuantityRule, float}>
     */
    public static function smallestQuantities(): iterable
    {
        yield 'a minimum that is a multiple of the step' => [new QuantityRule(minQuantity: 12, step: 6), 12.0];
        yield 'a minimum below the step' => [new QuantityRule(minQuantity: 2, step: 6), 6.0];
        yield 'a minimum between two multiples' => [new QuantityRule(minQuantity: 7, step: 6), 12.0];
        yield 'quarters from one' => [new QuantityRule(minQuantity: 1, step: 0.25, decimals: 2), 1.0];
        yield 'quarters from a third of a quarter' => [new QuantityRule(minQuantity: 0.1, step: 0.25, decimals: 2), 0.25];
        yield 'grams from half a kilogram' => [new QuantityRule(minQuantity: 0.5, step: 0.001, decimals: 3), 0.5];
    }

    #[DataProvider('smallestQuantities')]
    public function testTheSmallestQuantityIsTheFirstMultipleOfTheStepNotBelowTheMinimum(QuantityRule $rule, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $rule->smallestQuantity(), 0.0000001);
    }

    public function testDecimalsSellByMeasure(): void
    {
        self::assertTrue((new QuantityRule(step: 0.25, decimals: 2))->isSoldByMeasure());
    }

    public function testTheReferencePriceNeedsTheContentTheReferenceAndTheUnit(): void
    {
        self::assertTrue((new QuantityRule(unitQuantity: 0.75, referenceUnitQuantity: 1, unitCode: UnitCode::LITRE))->hasReferencePrice());
        self::assertFalse((new QuantityRule(unitQuantity: 0.75, referenceUnitQuantity: 1))->hasReferencePrice());
        self::assertFalse((new QuantityRule(referenceUnitQuantity: 1, unitCode: UnitCode::LITRE))->hasReferencePrice());
        self::assertFalse((new QuantityRule(unitQuantity: 0.75, unitCode: UnitCode::LITRE))->hasReferencePrice());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function rulesThatCannotDecideAQuantity(): iterable
    {
        yield 'a step of zero' => [['step' => 0.0]];
        yield 'a negative step' => [['step' => -1.0]];
        yield 'a minimum of zero' => [['minQuantity' => 0.0]];
        yield 'a negative minimum' => [['minQuantity' => -2.0]];
        yield 'negative decimals' => [['decimals' => -1]];
        yield 'four decimals' => [['decimals' => 4]];
        yield 'a step finer than the decimals' => [['step' => 0.25, 'decimals' => 1]];
        yield 'a decimal step sold by the piece' => [['step' => 0.5]];
        yield 'a minimum finer than the decimals' => [['minQuantity' => 1.5]];
        yield 'a unit quantity of zero' => [['unitQuantity' => 0.0]];
        yield 'a negative reference unit quantity' => [['referenceUnitQuantity' => -1.0]];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('rulesThatCannotDecideAQuantity')]
    public function testARuleThatCannotDecideAQuantityIsRefused(array $arguments): void
    {
        $this->expectException(InvalidQuantityRuleException::class);

        new QuantityRule(...$arguments);
    }

    public function testThreeDecimalsAndAStepOfOneThousandthAreAccepted(): void
    {
        $rule = new QuantityRule(minQuantity: 0.001, step: 0.001, decimals: 3);

        self::assertEqualsWithDelta(0.001, $rule->smallestQuantity(), 0.0000001);
    }

    public function testTheRuleIsReadFromTheDecimalStringsOfTheCombination(): void
    {
        $combination = (new ProductSaleElements())
            ->setMinQuantity('2.000000')
            ->setQuantityStep('0.250000')
            ->setQuantityDecimals(2)
            ->setUnitQuantity('0.750000')
            ->setReferenceUnitQuantity('1.000000')
            ->setUnitCode('l');

        $rule = QuantityRule::fromProductSaleElements($combination);

        self::assertSame(2.0, $rule->minQuantity);
        self::assertSame(0.25, $rule->step);
        self::assertSame(2, $rule->decimals);
        self::assertSame(0.75, $rule->unitQuantity);
        self::assertSame(1.0, $rule->referenceUnitQuantity);
        self::assertSame(UnitCode::LITRE, $rule->unitCode);
    }

    public function testANewCombinationReadsAsTheDefaultRule(): void
    {
        self::assertEquals(new QuantityRule(), QuantityRule::fromProductSaleElements(new ProductSaleElements()));
    }

    public function testAnUnknownUnitCodeReadsAsNoUnit(): void
    {
        $rule = QuantityRule::fromProductSaleElements((new ProductSaleElements())->setUnitCode('furlong'));

        self::assertNull($rule->unitCode);
    }

    public function testTheRuleIsWrittenOnTheCombinationAsDecimalStrings(): void
    {
        $combination = new ProductSaleElements();

        (new QuantityRule(minQuantity: 2, step: 6, unitQuantity: 0.75, referenceUnitQuantity: 1, unitCode: UnitCode::LITRE))->applyTo($combination);

        self::assertSame('2.000000', $combination->getMinQuantity());
        self::assertSame('6.000000', $combination->getQuantityStep());
        self::assertSame(0, $combination->getQuantityDecimals());
        self::assertSame('0.750000', $combination->getUnitQuantity());
        self::assertSame('1.000000', $combination->getReferenceUnitQuantity());
        self::assertSame('l', $combination->getUnitCode());

        (new QuantityRule())->applyTo($combination);

        self::assertNull($combination->getUnitQuantity());
        self::assertNull($combination->getReferenceUnitQuantity());
        self::assertNull($combination->getUnitCode());
    }
}
