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

/**
 * The provider refused the movement and took nothing: the one exception a payment module
 * throws to have the line settled as failed, with this message. Anything else it throws
 * leaves the line pending, since the call may have reached the provider.
 *
 * The code the provider answered, when the module has one, goes with the message:
 * build the refusal with withProviderCode(). A refusal without a code is written
 * without one; the line already reads as failed.
 */
class PaymentRefusedException extends PaymentException
{
    /** The size of `order_payment_transaction`.`error_code`. */
    public const PROVIDER_CODE_MAX_LENGTH = 50;

    private ?string $providerCode = null;

    public static function withProviderCode(string $message, ?string $providerCode, ?\Throwable $previous = null): self
    {
        $refusal = new self($message, 0, $previous);
        $code = trim((string) $providerCode);
        $refusal->providerCode = '' === $code ? null : mb_substr($code, 0, self::PROVIDER_CODE_MAX_LENGTH);

        return $refusal;
    }

    public function getProviderCode(): ?string
    {
        return $this->providerCode;
    }
}
