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

use Thelia\Model\Area;
use Thelia\Model\Event\AreaEvent;

/**
 * Class AreaAddCountryEvent
 * @package Thelia\Core\Event\Area
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaAddCountryEvent extends AreaEvent
{
    protected $areaId;
    protected $countryId;

    public function __construct(Area $area, $countryId)
    {
        parent::__construct($area);

        $this->countryId = $countryId;
    }

    /**
     *
     * @return $this
     */
    public function setCountryId($countryId)
    {
        $this->countryId = $countryId;

        return $this;
    }

    /**
     */
    public function getCountryId()
    {
        return $this->countryId;
    }
}
