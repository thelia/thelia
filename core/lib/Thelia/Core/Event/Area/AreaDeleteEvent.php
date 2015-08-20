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
 * Class AreaDeleteEvent
 * @package Thelia\Core\Event\Area
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaDeleteEvent extends AreaEvent
{
    /**
     * @var int area id
     */
    protected $area_id;

    public function __construct($area_id)
    {
        $this->area_id = $area_id;
    }

    /**
     * @param null $area_id
     *
     * @return $this
     */
    public function setAreaId($area_id)
    {
        $this->area_id = $area_id;

        return $this;
    }

    /**
     * @return null
     */
    public function getAreaId()
    {
        return $this->area_id;
    }
}
