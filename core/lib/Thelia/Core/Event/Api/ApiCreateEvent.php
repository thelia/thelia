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

namespace Thelia\Core\Event\Api;

use Thelia\Core\Event\ActionEvent;

/**
 * Class ApiCreateEvent
 * @package Thelia\Core\Event\Api
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ApiCreateEvent extends ActionEvent
{
    protected $label;
    protected $profile;

    public function __construct($label, $profile)
    {
        $this->label = $label;
        $this->profile = $profile;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param mixed $profile
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
    }

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }
}
