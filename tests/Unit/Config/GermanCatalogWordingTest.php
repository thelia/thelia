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

namespace Thelia\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The German core catalog writes the compound E-Mail-Adresse with a capital M and a hyphen,
 * and names the second address line and the name of an address the way German forms do.
 */
final class GermanCatalogWordingTest extends TestCase
{
    public function testNoEntrySplitsTheCompoundOfAnEmailAddress(): void
    {
        foreach (self::catalog() as $key => $value) {
            self::assertDoesNotMatchRegularExpression('/E-[Mm]ail Adress/', (string) $value, (string) $key);
        }
    }

    #[DataProvider('addressEntries')]
    public function testTheAddressFormReadsAsAGermanForm(string $key, string $expected): void
    {
        self::assertSame($expected, self::catalog()[$key] ?? null);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function addressEntries(): iterable
    {
        yield 'second address line' => ['Address Line 2', 'Adresszusatz'];
        yield 'additional address' => ['Additional address', 'Adresszusatz'];
        yield 'name of an address' => ['Address label', 'Bezeichnung'];
    }

    /**
     * @return array<string, string>
     */
    private static function catalog(): array
    {
        return require THELIA_LIB.'Config/I18n/de_DE.php';
    }
}
