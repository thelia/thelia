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

namespace Thelia\Core\Security\Exception;

use Thelia\Model\Customer;

/**
 * Class CustomerNotConfirmedException
 * @package Thelia\Core\Security\Exception
 * @author Baixas Alban <abaixas@openstudio.fr>
 */
class CustomerNotConfirmedException extends AuthenticationException
{
    /** @var Customer */
    protected $user;

    /**
     * @return Customer
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return $this
     */
    public function setUser(Customer $user)
    {
        $this->user = $user;
        return $this;
    }
}
