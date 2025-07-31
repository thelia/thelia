<?php

namespace Thelia\Api\State\Collection;

class DeliveryModuleCollection
{
    private array $delivery = [];
    private array $pickup = [];
    private array $localPickup = [];

    public function __construct(array $modules = [])
    {
        foreach ($modules as $module) {
            $this->addModule($module);
        }
    }

    private function addModule(array $module): void
    {
        match ($module['deliveryMode']) {
            'delivery' => $this->delivery[] = $module,
            'pickup' => $this->pickup[] = $module,
            'localPickup' => $this->localPickup[] = $module,
            default => null,
        };
    }

    public function getDelivery(): array
    {
        return $this->delivery;
    }

    public function getPickup(): array
    {
        return $this->pickup;
    }

    public function getLocalPickup(): array
    {
        return $this->localPickup;
    }

    public function hasDelivery(): bool
    {
        return !empty($this->delivery);
    }

    public function hasPickup(): bool
    {
        return !empty($this->pickup);
    }

    public function hasLocalPickup(): bool
    {
        return !empty($this->localPickup);
    }

    public function setDelivery(array $delivery): DeliveryModuleCollection
    {
        $this->delivery = $delivery;
        return $this;
    }
}
