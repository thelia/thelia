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

namespace Thelia\Domain\OrderReturn\Exception;

/**
 * Thrown when the database gave up on the transaction writing a return to
 * break a lock conflict with another request on the same rows: a deadlock, or
 * a row changed since the transaction first read it. Nothing was written, and
 * the same request sent again goes through or gets a real refusal.
 *
 * It is a ReturnNotAllowedException, so a caller that only knows refusals
 * still shows a message rather than failing; one that knows this class can
 * answer 409 and invite a retry.
 */
class ReturnRequestConflictException extends ReturnNotAllowedException
{
    public const string MESSAGE = 'Another request is being processed on this order, please try again.';
}
