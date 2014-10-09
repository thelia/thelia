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

use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;

use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class Module
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@thelia.net>
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

    public function delete(ModuleDeleteEvent $event)
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                if (null === $module->getFullNamespace()) {
                    throw new \LogicException(
                        Translator::getInstance()->trans(
                            'Cannot instanciante module "%name%": the namespace is null. Maybe the model is not loaded ?',
                            array('%name%' => $module->getCode())
                        )
                    );
                }

                try {
                    $instance = $module->createInstance();

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
                            array('%name%' => $module->getCode())
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
                ->setPostscriptum($event->getPostscriptum())
            ;

            $module->save();

            $event->setModule($module);
        }
    }

    /**
     * Call the payment method of the payment module of the given order
     *
     * @param  OrderPaymentEvent $event
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
                    array(
                        "%mid" => $order->getPaymentModuleId(),
                        "%oid" => $order->getId()
                    )
                )
            );
        }

        $paymentModuleInstance = $paymentModule->getModuleInstance($this->container);

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
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::MODULE_TOGGLE_ACTIVATION => array('toggleActivation', 128),
            TheliaEvents::MODULE_UPDATE_POSITION => array('updatePosition', 128),
            TheliaEvents::MODULE_DELETE => array('delete', 128),
            TheliaEvents::MODULE_UPDATE => array('update', 128),
            TheliaEvents::MODULE_PAY => array('pay', 128),
        );
    }
}
