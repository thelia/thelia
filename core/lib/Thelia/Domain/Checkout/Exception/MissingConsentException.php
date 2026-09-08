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
 * A consent the shop requires was not given.
 *
 * The message names the consent the buyer still has to tick, by the very wording they
 * were shown: "you must accept something" sends them back to the payment step with
 * nothing to look for.
 */
class MissingConsentException extends CheckoutException
{
    public function __construct(
        public readonly string $consentCode,
        public readonly string $consentTitle,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'You must accept "%consent" to place this order.',
            $code,
            $previous,
            ['%consent' => $consentTitle],
        );
    }
}
