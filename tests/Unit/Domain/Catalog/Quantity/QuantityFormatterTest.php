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
use Thelia\Domain\Catalog\Quantity\QuantityFormatter;
use Thelia\Domain\Catalog\Quantity\UnitCode;

final class QuantityFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{float, int, ?UnitCode, ?string, string}>
     */
    public static function quantities(): iterable
    {
        yield 'kilograms in French' => [1.25, 2, UnitCode::KILOGRAM, 'fr_FR', '1,25 kg'];
        yield 'kilograms in English' => [1.25, 2, UnitCode::KILOGRAM, 'en_US', '1.25 kg'];
        yield 'pieces without a unit' => [6.0, 0, null, null, '6'];
        yield 'pieces with their unit' => [6.0, 0, UnitCode::PIECE, 'en_US', '6 pc'];
        yield 'the decimals of the combination are kept' => [1.0, 2, UnitCode::LITRE, 'fr_FR', '1,00 l'];
        yield 'square metres' => [2.5, 1, UnitCode::SQUARE_METRE, 'en_US', '2.5 m²'];
        yield 'grams of a kilogram' => [0.125, 3, UnitCode::KILOGRAM, 'en_US', '0.125 kg'];
        yield 'a float that is not the decimal it means' => [0.1 + 0.2, 1, null, 'en_US', '0.3'];
        yield 'thousands in English' => [1234.5, 1, null, 'en_US', '1,234.5'];
    }

    #[DataProvider('quantities')]
    public function testAQuantityIsWrittenTheWayTheLanguageReadsIt(float $quantity, int $decimals, ?UnitCode $unitCode, ?string $locale, string $expected): void
    {
        self::assertSame($expected, (new QuantityFormatter())->format($quantity, $decimals, $unitCode, $locale));
    }
}
