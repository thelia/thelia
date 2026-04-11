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

namespace Thelia\Tests\Unit\Condition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Condition\Operators;
use Thelia\Core\Translation\Translator;

final class OperatorsTest extends TestCase
{
    public function testOperatorConstantsValues(): void
    {
        self::assertSame('<', Operators::INFERIOR);
        self::assertSame('<=', Operators::INFERIOR_OR_EQUAL);
        self::assertSame('==', Operators::EQUAL);
        self::assertSame('>=', Operators::SUPERIOR_OR_EQUAL);
        self::assertSame('>', Operators::SUPERIOR);
        self::assertSame('!=', Operators::DIFFERENT);
        self::assertSame('in', Operators::IN);
        self::assertSame('out', Operators::OUT);
    }

    #[DataProvider('knownOperators')]
    public function testGetI18nTranslatesKnownOperators(string $operator, string $expected): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('trans')->willReturnArgument(0);

        self::assertSame($expected, Operators::getI18n($translator, $operator));
    }

    public function testGetI18nFallsBackToOperatorSymbolForUnknown(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->expects(self::never())->method('trans');

        self::assertSame('~', Operators::getI18n($translator, '~'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function knownOperators(): iterable
    {
        yield 'inferior' => [Operators::INFERIOR, 'Less than'];
        yield 'inferior or equal' => [Operators::INFERIOR_OR_EQUAL, 'Less than or equals'];
        yield 'equal' => [Operators::EQUAL, 'Equal to'];
        yield 'superior or equal' => [Operators::SUPERIOR_OR_EQUAL, 'Greater than or equals'];
        yield 'superior' => [Operators::SUPERIOR, 'Greater than'];
        yield 'different' => [Operators::DIFFERENT, 'Not equal to'];
        yield 'in' => [Operators::IN, 'In'];
        yield 'out' => [Operators::OUT, 'Not in'];
    }
}
