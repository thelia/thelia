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
use Thelia\Tools\DateTimeFormat;

final class DateTimeFormatTest extends TestCase
{
    public function testGetFormatReturnsDateFormatForDateOutput(): void
    {
        $formatter = new DateTimeFormat($this->requestWithLang(date: 'Y-m-d'));

        self::assertSame('Y-m-d', $formatter->getFormat('date'));
    }

    public function testGetFormatReturnsTimeFormatForTimeOutput(): void
    {
        $formatter = new DateTimeFormat($this->requestWithLang(time: 'H:i:s'));

        self::assertSame('H:i:s', $formatter->getFormat('time'));
    }

    public function testGetFormatFallsBackToDateTimeForUnknownOutput(): void
    {
        $formatter = new DateTimeFormat($this->requestWithLang(datetime: 'Y-m-d H:i:s'));

        self::assertSame('Y-m-d H:i:s', $formatter->getFormat());
        self::assertSame('Y-m-d H:i:s', $formatter->getFormat('anything'));
    }

    private function requestWithLang(
        string $date = 'd/m/Y',
        string $time = 'H:i',
        string $datetime = 'd/m/Y H:i',
    ): Request {
        $lang = $this->createMock(Lang::class);
        $lang->method('getDateFormat')->willReturn($date);
        $lang->method('getTimeFormat')->willReturn($time);
        $lang->method('getDateTimeFormat')->willReturn($datetime);

        $session = $this->createMock(Session::class);
        $session->method('getLang')->willReturn($lang);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        return $request;
    }
}
