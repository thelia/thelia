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

namespace Thelia\Core\Event\ShippingZone;

use Thelia\Core\Event\ActionEvent;

/**
 * Class ShippingZoneAddAreaEvent
 * @package Thelia\Core\Event\ShippingZone
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ShippingZoneAddAreaEvent extends ActionEvent
{
    protected $area_id;
    protected $shipping_zone_id;

    public function __construct($area_id, $shipping_zone_id)
    {
        $this->area_id = $area_id;
        $this->shipping_zone_id = $shipping_zone_id;
    }

    /**
     * @param mixed $area_id
     *
     * @return $this
     */
    public function setAreaId($area_id)
    {
        $this->area_id = $area_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAreaId()
    {
        return $this->area_id;
    }

    /**
     * @param mixed $shipping_zone_id
     *
     * @return $this
     */
    public function setShippingZoneId($shipping_zone_id)
    {
        $this->shipping_zone_id = $shipping_zone_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getShippingZoneId()
    {
        return $this->shipping_zone_id;
    }
}
