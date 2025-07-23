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

namespace Thelia\Core\DependencyInjection\Compiler;

use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Hook\HookDefinition;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Model\Base\IgnoredModuleHookQuery;
use Thelia\Model\Hook;
use Thelia\Model\HookQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;

/**
 * Class RegisterListenersPass.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class RegisterHookListenersPass implements CompilerPassInterface
{
    protected bool $debugEnabled = false;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('event_dispatcher')) {
            return;
        }

        $definition = $container->getDefinition('event_dispatcher');

        // We have to check if Propel is initialized before registering hooks
        $managers = Propel::getServiceContainer()->getConnectionManagers();

        if (!\array_key_exists('TheliaMain', $managers)) {
            return;
        }

        $this->debugEnabled = $container->getParameter('kernel.debug');

        $this->processHook($container, $definition);
    }

    protected function logAlertMessage($message): void
    {
        Tlog::getInstance()->addAlert($message);
    }

    protected function processHook(ContainerBuilder $container, Definition $definition): void
    {
        foreach ($container->findTaggedServiceIds('hook.event_listener') as $id => $events) {
            $class = $container->getDefinition($id)->getClass();

            // the class must extends BaseHook
            $implementClass = HookDefinition::BASE_CLASS;

            if (!is_subclass_of($class, $implementClass)) {
                throw new \InvalidArgumentException(\sprintf('Hook class "%s" must extends class "%s".', $class, $implementClass));
            }

            $moduleCode = explode('\\', $class)[0];
            $module = ModuleQuery::create()->findOneByCode($moduleCode);

            if (null === $module) {
                // retrieve the module when no class is defined in xml
                $properties = $container->getDefinition($id)->getProperties();

                if (!\array_key_exists('module', $properties)) {
                    continue;
                }

                $moduleProperty = $properties['module'];

                if ($moduleProperty instanceof Definition) {
                    $moduleCode = explode('\\', (string) $moduleProperty->getClass())[1];
                }

                if ($moduleProperty instanceof Reference) {
                    $moduleCode = explode('.', $moduleProperty->__toString())[1];
                }

                if (null === $moduleCode) {
                    continue;
                }

                $module = ModuleQuery::create()->findOneByCode($moduleCode);

                if (null === $module) {
                    continue;
                }
            }

            if (method_exists($class, 'getSubscribedHooks')) {
                foreach ($class::getSubscribedHooks() as $eventName => $attributesArray) {
                    if (isset($attributesArray['type'])) {
                        $attributesArray = [$attributesArray];
                    }

                    foreach ($attributesArray as $attributes) {
                        $events[] = array_merge($attributes, ['event' => $eventName]);
                    }
                }
            }

            foreach ($events as $hookAttributes) {
                if (!empty($hookAttributes)) {
                    $this->registerHook($class, $module, $id, $hookAttributes);
                }
            }
        }

        // now we can add listeners for active hooks and active module
        $this->addHooksMethodCall($container, $definition);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function registerHook(string $class, Module $module, string $id, array $attributes): void
    {
        if (!isset($attributes['event'])) {
            throw new \InvalidArgumentException(\sprintf('Service "%s" must define the "event" attribute on "hook.event_listener" tags.', $id));
        }

        $active = 1 === (int) ($attributes['active'] ?? 1);
        $type = $this->getHookType($attributes['type'] ?? TemplateDefinition::FRONT_OFFICE);
        $templates = (string) ($attributes['templates'] ?? '');
        $method = $this->getMethodName($attributes)['method'];

        $hook = $this->getHook($attributes['event'], $type);

        if (!$hook instanceof Hook) {
            return;
        }

        $isValidMethod = $this->isValidHookMethod($class, $method, $hook->getBlock());
        $moduleHook = ModuleHookQuery::create()
            ->filterByModuleId($module->getId())
            ->filterByHook($hook)
            ->filterByMethod($method)
            ->findOne();

        if (!$moduleHook) {
            if (!$isValidMethod) {
                $this->logAlertMessage(\sprintf(
                    'Module [%s] could not be registered to hook [%s], method [%s] is not reachable.',
                    $module->getCode(),
                    $attributes['event'],
                    $method,
                ));

                return;
            }

            if (!IgnoredModuleHookQuery::create()->filterByHook($hook)->filterByModuleId($module->getId())->exists()) {
                (new ModuleHook())
                    ->setHook($hook)
                    ->setModuleId($module->getId())
                    ->setClassname($id)
                    ->setMethod($method)
                    ->setActive($active)
                    ->setHookActive(true)
                    ->setModuleActive(true)
                    ->setPosition(ModuleHook::MAX_POSITION)
                    ->setTemplates($templates)
                    ->save();
            }
        } elseif (!$isValidMethod) {
            $this->logAlertMessage(\sprintf(
                'Module [%s] could not use hook [%s], method [%s] is not reachable anymore.',
                $module->getCode(),
                $attributes['event'],
                $method,
            ));
            $moduleHook->setHookActive(false)->save();
        } elseif ($moduleHook->getClassname() !== $id) {
            $moduleHook->setClassname($id)->save();
        }
    }

    protected function addHooksMethodCall(ContainerBuilder $container, Definition $definition): void
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
            if (null === $moduleHook->getClassname() || !$container->hasDefinition($moduleHook->getClassname())) {
                continue;
            }

            $hook = $moduleHook->getHook();

            if (!$this->isValidHookMethod(
                $container->getDefinition($moduleHook->getClassname())->getClass(),
                $moduleHook->getMethod(),
                $hook->getBlock(),
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
                ++$modulePosition;
            }

            if (ModuleHook::MAX_POSITION === $moduleHook->getPosition()) {
                // new module hook, we set it at the end of the queue for this event
                $moduleHook->setPosition($modulePosition)->save();
            } else {
                $modulePosition = $moduleHook->getPosition();
            }

            // Add the the new listener for active hooks, we have to reverse the priority and the position
            if ($moduleHook->getActive() && $moduleHook->getModuleActive() && $moduleHook->getHookActive()) {
                $eventName = \sprintf('hook.%s.%s', $hook->getType(), $hook->getCode());

                // we a register an event which is relative to a specific module
                if ($hook->getByModule()) {
                    $eventName .= '.'.$moduleHook->getModuleId();
                }

                $definition->addMethodCall(
                    'addListener',
                    [
                        $eventName,
                        [
                            new ServiceClosureArgument(new Reference($moduleHook->getClassname())),
                            $moduleHook->getMethod(),
                        ],
                        ModuleHook::MAX_POSITION - $moduleHook->getPosition(),
                    ],
                );

                if ($moduleHook->getTemplates() && $container->hasDefinition($moduleHook->getClassname())) {
                    $moduleHookEventName = 'hook.'.$hook->getType().'.'.$hook->getCode();

                    if (true === $moduleHook->getHook()->getByModule()) {
                        $moduleHookEventName .= '.'.$moduleHook->getModuleId();
                    }

                    $container
                        ->getDefinition($moduleHook->getClassname())
                        ->addMethodCall(
                            'addTemplate',
                            [
                                $moduleHookEventName,
                                $moduleHook->getTemplates(),
                            ],
                        );
                }
            }
        }
    }

    protected function getHookType(string $name): int
    {
        $name = preg_replace('[^a-z]', '', strtolower(trim($name)));

        return match ($name) {
            'bo', 'back', 'backoffice' => TemplateDefinition::BACK_OFFICE,
            'email' => TemplateDefinition::EMAIL,
            'pdf' => TemplateDefinition::PDF,
            default => TemplateDefinition::FRONT_OFFICE,
        };
    }

    protected function getHook(string $hookName, int $hookType): ?Hook
    {
        $hook = HookQuery::create()
            ->filterByCode($hookName)
            ->filterByType($hookType)
            ->findOne();

        if (null === $hook) {
            $this->logAlertMessage(\sprintf('Hook %s is unknown.', $hookName));

            return null;
        }

        if (!$hook->getActivate()) {
            $this->logAlertMessage(\sprintf('Hook %s is not activated.', $hookName));
        }

        return $hook;
    }

    protected function isValidHookMethod(string $className, string $methodName, bool $block): bool
    {
        try {
            $method = new \ReflectionMethod($className, $methodName);

            $parameters = $method->getParameters();

            $eventType = ($block) ?
                HookDefinition::RENDER_BLOCK_EVENT :
                HookDefinition::RENDER_FUNCTION_EVENT;
            $parameterType = $parameters[0]->getType()?->getName();

            if ($parameterType !== $eventType && !is_subclass_of($parameterType, $eventType)) {
                $this->logAlertMessage(\sprintf('Method %s should use an event of type %s. found: %s', $methodName, $eventType, $parameters[0]->getType()));

                return false;
            }
        } catch (\ReflectionException $reflectionException) {
            $this->logAlertMessage(
                \sprintf('Method %s does not exist in %s : %s', $methodName, $className, $reflectionException),
            );

            return false;
        }

        return true;
    }

    protected function getMethodName($event)
    {
        if (!isset($event['method'])) {
            if (!empty($event['templates'])) {
                $event['method'] = BaseHook::INJECT_TEMPLATE_METHOD_NAME;

                return $event;
            }

            $callback = (static fn ($matches) => strtoupper((string) $matches[0]));
            $event['method'] = 'on'.preg_replace_callback(
                [
                    '/(?<=\b)[a-z]/i',
                    '/[^a-z0-9]/i',
                ],
                $callback,
                (string) $event['event'],
            );
            $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);

            return $event;
        }

        return $event;
    }
}
