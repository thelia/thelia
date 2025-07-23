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
    protected bool $validModule = false;
    protected float $minimumAmount;
    protected float $maximumAmount;

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

    public function isValidModule(): bool
    {
        return $this->validModule;
    }

    public function setValidModule(bool $validModule): static
    {
        $this->validModule = $validModule;

        return $this;
    }

    public function getMinimumAmount(): float
    {
        return $this->minimumAmount;
    }

    public function setMinimumAmount(float $minimumAmount): static
    {
        $this->minimumAmount = $minimumAmount;

        return $this;
    }

    public function getMaximumAmount(): float
    {
        return $this->maximumAmount;
    }

    public function setMaximumAmount(float $maximumAmount): static
    {
        $this->maximumAmount = $maximumAmount;

        return $this;
    }
}
