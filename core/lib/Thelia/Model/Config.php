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
    public function getEnvName()
    {
        return str_replace(
            ['.', '-'],
            '_',
            strtoupper(
                $this->getName()
            )
        );
    }

    public function getValue()
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
