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

use Thelia\Type\AlphaNumStringType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class AlphaNumStringTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testAlphaNumStringType()
    {
        $type = new AlphaNumStringType();
        $this->assertTrue($type->isValid('azs_qs-0-9ds'));
        $this->assertTrue($type->isValid('3.3'));
        $this->assertFalse($type->isValid('3 3'));
        $this->assertFalse($type->isValid('3€3'));
    }
}
