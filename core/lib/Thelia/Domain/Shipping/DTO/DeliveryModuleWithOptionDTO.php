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

namespace Thelia\Domain\Shipping\DTO;

use Thelia\Api\Resource\DeliveryModule;
use Thelia\Api\Resource\DeliveryModuleOption;

class DeliveryModuleWithOptionDTO
{
    /**
     * @param array<DeliveryModuleOption> $deliveryModuleOption
     */
    public function __construct(
        protected DeliveryModule $deliveryModule,
        protected array $deliveryModuleOption,
    ) {
    }

    public function getDeliveryModule(): DeliveryModule
    {
        return $this->deliveryModule;
    }

    /**
     * @return array<DeliveryModuleOption>
     */
    public function getDeliveryModuleOption(): array
    {
        return $this->deliveryModuleOption;
    }
}
