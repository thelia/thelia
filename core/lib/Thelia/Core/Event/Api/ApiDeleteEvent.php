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
use Thelia\Model\Api;

/**
 * Class ApiDeleteEvent
 * @package Thelia\Core\Event\Api
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ApiDeleteEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Api
     */
    protected $api;

    /**
     * @param $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * @return \Thelia\Model\Api
     */
    public function getApi()
    {
        return $this->api;
    }
}
