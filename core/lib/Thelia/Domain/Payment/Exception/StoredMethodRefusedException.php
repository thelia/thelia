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
 * Thrown by a payment module when its provider refuses to forget a stored method. The
 * shop keeps the method, and an HTTP caller answers 409.
 *
 * The message is the core's own and never varies: it is the body of that 409 and the
 * line the error listener logs. What the provider said is kept apart, in
 * providerReason, because a provider quotes what it was handed ("No such
 * payment_method: 'pm_…'"), and the core writes it nowhere, not even in a log. There is
 * no previous exception for the same reason: a logger writes the whole chain, and a
 * provider SDK exception carries the provider's message.
 */
final class StoredMethodRefusedException extends CustomerPaymentMethodException
{
    public const MESSAGE = 'The payment provider refused to forget this stored payment method.';

    public function __construct(
        public readonly ?string $providerReason = null,
    ) {
        parent::__construct(self::MESSAGE);
    }
}
