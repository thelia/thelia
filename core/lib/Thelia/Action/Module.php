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

namespace Thelia\Action;

use Exception;
use Propel\Runtime\Propel;
use SplFileInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
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
 * Class Module
 * @package Thelia\Action
 * @author  Manuel Raynaud <manu@thelia.net>
 */
class Module extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function toggleActivation(ModuleToggleActivationEvent $event)
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

            $this->cacheClear($event->getDispatcher());
        }
    }

    public function checkToggleActivation(ModuleToggleActivationEvent $event)
    {
        if (true === $event->isNoCheck()) {
            return;
        }

        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            try {
                if ($module->getActivate() == BaseModule::IS_ACTIVATED) {
                    $this->checkDeactivation($module);
                } else {
                    $this->checkActivation($module);
                }
            } catch (\Exception $ex) {
                $event->stopPropagation();
                throw $ex;
            }
        }
    }

    /**
     * Check if module can be activated : supported version of Thelia, module dependencies.
     *
     * @param \Thelia\Model\Module $module
     * @throws Exception if activation fails.
     * @return bool true if the module can be activated, otherwise false
     */
    private function checkActivation($module)
    {
        try {
            $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());
            $moduleValidator->validate(false);
        } catch (\Exception $ex) {
            throw $ex;
        }

        return true;
    }

    /**
     * Check if module can be deactivated safely because other modules
     * could have dependencies to this module
     *
     * @param  \Thelia\Model\Module $module
     * @return bool true if the module can be deactivated, otherwise false
     */
    private function checkDeactivation($module)
    {
        $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());

        $modules = $moduleValidator->getModulesDependOf();

        if (count($modules) > 0) {
            $moduleList = implode(', ', array_column($modules, 'code'));
            $message = (count($modules) == 1)
                ? Translator::getInstance()->trans(
                    '%s has dependency to module %s. You have to deactivate this module before.'
                )
                : Translator::getInstance()->trans(
                    '%s have dependencies to module %s. You have to deactivate these modules before.'
                )
            ;

            throw new ModuleException(
                sprintf($message, $moduleList, $moduleValidator->getModuleDefinition()->getCode())
            );
        }

        return true;
    }

    public function delete(ModuleDeleteEvent $event)
    {
        $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId(), $con)) {
            try {
                if (null === $module->getFullNamespace()) {
                    throw new \LogicException(
                        Translator::getInstance()->trans(
                            'Cannot instantiate module "%name%": the namespace is null. Maybe the model is not loaded ?',
                            ['%name%' => $module->getCode()]
                        )
                    );
                }

                try {
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
                } catch (\ReflectionException $ex) {
                    // Happens probably because the module directory has been deleted.
                    // Log a warning, and delete the database entry.
                    Tlog::getInstance()->addWarning(
                        Translator::getInstance()->trans(
                            'Failed to create instance of module "%name%" when trying to delete module. Module directory has probably been deleted',
                            ['%name%' => $module->getCode()]
                        )
                    );
                } catch (FileNotFoundException $fnfe) {
                    // The module directory has been deleted.
                    // Log a warning, and delete the database entry.
                    Tlog::getInstance()->addWarning(
                        Translator::getInstance()->trans(
                            'Module "%name%" directory was not found',
                            ['%name%' => $module->getCode()]
                        )
                    );
                }

                // If the module is referenced by an order, display a meaningful error
                // instead of 'delete cannot delete' caused by a constraint violation.
                // FIXME: we hav to find a way to delete modules used by order.
                if (
                    null !== OrderQuery::create()->findByDeliveryModuleId($module->getId())
                    ||
                    null !== OrderQuery::create()->findByDeliveryModuleId($module->getId())
                ) {
                    throw new \LogicException(
                        Translator::getInstance()->trans(
                            'The module "%name%" is currently in use by at least one order, and can\'t be deleted.',
                            ['%name%' => $module->getCode()]
                        )
                    );
                }

                $module->delete($con);

                $con->commit();

                $event->setModule($module);
                $this->cacheClear($event->getDispatcher());
            } catch (\Exception $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    /**
     * @param ModuleEvent $event
     */
    public function update(ModuleEvent $event)
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getId())) {
            $module
                ->setDispatcher($event->getDispatcher())
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
     * @param \Thelia\Core\Event\Module\ModuleInstallEvent $event
     *
     * @throws \Exception
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     * @throws \Exception
     */
    public function install(ModuleInstallEvent $event)
    {
        $moduleDefinition = $event->getModuleDefinition();

        $oldModule = ModuleQuery::create()->findOneByFullNamespace($moduleDefinition->getNamespace());

        $dispatcher = $event->getDispatcher();

        $fs = new Filesystem();

        $activated = false;

        // check existing module
        if (null !== $oldModule) {
            $activated = $oldModule->getActivate();

            if ($activated) {
                // deactivate
                $toggleEvent = new ModuleToggleActivationEvent($oldModule);
                // disable the check of the module because it's already done
                $toggleEvent->setNoCheck(true);
                $toggleEvent->setDispatcher($dispatcher);

                $dispatcher->dispatch(TheliaEvents::MODULE_TOGGLE_ACTIVATION, $toggleEvent);
            }

            // delete
            $modulePath = $oldModule->getAbsoluteBaseDir();

            $deleteEvent = new ModuleDeleteEvent($oldModule);
            $deleteEvent->setDispatcher($dispatcher);

            try {
                $dispatcher->dispatch(TheliaEvents::MODULE_DELETE, $deleteEvent);
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
        } catch (IOException $ex) {
            if (!$fs->exists($modulePath)) {
                throw $ex;
            }
        }

        // Update the module
        $moduleDescriptorFile = sprintf('%s%s%s%s%s', $modulePath, DS, 'Config', DS, 'module.xml');
        $moduleManagement = new ModuleManagement();
        $file = new SplFileInfo($moduleDescriptorFile);
        $module = $moduleManagement->updateModule($file, $this->container);

        // activate if old was activated
        if ($activated) {
            $toggleEvent = new ModuleToggleActivationEvent($module->getId());
            $toggleEvent->setNoCheck(true);
            $toggleEvent->setDispatcher($dispatcher);

            $dispatcher->dispatch(TheliaEvents::MODULE_TOGGLE_ACTIVATION, $toggleEvent);
        }

        $event->setModule($module);
    }

    /**
     * Call the payment method of the payment module of the given order
     *
     * @param OrderPaymentEvent $event
     *
     * @throws \RuntimeException if no payment module can be found.
     */
    public function pay(OrderPaymentEvent $event)
    {
        $order = $event->getOrder();

        /* call pay method */
        if (null === $paymentModule = ModuleQuery::create()->findPk($order->getPaymentModuleId())) {
            throw new \RuntimeException(
                Translator::getInstance()->trans(
                    "Failed to find a payment Module with ID=%mid for order ID=%oid",
                    [
                        "%mid" => $order->getPaymentModuleId(),
                        "%oid" => $order->getId()
                    ]
                )
            );
        }

        $paymentModuleInstance = $paymentModule->getPaymentModuleInstance($this->container);

        $response = $paymentModuleInstance->pay($order);

        if (null !== $response && $response instanceof \Symfony\Component\HttpFoundation\Response) {
            $event->setResponse($response);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(ModuleQuery::create(), $event);

        $this->cacheClear($event->getDispatcher());
    }

    protected function cacheClear(EventDispatcherInterface $dispatcher)
    {
        $cacheEvent = new CacheEvent(
            $this->container->getParameter('kernel.cache_dir')
        );

        $dispatcher->dispatch(TheliaEvents::CACHE_CLEAR, $cacheEvent);
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::MODULE_TOGGLE_ACTIVATION => [
                ['checkToggleActivation', 255],
                ['toggleActivation', 128]
            ],
            TheliaEvents::MODULE_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::MODULE_DELETE => ['delete', 128],
            TheliaEvents::MODULE_UPDATE => ['update', 128],
            TheliaEvents::MODULE_INSTALL => ['install', 128],
            TheliaEvents::MODULE_PAY => ['pay', 128],
        ];
    }
}
