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
 * Class ApiUpdateEvent
 * @package Thelia\Core\Event\Api
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ApiUpdateEvent extends ActionEvent
{
    protected $api;

    protected $profile;

    public function __construct($api, $profile)
    {
        $this->api = $api;
        $this->profile = $profile;
    }

    /**
     * @param \Thelia\Model\Api $api
     */
    public function setApi($api)
    {
        $this->api = $api;

        return $this;
    }

    /**
     * @return \Thelia\Model\Api
     */
    public function getApi()
    {
        return $this->api;
    }

    /**
     * @param mixed $profile
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }
}
