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

namespace Tests\Core\Archiver;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Archiver\ArchiverManager as SUT;
use Thelia\Core\Translation\Translator;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * Class ArchiverManagerTest
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ArchiverManagerTest extends ContainerAwareTestCase
{
    /**
     * @var \Thelia\Core\Archiver\ArchiverManager
     */
    protected $sut;

    /**
     * @var MockObject
     */
    protected $stubArchiver;

    public function setUp(): void
    {
        $this->sut = new SUT;
        $this->stubArchiver = $this->createMock('Thelia\\Core\\Archiver\\ArchiverInterface');

        new Translator($this->getContainer()->get("request_stack"));
    }

    public function testGetArchivers()
    {
        $archivers = $this->sut->getArchivers();

        $this->assertIsArray($archivers);
        $this->assertCount(0, $archivers);

        $archivers = $this->sut->getArchivers(true);

        $this->assertIsArray($archivers);
        $this->assertCount(0, $archivers);

        $archivers = $this->sut->getArchivers(false);

        $this->assertIsArray($archivers);
        $this->assertCount(0, $archivers);
    }

    public function testAddArgs()
    {
        $reflectedParameters = (new \ReflectionMethod($this->sut, 'add'))->getParameters();

        $this->assertCount(1, $reflectedParameters);
        $this->assertFalse($reflectedParameters[0]->allowsNull());
        $this->assertFalse($reflectedParameters[0]->isOptional());
        $this->assertEquals(
            'Thelia\\Core\\Archiver\\ArchiverInterface',
            $reflectedParameters[0]->getClass()->getName()
        );
    }

    public function testAdd()
    {
        $this->stubArchiver
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('archiver1', 'archiver2', 'archiver3', 'archiver1'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubArchiver);

            $archivers = $this->sut->getArchivers();
            $this->assertIsArray($archivers);
            $this->assertCount($i, $archivers);
        }

        $this->sut->add($this->stubArchiver);

        $archivers = $this->sut->getArchivers();
        $this->assertIsArray($archivers);
        $this->assertCount(3, $archivers);
    }

    public function testSetArchiversArgs()
    {
        $reflectedParameters = (new \ReflectionMethod($this->sut, 'setArchivers'))->getParameters();

        $this->assertCount(1, $reflectedParameters);
        $this->assertFalse($reflectedParameters[0]->allowsNull());
        $this->assertFalse($reflectedParameters[0]->isOptional());
        $this->assertTrue($reflectedParameters[0]->isArray());
    }

    public function testSetArchivers()
    {
        $this->stubArchiver
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('archiver1', 'archiver2', 'archiver3', 'archiver4', 'archiver5'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubArchiver);
        }

        $archivers = $this->sut->getArchivers();
        $this->assertIsArray($archivers);
        $this->assertCount(3, $archivers);
        $this->assertTrue($this->sut->has('archiver1'));
        $this->assertTrue($this->sut->has('archiver2'));
        $this->assertTrue($this->sut->has('archiver3'));
        $this->assertFalse($this->sut->has('archiver4'));
        $this->assertFalse($this->sut->has('archiver5'));

        $this->sut->setArchivers([$this->stubArchiver, $this->stubArchiver]);

        $archivers = $this->sut->getArchivers();
        $this->assertIsArray($archivers);
        $this->assertCount(2, $archivers);
        $this->assertFalse($this->sut->has('archiver1'));
        $this->assertFalse($this->sut->has('archiver2'));
        $this->assertFalse($this->sut->has('archiver3'));
        $this->assertTrue($this->sut->has('archiver4'));
        $this->assertTrue($this->sut->has('archiver5'));

        $this->expectException(\Exception::class);

        $this->sut->setArchivers(['notAnArchiverInterface']);
    }

    public function testReset()
    {
        $this->sut->reset();

        $archivers = $this->sut->getArchivers();
        $this->assertIsArray($archivers);
        $this->assertCount(0, $archivers);

        $this->stubArchiver
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('archiver1', 'archiver2', 'archiver3'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubArchiver);
        }

        $archivers = $this->sut->getArchivers();
        $this->assertIsArray($archivers);
        $this->assertCount(3, $archivers);

        $this->sut->reset();

        $archivers = $this->sut->getArchivers();
        $this->assertIsArray($archivers);
        $this->assertCount(0, $archivers);
    }

    public function testHas()
    {
        $this->stubArchiver
            ->expects($this->any())
            ->method('getId')
            ->will($this->returnValue('archiver1'))
        ;

        $this->assertFalse($this->sut->has('archiver1'));
        $this->assertFalse($this->sut->has('archiver2'));
        $this->assertFalse($this->sut->has(-1));
        $this->assertFalse($this->sut->has(0));
        $this->assertFalse($this->sut->has(1));
        $this->assertFalse($this->sut->has(null));
        $this->assertFalse($this->sut->has(true));
        $this->assertFalse($this->sut->has(false));

        $this->sut->add($this->stubArchiver);

        $this->assertTrue($this->sut->has('archiver1'));
        $this->assertFalse($this->sut->has('archiver2'));
        $this->assertFalse($this->sut->has(-1));
        $this->assertFalse($this->sut->has(0));
        $this->assertFalse($this->sut->has(1));
        $this->assertFalse($this->sut->has(null));
        $this->assertFalse($this->sut->has(true));
        $this->assertFalse($this->sut->has(false));
    }

    public function testHasThrowException()
    {
        $this->stubArchiver
            ->expects($this->any())
            ->method('getId')
            ->will($this->returnValue('archiver1'))
        ;

        $this->sut->add($this->stubArchiver);

        $this->assertTrue($this->sut->has('archiver1', true));

        $this->expectException(\InvalidArgumentException::class);

        $this->sut->has('archiver2', true);
    }

    public function testGet()
    {
        $this->stubArchiver
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('archiver1', 'archiver3'))
        ;
        $this->stubArchiver->expects($this->any())->method('isAvailable')->will($this->returnValue(true));

        $unavailableMock = $this->createMock('Thelia\\Core\\Archiver\\ArchiverInterface');
        $unavailableMock->expects($this->any())->method('getId')->will($this->returnValue('archiver2'));
        $unavailableMock->expects($this->any())->method('isAvailable')->will($this->returnValue(false));

        $this->sut->add($this->stubArchiver);
        $this->sut->add($this->stubArchiver);
        $this->sut->add($unavailableMock);

        $this->assertInstanceOf('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver1'));
        $this->assertInstanceOf('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver2'));
        $this->assertInstanceOf('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver3'));

        $this->assertInstanceOf('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver1', true));
        $this->isNull('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver2', true));
        $this->assertInstanceOf('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver3', true));

        $this->isNull('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver1', false));
        $this->assertInstanceOf('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver2', false));
        $this->isNull('Thelia\\Core\\Archiver\\ArchiverInterface', $this->sut->get('archiver3', false));

        $this->expectException(\InvalidArgumentException::class);

        $this->sut->get('archiver4');
    }

    public function testRemove()
    {
        $this->assertFalse($this->sut->has('archiver1'));
        $this->assertFalse($this->sut->has('archiver2'));
        $this->assertFalse($this->sut->has('archiver3'));
        $this->assertFalse($this->sut->has('archiver4'));

        $this->stubArchiver
            ->expects($this->any())
            ->method('getId')
            ->will($this->onConsecutiveCalls('archiver1', 'archiver2', 'archiver3'))
        ;

        for ($i = 1; $i <= 3; $i++) {
            $this->sut->add($this->stubArchiver);
        }

        $this->assertTrue($this->sut->has('archiver1'));
        $this->assertTrue($this->sut->has('archiver2'));
        $this->assertTrue($this->sut->has('archiver3'));
        $this->assertFalse($this->sut->has('archiver4'));

        $this->sut->remove('archiver2');

        $this->assertTrue($this->sut->has('archiver1'));
        $this->assertFalse($this->sut->has('archiver2'));
        $this->assertTrue($this->sut->has('archiver3'));
        $this->assertFalse($this->sut->has('archiver4'));

        $this->sut->remove('archiver1');
        $this->sut->remove('archiver3');

        $this->assertFalse($this->sut->has('archiver1'));
        $this->assertFalse($this->sut->has('archiver2'));
        $this->assertFalse($this->sut->has('archiver3'));
        $this->assertFalse($this->sut->has('archiver4'));

        $this->expectException(\InvalidArgumentException::class);

        $this->sut->remove('archiver4');
    }

    protected function buildContainer(ContainerBuilder $container)
    {
        // TODO: Implement buildContainer() method.
    }
}
