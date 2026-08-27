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

use Thelia\Model\Base\Config as BaseConfig;

class Config extends BaseConfig
{
    /**
     * The environment variable a configuration name is overridden by:
     * 'active-front-template' is overridden by ACTIVE_FRONT_TEMPLATE.
     */
    public static function getEnvNameFor(string $configName): string
    {
        return str_replace(['.', '-'], '_', strtoupper($configName));
    }

    public function getEnvName()
    {
        return self::getEnvNameFor((string) $this->getName());
    }

    /**
     * The stored value, ignoring any environment override. An override belongs to
     * the process that declares it, so this is the only value a cache shared by
     * several processes may hold.
     */
    public function getStoredValue(): ?string
    {
        return parent::getValue();
    }

    public function getValue(): ?string
    {
        if ($this->isOverriddenInEnv()) {
            return $_ENV[$this->getEnvName()];
        }

        return parent::getValue();
    }

    public function isOverriddenInEnv(): bool
    {
        return isset($_ENV[$this->getEnvName()]);
    }
}
