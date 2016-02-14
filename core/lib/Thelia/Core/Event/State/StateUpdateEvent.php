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

namespace Thelia\Core\Event\State;

/**
 * Class StateUpdateEvent
 * @package Thelia\Core\Event\State
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateUpdateEvent extends StateCreateEvent
{
    /** @var int */
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
     * @return $this
     */
    public function setStateId($state_id)
    {
        $this->state_id = $state_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getStateId()
    {
        return $this->state_id;
    }
}
