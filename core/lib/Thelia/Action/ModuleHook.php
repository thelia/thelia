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
use Thelia\Model\Base\ModuleHookQuery;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;


/**
 * Class ModuleHook
 * @package Thelia\Action
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHook extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function toggleModuleActivation(ModuleToggleActivationEvent $event)
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            Tlog::getInstance()->debug(sprintf(" GU %s %s", "toggle Module activation", $event->getModuleId()));
            ModuleHookQuery::create()
                ->filterByModuleId($module->getId())
                ->update(array('ModuleActive' => ! ($module->getActivate() == BaseModule::IS_ACTIVATED)));

            // already done
            // $this->cacheClear($event->getDispatcher());
        }
        return $event;
    }

    public function deleteModule(ModuleDeleteEvent $event)
    {
        if ($event->getModuleId()) {
            Tlog::getInstance()->debug(sprintf(" GU %s %s", "delete Module", $event->getModuleId()));

            ModuleHookQuery::create()
                ->filterByModuleId($event->getModuleId())
                ->delete();

            // already done
            // $this->cacheClear($event->getDispatcher());
        }
        return $event;
    }

    public function toggleHookActivation(ModuleDeleteEvent $event)
    {
        // Todo update module hook for the module hook
        return $event;
    }

    public function updateHookPosition(ModuleDeleteEvent $event)
    {
        // Todo update module hook for the module hook
        return $event;
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
            TheliaEvents::MODULE_TOGGLE_ACTIVATION => array('toggleModuleActivation', 256),
            TheliaEvents::MODULE_DELETE => array('deleteModule', 256),
            TheliaEvents::HOOK_UPDATE_POSITION => array('updateHookPosition', 128),
            TheliaEvents::HOOK_TOGGLE_ACTIVATION => array('toggleHookActivation', 128),
            // TheliaEvents::MODULE_HOOK_UPDATE_CONTEXT => array('updateModuleHookContext', 128),
        );
    }
}
