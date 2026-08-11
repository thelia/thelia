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

namespace Thelia\Core\Hook;

use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleHook;

/**
 * Remembers the part of a module hook that only the administrator can set: its rendering position
 * and its active state.
 *
 * The hook:clean command deletes the module_hook rows, and RegisterHookListenersPass recreates them
 * during the next container build, in another process. The saved configuration is therefore kept in
 * the config table: the command wipes the cache directory right after the deletion, and the
 * container may be rebuilt by another system user or on another server.
 */
final class ModuleHookConfigurationStore
{
    public const CONFIG_NAME = 'saved-module-hook-configuration';

    /**
     * @var array<string, array{position: int, active: bool}>|null
     */
    private static ?array $configuration = null;

    /**
     * Saves the configuration of the given module hooks, keeping the previously saved one.
     */
    public static function save(ObjectCollection $moduleHooks): void
    {
        $configuration = self::read();

        /** @var ModuleHook $moduleHook */
        foreach ($moduleHooks as $moduleHook) {
            $key = self::key($moduleHook->getModuleId(), $moduleHook->getHookId(), (string) $moduleHook->getMethod());

            $configuration[$key] = [
                'position' => $moduleHook->getPosition(),
                'active' => (bool) $moduleHook->getActive(),
            ];
        }

        if ([] === $configuration) {
            return;
        }

        self::$configuration = $configuration;

        ConfigQuery::write(self::CONFIG_NAME, json_encode($configuration, \JSON_THROW_ON_ERROR), true, true);
    }

    /**
     * @return array{position: int, active: bool}|null
     */
    public static function find(int $moduleId, int $hookId, string $method): ?array
    {
        $configuration = self::read();
        $key = self::key($moduleId, $hookId, $method);

        if (!isset($configuration[$key]) || !\is_array($configuration[$key])) {
            return null;
        }

        $saved = $configuration[$key];

        return [
            'position' => (int) ($saved['position'] ?? ModuleHook::MAX_POSITION),
            'active' => (bool) ($saved['active'] ?? true),
        ];
    }

    public static function discard(): void
    {
        self::$configuration = [];

        ConfigQuery::create()->filterByName(self::CONFIG_NAME)->delete();
    }

    /**
     * @return array<string, array{position: int, active: bool}>
     */
    private static function read(): array
    {
        if (null !== self::$configuration) {
            return self::$configuration;
        }

        $config = ConfigQuery::create()->findOneByName(self::CONFIG_NAME);

        try {
            $configuration = null === $config
                ? []
                : json_decode((string) $config->getValue(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $configuration = [];
        }

        return self::$configuration = \is_array($configuration) ? $configuration : [];
    }

    private static function key(int $moduleId, int $hookId, string $method): string
    {
        return $moduleId.':'.$hookId.':'.$method;
    }
}
