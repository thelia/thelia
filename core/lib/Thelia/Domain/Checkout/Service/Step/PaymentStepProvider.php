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
use Thelia\Domain\Checkout\Exception\CheckoutException;
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
final readonly class PaymentStepProvider implements CheckoutStepProviderInterface, CheckoutStepViolationCollectorInterface
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
        foreach ($this->guardsFor($cart) as $guard) {
            $guard();
        }
    }

    /**
     * The three guards are independent of one another — a billing address missing its
     * legal identifiers says nothing about the payment module, and neither says anything
     * about the boxes under it — so a buyer who got two of them wrong is told about both
     * rather than sent back to the same screen twice.
     */
    public function collectRefusals(Cart $cart): array
    {
        $refusals = [];

        foreach ($this->guardsFor($cart) as $guard) {
            try {
                $guard();
            } catch (CheckoutException $refusal) {
                $refusals[] = $refusal;
            }
        }

        return $refusals;
    }

    public function componentName(): ?string
    {
        return null;
    }

    /**
     * What this step asks the cart, in the order the buyer meets it on the screen: the
     * billing address, the payment choice, then the boxes under it. The single list both
     * `check()` and `collectRefusals()` read, so the two can never drift apart.
     *
     * @return list<callable(): void>
     */
    private function guardsFor(Cart $cart): array
    {
        return [
            function () use ($cart): void {
                $this->cartGuard->checkInvoiceAddressLegalIdentifiers($cart);
            },
            function () use ($cart): void {
                $this->cartGuard->checkValidPayment($cart);
            },
            function (): void {
                $this->consentGuard->checkMandatoryConsentsAccepted();
            },
        ];
    }
}
