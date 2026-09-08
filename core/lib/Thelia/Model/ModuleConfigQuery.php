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

namespace Thelia\Model;

use Thelia\Model\Base\ModuleConfigQuery as BaseModuleConfigQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'module_config' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ModuleConfigQuery extends BaseModuleConfigQuery
{
    /**
     * Configuration rows of a module, by name, once they have been read.
     *
     * A module reads its own configuration wherever it needs it, and a page
     * ends up asking for the same handful of names again and again. The whole
     * configuration of a module is small enough to read in one go and answer
     * from, the way {@see ConfigQuery} answers for the configuration table.
     *
     * @var array<int, array<string, ModuleConfig>>
     */
    private static array $configsByModuleId = [];

    /**
     * Resolved values, by "module id|name|locale".
     *
     * @var array<string, string|null>
     */
    private static array $values = [];

    /** The locale a row answers in when the caller names none. */
    private static ?string $rowDefaultLocale = null;

    public function getConfigValue(int $moduleId, string $variableName, mixed $defaultValue = null, $valueLocale = null): ?string
    {
        $valueKey = $moduleId.'|'.$variableName.'|'.($valueLocale ?? '');

        if (!\array_key_exists($valueKey, self::$values)) {
            self::$values[$valueKey] = self::readConfigValue($moduleId, $variableName, $valueLocale);
        }

        return self::$values[$valueKey] ?? (null === $defaultValue ? null : (string) $defaultValue);
    }

    /**
     * Drops everything read so far.
     *
     * @internal
     */
    public static function resetConfigCache(): void
    {
        self::$configsByModuleId = [];
        self::$values = [];
    }

    private static function readConfigValue(int $moduleId, string $variableName, ?string $valueLocale): ?string
    {
        $configValue = self::configsOfModule($moduleId)[$variableName] ?? null;

        if (null === $configValue) {
            return null;
        }

        // The row object is shared by every read of the request, so the locale
        // to answer in is set on every read and not only when the caller names
        // one: the locale a previous reader asked for must not leak into this
        // one.
        $configValue->setLocale($valueLocale ?? self::rowDefaultLocale());

        return $configValue->getValue();
    }

    /**
     * @return array<string, ModuleConfig>
     */
    private static function configsOfModule(int $moduleId): array
    {
        if (\array_key_exists($moduleId, self::$configsByModuleId)) {
            return self::$configsByModuleId[$moduleId];
        }

        $configs = [];

        foreach (self::create()->filterByModuleId($moduleId)->find() as $config) {
            $configs[$config->getName()] = $config;
        }

        return self::$configsByModuleId[$moduleId] = $configs;
    }

    private static function rowDefaultLocale(): string
    {
        return self::$rowDefaultLocale ??= (new ModuleConfig())->getLocale();
    }

    /**
     * Set module configuration variable, creating it if required.
     *
     * @param int    $moduleId          the module id
     * @param string $variableName      the variable name
     * @param string $variableValue     the variable value
     * @param null   $valueLocale       the locale, or null if not required
     * @param bool   $createIfNotExists if true, the variable will be created if not already defined
     *
     * @return $this;
     *
     * @throws \LogicException if variable does not exists and $createIfNotExists is false
     */
    public function setConfigValue(int $moduleId, string $variableName, string $variableValue, $valueLocale = null, bool $createIfNotExists = true)
    {
        $configValue = self::create()
            ->filterByModuleId($moduleId)
            ->filterByName($variableName)
            ->findOne();

        if (null === $configValue) {
            if (true === $createIfNotExists) {
                $configValue = new ModuleConfig();

                $configValue
                    ->setModuleId($moduleId)
                    ->setName($variableName);
            } else {
                throw new \LogicException(\sprintf('Module configuration variable %s does not exists. Create it first.', $variableName));
            }
        }

        if (null !== $valueLocale) {
            $configValue->setLocale($valueLocale);
        }

        $configValue
            ->setValue($variableValue !== null ? (string) $variableValue : null)
            ->save();

        self::resetConfigCache();

        return $this;
    }

    /**
     * Delete a module's configuration variable.
     *
     * @param int    $moduleId     the module id
     * @param string $variableName the variable name
     *
     * @return $this;
     */
    public function deleteConfigValue(int $moduleId, string $variableName)
    {
        if (null !== $moduleConfig = self::create()
            ->filterByModuleId($moduleId)
            ->filterByName($variableName)
            ->findOne()
        ) {
            $moduleConfig->delete();
        }

        self::resetConfigCache();

        return $this;
    }
}

// ModuleConfigQuery
