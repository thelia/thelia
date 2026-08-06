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

namespace Thelia\Tests\Integration\Tools;

use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\URL;

final class UrlTest extends IntegrationTestCase
{
    public function testAbsoluteUrlExpandsArrayParameters(): void
    {
        $url = URL::getInstance()->absoluteUrl('?view=category', [
            'tfilters' => ['attribute' => [1 => [0 => 2]]],
        ]);

        self::assertStringContainsString('tfilters%5Battribute%5D%5B1%5D%5B0%5D=2', $url);
        self::assertStringNotContainsString('=Array', $url);
    }

    public function testAbsoluteUrlSkipsEmptyArrayParameters(): void
    {
        $url = URL::getInstance()->absoluteUrl('?view=category', [
            'empty' => [],
            'page' => 2,
        ]);

        self::assertStringContainsString('page=2', $url);
        self::assertStringNotContainsString('empty', $url);
        self::assertStringNotContainsString('&&', $url);
    }

    /**
     * Scalar parameters keep their historical encoding: urlencode() spacing, '1' for true and an
     * empty value for both false and null.
     */
    public function testAbsoluteUrlKeepsScalarParameterEncoding(): void
    {
        $url = URL::getInstance()->absoluteUrl('?view=category', [
            'title' => 'a b',
            'yes' => true,
            'no' => false,
            'nothing' => null,
            'count' => 3,
        ]);

        self::assertStringContainsString('title=a+b', $url);
        self::assertStringContainsString('yes=1', $url);
        self::assertStringContainsString('no=&', $url);
        self::assertStringContainsString('nothing=&', $url);
        self::assertStringContainsString('count=3', $url);
    }
}
