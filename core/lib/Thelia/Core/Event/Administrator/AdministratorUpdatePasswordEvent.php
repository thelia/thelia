<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Administrator;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Admin;


/**
 * Class AdministratorUpdatePasswordEvent
 * @package Thelia\Core\Event\Administrator
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
    }

    /**
     * @return \Thelia\Model\Admin
     */
    public function getAdmin()
    {
        return $this->admin;
    }

}

