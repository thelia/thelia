<?php

declare(strict_types=1);

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
use Thelia\Model\Event\AreaEvent;

/**
 * Class AreaAddCountryEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaAddCountryEvent extends AreaEvent
{
    protected $areaId;

    protected $countryIds;

    public function __construct(Area $area, array $countryIds)
    {
        parent::__construct($area);

        $this->countryIds = $countryIds;
    }

    /**
     * @return $this
     */
    public function setCountryIds(int $countryIds): self
    {
        $this->countryIds = $countryIds;

        return $this;
    }

    public function getCountryIds(): array
    {
        return $this->countryIds;
    }
}
