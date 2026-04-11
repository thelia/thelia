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
use Thelia\Tools\I18n;

final class I18nTest extends TestCase
{
    public function testRealEscapeBuildsCharConcatFromSimpleString(): void
    {
        $escaped = I18n::realEscape('ab');

        self::assertSame(\sprintf('CONCAT(CHAR(%d),CHAR(%d))', \ord('a'), \ord('b')), $escaped);
    }

    public function testRealEscapeStripsLeadingAndTrailingQuotes(): void
    {
        self::assertSame(I18n::realEscape('ab'), I18n::realEscape('"ab"'));
        self::assertSame(I18n::realEscape('ab'), I18n::realEscape("'ab'"));
    }

    public function testRealEscapeEncodesEachCharacterIndividually(): void
    {
        $escaped = I18n::realEscape('foo');

        self::assertStringContainsString('CHAR('.\ord('f').')', $escaped);
        self::assertStringContainsString('CHAR('.\ord('o').')', $escaped);
        self::assertSame(
            'CONCAT(CHAR('.\ord('f').'),CHAR('.\ord('o').'),CHAR('.\ord('o').'))',
            $escaped,
        );
    }
}
