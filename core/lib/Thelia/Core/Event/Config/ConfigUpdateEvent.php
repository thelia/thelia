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

class ConfigUpdateEvent extends ConfigCreateEvent
{
    /** @var int */
    protected $config_id;

    protected $description;
    protected $chapo;
    protected $postscriptum;

    /**
     * @param int $config_id
     */
    public function __construct($config_id)
    {
        $this->setConfigId($config_id);
    }

    public function getConfigId()
    {
        return $this->config_id;
    }

    public function setConfigId($config_id)
    {
        $this->config_id = $config_id;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }
}
