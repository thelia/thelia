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

namespace Thelia\Domain\Payment\Exception;

use Thelia\Domain\Checkout\Exception\InvalidPaymentException;

/**
 * The instant shares and the payment module chosen cannot make up one payment. The
 * message is the core's own: no amount, no module data, so it can be answered as is.
 */
final class PaymentCompositionRefusedException extends InvalidPaymentException
{
    public const REMAINDER_WITHOUT_PROVIDER = 'remainder-without-provider';
    public const PROVIDER_CANNOT_CHARGE_BALANCE = 'provider-cannot-charge-balance';

    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function remainderWithoutProvider(): self
    {
        return new self(
            self::REMAINDER_WITHOUT_PROVIDER,
            'The payment means chosen do not cover the order and no payment module is left to charge the rest.',
        );
    }

    public static function providerCannotChargeBalance(): self
    {
        return new self(
            self::PROVIDER_CANNOT_CHARGE_BALANCE,
            'This payment module cannot be combined with another payment means.',
        );
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
