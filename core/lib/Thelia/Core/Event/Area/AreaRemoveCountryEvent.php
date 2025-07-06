<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Area;

use Thelia\Model\Area;

/**
 * Class AreaRemoveCountryEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaRemoveCountryEvent extends AreaAddCountryEvent
{
    /**
     * @param int|null $stateId
     */
    public function __construct(Area $area, array $countryIds, protected $stateId = null)
    {
        parent::__construct($area, $countryIds);
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
