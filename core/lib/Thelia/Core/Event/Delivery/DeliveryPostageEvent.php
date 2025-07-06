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
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\BaseModuleInterface;

/**
 * Class DeliveryPostageEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class DeliveryPostageEvent extends ActionEvent
{
    /** @var bool */
    protected $validModule = false;

    /** @var OrderPostage|null */
    protected $postage;

    /**
     * @var DateTime|null
     */
    protected $deliveryDate;

    /**
     * @var string
     */
    protected $deliveryMode;

    /** @var array */
    protected $additionalData = [];

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

    /**
     * @return DateTime|null
     */
    public function getDeliveryDate()
    {
        return $this->deliveryDate;
    }

    /**
     * @param DateTime|null $deliveryDate
     */
    public function setDeliveryDate($deliveryDate): static
    {
        $this->deliveryDate = $deliveryDate;

        return $this;
    }

    /**
     * @return AbstractDeliveryModule
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param AbstractDeliveryModule $module
     */
    public function setModule($module): static
    {
        $this->module = $module;

        return $this;
    }

    /**
     * @return OrderPostage|null
     */
    public function getPostage()
    {
        return $this->postage;
    }

    /**
     * @param float|OrderPostage|null $postage
     */
    public function setPostage($postage): static
    {
        $this->postage = OrderPostage::loadFromPostage($postage);

        return $this;
    }

    /**
     * @return bool
     */
    public function isValidModule()
    {
        return $this->validModule;
    }

    /**
     * @param bool $validModule
     */
    public function setValidModule($validModule): static
    {
        $this->validModule = $validModule;

        return $this;
    }

    public function hasAdditionalData(): bool
    {
        return \count($this->additionalData) > 0;
    }

    /**
     * @return array
     */
    public function getAdditionalData()
    {
        return $this->additionalData;
    }

    /**
     * @param array $additionalData
     */
    public function setAdditionalData($additionalData): static
    {
        $this->additionalData = $additionalData;

        return $this;
    }

    /**
     * @param string $key   the key of the additional data
     * @param mixed  $value the value of the additional data
     */
    public function addAdditionalData($key, $value): static
    {
        $this->additionalData[$key] = $value;

        return $this;
    }

    /**
     * @throws PropelException
     *
     * @return Country|null
     */
    public function getCountry()
    {
        return $this->getAddress() instanceof Address ? $this->getAddress()->getCountry() : $this->country;
    }

    /**
     * @throws PropelException
     *
     * @return State|null
     */
    public function getState()
    {
        return $this->getAddress() instanceof Address ? $this->getAddress()->getState() : $this->state;
    }

    public function getDeliveryMode()
    {
        return $this->deliveryMode;
    }

    /**
     * @throws \Exception
     */
    public function setDeliveryMode($deliveryMode): static
    {
        if (!\in_array($deliveryMode, ['delivery', 'pickup', 'localPickup'])) {
            throw new \Exception(Translator::getInstance()->trans('A delivery module can only be of type "delivery", "pickup" or "localPickup".'));
        }

        $this->deliveryMode = $deliveryMode;

        return $this;
    }
}
