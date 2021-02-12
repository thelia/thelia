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

namespace Thelia\Core\Event\ShippingZone;

use Thelia\Core\Event\ActionEvent;

/**
 * Class ShippingZoneAddAreaEvent.
 *
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
     * @return $this
     */
    public function setAreaId($area_id)
    {
        $this->area_id = $area_id;

        return $this;
    }

    public function getAreaId()
    {
        return $this->area_id;
    }

    /**
     * @return $this
     */
    public function setShippingZoneId($shipping_zone_id)
    {
        $this->shipping_zone_id = $shipping_zone_id;

        return $this;
    }

    public function getShippingZoneId()
    {
        return $this->shipping_zone_id;
    }
}
