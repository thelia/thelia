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

namespace Thelia\Tests\Core\Template\Loop;

use Thelia\Core\Template\Loop\Title;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class TitleTest extends \PHPUnit_Framework_TestCase
{
    public function testGetArgDefinitions()
    {
        $title = new Title();
/*
        $this->assertTrue($collection->getCount() == 3);

        $this->assertTrue($collection->key() == 'arg0');
        $collection->next();
        $this->assertTrue($collection->key() == 'arg1');
        $collection->next();
        $this->assertTrue($collection->key() == 'arg2');
        $collection->next();

        $this->assertFalse($collection->valid());

        $collection->rewind();
        $this->assertTrue($collection->key() == 'arg0');*/
    }
}
