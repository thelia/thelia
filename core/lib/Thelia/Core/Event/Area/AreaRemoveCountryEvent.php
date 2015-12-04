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
 * Class AreaRemoveCountryEvent
 * @package Thelia\Core\Event\Area
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaRemoveCountryEvent extends AreaAddCountryEvent
{
    /** @var int|null  */
    protected $stateId;

    public function __construct($areaId, $countryId, $stateId)
    {
        parent::__construct($areaId, $countryId);

        $this->stateId = $stateId;
    }

    /**
     * @return int|null
     */
    public function getStateId()
    {
        return $this->stateId;
    }

    /**
     * @param int|null $stateId
     */
    public function setStateId($stateId)
    {
        $this->stateId = $stateId;
        return $this;
    }
}
