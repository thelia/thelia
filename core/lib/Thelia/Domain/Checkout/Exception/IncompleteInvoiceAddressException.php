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
 * The billing address of the cart is there but not usable for an invoice.
 *
 * Kept apart from MissingAddressException so that a theme can send the buyer back to the
 * billing step with the address prefilled, instead of to the delivery step: the address
 * exists and is selected, only some of what an invoice requires is missing from it.
 */
class IncompleteInvoiceAddressException extends CheckoutException
{
    public function __construct(string $message = 'The billing address is missing information an invoice requires', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
