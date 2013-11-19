<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\ShippingZone;
use Thelia\Core\Event\ActionEvent;

/**
 * Class ShippingZoneAddAreaEvent
 * @package Thelia\Core\Event\ShippingZone
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ShippingZoneAddAreaEvent extends ActionEvent
{
    protected $area_id;
    protected $shopping_zone_id;

    public function __construct($area_id, $shopping_zone_id)
    {
        $this->area_id = $area_id;
        $this->shopping_zone_id = $shopping_zone_id;
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
     * @param mixed $shopping_zone_id
     *
     * @return $this
     */
    public function setShoppingZoneId($shopping_zone_id)
    {
        $this->shopping_zone_id = $shopping_zone_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getShoppingZoneId()
    {
        return $this->shopping_zone_id;
    }

}
