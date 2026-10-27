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

namespace Thelia\Domain\Cart\Exception;

/**
 * The sale element a line is added for has no price to write on it (no price in the default
 * currency): there is no line to add. An InvalidCartException, like every refusal of the
 * cart, so that the callers answering one (the front API with a 422, the live components of
 * the theme with their notice, a module catching it) answer this one the same way.
 */
final class ProductWithoutPriceException extends InvalidCartException
{
    public function __construct(string $message = 'This product has no price: it cannot be added to the cart.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
