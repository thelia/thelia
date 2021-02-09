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

namespace Tests\Core\Serializer\Serializer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Serializer\Serializer\JSONSerializer as SUT;

/**
 * Class JSONSerializerTest
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class JSONSerializerTest extends TestCase
{
    /**
     * @var \Thelia\Core\Serializer\Serializer\JSONSerializer
     */
    protected $sut;

    /**
     * @var MockObject
     */
    protected $stubArchiver;

    public function setUp(): void
    {
        $this->sut = new SUT;
    }

    public function testGetId()
    {
        $this->assertIsString($this->sut->getId());
        $this->assertEquals('thelia.json', $this->sut->getId());
    }

    public function testGetName()
    {
        $this->assertIsString($this->sut->getName());
        $this->assertEquals('JSON', $this->sut->getName());
    }

    public function testGetExtension()
    {
        $this->assertIsString($this->sut->getExtension());
        $this->assertEquals('json', $this->sut->getExtension());
    }

    public function testGetMimeType()
    {
        $this->assertIsString($this->sut->getMimeType());
        $this->assertEquals('application/json', $this->sut->getMimeType());
    }

    public function testSerialize()
    {
        $stdClass = new \stdClass;
        $stdClass->key = 'value';

        $dataSet = [
            ['simple string', '"simple string"'],
            ['-1', '"-1"'],
            ['0', '"0"'],
            ['1', '"1"'],
            ['-1.0', '"-1.0"'],
            ['0.0', '"0.0"'],
            ['1.0', '"1.0"'],
            [-1, '-1'],
            [0, '0'],
            [1, '1'],
            [-1.0, '-1.0'],
            [0.0, '0.0'],
            [1.0, '1.0'],
//            [[], '[]'],
            [['simple string'], '["simple string"]'],
            [['simple string', 'simple string'], '["simple string","simple string"]'],
            [['key' => 'value'], '{"key":"value"}'],
            [$stdClass, '{"key":"value"}']
        ];

        foreach ($dataSet as $data) {
            $this->assertEquals($data[1], $this->sut->serialize($data[0]));
        }
    }

    public function testSeparator()
    {
        $this->assertIsString($this->sut->separator());
        $this->assertEquals(',' . PHP_EOL, $this->sut->separator());
    }
}
