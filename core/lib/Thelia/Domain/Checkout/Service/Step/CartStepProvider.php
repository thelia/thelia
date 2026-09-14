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

namespace Thelia\Domain\Checkout\Service\Step;

use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Model\Cart;
use Thelia\Model\CheckoutStep;

/**
 * The step the checkout opens on: what the buyer is about to pay for.
 */
final readonly class CartStepProvider implements CheckoutStepProviderInterface
{
    public function __construct(
        private CartGuard $cartGuard,
    ) {
    }

    public function code(): string
    {
        return CheckoutStep::CODE_CART;
    }

    public function defaultPosition(): int
    {
        return 1;
    }

    public function isMandatory(): bool
    {
        return true;
    }

    public function isSkippedFor(Cart $cart): bool
    {
        return false;
    }

    public function check(Cart $cart): void
    {
        $this->cartGuard->checkCartNotEmpty($cart);
    }

    public function componentName(): ?string
    {
        return null;
    }
}
