<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
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
        $this->assertTrue($booleanType->isValid('y'));
        $this->assertTrue($booleanType->isValid('yes'));
        $this->assertTrue($booleanType->isValid('true'));
        $this->assertTrue($booleanType->isValid('no'));
        $this->assertTrue($booleanType->isValid('n'));
        $this->assertTrue($booleanType->isValid('false'));
        $this->assertFalse($booleanType->isValid('foo'));
        $this->assertFalse($booleanType->isValid(5));
    }

    public function testFormatBooleanType()
    {
        $booleanType = new BooleanType();
        $this->assertTrue($booleanType->getFormattedValue('yes'));
        $this->assertFalse($booleanType->getFormattedValue('no'));
        $this->assertNull($booleanType->getFormattedValue('foo'));
    }
}
