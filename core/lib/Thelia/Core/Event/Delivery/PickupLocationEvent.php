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

namespace Thelia\Core\Event\Delivery;

use Thelia\Api\Resource\DeliveryPickupLocation;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Address;
use Thelia\Model\Country;
use Thelia\Model\State;

/**
 * Class PickupLocationEvent.
 *
 * @author Damien Foulhoux <dfoulhoux@openstudio.com>
 */
class PickupLocationEvent extends ActionEvent
{
    protected ?string $address;
    protected ?string $city;
    protected ?string $zipCode;
    protected ?State $state;
    protected ?Country $country;
    protected ?int $radius;
    protected ?int $maxRelays;
    protected ?int $orderWeight;
    protected ?array $moduleIds;
    protected array $locations = [];

    /**
     * PickupLocationEvent constructor.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws \Exception
     */
    public function __construct(
        Address $addressModel = null,
        int $radius = null,
        int $maxRelays = null,
        string $address = null,
        string $city = null,
        string $zipCode = null,
        int $orderWeight = null,
        State $state = null,
        Country $country = null,
        array $moduleIds = null
    ) {
        $this->radius = $radius ?? 20000;
        $this->maxRelays = $maxRelays ?? 15;
        $this->orderWeight = $orderWeight;
        $this->address = $address;
        $this->city = $city;
        $this->zipCode = $zipCode;
        $this->state = $state;
        $this->country = $country;
        $this->moduleIds = $moduleIds;
        if (null !== $addressModel) {
            $this->address = $addressModel->getAddress1();
            $this->city = $addressModel->getCity();
            $this->zipCode = $addressModel->getZipcode();
            $this->state = $addressModel->getState();
            $this->country = $addressModel->getCountry();
        }

        if ($this->address === null && $this->city === null && $this->zipCode === null) {
            throw new \Exception('Not enough informations to retrieve pickup locations');
        }
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(?string $zipCode): void
    {
        $this->zipCode = $zipCode;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function setState(?State $state): void
    {
        $this->state = $state;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): void
    {
        $this->country = $country;
    }

    public function getRadius(): ?int
    {
        return $this->radius;
    }

    public function setRadius(?int $radius): void
    {
        $this->radius = $radius;
    }

    public function getModuleIds(): ?array
    {
        return $this->moduleIds;
    }

    public function setModuleIds(?array $moduleIds): void
    {
        $this->moduleIds = $moduleIds;
    }

    public function getLocations(): array
    {
        return $this->locations;
    }

    public function setLocations(array $locations): static
    {
        $this->locations = $locations;

        return $this;
    }

    public function appendLocation(DeliveryPickupLocation $location): static
    {
        $this->locations[] = $location;

        return $this;
    }

    public function getOrderWeight(): ?int
    {
        return $this->orderWeight;
    }

    public function setOrderWeight(?int $orderWeight): static
    {
        $this->orderWeight = $orderWeight;

        return $this;
    }

    public function getMaxRelays(): ?int
    {
        return $this->maxRelays;
    }

    public function setMaxRelays(?int $maxRelays): static
    {
        $this->maxRelays = $maxRelays;

        return $this;
    }
}
