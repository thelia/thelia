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
class ModuleHookTest extends BaseAction
{
    /** @var ModuleHook $action */
    protected $action;

    /** @var ModuleModel */
    protected $module;

    /** @var HookModel */
    protected $hook;

    public function setUp()
    {
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->action = new ModuleHook($stubContainer, $this->getMockEventDispatcher());

        $this->module = ModuleQuery::create()->findOneByActivate(1);

        $this->hook = HookQuery::create()->findOneByActivate(true);
    }

    public function testCreate()
    {
        $event = new ModuleHookCreateEvent();
        $event
            ->setHookId($this->hook->getId())
            ->setModuleId($this->module->getId());

        $this->action->createModuleHook($event);

        $createdModuleHook = $event->getModuleHook();

        $this->assertInstanceOf('\Thelia\Model\ModuleHook', $createdModuleHook);
        $this->assertFalse($createdModuleHook->isNew());
        $this->assertTrue($event->hasModuleHook());

        $this->assertEquals($event->getHookId(), $createdModuleHook->getHookId());
        $this->assertEquals($event->getModuleId(), $createdModuleHook->getModuleId());

        return $createdModuleHook;
    }

    /**
     * @param ModuleHookModel $moduleHook
     * @depends testCreate
     * @return ModuleHookModel
     */
    public function testToggleActivation(ModuleHookModel $moduleHook)
    {
        $activated = $moduleHook->getActive();

        $event = new ModuleHookToggleActivationEvent($moduleHook);

        $this->action->toggleModuleHookActivation($event, null, $this->getMockEventDispatcher());
        $updatedModuleHook = $event->getModuleHook();

        $this->assertEquals(!$activated, $updatedModuleHook->getActive());

        return $updatedModuleHook;
    }

    /**
     * @param ModuleHookModel $moduleHook
     * @depends testToggleActivation
     * @return ModuleHookModel
     */
    public function testUpdate(ModuleHookModel $moduleHook)
    {
        $event = new ModuleHookUpdateEvent($moduleHook);

        $event
            ->setHookId($moduleHook->getHookId())
            ->setClassname($moduleHook->getClassname())
            ->setMethod($moduleHook->getMethod())
            ->setActive(true);

        $this->action->updateModuleHook($event, null, $this->getMockEventDispatcher());

        $updatedModuleHook = $event->getModuleHook();

        $this->assertEquals($event->getHookId(), $updatedModuleHook->getHookId());
        $this->assertEquals($event->getClassname(), $updatedModuleHook->getClassname());
        $this->assertEquals($event->getMethod(), $updatedModuleHook->getMethod());
        $this->assertEquals($event->getActive(), $updatedModuleHook->getActive());

        return $updatedModuleHook;
    }

    /**
     * @param ModuleHookModel $moduleHook
     * @depends testUpdate
     * @return ModuleHookModel
     */
    public function testDelete(ModuleHookModel $moduleHook)
    {
        $event = new ModuleHookDeleteEvent($moduleHook->getId());

        $this->action->deleteModuleHook($event, null, $this->getMockEventDispatcher());

        $deletedModuleHook = $event->getModuleHook();

        $this->assertInstanceOf('Thelia\Model\ModuleHook', $deletedModuleHook);
        $this->assertTrue($deletedModuleHook->isDeleted());
    }
}
