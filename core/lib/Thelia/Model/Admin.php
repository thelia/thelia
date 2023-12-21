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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Security\Role\Role;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\Security\User\UserPermissionsTrait;
use Thelia\Model\Base\Admin as BaseAdmin;

/**
 * Skeleton subclass for representing a row from the 'admin' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Admin extends BaseAdmin implements UserInterface
{
    use UserPermissionsTrait;

    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        // Set the serial number (for auto-login)
        $this->setRememberMeSerial(uniqid());

        return true;
    }

    public function setPassword($password)
    {
        if ($this->isNew() && ($password === null || trim($password) == '')) {
            throw new \InvalidArgumentException('customer password is mandatory on creation');
        }

        if ($password !== null && trim($password) != '') {
            $this->setAlgo('PASSWORD_BCRYPT');

            return parent::setPassword(password_hash($password, \PASSWORD_BCRYPT));
        }

        return $this;
    }

    public function checkPassword($password)
    {
        return password_verify($password, $this->password);
    }

    public function getUsername()
    {
        return $this->getLogin();
    }

    public function eraseCredentials(): void
    {
        parent::setPassword(null);
        $this->resetModified();
    }

    public function getRoles()
    {
        return [new Role('ADMIN')];
    }

    public function getToken()
    {
        return $this->getRememberMeToken();
    }

    public function setToken($token): void
    {
        $this->setRememberMeToken($token)->save();
    }

    public function getSerial()
    {
        return $this->getRememberMeSerial();
    }

    public function setSerial($serial): void
    {
        $this->setRememberMeSerial($serial)->save();
    }
}
