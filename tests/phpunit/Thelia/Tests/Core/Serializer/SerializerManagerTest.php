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

namespace Tests\Core\Serializer;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\Core\Serializer\SerializerManager as SUT;

/**
 * Class SerializerManagerTest
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class SerializerManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Thelia\Core\Serializer\SerializerManager
     */
    protected $sut;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $stubSerializer;

    public function setUp()
    {
        $this->sut = new SUT;
        $this->stubSerializer = $this->getMock('Thelia\\Core\\Serializer\\SerializerInterface');

        new Translator(new Container);
    }

    public function testGetSerializers()
    {
        $serializers = $this->sut->getSerializers();

        $this->assertInternalType('array', $serializers);
        $this->assertCount(0, $serializers);
    }

    public function testAddArgs()
    {
        $reflectedParameters = (new \ReflectionMethod($this->sut, 'add'))->getParameters();

        $this->assertCount(1, $reflectedParameters);
        $this->assertFalse($reflectedParameters[0]->allowsNull());
        $this->assertFalse($reflectedParameters[0]->isOptional());
        $this->assertEquals(
            'Thelia\\Core\\Serializer\\SerializerInterface',
            $reflectedParameters[0]->getClass()->getName()
        );
    }

    public function testAdd()
    {
        $this->stubSerializer
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('serializer1', 'serializer2', 'serializer3', 'serializer1'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubSerializer);

            $serializers = $this->sut->getSerializers();
            $this->assertInternalType('array', $serializers);
            $this->assertCount($i, $serializers);
        }

        $this->sut->add($this->stubSerializer);

        $serializers = $this->sut->getSerializers();
        $this->assertInternalType('array', $serializers);
        $this->assertCount(3, $serializers);
    }

    public function testSetSerializersArgs()
    {
        $reflectedParameters = (new \ReflectionMethod($this->sut, 'setSerializers'))->getParameters();

        $this->assertCount(1, $reflectedParameters);
        $this->assertFalse($reflectedParameters[0]->allowsNull());
        $this->assertFalse($reflectedParameters[0]->isOptional());
        $this->assertTrue($reflectedParameters[0]->isArray());
    }

    public function testSetSerializers()
    {
        $this->stubSerializer
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('serializer1', 'serializer2', 'serializer3', 'serializer4', 'serializer5'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubSerializer);
        }

        $serializers = $this->sut->getSerializers();
        $this->assertInternalType('array', $serializers);
        $this->assertCount(3, $serializers);
        $this->assertTrue($this->sut->has('serializer1'));
        $this->assertTrue($this->sut->has('serializer2'));
        $this->assertTrue($this->sut->has('serializer3'));
        $this->assertFalse($this->sut->has('serializer4'));
        $this->assertFalse($this->sut->has('serializer5'));

        $this->sut->setSerializers([$this->stubSerializer, $this->stubSerializer]);

        $serializers = $this->sut->getSerializers();
        $this->assertInternalType('array', $serializers);
        $this->assertCount(2, $serializers);
        $this->assertFalse($this->sut->has('serializer1'));
        $this->assertFalse($this->sut->has('serializer2'));
        $this->assertFalse($this->sut->has('serializer3'));
        $this->assertTrue($this->sut->has('serializer4'));
        $this->assertTrue($this->sut->has('serializer5'));

        $this->setExpectedException('Exception');

        $this->sut->setSerializers(['notASerializerInterface']);
    }

    public function testReset()
    {
        $this->sut->reset();

        $serializers = $this->sut->getSerializers();
        $this->assertInternalType('array', $serializers);
        $this->assertCount(0, $serializers);

        $this->stubSerializer
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('serializer1', 'serializer2', 'serializer3'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubSerializer);
        }

        $serializers = $this->sut->getSerializers();
        $this->assertInternalType('array', $serializers);
        $this->assertCount(3, $serializers);

        $this->sut->reset();

        $serializers = $this->sut->getSerializers();
        $this->assertInternalType('array', $serializers);
        $this->assertCount(0, $serializers);
    }

    public function testHas()
    {
        $this->stubSerializer
            ->expects($this->any())
            ->method('getId')
            ->will($this->returnValue('serializer1'))
        ;

        $this->assertFalse($this->sut->has('serializer1'));
        $this->assertFalse($this->sut->has('serializer2'));
        $this->assertFalse($this->sut->has(-1));
        $this->assertFalse($this->sut->has(0));
        $this->assertFalse($this->sut->has(1));
        $this->assertFalse($this->sut->has(null));
        $this->assertFalse($this->sut->has(true));
        $this->assertFalse($this->sut->has(false));

        $this->sut->add($this->stubSerializer);

        $this->assertTrue($this->sut->has('serializer1'));
        $this->assertFalse($this->sut->has('serializer2'));
        $this->assertFalse($this->sut->has(-1));
        $this->assertFalse($this->sut->has(0));
        $this->assertFalse($this->sut->has(1));
        $this->assertFalse($this->sut->has(null));
        $this->assertFalse($this->sut->has(true));
        $this->assertFalse($this->sut->has(false));
    }

    public function testHasThrowException()
    {
        $this->stubSerializer
            ->expects($this->any())
            ->method('getId')
            ->will($this->returnValue('serializer1'))
        ;

        $this->sut->add($this->stubSerializer);

        $this->assertTrue($this->sut->has('serializer1', true));

        $this->setExpectedException('InvalidArgumentException');

        $this->sut->has('serializer2', true);
    }

    public function testGet()
    {
        $this->stubSerializer
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('serializer1', 'serializer2', 'serializer3'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubSerializer);
        }

        $this->assertInstanceOf('Thelia\\Core\\Serializer\\SerializerInterface', $this->sut->get('serializer1'));
        $this->assertInstanceOf('Thelia\\Core\\Serializer\\SerializerInterface', $this->sut->get('serializer2'));
        $this->assertInstanceOf('Thelia\\Core\\Serializer\\SerializerInterface', $this->sut->get('serializer3'));

        $this->setExpectedException('InvalidArgumentException');

        $this->sut->get('serializer4');
    }

    public function testRemove()
    {
        $this->assertFalse($this->sut->has('serializer1'));
        $this->assertFalse($this->sut->has('serializer2'));
        $this->assertFalse($this->sut->has('serializer3'));
        $this->assertFalse($this->sut->has('serializer4'));

        $this->stubSerializer
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('serializer1', 'serializer2', 'serializer3'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubSerializer);
        }

        $this->assertTrue($this->sut->has('serializer1'));
        $this->assertTrue($this->sut->has('serializer2'));
        $this->assertTrue($this->sut->has('serializer3'));
        $this->assertFalse($this->sut->has('serializer4'));

        $this->sut->remove('serializer2');

        $this->assertTrue($this->sut->has('serializer1'));
        $this->assertFalse($this->sut->has('serializer2'));
        $this->assertTrue($this->sut->has('serializer3'));
        $this->assertFalse($this->sut->has('serializer4'));

        $this->sut->remove('serializer1');
        $this->sut->remove('serializer3');

        $this->assertFalse($this->sut->has('serializer1'));
        $this->assertFalse($this->sut->has('serializer2'));
        $this->assertFalse($this->sut->has('serializer3'));
        $this->assertFalse($this->sut->has('serializer4'));

        $this->setExpectedException('InvalidArgumentException');

        $this->sut->remove('serializer4');
    }
}
