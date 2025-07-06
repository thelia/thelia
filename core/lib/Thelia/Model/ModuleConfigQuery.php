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
     * Get a module's configuration variable.
     *
     * @param int    $moduleId     the module id
     * @param string $variableName the variable name
     * @param string $defaultValue the default value, if variable is not defined
     * @param null   $valueLocale  the required locale, or null to get default one
     *
     * @return string the variable value
     */
    public function getConfigValue($moduleId, $variableName, $defaultValue = null, $valueLocale = null)
    {
        $value = null;

        $configValue = self::create()
            ->filterByModuleId($moduleId)
            ->filterByName($variableName)
            ->findOne();

        if (null !== $configValue) {
            if (null !== $valueLocale) {
                $configValue->setLocale($valueLocale);
            }

            $value = $configValue->getValue();
        }

        return $value ?? $defaultValue;
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
     * @throws \LogicException if variable does not exists and $createIfNotExists is false
     *
     * @return $this;
     */
    public function setConfigValue($moduleId, $variableName, $variableValue, $valueLocale = null, $createIfNotExists = true)
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
                    ->setName($variableName)
                ;
            } else {
                throw new \LogicException(\sprintf('Module configuration variable %s does not exists. Create it first.', $variableName));
            }
        }

        if (null !== $valueLocale) {
            $configValue->setLocale($valueLocale);
        }

        $configValue
            ->setValue($variableValue)
            ->save();

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
    public function deleteConfigValue($moduleId, $variableName)
    {
        if (null !== $moduleConfig = self::create()
            ->filterByModuleId($moduleId)
            ->filterByName($variableName)
            ->findOne()
        ) {
            $moduleConfig->delete();
        }

        return $this;
    }
}

// ModuleConfigQuery
