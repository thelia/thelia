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

/**
 * A change the merchant asked for that would leave the checkout without a shape it can
 * sell with: the cart somewhere other than first, the payment not next to last, the
 * confirmation not last, or a mandatory step turned off.
 *
 * Deliberately not a CheckoutException: that one means "this cart is not ready yet" and
 * is caught by the progression to find the step a buyer is on. This one means "the back
 * office asked for something impossible", and nothing catches it silently.
 */
class CheckoutStepConfigurationException extends \RuntimeException
{
    /**
     * @param array<string, string> $parameters placeholders of the message, substituted whether or
     *                                          not the shop has a translation for it
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $parameters = [])
    {
        parent::__construct(Translator::getInstance()->trans($message, $parameters), $code, $previous);
    }
}
