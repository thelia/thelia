<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

namespace Thelia\Tests\Config\Dumper;

use Thelia\Config\Dumper\TpexConfigDumper;


class TpexConfigDumperTest extends \PHPUnit_Framework_TestCase
{

    static protected $fixturePath;

    public static function setUpBeforeClass()
    {
        self::$fixturePath = realpath(__DIR__ . "/../Fixtures/Dumper/Config");
    }

    public function testDumpWithEmptyConfig()
    {
        $tpexDumper = new TpexConfigDumper(array(), array(), array(), array());

        $this->assertStringEqualsFile(self::$fixturePath . "/Empty.php", $tpexDumper->dump());
    }

}