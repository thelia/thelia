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

use Thelia\Type\EnumType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class EnumTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testEnumType()
    {
        $enumType = new EnumType(array("cat", "dog"));
        $this->assertTrue($enumType->isValid('cat'));
        $this->assertTrue($enumType->isValid('dog'));
        $this->assertFalse($enumType->isValid('monkey'));
        $this->assertFalse($enumType->isValid('catdog'));
    }
}
