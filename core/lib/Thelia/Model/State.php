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

namespace Thelia\Model;

use Thelia\Model\Base\State as BaseState;

class State extends BaseState
{
    /**
     * Get the full ISO 3166-2 code of this state (e.g. "US-CA"), built from
     * the country ISO alpha-2 code and the state ISO code.
     *
     * @return string|null the full ISO 3166-2 code, or null if the country or the state ISO code is not defined
     */
    public function getIsoCode3166_2(): ?string
    {
        $country = $this->getCountry();
        $isocode = $this->getIsocode();

        if (null === $country || null === $isocode) {
            return null;
        }

        return $country->getIsoalpha2().'-'.$isocode;
    }
}
