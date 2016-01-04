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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\State;

/**
 * Class StateEvent
 * @package Thelia\Core\Event\State
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateEvent extends ActionEvent
{
    /*
     * @var \Thelia\Model\State
     */
    protected $state;

    public function __construct(State $state = null)
    {
        $this->state = $state;
    }

    /**
     * @param mixed $state
     */
    public function setState(State $state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return null|\Thelia\Model\State
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return bool
     */
    public function hasState()
    {
        return null !== $this->state;
    }
}
