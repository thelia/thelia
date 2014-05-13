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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Hook\ModuleHookToggleActivationEvent;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;

use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Log\Tlog;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class ModuleHook
 * @package Thelia\Action
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHook extends BaseAction  implements EventSubscriberInterface
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
        }

        return $event;
    }

    public function deleteModule(ModuleDeleteEvent $event)
    {
        if ($event->getModuleId()) {
            ModuleHookQuery::create()
                ->filterByModuleId($event->getModuleId())
                ->delete();
        }

        return $event;
    }

    public function toggleHookActivation(ModuleHookToggleActivationEvent $event)
    {
        if (null !== $moduleHook = $event->getModuleHook()) {
            if ($moduleHook->getModuleActive()) {
                $moduleHook->setActive(! $moduleHook->getActive());
                $moduleHook->save();
            } else {
                throw new \LogicException($this->getTranslator()->trans("The module has to be activated."));
            }
        }
        $this->cacheClear($event->getDispatcher());

        return $event;
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param   UpdatePositionEvent $event
     * @return  UpdatePositionEvent $event
     */
    public function updateHookPosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(ModuleHookQuery::create(), $event);
        $this->cacheClear($event->getDispatcher());

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
            TheliaEvents::MODULE_HOOK_UPDATE_POSITION => array('updateHookPosition', 128),
            TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION => array('toggleHookActivation', 128),
        );
    }
}
