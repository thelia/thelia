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
use Thelia\Domain\Checkout\Service\ConsentGuard;
use Thelia\Model\Cart;
use Thelia\Model\CheckoutStep;

/**
 * Who is being invoiced, how they pay, and what they have to agree to first.
 *
 * The three checks run in the order the buyer meets them on the screen — the billing
 * address, the payment choice, then the boxes under it — which is also the order
 * CheckoutValidationService refuses an order in.
 *
 * The consents are reached through ConsentAcceptanceReaderInterface rather than from the
 * session directly, so that the verdict this step gives about a cart can be reproduced
 * outside a browser — from the API, from a command line — by binding another reader.
 */
final readonly class PaymentStepProvider implements CheckoutStepProviderInterface
{
    public function __construct(
        private CartGuard $cartGuard,
        private ConsentGuard $consentGuard,
    ) {
    }

    public function code(): string
    {
        return CheckoutStep::CODE_PAYMENT;
    }

    public function defaultPosition(): int
    {
        return 3;
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
        $this->cartGuard->checkInvoiceAddressLegalIdentifiers($cart);
        $this->cartGuard->checkValidPayment($cart);
        $this->consentGuard->checkMandatoryConsentsAccepted();
    }

    public function componentName(): ?string
    {
        return null;
    }
}
