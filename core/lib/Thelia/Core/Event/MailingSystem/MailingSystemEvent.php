<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
