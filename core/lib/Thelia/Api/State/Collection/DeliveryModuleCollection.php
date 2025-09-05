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

namespace Thelia\Api\State\Collection;

use Thelia\Domain\Checkout\Enum\DeliveryMode;

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
        $deliveryMode = $module['deliveryMode'] ?? null;

        match ($deliveryMode) {
            DeliveryMode::DELIVERY->value => $this->delivery[] = $module,
            DeliveryMode::PICKUP->value => $this->pickup[] = $module,
            DeliveryMode::LOCAL_PICKUP->value => $this->localPickup[] = $module,
            default => $this->delivery[] = $module,
        };
    }

    public function getAll(): array
    {
        return array_merge($this->delivery, $this->pickup, $this->localPickup);
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

    public function setDelivery(array $deliveryModules): self
    {
        $this->delivery = $deliveryModules;

        return $this;
    }

    public function setPickup(array $pickupModules): self
    {
        $this->pickup = $pickupModules;

        return $this;
    }

    public function setLocalPickup(array $localPickupModules): self
    {
        $this->localPickup = $localPickupModules;

        return $this;
    }
}
