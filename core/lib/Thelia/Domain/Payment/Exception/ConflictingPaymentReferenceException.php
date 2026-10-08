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
 * A movement was reported under a provider reference the journal already holds with
 * another outcome or another amount. A line is never rewritten: a new attempt carries a
 * new reference.
 */
final class ConflictingPaymentReferenceException extends PaymentException
{
}
