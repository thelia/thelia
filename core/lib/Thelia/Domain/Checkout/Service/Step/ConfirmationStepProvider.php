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

use Thelia\Model\Cart;
use Thelia\Model\CheckoutStep;

/**
 * The step the tunnel ends on, once the order is placed.
 *
 * It checks nothing: by the time the buyer reads it, there is nothing left for them to
 * do. It is still a step of the list, because it is what tells the theme how many
 * screens the buyer is walking through and which one they are on.
 */
final readonly class ConfirmationStepProvider implements CheckoutStepProviderInterface
{
    public function code(): string
    {
        return CheckoutStep::CODE_CONFIRMATION;
    }

    public function defaultPosition(): int
    {
        return 4;
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
    }

    public function componentName(): ?string
    {
        return null;
    }
}
