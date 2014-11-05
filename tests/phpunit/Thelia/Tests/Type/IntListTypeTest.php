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

use Thelia\Type\IntListType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class IntListTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testIntListType()
    {
        $intListType = new IntListType();
        $this->assertTrue($intListType->isValid('1'));
        $this->assertTrue($intListType->isValid('1,2,3'));
        $this->assertFalse($intListType->isValid('1,2,3.3'));
    }

    public function testFormatIntListType()
    {
        $intListType = new IntListType();
        $this->assertTrue(is_array($intListType->getFormattedValue('1,2,3')));
        $this->assertNull($intListType->getFormattedValue('foo'));
    }
}
