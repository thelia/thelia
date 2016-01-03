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

use Thelia\Type\IntToCombinedStringsListType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class IntToCombinedStringsListTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testIntToCombinedStringsListType()
    {
        $type = new IntToCombinedStringsListType();
        $this->assertTrue($type->isValid('1: foo & bar | (fooo &baar), 4: *, 67: (foooo & baaar)'));
        $this->assertFalse($type->isValid('1,2,3'));
    }

    public function testFormatJsonType()
    {
        $type = new IntToCombinedStringsListType();
        $this->assertEquals(
            $type->getFormattedValue('1: foo & bar | (fooo &baar), 4: *, 67: (foooo & baaar)'),
            array(
                1 => array(
                    "values" => array('foo', 'bar', 'fooo', 'baar'),
                    "expression" => 'foo&bar|(fooo&baar)',
                ),
                4 => array(
                    "values" => array('*'),
                    "expression" => '*',
                ),
                67 => array(
                    "values" => array('foooo', 'baaar'),
                    "expression" => '(foooo&baaar)',
                ),
            )
        );
        $this->assertNull($type->getFormattedValue('foo'));
    }
}
