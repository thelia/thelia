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
 * The payment module failed without a refusal of the provider — a timeout, an answer it
 * could not read, a fault of its own. Whether the money moved is not known: the line
 * stays pending until the provider's notification settles it.
 *
 * The message is meant for the merchant; the module's own, which may carry what it sent
 * the provider, is in the previous exception and in the log.
 */
final class PaymentProviderUnreachableException extends PaymentException
{
}
