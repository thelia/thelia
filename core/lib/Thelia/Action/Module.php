<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Action;

use Exception;
use LogicException;
use ReflectionException;
use SplFileInfo;
use RuntimeException;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\FileNotFoundException;
use Thelia\Exception\ModuleException;
use Thelia\Log\Tlog;
use Thelia\Model\Base\OrderQuery;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\ModuleManagement;
use Thelia\Module\Validator\ModuleValidator;

/**
 * Class Module.
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class Module extends BaseAction implements EventSubscriberInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function toggleActivation(ModuleToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            $moduleInstance = $module->createInstance();

            if (method_exists($moduleInstance, 'setContainer')) {
                $moduleInstance->setContainer($this->container);
                if ($module->getActivate() == BaseModule::IS_ACTIVATED) {
                    $moduleInstance->deActivate($module);
                } else {
                    $moduleInstance->activate($module);
                }
            }

            $event->setModule($module);

            $this->cacheClear($dispatcher);
        }
    }

    public function checkToggleActivation(ModuleToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (true === $event->isNoCheck()) {
            return;
        }

        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            try {
                if ($module->getActivate() == BaseModule::IS_ACTIVATED) {
                    if ($module->getMandatory() == BaseModule::IS_MANDATORY && $event->getAssumeDeactivate() === false) {
                        throw new Exception(
                            Translator::getInstance()->trans("Can't deactivate a secure module")
                        );
                    }

                    if ($event->isRecursive()) {
                        $this->recursiveDeactivation($event, $eventName, $dispatcher);
                    }

                    $this->checkDeactivation($module);
                } else {
                    if ($event->isRecursive()) {
                        $this->recursiveActivation($event, $eventName, $dispatcher);
                    }

                    $this->checkActivation($module);
                }
            } catch (Exception $ex) {
                $event->stopPropagation();
                throw $ex;
            }
        }
    }

    /**
     * Check if module can be activated : supported version of Thelia, module dependencies.
     *
     * @param \Thelia\Model\Module $module
     *
     * @throws Exception if activation fails
     *
     * @return bool true if the module can be activated, otherwise false
     */
    private function checkActivation($module): bool
    {
        $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());
        $moduleValidator->validate(false);
        return true;
    }

    /**
     * Check if module can be deactivated safely because other modules
     * could have dependencies to this module.
     *
     * @param \Thelia\Model\Module $module
     *
     * @return bool true if the module can be deactivated, otherwise false
     */
    private function checkDeactivation($module): bool
    {
        $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());

        $modules = $moduleValidator->getModulesDependOf();

        if ($modules !== []) {
            $moduleList = implode(', ', array_column($modules, 'code'));

            $message = (\count($modules) == 1)
                ? Translator::getInstance()->trans(
                    '%s has dependency to module %s. You have to deactivate this module before.'
                )
                : Translator::getInstance()->trans(
                    '%s have dependencies to module %s. You have to deactivate these modules before.'
                );

            throw new ModuleException(
                sprintf($message, $moduleList, $moduleValidator->getModuleDefinition()->getCode())
            );
        }

        return true;
    }

    /**
     * Get dependencies of the current module and activate it if needed.
     */
    public function recursiveActivation(ModuleToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());
            $dependencies = $moduleValidator->getCurrentModuleDependencies();
            foreach ($dependencies as $defMod) {
                $submodule = ModuleQuery::create()
                    ->findOneByCode($defMod['code']);
                if ($submodule && $submodule->getActivate() != BaseModule::IS_ACTIVATED) {
                    $subevent = new ModuleToggleActivationEvent($submodule->getId());
                    $subevent->setRecursive(true);
                    $dispatcher->dispatch($subevent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
                }
            }
        }
    }

    /**
     * Get modules having current module in dependence and deactivate it if needed.
     */
    public function recursiveDeactivation(ModuleToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());
            $dependencies = $moduleValidator->getModulesDependOf(true);
            foreach ($dependencies as $defMod) {
                $submodule = ModuleQuery::create()
                    ->findOneByCode($defMod['code']);
                if ($submodule && $submodule->getActivate() == BaseModule::IS_ACTIVATED) {
                    $subevent = new ModuleToggleActivationEvent($submodule->getId());
                    $subevent->setRecursive(true);
                    $dispatcher->dispatch($subevent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
                }
            }
        }
    }

    public function delete(ModuleDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId(), $con)) {
            try {
                if (null === $module->getFullNamespace()) {
                    throw new LogicException(
                        Translator::getInstance()->trans(
                            'Cannot instantiate module "%name%": the namespace is null. Maybe the model is not loaded ?',
                            ['%name%' => $module->getCode()]
                        )
                    );
                }

                // If the module is referenced by an order, display a meaningful error
                // instead of 'delete cannot delete' caused by a constraint violation.
                // FIXME: we hav to find a way to delete modules used by order.
                if (OrderQuery::create()->filterByDeliveryModuleId($module->getId())->count() > 0
                    || OrderQuery::create()->filterByPaymentModuleId($module->getId())->count() > 0
                ) {
                    throw new LogicException(
                        Translator::getInstance()->trans(
                            'The module "%name%" is currently in use by at least one order, and can\'t be deleted.',
                            ['%name%' => $module->getCode()]
                        )
                    );
                }

                try {
                    if ($module->getMandatory() == BaseModule::IS_MANDATORY && $event->getAssumeDelete() === false) {
                        throw new Exception(
                            Translator::getInstance()->trans("Can't remove a core module")
                        );
                    }

                    // First, try to create an instance
                    $instance = $module->createInstance();

                    // Then, if module is activated, check if we can deactivate it
                    if ($module->getActivate()) {
                        // check for modules that depend of this one
                        $this->checkDeactivation($module);
                    }

                    $instance->setContainer($this->container);

                    $path = $module->getAbsoluteBaseDir();

                    $instance->destroy($con, $event->getDeleteData());

                    $fs = new Filesystem();
                    $fs->remove($path);
                } catch (ReflectionException) {
                    // Happens probably because the module directory has been deleted.
                    // Log a warning, and delete the database entry.
                    Tlog::getInstance()->addWarning(
                        Translator::getInstance()->trans(
                            'Failed to create instance of module "%name%" when trying to delete module. Module directory has probably been deleted',
                            ['%name%' => $module->getCode()]
                        )
                    );
                } catch (FileNotFoundException) {
                    // The module directory has been deleted.
                    // Log a warning, and delete the database entry.
                    Tlog::getInstance()->addWarning(
                        Translator::getInstance()->trans(
                            'Module "%name%" directory was not found',
                            ['%name%' => $module->getCode()]
                        )
                    );
                }

                $module->delete($con);

                $con->commit();

                $event->setModule($module);
                $this->cacheClear($dispatcher);
            } catch (Exception $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    public function update(ModuleEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getId())) {
            $module

                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setChapo($event->getChapo())
                ->setDescription($event->getDescription())
                ->setPostscriptum($event->getPostscriptum());

            $module->save();

            $event->setModule($module);
        }
    }

    /**
     * @throws Exception
     * @throws IOException
     * @throws Exception
     */
    public function install(ModuleInstallEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $moduleDefinition = $event->getModuleDefinition();

        $oldModule = ModuleQuery::create()->findOneByFullNamespace($moduleDefinition->getNamespace());

        $fs = new Filesystem();

        $activated = false;

        // check existing module
        if (null !== $oldModule) {
            $activated = $oldModule->getActivate();

            if ($activated) {
                // deactivate
                $toggleEvent = new ModuleToggleActivationEvent($oldModule->getId());
                // disable the check of the module because it's already done
                $toggleEvent->setNoCheck(true);

                $dispatcher->dispatch($toggleEvent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
            }

            // delete
            $modulePath = $oldModule->getAbsoluteBaseDir();

            $deleteEvent = new ModuleDeleteEvent($oldModule->getId());

            try {
                $dispatcher->dispatch($deleteEvent, TheliaEvents::MODULE_DELETE);
            } catch (Exception $ex) {
                // if module has not been deleted
                if ($fs->exists($modulePath)) {
                    throw $ex;
                }
            }
        }

        // move new module
        $modulePath = sprintf('%s%s', THELIA_MODULE_DIR, $event->getModuleDefinition()->getCode());

        try {
            $fs->mirror($event->getModulePath(), $modulePath);
        } catch (IOException $ioException) {
            if (!$fs->exists($modulePath)) {
                throw $ioException;
            }
        }

        // Update the module
        $moduleDescriptorFile = sprintf('%s%s%s%s%s', $modulePath, DS, 'Config', DS, 'module.xml');
        $eventDispatcher = $this->container->get('event_dispatcher');
        $moduleManagement = new ModuleManagement($this->container, $eventDispatcher);
        $file = new SplFileInfo($moduleDescriptorFile);
        $module = $moduleManagement->updateModule($file, $this->container);

        // activate if old was activated
        if ($activated) {
            $toggleEvent = new ModuleToggleActivationEvent($module->getId());
            $toggleEvent->setNoCheck(true);

            $dispatcher->dispatch($toggleEvent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
        }

        $event->setModule($module);
    }

    /**
     * Call the payment method of the payment module of the given order.
     *
     * @throws RuntimeException if no payment module can be found
     */
    public function pay(OrderPaymentEvent $event): void
    {
        $order = $event->getOrder();

        /* call pay method */
        if (null === $paymentModule = ModuleQuery::create()->findPk($order->getPaymentModuleId())) {
            throw new RuntimeException(
                Translator::getInstance()->trans(
                    'Failed to find a payment Module with ID=%mid for order ID=%oid',
                    [
                        '%mid' => $order->getPaymentModuleId(),
                        '%oid' => $order->getId(),
                    ]
                )
            );
        }

        $paymentModuleInstance = $paymentModule->getPaymentModuleInstance($this->container);

        $response = $paymentModuleInstance->pay($order);

        if (null !== $response && $response instanceof Response) {
            $event->setResponse($response);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(ModuleQuery::create(), $event, $dispatcher);

        $this->cacheClear($dispatcher);
    }

    protected function cacheClear(EventDispatcherInterface $dispatcher): void
    {
        $cacheEvent = new CacheEvent(
            $this->container->getParameter('kernel.cache_dir')
        );

        $dispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::MODULE_TOGGLE_ACTIVATION => [
                ['checkToggleActivation', 255],
                ['toggleActivation', 128],
            ],
            TheliaEvents::MODULE_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::MODULE_DELETE => ['delete', 128],
            TheliaEvents::MODULE_UPDATE => ['update', 128],
            TheliaEvents::MODULE_INSTALL => ['install', 128],
            TheliaEvents::MODULE_PAY => ['pay', 128],
        ];
    }
}
