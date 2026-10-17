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
 * A customer asked for more payments with a stored method than the shop allows in the
 * window: a stolen session trying card after card, or a client looping. Nothing was
 * asked of the provider.
 */
final class StoredMethodPaymentRateLimitedException extends CustomerPaymentMethodException
{
    public function __construct()
    {
        parent::__construct('Too many payments with a stored payment method. Please try again later.');
    }
}
