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

/**
 * Class AreaCreateEvent
 * @package Thelia\Core\Event\Area
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AreaCreateEvent extends AreaEvent
{
    protected $name;

    /**
     * @param mixed $name
     */
    public function setAreaName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getAreaName()
    {
        return $this->name;
    }

}
