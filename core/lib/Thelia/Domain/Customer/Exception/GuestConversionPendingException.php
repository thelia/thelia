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
 * The guest account already holds a password that is waiting for its activation code.
 *
 * Replacing it would let the code that was mailed open the account on a password its
 * owner never chose. The password stays until the code is answered or expires, unless
 * the caller has proved they read the mailbox.
 */
final class GuestConversionPendingException extends \RuntimeException
{
}
