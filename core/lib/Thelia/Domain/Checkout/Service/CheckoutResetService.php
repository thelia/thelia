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

namespace Thelia\Domain\Checkout\Service;

use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Shipping\ShippingFacade;

readonly class CheckoutResetService
{
    public function __construct(
        private CartFacade $cartFacade,
        private ShippingFacade $shippingFacade,
    ) {
    }

    public function reset(): void
    {
        $this->cartFacade->reset();
        $this->shippingFacade->clearDeliveryData();
    }
}
