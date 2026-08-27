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
 * Raised when a password reset link is not one this shop issued, no longer matches
 * the account it names, or has expired.
 *
 * The message must stay the same in every case: the visitor holding the link learns
 * only that it cannot be used any more.
 */
final class InvalidPasswordResetTokenException extends \RuntimeException
{
}
