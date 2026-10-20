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

use Thelia\Model\OrderPaymentTransaction;
use Thelia\Tools\TerminalText;

/**
 * The provider refused the movement and took nothing: the one exception a payment module
 * throws to have the line settled as failed, with this message. Anything else it throws
 * leaves the line pending, since the call may have reached the provider.
 *
 * The code the provider answered, when the module has one, goes with the message:
 * build the refusal with withProviderCode(). A refusal without a code is written
 * without one; the line already reads as failed. The integer code of the exception
 * (`new PaymentRefusedException($message, 51)`) is not read: it is no provider code.
 */
final class PaymentRefusedException extends PaymentException
{
    public const PROVIDER_CODE_MAX_LENGTH = OrderPaymentTransaction::ERROR_CODE_MAX_LENGTH;

    public const MESSAGE_MAX_LENGTH = OrderPaymentTransaction::ERROR_MESSAGE_MAX_LENGTH;

    private ?string $providerCode = null;

    /**
     * The message carries the words of a provider, through a module: it is shown in a flash,
     * written to the administration log and answered by the admin API, so it is kept on one
     * line, without the characters that reorder or hide text, and cut to what the journal keeps.
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(mb_substr(TerminalText::onOneLine($message), 0, self::MESSAGE_MAX_LENGTH), $code, $previous);
    }

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
