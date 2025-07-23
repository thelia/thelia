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

namespace Thelia\Core\Event\Delivery;

use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\BaseModuleInterface;

/**
 * Class DeliveryPostageEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class DeliveryPostageEvent extends ActionEvent
{
    protected bool $validModule = false;
    protected ?OrderPostage $postage = null;
    protected ?DateTime $deliveryDate = null;
    protected string $deliveryMode;
    protected array $additionalData = [];

    /**
     * DeliveryPostageEvent constructor.
     *
     * @param BaseModuleInterface $module
     */
    public function __construct(protected $module, protected Cart $cart, protected ?Address $address = null, protected ?Country $country = null, protected ?State $state = null)
    {
    }

    public function getCart(): Cart
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

    public function setAddress(?Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getDeliveryDate(): ?DateTime
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?DateTime $deliveryDate): static
    {
        $this->deliveryDate = $deliveryDate;

        return $this;
    }

    public function getModule(): BaseModuleInterface
    {
        return $this->module;
    }

    public function setModule(BaseModuleInterface $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getPostage(): ?OrderPostage
    {
        return $this->postage;
    }

    public function setPostage(float|OrderPostage|null $postage): static
    {
        $this->postage = OrderPostage::loadFromPostage($postage);

        return $this;
    }

    public function isValidModule(): bool
    {
        return $this->validModule;
    }

    public function setValidModule(bool $validModule): static
    {
        $this->validModule = $validModule;

        return $this;
    }

    public function hasAdditionalData(): bool
    {
        return [] !== $this->additionalData;
    }

    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    public function setAdditionalData(array $additionalData): static
    {
        $this->additionalData = $additionalData;

        return $this;
    }

    /**
     * @param string $key   the key of the additional data
     * @param mixed  $value the value of the additional data
     */
    public function addAdditionalData(string $key, mixed $value): static
    {
        $this->additionalData[$key] = $value;

        return $this;
    }

    /**
     * @throws PropelException
     */
    public function getCountry(): ?Country
    {
        return $this->getAddress() instanceof Address ? $this->getAddress()->getCountry() : $this->country;
    }

    /**
     * @throws PropelException
     */
    public function getState(): ?State
    {
        return $this->getAddress() instanceof Address ? $this->getAddress()->getState() : $this->state;
    }

    public function getDeliveryMode(): string
    {
        return $this->deliveryMode;
    }

    /**
     * @throws \Exception
     */
    public function setDeliveryMode($deliveryMode): static
    {
        if (!\in_array($deliveryMode, ['delivery', 'pickup', 'localPickup'], true)) {
            throw new \Exception(Translator::getInstance()->trans('A delivery module can only be of type "delivery", "pickup" or "localPickup".'));
        }

        $this->deliveryMode = $deliveryMode;

        return $this;
    }
}
