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

use Thelia\Action\Administrator;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Model\AdminQuery;
use Thelia\Model\LangQuery;

/**
 * Class AdministratorTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AdministratorTest extends \PHPUnit_Framework_TestCase
{

    public function testCreate()
    {
        $login = 'thelia'.uniqid();
        $locale = LangQuery::create()->findOne()->getLocale();
        $adminEvent = new AdministratorEvent();
        $adminEvent
            ->setFirstname('thelia')
            ->setLastname('thelia')
            ->setLogin($login)
            ->setPassword('azerty')
            ->setLocale($locale)
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"))
        ;

        $admin = new Administrator();
        $admin->create($adminEvent);

        $createdAdmin = $adminEvent->getAdministrator();

        $this->assertInstanceOf("Thelia\Model\Admin", $createdAdmin);
        $this->assertFalse($createdAdmin->isNew());

        $this->assertEquals($adminEvent->getFirstname(), $createdAdmin->getFirstname());
        $this->assertEquals($adminEvent->getLastname(), $createdAdmin->getLastname());
        $this->assertEquals($adminEvent->getLogin(), $createdAdmin->getLogin());
        $this->assertEquals($adminEvent->getLocale(), $createdAdmin->getLocale());
        $this->assertEquals($adminEvent->getProfile(), $createdAdmin->getProfileId());
        $this->assertTrue(password_verify($adminEvent->getPassword(), $createdAdmin->getPassword()));
    }

    public function testUpdate()
    {
        $admin = AdminQuery::create()->findOne();

        $login = 'thelia'.uniqid();
        $locale = LangQuery::create()->findOne()->getLocale();
        $adminEvent = new AdministratorEvent();
        $adminEvent
            ->setId($admin->getId())
            ->setFirstname('thelia_update')
            ->setLastname('thelia_update')
            ->setLogin($login)
            ->setPassword('azertyuiop')
            ->setLocale($locale)
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"))
        ;

        $actionAdmin = new Administrator();
        $actionAdmin->update($adminEvent);

        $updatedAdmin = $adminEvent->getAdministrator();

        $this->assertInstanceOf("Thelia\Model\Admin", $updatedAdmin);
        $this->assertFalse($updatedAdmin->isNew());

        $this->assertEquals($adminEvent->getFirstname(), $updatedAdmin->getFirstname());
        $this->assertEquals($adminEvent->getLastname(), $updatedAdmin->getLastname());
        $this->assertEquals($adminEvent->getLogin(), $updatedAdmin->getLogin());
        $this->assertEquals($adminEvent->getLocale(), $updatedAdmin->getLocale());
        $this->assertEquals($adminEvent->getProfile(), $updatedAdmin->getProfileId());
        $this->assertTrue(password_verify($adminEvent->getPassword(), $updatedAdmin->getPassword()));
    }

    public function testDelete()
    {
        $admin = AdminQuery::create()->findOne();

        $adminEvent = new AdministratorEvent();

        $adminEvent
            ->setId($admin->getId())
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"))
        ;

        $actionAdmin = new Administrator();
        $actionAdmin->delete($adminEvent);

        $deletedAdmin = $adminEvent->getAdministrator();

        $this->assertInstanceOf("Thelia\Model\Admin", $deletedAdmin);
        $this->assertTrue($deletedAdmin->isDeleted());
    }

    public function testUpdatePassword()
    {
        $admin = AdminQuery::create()->findOne();

        $adminEvent = new AdministratorUpdatePasswordEvent($admin);
        $adminEvent
            ->setPassword('toto')
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $actionAdmin = new Administrator();
        $actionAdmin->updatePassword($adminEvent);

        $updatedAdmin = $adminEvent->getAdmin();

        $this->assertInstanceOf("Thelia\Model\Admin", $updatedAdmin);
        $this->assertTrue(password_verify($adminEvent->getPassword(), $updatedAdmin->getPassword()));
    }

}
