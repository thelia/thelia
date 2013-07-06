<?php

namespace Thelia\Model;

use Thelia\Model\om\BaseCustomer;
use Thelia\Core\Security\User\UserInterface;


/**
 * Skeleton subclass for representing a row from the 'customer' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Thelia.Model
 */
class Customer extends BaseCustomer implements UserInterface
{
    /**
     * {@inheritDoc}
     */

    public function getUsername() {
        return $this->getEmail();
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
        return array(new Role('USER_CUSTOMER'));
    }
}


