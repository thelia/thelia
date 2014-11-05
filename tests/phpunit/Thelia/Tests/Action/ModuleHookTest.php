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

use Thelia\Action\ModuleHook;
use Thelia\Core\Event\Hook\ModuleHookCreateEvent;
use Thelia\Core\Event\Hook\ModuleHookDeleteEvent;
use Thelia\Core\Event\Hook\ModuleHookToggleActivationEvent;
use Thelia\Core\Event\Hook\ModuleHookUpdateEvent;
use Thelia\Model\Hook as HookModel;
use Thelia\Model\Module as ModuleModel;
use Thelia\Model\ModuleHook as ModuleHookModel;
use Thelia\Model\HookQuery;
use Thelia\Model\ModuleQuery;

/**
 * Class HookTest
 * @package Thelia\Tests\Action
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface */
    protected $dispatcher;

    /** @var ModuleHook $action */
    protected $action;

    /** @var ModuleModel */
    protected $module;

    /** @var HookModel */
    protected $hook;

    public function setUp()
    {
        $this->dispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->action = new ModuleHook($stubContainer);

        $this->module = ModuleQuery::create()->findOneByActivate(1);

        $this->hook = HookQuery::create()->findOneByActivate(true);
    }

    public function testCreate()
    {
        $event = new ModuleHookCreateEvent();
        $event
            ->setHookId($this->hook->getId())
            ->setModuleId($this->module->getId())
            ->setDispatcher($this->dispatcher);

        $this->action->createModuleHook($event);

        $createdModuleHook = $event->getModuleHook();

        $this->assertInstanceOf('\Thelia\Model\ModuleHook', $createdModuleHook);
        $this->assertFalse($createdModuleHook->isNew());
        $this->assertTrue($event->hasModuleHook());

        $this->assertEquals($event->getHookId(), $createdModuleHook->getHookId());
        $this->assertEquals($event->getModuleId(), $createdModuleHook->getHookId());

        return $createdModuleHook;
    }

    /**
     * @params ModuleHookModel $hook
     * @depends testCreate
     */
    public function testToggleActivation(ModuleHookModel $moduleHook)
    {
        $activated = $moduleHook->getActive();

        $event = new ModuleHookToggleActivationEvent($moduleHook);
        $event->setDispatcher($this->dispatcher);

        $this->action->toggleModuleHookActivation($event);
        $updatedModuleHook = $event->getModuleHook();

        $this->assertEquals(!$activated, $updatedModuleHook->getActive());

        return $updatedModuleHook;
    }

    /**
     * @params ModuleHookModel $hook
     * @depends testToggleActivation
     */
    public function testUpdate(ModuleHookModel $moduleHook)
    {
        $event = new ModuleHookUpdateEvent($moduleHook);

        $event
            ->setHookId($moduleHook->getHookId())
            ->setClassname($moduleHook->getClassname())
            ->setMethod($moduleHook->getMethod())
            ->setActive(true)
            ->setDispatcher($this->dispatcher);

        $this->action->updateModuleHook($event);

        $updatedModuleHook = $event->getModuleHook();

        $this->assertEquals($event->getHookId(), $updatedModuleHook->getHookId());
        $this->assertEquals($event->getClassname(), $updatedModuleHook->getClassname());
        $this->assertEquals($event->getMethod(), $updatedModuleHook->getMethod());
        $this->assertEquals($event->getActive(), $updatedModuleHook->getActive());

        return $updatedModuleHook;
    }

    /**
     * @params ModuleHookModel $hook
     * @depends testUpdate
     */
    public function testDelete(ModuleHookModel $moduleHook)
    {
        $event = new ModuleHookDeleteEvent($moduleHook->getId());

        $event->setDispatcher($this->dispatcher);

        $this->action->deleteModuleHook($event);

        $deletedModuleHook = $event->getModuleHook();

        $this->assertInstanceOf('Thelia\Model\ModuleHook', $deletedModuleHook);
        $this->assertTrue($deletedModuleHook->isDeleted());
    }
}
