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

namespace Thelia\Domain\Checkout\Exception;

use Thelia\Core\Translation\Translator;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;

abstract class CheckoutException extends \RuntimeException
{
    /**
     * @param array<string, string> $parameters placeholders of the message, substituted whether or
     *                                          not the shop has a translation for it
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $parameters = [])
    {
        $message = Translator::getInstance()->trans($message, $parameters);
        parent::__construct($message, $code, $previous);
    }

    /**
     * What a client branches on, as opposed to the message, which is what it shows.
     *
     * Answered here rather than declared abstract so that a refusal shipped by a module
     * keeps working as it is: it reads as "the checkout refused this cart" until the
     * module has something more precise to say.
     */
    public function violationCode(): string
    {
        return CheckoutViolationCode::CheckoutRefused->value;
    }

    /**
     * What this refusal knows beyond its wording — the code of the consent that was not
     * ticked, the field of the address that is missing — for a caller that has to act on
     * it rather than print it.
     *
     * @return array<string, mixed>
     */
    public function violationDetails(): array
    {
        return [];
    }
}
