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

namespace Thelia\Tests\Integration\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\DependencyInjection\Compiler\RegisterHookListenersPass;
use Thelia\Core\Hook\DefaultHook;
use Thelia\Core\Hook\ModuleHookConfigurationStore;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\Hook;
use Thelia\Model\Module;
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The order in which the listeners of a hook are rendered is frozen at container build time, from the
 * module_hook.position column. These tests cover the position a module declares in its hook declaration,
 * which is the only order a module can express before an administrator reorders the hooks.
 */
final class RegisterHookListenersPassTest extends IntegrationTestCase
{
    private const HOOK_METHOD = 'insertTemplate';

    private const FIRST_SERVICE_ID = 'test.hook.position.first';

    private const SECOND_SERVICE_ID = 'test.hook.position.second';

    protected function setUp(): void
    {
        parent::setUp();

        ModuleHookConfigurationStore::discard();
    }

    public function testDeclaredPositionIsUsedWhenTheHookIsRegistered(): void
    {
        $hook = $this->createHook('test.hook.position.declared');

        $this->registerHook($this->getModule('Cheque'), self::FIRST_SERVICE_ID, $hook, position: 3);

        $moduleHook = ModuleHookQuery::create()->filterByHookId($hook->getId())->findOne();
        self::assertNotNull($moduleHook);
        self::assertSame(3, $moduleHook->getPosition());
    }

    public function testHookWithoutDeclaredPositionIsAppendedAtTheEndOfTheQueue(): void
    {
        $hook = $this->createHook('test.hook.position.undeclared');

        $this->registerHook($this->getModule('Cheque'), self::FIRST_SERVICE_ID, $hook);

        $moduleHook = ModuleHookQuery::create()->filterByHookId($hook->getId())->findOne();
        self::assertNotNull($moduleHook);
        self::assertSame(ModuleHook::MAX_POSITION, $moduleHook->getPosition());
    }

    public function testOutOfRangeDeclaredPositionIsIgnored(): void
    {
        $hook = $this->createHook('test.hook.position.out-of-range');

        $this->registerHook($this->getModule('Cheque'), self::FIRST_SERVICE_ID, $hook, position: 0);

        $moduleHook = ModuleHookQuery::create()->filterByHookId($hook->getId())->findOne();
        self::assertNotNull($moduleHook);
        self::assertSame(ModuleHook::MAX_POSITION, $moduleHook->getPosition());
    }

    public function testPositionSetInTheBackOfficeWinsOverTheDeclaredOne(): void
    {
        $module = $this->getModule('Cheque');
        $hook = $this->createHook('test.hook.position.back-office');

        $this->registerHook($module, self::FIRST_SERVICE_ID, $hook, position: 3);

        $moduleHook = ModuleHookQuery::create()->filterByHookId($hook->getId())->findOne();
        self::assertNotNull($moduleHook);
        $moduleHook->setPosition(9)->save();

        $this->registerHook($module, self::FIRST_SERVICE_ID, $hook, position: 3);

        self::assertSame(9, ModuleHookQuery::create()->filterByHookId($hook->getId())->findOne()?->getPosition());
    }

    /**
     * A module loaded after another one can no longer be forced to render after it: the declared
     * position, not the module load order, decides.
     */
    public function testDeclaredPositionOrdersTheListenersOfAHook(): void
    {
        $hook = $this->createHook('test.hook.position.ordering');

        $this->registerHook($this->getModule('Cheque'), self::FIRST_SERVICE_ID, $hook, position: 20);
        $this->registerHook($this->getModule('FreeOrder'), self::SECOND_SERVICE_ID, $hook, position: 10);

        $listeners = $this->compileListenersOf($hook);

        self::assertSame([self::SECOND_SERVICE_ID, self::FIRST_SERVICE_ID], array_keys($listeners));
        self::assertGreaterThan($listeners[self::FIRST_SERVICE_ID], $listeners[self::SECOND_SERVICE_ID]);
    }

    private function getModule(string $code): Module
    {
        $module = ModuleQuery::create()->findOneByCode($code);
        self::assertNotNull($module, \sprintf('%s module must be registered by bin/test-prepare', $code));

        return $module;
    }

    private function createHook(string $code): Hook
    {
        $hook = new Hook();
        $hook
            ->setCode($code)
            ->setType(TemplateDefinition::FRONT_OFFICE)
            ->setNative(false)
            ->setActivate(true)
            ->setBlock(false)
            ->setByModule(false);
        $hook
            ->setLocale('en_US')
            ->setTitle('Hook '.$code);
        $hook->save();

        return $hook;
    }

    private function registerHook(Module $module, string $serviceId, Hook $hook, ?int $position = null): void
    {
        $attributes = ['event' => $hook->getCode(), 'type' => 'front', 'method' => self::HOOK_METHOD];

        if (null !== $position) {
            $attributes['position'] = $position;
        }

        (new \ReflectionMethod(RegisterHookListenersPass::class, 'registerHook'))->invoke(
            new RegisterHookListenersPass(),
            DefaultHook::class,
            $module,
            $serviceId,
            $attributes,
        );
    }

    /**
     * Runs the part of the compiler pass that turns module_hook rows into event dispatcher listeners,
     * and returns the priority given to each of the hook's listeners, in registration order.
     *
     * @return array<string, int>
     */
    private function compileListenersOf(Hook $hook): array
    {
        $container = new ContainerBuilder();
        $container->register(self::FIRST_SERVICE_ID, DefaultHook::class);
        $container->register(self::SECOND_SERVICE_ID, DefaultHook::class);

        $dispatcher = new Definition();

        (new \ReflectionMethod(RegisterHookListenersPass::class, 'addHooksMethodCall'))->invoke(
            new RegisterHookListenersPass(),
            $container,
            $dispatcher,
        );

        $eventName = \sprintf('hook.%s.%s', $hook->getType(), $hook->getCode());
        $priorities = [];

        foreach ($dispatcher->getMethodCalls() as [$method, $arguments]) {
            if ('addListener' !== $method || $eventName !== $arguments[0]) {
                continue;
            }

            $priorities[(string) $arguments[1][0]->getValues()[0]] = $arguments[2];
        }

        return $priorities;
    }
}
