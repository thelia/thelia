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
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaAddCountryEvent extends AreaEvent
{
    protected $areaId;
    protected $countryId;

    public function __construct($areaId, $countryId)
    {
        parent::__construct();

        $this->areaId = $areaId;
        $this->countryId = $countryId;
    }

    /**
     * @param mixed $areaId
     *
     * @return $this
     */
    public function setAreaId($areaId)
    {
        $this->areaId = $areaId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAreaId()
    {
        return $this->areaId;
    }

    /**
     * @param mixed $countryId
     *
     * @return $this
     */
    public function setCountryId($countryId)
    {
        $this->countryId = $countryId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCountryId()
    {
        return $this->countryId;
    }
}
