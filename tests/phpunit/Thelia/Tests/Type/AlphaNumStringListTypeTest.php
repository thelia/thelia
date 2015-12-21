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

use Thelia\Type\AlphaNumStringListType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class AlphaNumStringListTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testAlphaNumStringListType()
    {
        $type = new AlphaNumStringListType();
        $this->assertTrue($type->isValid('FOO1,FOO_2,FOO-3'));
        $this->assertFalse($type->isValid('FOO.1,FOO$_2,FOO-3'));
    }

    public function testFormatAlphaNumStringListType()
    {
        $type = new AlphaNumStringListType();
        $this->assertTrue(is_array($type->getFormattedValue('FOO1,FOO_2,FOO-3')));
        $this->assertNull($type->getFormattedValue('5€'));

        $result = $type->getFormattedValue('FOO');

        $this->assertTrue(is_array($result));
        $this->assertCount(1, $result);
    }
}
