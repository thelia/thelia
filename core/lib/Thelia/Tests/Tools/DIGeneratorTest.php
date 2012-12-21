<?php

namespace Thelia\Tests\Tools;

use Thelia\Tools\DIGenerator;

class DIGeneratorTest extends \PHPUnit_Framework_TestCase
{
    
    public function testGenDiModelWithoutModels()
    {
        $models = DIGenerator::genDiModel(__DIR__ . '/_filesEmpty');
        
        $this->assertEmpty($models);
    }
    
    public function testGenDiModelWithModels()
    {
        $models = DIGenerator::genDiModel(__DIR__ . '/_files');
        
        $this->assertArrayHasKey("A", $models);
        $this->assertArrayHasKey("B", $models);
        $this->assertEquals("Thelia\Tests\Tools\_files\A", $models["A"]);
        $this->assertCount(2, $models, "There is more than 2 models in this directory");
    }
    
    public function testGenDiModelWithModelsAndExclusion()
    {
        $models = DIGenerator::genDiModel(__DIR__ . '/_files',array('B'));
        
        $this->assertArrayHasKey("A", $models);
        $this->assertCount(1, $models, "There is more than 2 models in this directory");
    }
    
    /**
     * @expectedException RuntimeException
     */
    public function testGenDiModelWithWrongDirectory()
    {
        $models = DIGenerator::genDiModel(__DIR__ . '/wrongDirectory');
        
        
    }
}
