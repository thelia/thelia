<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Administrator;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Admin;

/**
 * Class AdministratorUpdatePasswordEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AdministratorUpdatePasswordEvent extends ActionEvent
{
    /**
     * @var string new administrator password
     */
    protected $password;

    public function __construct(protected Admin $admin)
    {
    }

    /**
     * @param string $password
     */
    public function setPassword($password): static
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

    public function setAdmin(Admin $admin): static
    {
        $this->admin = $admin;

        return $this;
    }

    public function getAdmin(): Admin
    {
        return $this->admin;
    }
}
