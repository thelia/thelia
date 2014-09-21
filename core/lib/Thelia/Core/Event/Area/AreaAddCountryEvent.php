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

namespace Thelia\Core\Event\Area;

/**
 * Class AreaAddCountryEvent
 * @package Thelia\Core\Event\Area
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AreaAddCountryEvent extends AreaEvent
{
    protected $area_id;
    protected $country_id;

    public function __construct($area_id, $country_id)
    {
        $this->area_id = $area_id;
        $this->country_id = $country_id;
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
     * @param mixed $country_id
     *
     * @return $this
     */
    public function setCountryId($country_id)
    {
        $this->country_id = $country_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCountryId()
    {
        return $this->country_id;
    }
}
