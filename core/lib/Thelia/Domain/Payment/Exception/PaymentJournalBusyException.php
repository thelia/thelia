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
 * Another worker is writing the payment journal of the same order and did not finish in
 * time. Nothing was written: the caller retries, the way a provider retries a notification.
 */
final class PaymentJournalBusyException extends PaymentException
{
}
