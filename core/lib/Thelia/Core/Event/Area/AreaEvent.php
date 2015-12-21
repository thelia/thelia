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

use Thelia\Core\Event\ActionEvent;

/**
 * Class AreaEvent
 * @package Thelia\Core\Event\Shipping
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Area
     */
    protected $area;

    public function __construct($area = null)
    {
        $this->area = $area;
    }

    /**
     * @param mixed $area
     *
     * @return $this
     */
    public function setArea($area)
    {
        $this->area = $area;

        return $this;
    }

    /**
     * @return null|\Thelia\Model\Area
     */
    public function getArea()
    {
        return $this->area;
    }

    public function hasArea()
    {
        return null !== $this->area;
    }
}
