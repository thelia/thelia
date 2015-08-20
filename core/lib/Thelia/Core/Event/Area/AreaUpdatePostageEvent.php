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
 * Class AreaUpdatePostageEvent
 * @package Thelia\Core\Event\Area
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaUpdatePostageEvent extends AreaEvent
{
    protected $area_id;
    protected $postage;

    public function __construct($area_id)
    {
        $this->area_id = $area_id;
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
     * @param mixed $postage
     *
     * @return $this
     */
    public function setPostage($postage)
    {
        $this->postage = $postage;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPostage()
    {
        return $this->postage;
    }
}
