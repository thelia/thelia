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

namespace Thelia\Tests\Unit\Domain\Company;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Company\Service\CompanyAmount;

/**
 * Limits are DECIMAL strings: they are compared digit by digit, never as floats.
 */
final class CompanyAmountTest extends TestCase
{
    #[DataProvider('amounts')]
    public function testWhatTheColumnHoldsIsValidAndNothingElse(string $amount, bool $valid): void
    {
        self::assertSame($valid, CompanyAmount::isValid($amount));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function amounts(): iterable
    {
        yield 'zero' => ['0', true];
        yield 'whole' => ['500', true];
        yield 'six decimals' => ['2000.123456', true];
        yield 'as stored' => ['500.000000', true];
        yield 'ten digits' => ['9999999999.999999', true];
        yield 'eleven digits' => ['10000000000', false];
        yield 'seven decimals' => ['1.1234567', false];
        yield 'negative' => ['-1', false];
        yield 'empty' => ['', false];
        yield 'text' => ['abc', false];
        yield 'comma' => ['1,5', false];
        yield 'exponent' => ['1e3', false];
        yield 'blank' => [' 1', false];
        yield 'trailing point' => ['1.', false];
    }

    #[DataProvider('comparisons')]
    public function testAmountsAreComparedAsNumbers(string $left, string $right, int $expected): void
    {
        self::assertSame($expected, CompanyAmount::compare($left, $right) <=> 0);
        self::assertSame(-$expected, CompanyAmount::compare($right, $left) <=> 0);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function comparisons(): iterable
    {
        yield 'equal in different writings' => ['500', '500.000000', 0];
        yield 'leading zeros' => ['0500.5', '500.500000', 0];
        yield 'integer part decides' => ['600', '500.999999', 1];
        yield 'longer integer is larger' => ['1000', '999.999999', 1];
        yield 'decimals decide' => ['500.000001', '500', 1];
        yield 'zero against a cent' => ['0', '0.01', -1];
        yield 'small fractions' => ['0.000100', '0.000020', 1];
    }
}
