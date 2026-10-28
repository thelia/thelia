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

namespace Thelia\Domain\Customer\Exception;

/**
 * The discount of a customer is a percentage: a rate outside 0..100 is refused before
 * anything is written. One that is already stored and out of range (only a direct write to the
 * database can leave one) counts for nothing in the prices, as {@see \Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate}
 * reads it.
 */
final class InvalidCustomerDiscountException extends \InvalidArgumentException
{
    public static function outsideTheRange(float $discount): self
    {
        return new self(\sprintf('The discount of a customer is a percentage between 0 and 100: %s was given.', rtrim(rtrim(number_format($discount, 6, '.', ''), '0'), '.')));
    }
}
