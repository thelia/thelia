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

namespace Thelia\Tests\Action;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Action\Lang;
use Thelia\Core\Event\Lang\LangDeleteEvent;
use Thelia\Core\Event\Lang\LangToggleDefaultEvent;
use Thelia\Core\Event\Lang\LangUpdateEvent;
use Thelia\Model\LangQuery;
use Thelia\Model\Lang as LangModel;
use Thelia\Core\Event\Lang\LangCreateEvent;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * Class LangTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class LangTest extends ContainerAwareTestCase
{
    protected $dispatcher;

    protected static $defaultId;

    public static function setUpBeforeClass()
    {
        $lang = LangQuery::create()
            ->filterByByDefault(1)
            ->findOne();

        self::$defaultId = $lang->getId();
    }

    public function setUp()
    {
        parent::setUp();
        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    public function testCreate()
    {
        $event = new LangCreateEvent();

        $event
            ->setLocale('te_TE')
            ->setTitle('test')
            ->setCode('TES')
            ->setDateFormat('Y-m-d')
            ->setTimeFormat('H:i:s')
            ->setDecimalSeparator(".")
            ->setThousandsSeparator(" ")
            ->setDecimals("2")
            ->setDispatcher($this->dispatcher)
        ;

        $action = new Lang();
        $action->create($event);

        $createdLang = $event->getLang();

        $this->assertInstanceOf('Thelia\Model\Lang', $createdLang);

        $this->assertFalse($createdLang->isNew());

        $this->assertEquals('te_TE', $createdLang->getLocale());
        $this->assertEquals('test', $createdLang->getTitle());
        $this->assertEquals('TES', $createdLang->getCode());
        $this->assertEquals('Y-m-d', $createdLang->getDateFormat());
        $this->assertEquals('H:i:s', $createdLang->getTimeFormat());
        $this->assertEquals('.', $createdLang->getDecimalSeparator());
        $this->assertEquals(' ', $createdLang->getThousandsSeparator());
        $this->assertEquals('2', $createdLang->getDecimals());
        $this->assertEquals('Y-m-d H:i:s', $createdLang->getDatetimeFormat());

        return $createdLang;
    }

    /**
     * @param LangModel $lang
     * @depends testCreate
     */
    public function testUpdate(LangModel $lang)
    {
        $event = new LangUpdateEvent($lang->getId());

        $event
            ->setLocale('te_TE')
            ->setTitle('test update')
            ->setCode('TEST')
            ->setDateFormat('d-m-Y')
            ->setTimeFormat('H-i-s')
            ->setDecimalSeparator(",")
            ->setThousandsSeparator(".")
            ->setDecimals("1")
            ->setDispatcher($this->dispatcher)
        ;

        $action = new Lang();
        $action->update($event);

        $updatedLang = $event->getLang();

        $this->assertInstanceOf('Thelia\Model\Lang', $updatedLang);

        $this->assertEquals('te_TE', $updatedLang->getLocale());
        $this->assertEquals('TEST', $updatedLang->getCode());
        $this->assertEquals('test update', $updatedLang->getTitle());
        $this->assertEquals('d-m-Y', $updatedLang->getDateFormat());
        $this->assertEquals('H-i-s', $updatedLang->getTimeFormat());
        $this->assertEquals(',', $updatedLang->getDecimalSeparator());
        $this->assertEquals('.', $updatedLang->getThousandsSeparator());
        $this->assertEquals('1', $updatedLang->getDecimals());
        $this->assertEquals('d-m-Y H-i-s', $updatedLang->getDatetimeFormat());

        return $updatedLang;
    }

    /**
     * @param LangModel $lang
     * @depends testUpdate
     */
    public function testToggleDefault(LangModel $lang)
    {
        $event = new LangToggleDefaultEvent($lang->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Lang();
        $action->toggleDefault($event);

        $updatedLang = $event->getLang();

        $this->assertInstanceOf('Thelia\Model\Lang', $updatedLang);

        $this->assertEquals('1', $updatedLang->getByDefault());

        $this->assertEquals('1', LangQuery::create()->filterByByDefault(1)->count());

        return $updatedLang;
    }

    /**
     * @param LangModel $lang
     * @depends testToggleDefault
     */
    public function testDelete(LangModel $lang)
    {
        $lang->setByDefault(0)
            ->save();

        $event = new LangDeleteEvent($lang->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Lang();
        $action->delete($event);

        $deletedLang = $event->getLang();

        $this->assertInstanceOf('Thelia\Model\Lang', $deletedLang);

        $this->assertTrue($deletedLang->isDeleted());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage It is not allowed to delete the default language
     */
    public function testDeleteDefault()
    {
        self::tearDownAfterClass();
        $lang = LangQuery::create()->findOneByByDefault(1);

        $event = new LangDeleteEvent($lang->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Lang();
        $action->delete($event);
    }

    public static function tearDownAfterClass()
    {
        LangQuery::create()
            ->filterById(self::$defaultId)
            ->update(array('ByDefault' => true));
    }

    protected function tearDown()
    {
        @unlink(THELIA_TEMPLATE_DIR . "/backOffice/default/assets/img/flags/TEST.png");
        @unlink(THELIA_TEMPLATE_DIR . "/backOffice/default/assets/img/flags/TES.png");
    }

    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        // TODO: Implement buildContainer() method.
    }
}
