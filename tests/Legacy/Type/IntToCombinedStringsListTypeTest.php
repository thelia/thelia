<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Type;

use PHPUnit\Framework\TestCase;
use Thelia\Type\IntToCombinedStringsListType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class IntToCombinedStringsListTypeTest extends TestCase
{
    public function testIntToCombinedStringsListType()
    {
        $type = new IntToCombinedStringsListType();
        $this->assertTrue($type->isValid('1: foo & bar | (fooo &baar), 4: *, 67: (foooo & baaar)'));
        $this->assertTrue($type->isValid('9:royal \:enfield,10:500\, continental\, gt,11:(abc & def\&ghi\|ttt)'));

        $this->assertFalse($type->isValid('1,2,3'));
    }

    public function testFormatJsonType()
    {
        $type = new IntToCombinedStringsListType();
        $this->assertEquals(
            $type->getFormattedValue('1: foo & bar | (fooo &baar), 4: *, 67: (foooo & baaar)'),
            [
                1 => [
                    "values" => ['foo', 'bar', 'fooo', 'baar'],
                    "expression" => 'foo & bar | (fooo &baar)',
                ],
                4 => [
                    "values" => ['*'],
                    "expression" => '*',
                ],
                67 => [
                    "values" => ['foooo', 'baaar'],
                    "expression" => '(foooo & baaar)',
                ],
            ]
        );

        $this->assertEquals(
            $type->getFormattedValue('9:royal \:enfield,10:500\, continental\, gt,11:(abc & def\&ghi\|ttt)'),
            [
                9 => [
                    "values" => ['royal :enfield'],
                    "expression" => 'royal :enfield',
                ],
                10 => [
                    "values" => ['500, continental, gt'],
                    "expression" => '500, continental, gt',
                ],
                11 => [
                    "values" => ['abc', 'def&ghi|ttt'],
                    "expression" => '(abc & def&ghi|ttt)',
                ],
            ]
        );

        $this->assertNull($type->getFormattedValue('foo'));
    }

    public function testEscape()
    {
        $this->assertEquals(
            IntToCombinedStringsListType::escape('def&ghi|jkl,mno(pqr)stu:vwx'),
            'def\&ghi\|jkl\,mno\(pqr\)stu\:vwx'
        );
    }

    public function testUnescape()
    {
        $this->assertEquals(
            IntToCombinedStringsListType::unescape('def\&ghi\|jkl\,mno\(pqr\)stu\:vwx'),
            'def&ghi|jkl,mno(pqr)stu:vwx'
        );
    }
}
