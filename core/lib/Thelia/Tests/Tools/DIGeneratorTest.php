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
namespace Thelia\Tests\Tools;

use Thelia\Tools\DIGenerator;

class DIGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers DIGenerator::genDiModel
     */
    public function testGenDiModelWithoutModels()
    {
        $models = DIGenerator::genDiModel(__DIR__ . '/_filesEmpty');

        $this->assertEmpty($models);
    }

    /**
     * @covers DIGenerator::genDiModel
     */
    public function testGenDiModelWithModels()
    {
        $models = DIGenerator::genDiModel(__DIR__ . '/_files');

        $this->assertArrayHasKey("A", $models);
        $this->assertArrayHasKey("B", $models);
        $this->assertEquals("Thelia\Tests\Tools\_files\A", $models["A"]);
        $this->assertCount(2, $models, "There is more than 2 models in this directory");
    }

    /**
     * @covers DIGenerator::genDiModel
     */
    public function testGenDiModelWithModelsAndExclusion()
    {
        $models = DIGenerator::genDiModel(__DIR__ . '/_files',array('B'));

        $this->assertArrayHasKey("A", $models);
        $this->assertCount(1, $models, "There is more than 2 models in this directory");
    }

    /**
     * @expectedException RuntimeException
     * @covers DIGenerator::genDiModel
     */
    public function testGenDiModelWithWrongDirectory()
    {
        $models = DIGenerator::genDiModel(__DIR__ . '/wrongDirectory');

    }
}
