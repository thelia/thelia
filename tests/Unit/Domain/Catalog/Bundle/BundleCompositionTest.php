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

namespace Thelia\Tests\Unit\Domain\Catalog\Bundle;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;

final class BundleCompositionTest extends TestCase
{
    public function testTheCanonicalJsonIsSortedAndTheQuantitiesAreNormalised(): void
    {
        $composition = BundleComposition::fromArray([
            ['bundleItemId' => 13, 'quantity' => '2.000000'],
            ['bundleItemId' => 12, 'quantity' => 1],
        ]);

        self::assertSame('[{"bundleItemId":12,"quantity":"1"},{"bundleItemId":13,"quantity":"2"}]', $composition->toCanonicalJson());
    }

    public function testTwoSpellingsOfOneChoiceAreOneValue(): void
    {
        $first = BundleComposition::fromArray([['bundleItemId' => 12, 'quantity' => '1'], ['bundleItemId' => 13, 'quantity' => '2.0'], ['bundleItemId' => 14, 'quantity' => '0']]);
        $second = BundleComposition::fromJson('[{"bundleItemId":13,"quantity":"2"},{"bundleItemId":"12","quantity":1.0}]');

        self::assertSame($first->toCanonicalJson(), $second->toCanonicalJson());
    }

    public function testAnItemOutOfTheCompositionHasAZeroQuantity(): void
    {
        $composition = BundleComposition::fromArray([['bundleItemId' => 12, 'quantity' => '0.5']]);

        self::assertSame('0.5', $composition->quantityOf(12));
        self::assertSame('0', $composition->quantityOf(99));
        self::assertTrue(BundleComposition::fromArray([])->isEmpty());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedJson(): iterable
    {
        yield 'not json' => ['{'];
        yield 'object instead of a list' => ['{"bundleItemId":1,"quantity":"1"}'];
        yield 'missing quantity' => ['[{"bundleItemId":1}]'];
        yield 'negative quantity' => ['[{"bundleItemId":1,"quantity":"-1"}]'];
        yield 'item twice' => ['[{"bundleItemId":1,"quantity":"1"},{"bundleItemId":1,"quantity":"2"}]'];
        yield 'item id zero' => ['[{"bundleItemId":0,"quantity":"1"}]'];
        yield 'a price smuggled in is no quantity' => ['[{"bundleItemId":1,"quantity":{"price":"0"}}]'];
        yield 'a quantity above the bound, as a string' => ['[{"bundleItemId":1,"quantity":"10000000000000"}]'];
        yield 'a quantity above the bound, as a JSON integer' => ['[{"bundleItemId":1,"quantity":10000000000000}]'];
        yield 'a quantity of the largest integer' => ['[{"bundleItemId":1,"quantity":9223372036854775807}]'];
        yield 'a quantity beyond the integers, a float' => ['[{"bundleItemId":1,"quantity":1e30}]'];
        yield 'a quantity that is a float overflow' => ['[{"bundleItemId":1,"quantity":1e999}]'];
        yield 'a quantity just above the per item bound' => ['[{"bundleItemId":1,"quantity":"1000000.000001"}]'];
        yield 'a number of a hundred digits' => ['[{"bundleItemId":1,"quantity":"99999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999999"}]'];
        yield 'an item id that does not fit an integer' => ['[{"bundleItemId":"99999999999999999999","quantity":"1"}]'];
        yield 'an item id that is a boolean' => ['[{"bundleItemId":true,"quantity":"1"}]'];
        yield 'a composition too long to be one' => ['['.str_repeat('{"bundleItemId":1,"quantity":"1"},', 3000).'{"bundleItemId":2,"quantity":"1"}]'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedJson')]
    public function testAMalformedCompositionIsRefused(string $json): void
    {
        $this->expectException(InvalidBundleCompositionException::class);

        BundleComposition::fromJson($json);
    }

    public function testTheBoundIsAcceptedAndTheLinesOfAnArrayAreBoundedToo(): void
    {
        self::assertSame('1000000', BundleComposition::fromArray([['bundleItemId' => 1, 'quantity' => 1_000_000]])->quantityOf(1));

        $this->expectException(InvalidBundleCompositionException::class);

        BundleComposition::fromArray(array_map(static fn (int $id): array => ['bundleItemId' => $id, 'quantity' => 1], range(1, BundleComposition::MAX_LINES + 1)));
    }

    public function testAnArrayQuantityBeyondTheIntegersIsARefusalNotAnError(): void
    {
        $this->expectException(InvalidBundleCompositionException::class);

        BundleComposition::fromArray([['bundleItemId' => 1, 'quantity' => 1.0e13]]);
    }

    public function testExtraKeysOfALineAreIgnoredSoNoPriceCanBeCarried(): void
    {
        $composition = BundleComposition::fromJson('[{"bundleItemId":1,"quantity":"1","price":"0.01"}]');

        self::assertSame('[{"bundleItemId":1,"quantity":"1"}]', $composition->toCanonicalJson());
    }
}
