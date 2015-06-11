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

use Thelia\Type\BooleanType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class BooleanTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testBooleanType()
    {
        $booleanType = new BooleanType();
        $this->assertTrue($booleanType->isValid('1'));
        $this->assertTrue($booleanType->isValid('yes'));
        $this->assertTrue($booleanType->isValid('true'));
        $this->assertTrue($booleanType->isValid('no'));
        $this->assertTrue($booleanType->isValid('off'));
        $this->assertTrue($booleanType->isValid(0));
        $this->assertTrue($booleanType->isValid('false'));
        $this->assertFalse($booleanType->isValid('foo'));
        $this->assertFalse($booleanType->isValid(2));
    }

    public function testFormatBooleanType()
    {
        $booleanType = new BooleanType();
        $this->assertTrue($booleanType->getFormattedValue('on'));
        $this->assertFalse($booleanType->getFormattedValue('0'));
        $this->assertFalse($booleanType->getFormattedValue(0));
        $this->assertNull($booleanType->getFormattedValue(3));
    }
}
