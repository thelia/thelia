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

use Thelia\Type\EnumListType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class EnumListTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testEnumListType()
    {
        $enumListType = new EnumListType(array("cat", "dog", "frog"));
        $this->assertTrue($enumListType->isValid('cat'));
        $this->assertTrue($enumListType->isValid('cat,dog'));
        $this->assertFalse($enumListType->isValid('potato'));
        $this->assertFalse($enumListType->isValid('cat,monkey'));
    }

    public function testFormatEnumListType()
    {
        $enumListType = new EnumListType(array("cat", "dog", "frog"));
        $this->assertTrue(is_array($enumListType->getFormattedValue('cat,dog')));
        $this->assertNull($enumListType->getFormattedValue('cat,monkey'));
    }
}
