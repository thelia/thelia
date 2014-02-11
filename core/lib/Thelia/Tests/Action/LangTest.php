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

namespace Thelia\Tests\Action;

use Thelia\Action\Lang;
use Thelia\Core\Event\Lang\LangDeleteEvent;
use Thelia\Core\Event\Lang\LangToggleDefaultEvent;
use Thelia\Core\Event\Lang\LangUpdateEvent;
use Thelia\Model\LangQuery;
use Thelia\Model\Lang as LangModel;
use Thelia\Core\Event\Lang\LangCreateEvent;


/**
 * Class LangTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class LangTest extends \PHPUnit_Framework_TestCase
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
        $event = new LangDeleteEvent($lang->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Lang();
        $action->delete($event);

        $deletedLang = $event->getLang();

        $this->assertInstanceOf('Thelia\Model\Lang', $deletedLang);

        $this->assertTrue($deletedLang->isDeleted());
    }

    public static function tearDownAfterClass()
    {
        LangQuery::create()
            ->filterById(self::$defaultId)
            ->update(array('ByDefault' => true));
    }

} 