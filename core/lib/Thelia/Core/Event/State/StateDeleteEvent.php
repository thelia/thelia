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

namespace Thelia\Core\Event\State;

/**
 * Class StateDeleteEvent
 * @package Thelia\Core\Event\State
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateDeleteEvent extends StateEvent
{
    /**
     * @var int state id
     */
    protected $state_id;

    /**
     * @param int $state_id
     */
    public function __construct($state_id)
    {
        $this->state_id = $state_id;
    }

    /**
     * @param int $state_id
     */
    public function setStateId($state_id)
    {
        $this->state_id = $state_id;
    }

    /**
     * @return int
     */
    public function getStateId()
    {
        return $this->state_id;
    }
}
