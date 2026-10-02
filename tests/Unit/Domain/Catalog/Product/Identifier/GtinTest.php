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

namespace Thelia\Tests\Unit\Domain\Catalog\Product\Identifier;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Product\Identifier\Gtin;
use Thelia\Domain\Catalog\Product\Identifier\GtinViolation;
use Thelia\Domain\Catalog\Product\Identifier\InvalidGtinException;

final class GtinTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validCodes(): iterable
    {
        yield 'EAN-8' => ['96385074'];
        yield 'UPC-A' => ['036000291452'];
        yield 'EAN-13' => ['4006381333931'];
        yield 'ISBN-13' => ['9780306406157'];
        yield 'GTIN-14' => ['10012345600019'];
        yield 'all zeros' => ['0000000000000'];
    }

    #[DataProvider('validCodes')]
    public function testAGtinOfEveryLengthOfTheFamilyIsAccepted(string $code): void
    {
        self::assertNull(Gtin::violationOf($code));
    }

    /**
     * @return iterable<string, array{string, GtinViolation}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'EAN-13 with a wrong last digit' => ['4006381333932', GtinViolation::CheckDigit];
        yield 'UPC-A with a wrong last digit' => ['036000291453', GtinViolation::CheckDigit];
        yield 'eleven digits' => ['03600029145', GtinViolation::Length];
        yield 'nine digits' => ['963850740', GtinViolation::Length];
        yield 'fifteen digits' => ['100123456000190', GtinViolation::Length];
        yield 'ISBN-10' => ['0306406152', GtinViolation::Length];
        yield 'letters' => ['40063813339A1', GtinViolation::NotDigits];
        yield 'a decimal number' => ['4006381.33393', GtinViolation::NotDigits];
    }

    #[DataProvider('invalidCodes')]
    public function testACodeOutsideTheFamilyIsRefusedWithItsReason(string $code, GtinViolation $expected): void
    {
        self::assertSame($expected, Gtin::violationOf($code));
    }

    public function testSpacesAndHyphensTypedBetweenTheDigitGroupsAreDropped(): void
    {
        self::assertSame('4006381333931', Gtin::normalize(' 4 006381-333931 '));
        self::assertSame('4006381333931', Gtin::normalize("4006381\t333931"));
    }

    public function testTheLeadingZeroIsKept(): void
    {
        $normalized = Gtin::normalize('0 36000 29145 2');

        self::assertSame('036000291452', $normalized);
        self::assertNull(Gtin::violationOf($normalized));
    }

    public function testTheCheckDigitFollowsTheGs1Weights(): void
    {
        self::assertSame(1, Gtin::checkDigitOf('400638133393'));
        self::assertSame(2, Gtin::checkDigitOf('03600029145'));
        self::assertSame(4, Gtin::checkDigitOf('9638507'));
        self::assertSame(9, Gtin::checkDigitOf('1001234560001'));
    }

    public function testTheRefusalSaysWhatToFix(): void
    {
        self::assertStringContainsString(
            'check digit',
            (new InvalidGtinException('4006381333932', GtinViolation::CheckDigit, 'PSE-1'))->getMessage(),
        );

        $lengthRefusal = (new InvalidGtinException('03600029145', GtinViolation::Length))->getMessage();
        self::assertStringContainsString('8, 12, 13 or 14', $lengthRefusal);
        self::assertStringContainsString('11', $lengthRefusal);
    }
}
