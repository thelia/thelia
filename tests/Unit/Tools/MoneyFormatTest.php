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
use Thelia\Tools\MoneyFormat;

final class MoneyFormatTest extends TestCase
{
    public function testFormatReturnsBareNumberWhenNoSymbolIsProvided(): void
    {
        $formatter = new MoneyFormat($this->buildRequestWithLang());

        self::assertSame('1 234,57', $formatter->format(1234.5678));
    }

    public function testFormatAppendsSymbolAfterNumber(): void
    {
        $formatter = new MoneyFormat($this->buildRequestWithLang());

        self::assertSame('1 234,57 €', $formatter->format(1234.5678, null, null, null, '€'));
    }

    public function testFormatStandardMoneyUsesDotAndNoThousandsSeparator(): void
    {
        $formatter = new MoneyFormat($this->buildRequestWithLang());

        self::assertSame('1234.57', $formatter->formatStandardMoney(1234.5678));
    }

    private function buildRequestWithLang(): Request
    {
        $lang = $this->createMock(Lang::class);
        $lang->method('getDecimals')->willReturn('2');
        $lang->method('getDecimalSeparator')->willReturn(',');
        $lang->method('getThousandsSeparator')->willReturn(' ');

        $session = $this->createMock(Session::class);
        $session->method('getLang')->willReturn($lang);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        return $request;
    }
}
