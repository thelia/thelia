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
 * Where the order is going and who carries it.
 *
 * A cart holding nothing to ship has no such question to answer, so the step is left
 * out of the tunnel rather than shown with a single "no shipping needed" line.
 */
final readonly class DeliveryStepProvider implements CheckoutStepProviderInterface
{
    public function __construct(
        private CartGuard $cartGuard,
    ) {
    }

    public function code(): string
    {
        return CheckoutStep::CODE_DELIVERY;
    }

    public function defaultPosition(): int
    {
        return 2;
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function isSkippedFor(Cart $cart): bool
    {
        return $cart->isVirtual();
    }

    public function check(Cart $cart): void
    {
        $this->cartGuard->checkValidDelivery($cart);
    }

    public function componentName(): ?string
    {
        return null;
    }
}
