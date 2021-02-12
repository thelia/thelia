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

namespace Thelia\Core\Event\MailingSystem;

use Thelia\Core\Event\ActionEvent;

class MailingSystemEvent extends ActionEvent
{
    protected $enabled;
    protected $host;
    protected $port;
    protected $encryption;
    protected $username;
    protected $password;
    protected $authMode;
    protected $timeout;
    protected $sourceIp;

    /**
     * @param null $authMode
     */
    public function setAuthMode($authMode): void
    {
        $this->authMode = $authMode;
    }

    /**
     * @return null
     */
    public function getAuthMode()
    {
        return $this->authMode;
    }

    /**
     * @param null $enabled
     */
    public function setEnabled($enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return null
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param null $encryption
     */
    public function setEncryption($encryption): void
    {
        $this->encryption = $encryption;
    }

    /**
     * @return null
     */
    public function getEncryption()
    {
        return $this->encryption;
    }

    /**
     * @param null $host
     */
    public function setHost($host): void
    {
        $this->host = $host;
    }

    /**
     * @return null
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param null $password
     */
    public function setPassword($password): void
    {
        $this->password = $password;
    }

    /**
     * @return null
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param null $port
     */
    public function setPort($port): void
    {
        $this->port = $port;
    }

    /**
     * @return null
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param null $sourceIp
     */
    public function setSourceIp($sourceIp): void
    {
        $this->sourceIp = $sourceIp;
    }

    /**
     * @return null
     */
    public function getSourceIp()
    {
        return $this->sourceIp;
    }

    /**
     * @param null $timeout
     */
    public function setTimeout($timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return null
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param null $username
     */
    public function setUsername($username): void
    {
        $this->username = $username;
    }

    /**
     * @return null
     */
    public function getUsername()
    {
        return $this->username;
    }
}
