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

class AdministratorEvent extends ActionEvent
{
    protected $administrator = null;
    protected $id = null;
    protected $firstname = null;
    protected $lastname = null;
    protected $login = null;
    protected $email = null;
    protected $password = null;
    protected $profile = null;
    protected $locale = null;

    public function __construct(Admin $administrator = null)
    {
        $this->administrator = $administrator;
    }

    public function hasAdministrator()
    {
        return ! is_null($this->administrator);
    }

    public function getAdministrator()
    {
        return $this->administrator;
    }

    public function setAdministrator(Admin $administrator)
    {
        $this->administrator = $administrator;

        return $this;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getLastname()
    {
        return $this->lastname;
    }

    public function setLogin($login)
    {
        $this->login = $login;

        return $this;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setProfile($profile)
    {
        if (0 === $profile) {
            $profile = null;
        }

        $this->profile = $profile;

        return $this;
    }

    public function getProfile()
    {
        return $this->profile;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return $this
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }
}
