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

namespace Thelia\Core\Event;

/**
 * Class LostPasswordEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LostPasswordEvent extends ActionEvent
{
    protected $email;

    public function __construct($email)
    {
        $this->email = $email;
    }

    public function getEmail()
    {
        return $this->email;
    }
}
