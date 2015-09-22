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
 * Class AreaCreateEvent
 * @package Thelia\Core\Event\Area
 * @author Manuel Raynaud <manu@raynaud.io>
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

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAreaName()
    {
        return $this->name;
    }
}
