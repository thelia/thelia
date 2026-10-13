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

namespace Thelia\Domain\Shipping\DeliveryDate\DTO;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Model\Country;
use Thelia\Model\Module;

/**
 * The soonest delivery a product page can announce, with the country it was estimated for:
 * a page that does not name the destination would promise days it cannot keep elsewhere.
 */
#[Exclude]
final readonly class ProductDeliveryEstimate
{
    public function __construct(
        public DeliveryEstimate $estimate,
        public Country $country,
        public Module $carrier,
    ) {
    }
}
