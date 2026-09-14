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

namespace Thelia\Core\Event\CheckoutStep;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CheckoutStep;

class CheckoutStepEvent extends ActionEvent
{
    public function __construct(protected ?CheckoutStep $checkoutStep = null)
    {
    }

    public function hasCheckoutStep(): bool
    {
        return $this->checkoutStep instanceof CheckoutStep;
    }

    public function getCheckoutStep(): ?CheckoutStep
    {
        return $this->checkoutStep;
    }

    public function setCheckoutStep(CheckoutStep $checkoutStep): static
    {
        $this->checkoutStep = $checkoutStep;

        return $this;
    }
}
