<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Action;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Module as ModuleAction;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Module as ModuleModel;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\Validator\ModuleDefinition;

/**
 * Uploading a newer zip of an installed module deactivates the current one, deletes it, then
 * installs the new files. A payment or a delivery module referenced by an order cannot be deleted,
 * so the replacement is refused — and the shop must not be left with its payment method switched
 * off, which takes the checkout down while the administrator only reads that the install failed.
 */
class ModuleInstallTest extends BaseAction
{
    /** @var EventDispatcher */
    private $dispatcher;

    /** @var ModuleAction */
    private $action;

    /** @var ModuleModel */
    private $module;

    protected function setUp(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module, 'The Cheque module must be installed to run this test.');

        $this->module = $module;
        $this->module->setActivate(BaseModule::IS_ACTIVATED)->save();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', THELIA_CACHE_DIR);
        $container->set('thelia.translator', Translator::getInstance());

        $this->action = new ModuleAction($container);

        $this->dispatcher = new EventDispatcher();
        $container->set('event_dispatcher', $this->dispatcher);

        $this->dispatcher->addListener(TheliaEvents::MODULE_TOGGLE_ACTIVATION, [$this->action, 'checkToggleActivation'], 255);
        $this->dispatcher->addListener(TheliaEvents::MODULE_TOGGLE_ACTIVATION, [$this->action, 'toggleActivation'], 128);
        $this->dispatcher->addListener(TheliaEvents::CACHE_CLEAR, function (): void {});
    }

    public function testTheDeleteEventIsUsableByTheDeleteListener(): void
    {
        $seen = null;

        $this->dispatcher->addListener(TheliaEvents::MODULE_DELETE, function (ModuleDeleteEvent $event) use (&$seen): void {
            // The two values Module::delete() reads out of the event.
            $seen = [$event->getModuleId(), $event->getDeleteData()];

            throw new \LogicException('Deletion refused');
        });

        try {
            $this->install();
        } catch (\LogicException $exception) {
            // The refusal itself is the subject of the next test.
        }

        self::assertSame(
            [$this->module->getId(), false],
            $seen,
            'Upgrading a module must dispatch a delete event carrying its id and no delete-data flag.'
        );
    }

    public function testARefusedUpgradeLeavesTheModuleActivated(): void
    {
        // Stands for the guard of Module::delete(): a module an order was placed with is not deletable.
        $this->dispatcher->addListener(TheliaEvents::MODULE_DELETE, function (): void {
            throw new \LogicException('The module "Cheque" is currently in use by at least one order.');
        });

        try {
            $this->install();

            self::fail('Installing over a module an order was placed with should have been refused.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('in use by at least one order', $exception->getMessage());
        }

        self::assertSame(
            BaseModule::IS_ACTIVATED,
            ModuleQuery::create()->findPk($this->module->getId())->getActivate(),
            'A refused upgrade must leave the module activated, not silently switched off.'
        );
    }

    private function install(): void
    {
        $definition = new ModuleDefinition();
        $definition->setCode($this->module->getCode());
        $definition->setNamespace($this->module->getFullNamespace());
        $definition->setVersion('99.0.0');

        $event = new ModuleInstallEvent();
        $event->setModuleDefinition($definition);
        $event->setModulePath($this->module->getAbsoluteBaseDir());

        $this->action->install($event, TheliaEvents::MODULE_INSTALL, $this->dispatcher);
    }
}
