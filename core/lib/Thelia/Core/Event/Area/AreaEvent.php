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

namespace Thelia\Core\Event\Area;
use Thelia\Core\Event\ActionEvent;

/**
 * Class AreaEvent
 * @package Thelia\Core\Event\Shipping
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
