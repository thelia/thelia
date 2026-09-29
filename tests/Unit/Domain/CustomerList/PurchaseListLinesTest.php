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

namespace Thelia\Tests\Unit\Domain\CustomerList;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\CustomerList\DTO\PurchaseListLines;
use Thelia\Domain\CustomerList\Exception\InvalidPurchaseListException;

final class PurchaseListLinesTest extends TestCase
{
    public function testAReferencePastedFromASpreadsheetLosesItsInvisibleCharacters(): void
    {
        $lines = new PurchaseListLines([new ReferenceQuantity(" \u{FEFF}\u{200B}ABC-1\u{00A0}\t", 2)]);

        self::assertSame('ABC-1', $lines->all()[0]->reference);
    }

    public function testTheSameReferenceTwiceIsKeptOnceWithTheQuantitiesAddedUp(): void
    {
        $lines = new PurchaseListLines([
            new ReferenceQuantity('ABC-1', 2),
            new ReferenceQuantity('XYZ-9', 1),
            new ReferenceQuantity(' ABC-1 ', 3),
        ]);

        self::assertCount(2, $lines);
        self::assertSame(['ABC-1', 'XYZ-9'], array_map(static fn (ReferenceQuantity $line): string => $line->reference, $lines->all()));
        self::assertSame(5, $lines->all()[0]->quantity);
    }

    public function testOneReferenceSettledOnTwoSaleElementsStaysTwoLines(): void
    {
        $lines = new PurchaseListLines([
            new ReferenceQuantity('SHARED', 1, 10),
            new ReferenceQuantity('SHARED', 1, 11),
        ]);

        self::assertCount(2, $lines);
    }

    public function testMergingAddsTheNewLinesAfterTheCurrentOnes(): void
    {
        $current = new PurchaseListLines([new ReferenceQuantity('A', 1), new ReferenceQuantity('B', 1)]);
        $merged = $current->merge(new PurchaseListLines([new ReferenceQuantity('C', 4), new ReferenceQuantity('A', 2)]));

        self::assertSame(['A', 'B', 'C'], array_map(static fn (ReferenceQuantity $line): string => $line->reference, $merged->all()));
        self::assertSame(3, $merged->all()[0]->quantity);
    }

    public function testFiveHundredLinesFit(): void
    {
        self::assertCount(PurchaseListLines::MAX_LINES, new PurchaseListLines(self::distinctLines(PurchaseListLines::MAX_LINES)));
    }

    public function testFiveHundredAndOneLinesAreRefused(): void
    {
        $this->expectException(InvalidPurchaseListException::class);

        new PurchaseListLines(self::distinctLines(PurchaseListLines::MAX_LINES + 1));
    }

    /**
     * @return iterable<string, array{ReferenceQuantity}>
     */
    public static function invalidLines(): iterable
    {
        yield 'empty reference' => [new ReferenceQuantity("  \u{200B} ", 1)];
        yield 'zero quantity' => [new ReferenceQuantity('ABC', 0)];
        yield 'negative quantity' => [new ReferenceQuantity('ABC', -3)];
        yield 'reference too long' => [new ReferenceQuantity(str_repeat('a', PurchaseListLines::MAX_REFERENCE_LENGTH + 1), 1)];
    }

    #[DataProvider('invalidLines')]
    public function testALineTheListCannotHoldIsRefused(ReferenceQuantity $line): void
    {
        $this->expectException(InvalidPurchaseListException::class);

        new PurchaseListLines([$line]);
    }

    /**
     * @return list<ReferenceQuantity>
     */
    private static function distinctLines(int $count): array
    {
        return array_map(static fn (int $n): ReferenceQuantity => new ReferenceQuantity('REF-'.$n, 1), range(1, $count));
    }
}
