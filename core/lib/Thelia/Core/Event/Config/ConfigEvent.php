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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Config;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\ConfigEvent
 */
class ConfigEvent extends ActionEvent
{
    public function __construct(protected ?Config $config = null)
    {
    }

    public function hasConfig(): bool
    {
        return $this->config instanceof Config;
    }

    public function getConfig(): ?Config
    {
        return $this->config;
    }

    public function setConfig(?Config $config): static
    {
        $this->config = $config;

        return $this;
    }
}
