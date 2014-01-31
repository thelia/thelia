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


} 