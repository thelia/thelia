<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
