<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\MailingSystem;

use Thelia\Core\Event\ActionEvent;

class MailingSystemEvent extends ActionEvent
{
    protected $enabled = null;
    protected $host = null;
    protected $port = null;
    protected $encryption = null;
    protected $username = null;
    protected $password = null;
    protected $authMode = null;
    protected $timeout = null;
    protected $sourceIp = null;

    /**
     * @param null $authMode
     */
    public function setAuthMode($authMode)
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
    public function setEnabled($enabled)
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
    public function setEncryption($encryption)
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
    public function setHost($host)
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
    public function setPassword($password)
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
    public function setPort($port)
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
    public function setSourceIp($sourceIp)
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
    public function setTimeout($timeout)
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
    public function setUsername($username)
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
