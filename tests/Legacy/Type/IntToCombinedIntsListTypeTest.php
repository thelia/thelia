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
use Thelia\Type\IntToCombinedIntsListType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class IntToCombinedIntsListTypeTest extends TestCase
{
    public function testIntToCombinedIntsListType()
    {
        $type = new IntToCombinedIntsListType();
        $this->assertTrue($type->isValid('1: 2 & 5 | (6 &7), 4: *, 67: (1 & 9)'));
        $this->assertFalse($type->isValid('1,2,3'));
    }

    public function testFormatJsonType()
    {
        $type = new IntToCombinedIntsListType();
        $this->assertEquals(
            $type->getFormattedValue('1: 2 & 5 | (6 &7), 4: *, 67: (1 & 9)'),
            [
                1 => [
                    "values" => [2, 5, 6, 7],
                    "expression" => '2&5|(6&7)',
                ],
                4 => [
                    "values" => ['*'],
                    "expression" => '*',
                ],
                67 => [
                    "values" => [1, 9],
                    "expression" => '(1&9)',
                ],
            ]
        );
        $this->assertNull($type->getFormattedValue('foo'));
    }
}
