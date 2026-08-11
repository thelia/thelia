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

namespace Thelia\Tests\Integration\Command;

use Thelia\Command\HookCleanCommand;
use Thelia\Core\DependencyInjection\Compiler\RegisterHookListenersPass;
use Thelia\Core\Hook\DefaultHook;
use Thelia\Core\Hook\ModuleHookConfigurationStore;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Hook;
use Thelia\Model\IgnoredModuleHook;
use Thelia\Model\IgnoredModuleHookQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * hook:clean deletes the module hooks and RegisterHookListenersPass recreates them on the next
 * container build. Both halves are exercised here, but not the container build itself: the command
 * is not run through the console, because it clears the cache directory of the running kernel.
 */
final class HookCleanCommandTest extends IntegrationTestCase
{
    private const HOOK_METHOD = 'insertTemplate';

    private const SERVICE_ID = 'test.hook.clean.listener';

    protected function setUp(): void
    {
        parent::setUp();

        ModuleHookConfigurationStore::discard();
    }

    public function testCleanRestoresThePositionAndTheActiveStateSetInTheBackOffice(): void
    {
        $module = $this->getActiveModule();
        $hook = $this->createHook('test.hook.clean.position');
        $this->createModuleHook($module, $hook, position: 7, active: false);

        $this->cleanHooks(preserveConfiguration: true);

        self::assertNull($this->findModuleHook($hook));
        self::assertNotNull(
            ConfigQuery::create()->findOneByName(ModuleHookConfigurationStore::CONFIG_NAME),
            'the configuration must be saved in database, the container is rebuilt in another process',
        );

        $this->registerHook($module, $hook);

        $recreated = $this->findModuleHook($hook);
        self::assertNotNull($recreated);
        self::assertSame(7, $recreated->getPosition());
        self::assertFalse($recreated->getActive());
    }

    public function testCleanKeepsTheHooksRemovedInTheBackOfficeRemoved(): void
    {
        $module = $this->getActiveModule();
        $hook = $this->createHook('test.hook.clean.ignored');

        (new IgnoredModuleHook())
            ->setModuleId($module->getId())
            ->setHookId($hook->getId())
            ->setMethod(self::HOOK_METHOD)
            ->setClassname(self::SERVICE_ID)
            ->save();

        $this->cleanHooks(preserveConfiguration: true);

        self::assertTrue(
            IgnoredModuleHookQuery::create()->filterByHookId($hook->getId())->exists(),
        );

        $this->registerHook($module, $hook);

        self::assertNull($this->findModuleHook($hook));
    }

    public function testResetPositionsOptionStartsOver(): void
    {
        $module = $this->getActiveModule();
        $hook = $this->createHook('test.hook.clean.reset');
        $this->createModuleHook($module, $hook, position: 7, active: false);

        (new IgnoredModuleHook())
            ->setModuleId($module->getId())
            ->setHookId($hook->getId())
            ->setMethod('onSomethingElse')
            ->setClassname(self::SERVICE_ID)
            ->save();

        $this->cleanHooks(preserveConfiguration: false);

        self::assertFalse(
            IgnoredModuleHookQuery::create()->filterByHookId($hook->getId())->exists(),
        );

        $this->registerHook($module, $hook);

        $recreated = $this->findModuleHook($hook);
        self::assertNotNull($recreated);
        self::assertSame(ModuleHook::MAX_POSITION, $recreated->getPosition());
        self::assertTrue($recreated->getActive());
    }

    private function getActiveModule(): Module
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module, 'Cheque module must be registered by bin/test-prepare');

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

    private function createModuleHook(Module $module, Hook $hook, int $position, bool $active): void
    {
        (new ModuleHook())
            ->setHook($hook)
            ->setModuleId($module->getId())
            ->setClassname(self::SERVICE_ID)
            ->setMethod(self::HOOK_METHOD)
            ->setActive($active)
            ->setHookActive(true)
            ->setModuleActive(true)
            ->setPosition($position)
            ->setTemplates('')
            ->save();
    }

    private function findModuleHook(Hook $hook): ?ModuleHook
    {
        return ModuleHookQuery::create()->filterByHookId($hook->getId())->findOne();
    }

    private function cleanHooks(bool $preserveConfiguration): void
    {
        $command = new HookCleanCommand();

        (new \ReflectionMethod($command, 'deleteHooks'))
            ->invoke($command, null, $preserveConfiguration);
    }

    /**
     * Replays what RegisterHookListenersPass does for a hook declared by a module.
     */
    private function registerHook(Module $module, Hook $hook): void
    {
        $pass = new RegisterHookListenersPass();

        (new \ReflectionMethod($pass, 'registerHook'))->invoke(
            $pass,
            DefaultHook::class,
            $module,
            self::SERVICE_ID,
            ['event' => $hook->getCode(), 'type' => 'front', 'method' => self::HOOK_METHOD],
        );
    }
}
