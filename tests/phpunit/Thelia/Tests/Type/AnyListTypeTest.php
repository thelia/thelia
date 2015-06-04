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

use Thelia\Type\AnyListType;
use Thelia\Type\IntListType;

/**
 * Class AnyListTypeTest
 * @package Thelia\Tests\Type
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class AnyListTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testAnyListType()
    {
        $AnyListType = new AnyListType();
        $this->assertTrue($AnyListType->isValid('string'));
    }

    public function testFormatAnyListType()
    {
        $AnyListType = new AnyListType();

        $AnyListFormat = $AnyListType->getFormattedValue('string_1,string_2,string_3');

        $this->assertTrue(is_array($AnyListFormat));
        $this->assertEquals(count($AnyListFormat), 3);
        $this->assertEquals($AnyListFormat[1], 'string_2');
    }
}
