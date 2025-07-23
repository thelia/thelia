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

namespace Thelia\Core\Event\Config;

class ConfigDeleteEvent extends ConfigEvent
{
    protected int $config_id;

    public function __construct(int $config_id)
    {
        $this->setConfigId($config_id);
    }

    public function getConfigId(): int
    {
        return $this->config_id;
    }

    public function setConfigId(int $config_id): static
    {
        $this->config_id = $config_id;

        return $this;
    }
}
