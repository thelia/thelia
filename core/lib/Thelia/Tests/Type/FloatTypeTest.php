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

use Thelia\Type\FloatType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class FloatTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testFloatType()
    {
        $floatType = new FloatType();
        $this->assertTrue($floatType->isValid('1.1'));
        $this->assertTrue($floatType->isValid(2.2));
        $this->assertFalse($floatType->isValid('foo'));
    }
}
