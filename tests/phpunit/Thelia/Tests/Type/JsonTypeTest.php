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

use Thelia\Type\JsonType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class JsonTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testJsonType()
    {
        $jsonType = new JsonType();
        $this->assertTrue($jsonType->isValid('{"k0":"v0","k1":"v1","k2":"v2"}'));
        $this->assertFalse($jsonType->isValid('1,2,3'));
    }

    public function testFormatJsonType()
    {
        $jsonType = new JsonType();
        $this->assertTrue(is_array($jsonType->getFormattedValue('{"k0":"v0","k1":"v1","k2":"v2"}')));
        $this->assertNull($jsonType->getFormattedValue('foo'));
    }
}
