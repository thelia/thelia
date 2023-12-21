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

namespace Thelia\Core\Security\Role;

/**
 * Role is a simple implementation of a RoleInterface where the role is a
 * string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Role implements RoleInterface
{
    private $role;

    /**
     * Constructor.
     *
     * @param string $role The role name
     */
    public function __construct(string $role)
    {
        $this->role = (string) $role;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function __toString()
    {
        return $this->role;
    }
}
