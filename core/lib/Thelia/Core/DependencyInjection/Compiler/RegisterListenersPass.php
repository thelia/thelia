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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Thelia\Log\Tlog;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleQuery;


/**
 * Class RegisterListenersPass
 * @package Thelia\Core\DependencyInjection\Compiler
 *
 * Source code come from Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass class
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class RegisterListenersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('event_dispatcher')) {
            return;
        }

        $definition = $container->getDefinition('event_dispatcher');

        foreach ($container->findTaggedServiceIds('kernel.event_listener') as $id => $events) {
            foreach ($events as $event) {
                $priority = isset($event['priority']) ? $event['priority'] : 0;

                if (!isset($event['event'])) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "kernel.event_listener" tags.', $id));
                }

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace(array(
                        '/(?<=\b)[a-z]/ie',
                        '/[^a-z0-9]/i'
                    ), array('strtoupper("\\0")', ''), $event['event']);
                }

                $definition->addMethodCall('addListenerService', array($event['event'], array($id, $event['method']), $priority));
            }
        }

        foreach ($container->findTaggedServiceIds('kernel.event_subscriber') as $id => $attributes) {
            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $container->getDefinition($id)->getClass();

            $refClass = new \ReflectionClass($class);
            $interface = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';
            if (!$refClass->implementsInterface($interface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            $definition->addMethodCall('addSubscriberService', array($id, $class));
        }

        //return;
        // Hook listener
        foreach ($container->findTaggedServiceIds('hook.event_listener') as $id => $events) {

            $class = $container->getDefinition($id)->getClass();

            // the class must implements BaseHookInterface
            $refClass = new \ReflectionClass($class);
            $interface = 'Thelia\Core\Hook\BaseHookInterface';
            if (!$refClass->implementsInterface($interface)) {
                throw new \InvalidArgumentException(sprintf('Hook "%s" must implement interface "%s".', $id, $interface));
            }

            // retrieve the module id
            $properties = $container->getDefinition($id)->getProperties();
            $module = null;
            if (array_key_exists('module', $properties)){
                $moduleCode = explode(".", $properties['module'])[1];
                //Tlog::getInstance()->addDebug("_HOOK_ addListenerService :: module = " . $moduleCode );
                if (null !== $module = ModuleQuery::create()->findOneByCode($moduleCode)){
                    $module = $module->getId();
                }
            }
            //Tlog::getInstance()->addDebug("_HOOK_ addListenerService :: module = " . print_r($module, true));

            foreach ($events as $event) {

                $priority = isset($event['priority']) ? $event['priority'] : 0;

                if (!isset($event['event'])) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "hook.event_listener" tags.', $id));
                }

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace(array(
                            '/(?<=\b)[a-z]/ie',
                            '/[^a-z0-9]/i'
                        ), array('strtoupper("\\0")', ''), $event['event']);
                }

                // test if hook is already registered in ModuleHook
                $moduleHook = ModuleHookQuery::create()
                    ->filterByModuleId($module)
                    ->filterByEvent($event['event'])
                    ->findOne();
                if (null === $moduleHook) {
                    // hook for module doesn't exist, we add it with default registered values
                    $moduleHook = new ModuleHook();
                    //$moduleHook->setModuleId();
                    $moduleHook->setEvent($event['event'])
                        ->setModuleId($module)
                        ->setClassname($id)
                        ->setMethod($event['method'])
                        ->setActive(true)
                        ->setModuleActive(true)
                        ->setPriority($priority)
                        ->save();
                }
            }
        }

        // now we can add listeners for active hooks and active module
        $moduleHooks = ModuleHookQuery::create()
            ->filterByModuleId(8)
            ->filterByActive(true)
            ->filterByModuleActive(true)
            ->find();
        foreach ($moduleHooks as $moduleHook) {
            Tlog::getInstance()->addDebug("_HOOK_ addListenerService");
            $definition->addMethodCall('addListenerService',
                array(
                    'hook.' . $moduleHook->getEvent(),
                    array($moduleHook->getClassname(), $moduleHook->getMethod()),
                    $moduleHook->getPriority()
                )
            );
        }

    }
}
