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
namespace Thelia\Core\Event\Payment;

use Thelia\Model\Cart;
use Thelia\Module\PaymentModuleInterface;

/**
 * Class IsValidPaymentEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class IsValidPaymentEvent extends BasePaymentEvent
{
    /** @var bool */
    protected $validModule = false;

    /** @var float */
    protected $minimumAmount;

    /** @var float */
    protected $maximumAmount;

    /**
     * IsValidPaymentEvent constructor.
     */
    public function __construct(PaymentModuleInterface $module, protected Cart $cart)
    {
        parent::__construct($module);
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

    /**
     * @return float
     */
    public function getMinimumAmount()
    {
        return $this->minimumAmount;
    }

    /**
     * @param float $minimumAmount
     */
    public function setMinimumAmount($minimumAmount): static
    {
        $this->minimumAmount = $minimumAmount;

        return $this;
    }

    /**
     * @return float
     */
    public function getMaximumAmount()
    {
        return $this->maximumAmount;
    }

    /**
     * @param float $maximumAmount
     */
    public function setMaximumAmount($maximumAmount): static
    {
        $this->maximumAmount = $maximumAmount;

        return $this;
    }
}
