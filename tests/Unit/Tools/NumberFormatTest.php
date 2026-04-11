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

namespace Thelia\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Lang;
use Thelia\Tools\NumberFormat;

final class NumberFormatTest extends TestCase
{
    public function testFormatUsesLangDefaults(): void
    {
        $lang = $this->createMock(Lang::class);
        $lang->method('getDecimals')->willReturn('2');
        $lang->method('getDecimalSeparator')->willReturn(',');
        $lang->method('getThousandsSeparator')->willReturn(' ');

        $formatter = new NumberFormat($this->buildRequestWithLang($lang));

        self::assertSame('1 234,57', $formatter->format(1234.5678));
    }

    public function testFormatAllowsPerCallOverrides(): void
    {
        $formatter = new NumberFormat($this->buildRequestWithLang($this->createMock(Lang::class)));

        self::assertSame('1,234.00', $formatter->format(1234, 2, '.', ','));
    }

    public function testFormatStandardNumberUsesLangDecimalsAndDot(): void
    {
        $lang = $this->createMock(Lang::class);
        $lang->method('getDecimals')->willReturn('3');

        $formatter = new NumberFormat($this->buildRequestWithLang($lang));

        self::assertSame('42.500', $formatter->formatStandardNumber(42.5));
    }

    public function testFormatStandardNumberAcceptsExplicitDecimals(): void
    {
        $formatter = new NumberFormat($this->buildRequestWithLang($this->createMock(Lang::class)));

        self::assertSame('42.5', $formatter->formatStandardNumber(42.5, '1'));
        self::assertSame('43', $formatter->formatStandardNumber(42.5, '0'));
    }

    private function buildRequestWithLang(Lang $lang): Request
    {
        $session = $this->createMock(Session::class);
        $session->method('getLang')->willReturn($lang);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        return $request;
    }
}
