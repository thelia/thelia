<?php

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
    protected $config;

    public function __construct(Config $config = null)
    {
        $this->config = $config;
    }

    public function hasConfig()
    {
        return ! \is_null($this->config);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }
}
