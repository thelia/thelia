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

namespace Thelia\Domain\Checkout\DTO;

/**
 * What a payment module asks the shop to show so a buyer can pay the checkout from a wallet.
 *
 * Thelia knows nothing of Apple Pay, Google Pay or any other wallet: it knows there is a
 * button, who it belongs to and where it leads. What the button looks like and what
 * happens once it is clicked belong to the module, which is why `attributes` is passed
 * through untouched — a wallet needs its own identifiers on the element, and the core has
 * no business naming them.
 *
 * `amountUrl`, `confirmationUrl` and `confirmationToken` are the shop's, never the
 * module's: the collector adds them to every button it hands out. The module's script
 * asks the amount route what the checkout comes to, and posts the wallet's payment to the
 * confirmation route, with the token attached to both.
 */
final readonly class ExpressPaymentButton
{
    /**
     * @param array<string, scalar> $attributes what the module needs on the rendered element
     */
    public function __construct(
        public int $paymentModuleId,
        public string $paymentModuleCode,
        public string $code,
        public string $label,
        public ?string $logo = null,
        public array $attributes = [],
        public ?string $confirmationUrl = null,
        public ?string $confirmationToken = null,
        public ?string $amountUrl = null,
    ) {
    }

    public function withConfirmation(string $confirmationUrl, string $amountUrl, string $token): self
    {
        return new self(
            $this->paymentModuleId,
            $this->paymentModuleCode,
            $this->code,
            $this->label,
            $this->logo,
            $this->attributes,
            $confirmationUrl,
            $token,
            $amountUrl,
        );
    }
}
