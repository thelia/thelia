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

use Thelia\Core\Serializer\Serializer\JSONSerializer as SUT;

/**
 * Class JSONSerializerTest
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class JSONSerializerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Thelia\Core\Serializer\Serializer\JSONSerializer
     */
    protected $sut;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $stubArchiver;

    public function setUp()
    {
        $this->sut = new SUT;
    }

    public function testGetId()
    {
        $this->assertInternalType('string', $this->sut->getId());
        $this->assertEquals('thelia.json', $this->sut->getId());
    }

    public function testGetName()
    {
        $this->assertInternalType('string', $this->sut->getName());
        $this->assertEquals('JSON', $this->sut->getName());
    }

    public function testGetExtension()
    {
        $this->assertInternalType('string', $this->sut->getExtension());
        $this->assertEquals('json', $this->sut->getExtension());
    }

    public function testGetMimeType()
    {
        $this->assertInternalType('string', $this->sut->getMimeType());
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
            [[], '[]'],
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
        $this->assertInternalType('string', $this->sut->separator());
        $this->assertEquals(',' . PHP_EOL, $this->sut->separator());
    }
}
