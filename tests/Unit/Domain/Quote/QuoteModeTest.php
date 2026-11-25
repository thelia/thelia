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

namespace Thelia\Tests\Unit\Domain\Quote;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Quote\Enum\QuoteMode;

final class QuoteModeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, QuoteMode}>
     */
    public static function storedValues(): iterable
    {
        yield 'disabled' => ['disabled', QuoteMode::Disabled];
        yield 'alongside' => ['alongside', QuoteMode::Alongside];
        yield 'only' => ['only', QuoteMode::Only];
        yield 'an unknown value' => ['sometimes', QuoteMode::Disabled];
        yield 'an empty value' => ['', QuoteMode::Disabled];
        yield 'a value in the wrong case' => ['ONLY', QuoteMode::Disabled];
        yield 'a value with a stray space' => [' only', QuoteMode::Disabled];
    }

    #[DataProvider('storedValues')]
    public function testAStoredValueNamesItsModeAndAnythingElseMeansDisabled(string $stored, QuoteMode $expected): void
    {
        self::assertSame($expected, QuoteMode::fromConfig($stored));
    }

    public function testTheSettingAShopStartsWithIsTheDisabledMode(): void
    {
        self::assertSame('disabled', QuoteMode::Disabled->value);
    }
}
