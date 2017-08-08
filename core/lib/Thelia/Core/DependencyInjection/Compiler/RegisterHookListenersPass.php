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

use Propel\Runtime\Propel;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Hook\HookDefinition;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Model\Base\IgnoredModuleHookQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Hook;
use Thelia\Model\HookQuery;
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;

/**
 * Class RegisterListenersPass
 * @package Thelia\Core\DependencyInjection\Compiler
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class RegisterHookListenersPass implements CompilerPassInterface
{
    protected $debugEnabled;

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('event_dispatcher')) {
            return;
        }

        $definition = $container->getDefinition('event_dispatcher');

        // We have to check if Propel is initialized before registering hooks
        $managers = Propel::getServiceContainer()->getConnectionManagers();
        if (! array_key_exists('thelia', $managers)) {
            return;
        }

        $this->debugEnabled = $container->getParameter("kernel.debug");

        if (true === version_compare(ConfigQuery::getTheliaSimpleVersion(), '2.1.0', ">=")) {
            $this->processHook($container, $definition);
        }
    }

    protected function logAlertMessage($message, $failSafe = false)
    {
        Tlog::getInstance()->addAlert($message);

        if (!$failSafe && $this->debugEnabled) {
            throw new \InvalidArgumentException($message);
        }
    }

    protected function processHook(ContainerBuilder $container, $definition)
    {
        foreach ($container->findTaggedServiceIds('hook.event_listener') as $id => $events) {
            $class = $container->getDefinition($id)->getClass();

            // the class must extends BaseHook
            $implementClass = HookDefinition::BASE_CLASS;
            if (! is_subclass_of($class, $implementClass)) {
                throw new \InvalidArgumentException(sprintf('Hook class "%s" must extends class "%s".', $class, $implementClass));
            }

            // retrieve the module id
            $properties = $container->getDefinition($id)->getProperties();
            $module = null;
            if (array_key_exists('module', $properties)) {
                $moduleCode = explode(".", $properties['module'])[1];
                $module = ModuleQuery::create()->findOneByCode($moduleCode);
            }

            foreach ($events as $hookAttributes) {
                $this->registerHook($class, $module, $id, $hookAttributes);
            }
        }

        // now we can add listeners for active hooks and active module
        $this->addHooksMethodCall($container, $definition);
    }

    /**
     * Create a new hook if the hook definition is valid.
     *
     * @param string               $class  the namespace of the class
     * @param \Thelia\Model\Module $module the module
     * @param string               $id     the service (hook) id
     * @param array                $attributes  the hook attributes
     *
     * @throws \InvalidArgumentException
     */
    protected function registerHook($class, $module, $id, $attributes)
    {
        if (!isset($attributes['event'])) {
            throw new \InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "hook.event_listener" tags.', $id));
        }

        $active = isset($attributes['active']) ? intval($attributes['active']) : 1;
        $attributes['active'] = (1 === $active);
        $attributes['templates'] = isset($attributes['templates']) ? strval($attributes['templates']) : '';
        $attributes['type'] = (isset($attributes['type'])) ? $this->getHookType($attributes['type']) : TemplateDefinition::FRONT_OFFICE;

        if (null === $hook = $this->getHook($attributes['event'], $attributes['type'])) {
            return;
        }

        $attributes = $this->getMethodName($attributes);

        // test if method exists
        $validMethod = true;
        if (! $this->isValidHookMethod($class, $attributes['method'], $hook->getBlock())) {
            $validMethod = false;
        }

        // test if hook is already registered in ModuleHook
        $moduleHook = ModuleHookQuery::create()
            ->filterByModuleId($module->getId())
            ->filterByHook($hook)
            ->filterByMethod($attributes['method'])
            ->findOne();

        if (null === $moduleHook) {
            if (!$validMethod) {
                $this->logAlertMessage(
                    sprintf(
                        "Module [%s] could not be registered hook [%s], method [%s] is not reachable.",
                        $module->getCode(),
                        $attributes['event'],
                        $attributes['method']
                    )
                );
                return;
            }

            // Assign the module to the hook only if it has not been "deleted"
            $ignoreCount = IgnoredModuleHookQuery::create()
                ->filterByHook($hook)
                ->filterByModuleId($module->getId())
                ->count();

            if (0 === $ignoreCount) {
                // hook for module doesn't exist, we add it with default registered values
                $moduleHook = new ModuleHook();

                $moduleHook->setHook($hook)
                    ->setModuleId($module->getId())
                    ->setClassname($id)
                    ->setMethod($attributes['method'])
                    ->setActive($active)
                    ->setHookActive(true)
                    ->setModuleActive(true)
                    ->setPosition(ModuleHook::MAX_POSITION);

                if (isset($attributes['templates'])) {
                    $moduleHook->setTemplates($attributes['templates']);
                }

                $moduleHook->save();
            }
        } else {
            if (!$validMethod) {
                $this->logAlertMessage(
                    sprintf(
                        "Module [%s] could not use hook [%s], method [%s] is not reachable anymore.",
                        $module->getCode(),
                        $attributes['event'],
                        $attributes['method']
                    )
                );

                $moduleHook
                    ->setHookActive(false)
                    ->save();
            } else {
                //$moduleHook->setTemplates($attributes['templates']);

                // Update hook if id was changed in the definition
                if ($moduleHook->getClassname() != $id) {
                    $moduleHook
                        ->setClassname($id);
                }

                $moduleHook->save();
            }
        }
    }


    /**
     * First the new hooks are positioning next to the last module hook.
     * Next, if the module, hook and module hook is active, a new listener is
     * added to the service definition.
     *
     * @param ContainerBuilder $container
     * @param Definition $definition The service definition
     */
    protected function addHooksMethodCall(ContainerBuilder $container, Definition $definition)
    {
        $moduleHooks = ModuleHookQuery::create()
            ->orderByHookId()
            ->orderByPosition()
            ->orderById()
            ->find();

        $modulePosition = 0;
        $hookId = 0;
        /** @var ModuleHook $moduleHook */
        foreach ($moduleHooks as $moduleHook) {

            // check if class and method exists
            if (!$container->hasDefinition($moduleHook->getClassname())) {
                continue;
            }

            $hook = $moduleHook->getHook();

            if (!$this->isValidHookMethod(
                $container->getDefinition($moduleHook->getClassname())->getClass(),
                $moduleHook->getMethod(),
                $hook->getBlock(),
                true
            )
            ) {
                $moduleHook->delete();
                continue;
            }

            // manage module hook position for new hook
            if ($hookId !== $moduleHook->getHookId()) {
                $hookId = $moduleHook->getHookId();
                $modulePosition = 1;
            } else {
                $modulePosition++;
            }

            if ($moduleHook->getPosition() === ModuleHook::MAX_POSITION) {
                // new module hook, we set it at the end of the queue for this event
                $moduleHook->setPosition($modulePosition)->save();
            } else {
                $modulePosition = $moduleHook->getPosition();
            }

            // Add the the new listener for active hooks, we have to reverse the priority and the position
            if ($moduleHook->getActive() && $moduleHook->getModuleActive() && $moduleHook->getHookActive()) {
                $eventName = sprintf('hook.%s.%s', $hook->getType(), $hook->getCode());

                // we a register an event which is relative to a specific module
                if ($hook->getByModule()) {
                    $eventName .= '.' . $moduleHook->getModuleId();
                }

                $definition->addMethodCall(
                    'addListenerService',
                    array(
                        $eventName,
                        array($moduleHook->getClassname(), $moduleHook->getMethod()),
                        ModuleHook::MAX_POSITION - $moduleHook->getPosition()
                    )
                );

                if ($moduleHook->getTemplates()) {
                    if ($container->hasDefinition($moduleHook->getClassname())) {
                        $moduleHookEventName = 'hook.' . $hook->getType() . '.' . $hook->getCode();
                        if (true === $moduleHook->getHook()->getByModule()) {
                            $moduleHookEventName .= '.' . $moduleHook->getModuleId();
                        }
                        $container
                            ->getDefinition($moduleHook->getClassname())
                            ->addMethodCall(
                                'addTemplate',
                                array(
                                    $moduleHookEventName,
                                    $moduleHook->getTemplates()
                                )
                            )
                        ;
                    }
                }
            }
        }
    }


    /**
     * get the hook type according to the type attribute of the hook tag
     *
     * @param string $name
     *
     * @return int the hook type
     */
    protected function getHookType($name)
    {
        $type = TemplateDefinition::FRONT_OFFICE;

        if (null !== $name && is_string($name)) {
            $name = preg_replace("[^a-z]", "", strtolower(trim($name)));
            if (in_array($name, array('bo', 'back', 'backoffice'))) {
                $type = TemplateDefinition::BACK_OFFICE;
            } elseif (in_array($name, array('email'))) {
                $type = TemplateDefinition::EMAIL;
            } elseif (in_array($name, array('pdf'))) {
                $type = TemplateDefinition::PDF;
            }
        }

        return $type;
    }

    /**
     * G<et a hook for a hook name (code) and a hook type. The hook should exists and be activated.
     *
     * @param string $hookName
     * @param int $hookType
     *
     * @return Hook|null
     */
    protected function getHook($hookName, $hookType)
    {
        $hook = HookQuery::create()
            ->filterByCode($hookName)
            ->filterByType($hookType)
            ->findOne();

        if (null === $hook) {
            $this->logAlertMessage(sprintf("Hook %s is unknown.", $hookName));

            return null;
        }

        if (! $hook->getActivate()) {
            $this->logAlertMessage(sprintf("Hook %s is not activated.", $hookName), true);
        }

        return $hook;
    }

    /**
     * Test if the method that will handled the hook is valid
     *
     * @param string $className  the namespace of the class
     * @param string $methodName the method name
     * @param bool   $block      tell if the hook is a block or a function
     * @param bool   $failSafe
     *
     * @return bool
     */
    protected function isValidHookMethod($className, $methodName, $block, $failSafe = false)
    {
        try {
            $method = new ReflectionMethod($className, $methodName);

            $parameters = $method->getParameters();

            $eventType = ($block) ?
                HookDefinition::RENDER_BLOCK_EVENT :
                HookDefinition::RENDER_FUNCTION_EVENT;

            if (!($parameters[0]->getClass()->getName() == $eventType || is_subclass_of($parameters[0]->getClass()->getName(), $eventType))) {
                $this->logAlertMessage(sprintf("Method %s should use an event of type %s. found: %s", $methodName, $eventType, $parameters[0]->getClass()->getName()));

                return false;
            }
        } catch (ReflectionException $ex) {
            $this->logAlertMessage(
                sprintf("Method %s does not exist in %s : %s", $methodName, $className, $ex),
                $failSafe
            );

            return false;
        }

        return true;
    }

    /**
     * @param $event
     * @return mixed
     */
    protected function getMethodName($event)
    {
        if (!isset($event['method'])) {
            if (!empty($event['templates'])) {
                $event['method'] = BaseHook::INJECT_TEMPLATE_METHOD_NAME;
                return $event;
            } else {
                $callback = function ($matches) {
                    return strtoupper($matches[0]);
                };
                $event['method'] = 'on' . preg_replace_callback(
                    array(
                        '/(?<=\b)[a-z]/i',
                        '/[^a-z0-9]/i',
                    ),
                    $callback,
                    $event['event']
                );
                $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);
                return $event;
            }
        }

        return $event;
    }
}
