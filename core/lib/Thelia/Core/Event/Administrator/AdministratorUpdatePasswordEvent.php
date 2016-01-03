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

namespace Thelia\Core\Event\Administrator;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Admin;

/**
 * Class AdministratorUpdatePasswordEvent
 * @package Thelia\Core\Event\Administrator
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AdministratorUpdatePasswordEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Admin
     */
    protected $admin;

    /**
     * @var string new administrator password
     */
    protected $password;

    public function __construct(Admin $admin)
    {
        $this->admin = $admin;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param \Thelia\Model\Admin $admin
     */
    public function setAdmin(Admin $admin)
    {
        $this->admin = $admin;

        return $this;
    }

    /**
     * @return \Thelia\Model\Admin
     */
    public function getAdmin()
    {
        return $this->admin;
    }
}
