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

/**
 * A confirmation the shop will not even read.
 *
 * Unlike a refusal, nothing went wrong between the shop and the wallet: the request did
 * not come from a page the shop served for this cart, or it names a module that takes no
 * express payment. There is nothing to tell the buyer, and a front that sees this has a
 * bug or is not the front it claims to be.
 */
final class ExpressCheckoutConfirmationDeniedException extends \RuntimeException
{
    public static function becauseTheTokenIsNotForThisCart(): self
    {
        return new self('This confirmation was not issued for the cart in hand.');
    }

    public static function becauseTheModuleOffersNoExpressPayment(string $moduleCode): self
    {
        return new self(\sprintf('The module "%s" offers no express payment.', $moduleCode));
    }
}
