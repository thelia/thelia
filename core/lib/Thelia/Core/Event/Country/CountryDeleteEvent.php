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

namespace Thelia\Core\Event\Country;

/**
 * Class CountryDeleteEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CountryDeleteEvent extends CountryEvent
{
    /**
     * @param int $country_id
     */
    public function __construct(protected $country_id)
    {
    }

    public function setCountryId(int $country_id): void
    {
        $this->country_id = $country_id;
    }

    public function getCountryId(): int
    {
        return $this->country_id;
    }
}
