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

namespace Thelia\Core\Event\Cache;

use Thelia\Core\Event\ActionEvent;

/**
 * Class CacheEvent
 * @package Thelia\Core\Event\Cache
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CacheEvent extends ActionEvent
{
    /**
     * @var string cache directory
     */
    protected $dir;

    public function __construct($dir)
    {
        $this->dir = $dir;
    }

    /**
     * @param mixed $dir
     *
     * @return $this
     */
    public function setDir($dir)
    {
        $this->dir = $dir;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDir()
    {
        return $this->dir;
    }

}
