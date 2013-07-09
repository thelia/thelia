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
