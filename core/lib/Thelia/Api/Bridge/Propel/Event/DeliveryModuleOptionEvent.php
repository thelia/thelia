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

namespace Thelia\Api\Bridge\Propel\Event;

use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\State;

class DeliveryModuleOptionEvent extends ActionEvent
{
    protected Module $module;
    protected ?Cart $cart;
    protected ?Address $address;
    protected ?Country $country;
    protected ?State $state;

    protected array $deliveryModuleOptions = [];

    /**
     * @throws \RuntimeException
     */
    public function __construct(
        Module $module,
        Address $address = null,
        Cart $cart = null,
        Country $country = null,
        State $state = null
    ) {
        $this->module = $module;
        $this->address = $address;
        $this->cart = $cart;
        $this->country = $country;
        $this->state = $state;

        if (null === $this->address && null === $this->country) {
            throw new \RuntimeException(Translator::getInstance()->trans('Not enough informations to retrieve module options'));
        }

        if (!$module->isDeliveryModule()) {
            throw new \RuntimeException(Translator::getInstance()->trans($module->getTitle().' is not a delivery module.'));
        }
    }

    /**
     * @return DeliveryModuleOption[]
     */
    public function getDeliveryModuleOptions(): array
    {
        return $this->deliveryModuleOptions;
    }

    public function setDeliveryModuleOptions(array $deliveryModuleOptions): static
    {
        $this->deliveryModuleOptions = $deliveryModuleOptions;

        return $this;
    }

    public function appendDeliveryModuleOptions(DeliveryModuleOption $deliveryModuleOption): static
    {
        $this->deliveryModuleOptions[] = $deliveryModuleOption;

        return $this;
    }

    public function getModule(): Module
    {
        return $this->module;
    }

    public function setModule(Module $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function setState(State $state): static
    {
        $this->state = $state;

        return $this;
    }
}
