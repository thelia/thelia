<?php

namespace Thelia\Domain\Shipping\DTO;

use Thelia\Api\Resource\DeliveryModule;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Model\Module;

class DeliveryModuleWithOptionDTO
{

    /**
     * @param Module $module
     * @param array<DeliveryModuleOption> $deliveryModuleOption
     */
    public function __construct(
        protected DeliveryModule $deliveryModule,
        protected array $deliveryModuleOption,
    )
    {
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
