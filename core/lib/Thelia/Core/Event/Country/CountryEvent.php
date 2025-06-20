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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Country;

/**
 * Class CountryEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\CountryEvent
 */
class CountryEvent extends ActionEvent
{
    public function __construct(protected ?Country $country = null)
    {
    }

    /**
     * @param mixed $country
     */
    public function setCountry(Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function hasCountry(): bool
    {
        return $this->country instanceof Country;
    }
}
