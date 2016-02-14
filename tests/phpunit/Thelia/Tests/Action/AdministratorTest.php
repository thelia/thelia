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

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Action\Administrator;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\AdminQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;

/**
 * Class AdministratorTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AdministratorTest extends BaseAction
{
    protected $mailerFactory;
    protected $tokenProvider;

    public function setUp()
    {
        $session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($session);
        
        $this->mailerFactory = $this->getMockBuilder("Thelia\\Mailer\\MailerFactory")
            ->disableOriginalConstructor()
            ->getMock();

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = new Translator(new Container());

        $this->tokenProvider = new TokenProvider($requestStack, $translator, 'test');
    }

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
            ->setEmail(uniqid().'@example.com')
        ;

        $admin = new Administrator($this->mailerFactory, $this->tokenProvider);

        $admin->create($adminEvent, null, $this->getMockEventDispatcher());

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
            ->setEmail(uniqid().'@example.com')
            ->setDispatcher($this->getMockEventDispatcher())
        ;

        $actionAdmin = new Administrator($this->mailerFactory, $this->tokenProvider);

        $actionAdmin->update($adminEvent, null, $this->getMockEventDispatcher());

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
        ;

        $actionAdmin = new Administrator($this->mailerFactory, $this->tokenProvider);

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
            ->setPassword('toto');

        $actionAdmin = new Administrator($this->mailerFactory, $this->tokenProvider);

        $actionAdmin->updatePassword($adminEvent);

        $updatedAdmin = $adminEvent->getAdmin();

        $this->assertInstanceOf("Thelia\Model\Admin", $updatedAdmin);
        $this->assertTrue(password_verify($adminEvent->getPassword(), $updatedAdmin->getPassword()));
    }

    public function testRenewPassword()
    {
        $admin = AdminQuery::create()->findOne();
        $admin->setPasswordRenewToken(null)->setEmail('no_reply@thelia.net')->save();

        $adminEvent = new AdministratorEvent($admin);

        $actionAdmin = new Administrator($this->mailerFactory, $this->tokenProvider);
        $actionAdmin->createPassword($adminEvent);

        $updatedAdmin = $adminEvent->getAdministrator();

        $this->assertInstanceOf("Thelia\Model\Admin", $updatedAdmin);
        $this->assertNotEmpty($admin->getPasswordRenewToken());
    }
}
