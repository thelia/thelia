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

    public function setAuthMode($authMode): void
    {
        $this->authMode = $authMode;
    }

    public function getAuthMode()
    {
        return $this->authMode;
    }

    public function setEnabled($enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getEnabled()
    {
        return $this->enabled;
    }

    public function setEncryption($encryption): void
    {
        $this->encryption = $encryption;
    }

    public function getEncryption()
    {
        return $this->encryption;
    }

    public function setHost($host): void
    {
        $this->host = $host;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function setPassword($password): void
    {
        $this->password = $password;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPort($port): void
    {
        $this->port = $port;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function setSourceIp($sourceIp): void
    {
        $this->sourceIp = $sourceIp;
    }

    public function getSourceIp()
    {
        return $this->sourceIp;
    }

    public function setTimeout($timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setUsername($username): void
    {
        $this->username = $username;
    }

    public function getUsername()
    {
        return $this->username;
    }
}
