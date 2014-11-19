<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Type;

use Thelia\Type\IntToCombinedIntsListType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class IntToCombinedIntsListTypeTest extends \PHPUnit_Framework_TestCase
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
            array(
                1 => array(
                    "values" => array(2, 5, 6, 7),
                    "expression" => '2&5|(6&7)',
                ),
                4 => array(
                    "values" => array('*'),
                    "expression" => '*',
                ),
                67 => array(
                    "values" => array(1, 9),
                    "expression" => '(1&9)',
                ),
            )
        );
        $this->assertNull($type->getFormattedValue('foo'));
    }
}
