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
        $anyListType = new AnyListType();

        $anyListFormat = $anyListType->getFormattedValue('string_1,string_2,string_3');

        $this->assertTrue(is_array($anyListFormat));
        $this->assertCount(3, $anyListFormat);
        $this->assertEquals($anyListFormat[1], 'string_2');
    }

    public function testEmptyAnyListType()
    {
        $anyListType = new AnyListType();

        $this->assertNull($anyListType->getFormattedValue(null));
        $this->assertNull($anyListType->getFormattedValue(''));
    }

    public function testSimpleStringAnyListType()
    {
        $anyListType = new AnyListType();

        $result = $anyListType->getFormattedValue('foo');

        $this->assertTrue(is_array($result));
        $this->assertCount(1, $result);
        $this->assertEquals('foo', $result[0]);
    }
}
