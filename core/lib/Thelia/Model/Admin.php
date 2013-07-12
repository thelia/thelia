<?php

namespace Thelia\Model;

use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\Security\Role\Role;

use Thelia\Model\Base\Admin as BaseAdmin;

/**
 * Skeleton subclass for representing a row from the 'admin' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Thelia.Model
 */
class Admin extends BaseAdmin implements UserInterface
{

    public function setPassword($password)
    {
        \Thelia\Log\Tlog::getInstance()->debug($password);

        if ($this->isNew() && ($password === null || trim($password) == "")) {
            throw new InvalidArgumentException("customer password is mandatory on creation");
        }

        if($password !== null && trim($password) != "") {
            $this->setAlgo("PASSWORD_BCRYPT");
            return parent::setPassword(password_hash($password, PASSWORD_BCRYPT));
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function checkPassword($password)
    {
    	return password_verify($password, $this->password);
    }

	/**
     * {@inheritDoc}
     */
    public function getUsername() {
        return $this->getLogin();
    }

    /**
     * {@inheritDoc}
     */
    public function eraseCredentials() {
    	$this->setPassword(null);
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles() {
    	return array(new Role('ROLE_ADMIN'));
    }
}
