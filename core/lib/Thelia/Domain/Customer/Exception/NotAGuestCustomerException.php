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
 * The account asked to be turned into a real one is not a guest.
 *
 * Converting again would overwrite the password of an account whose owner already
 * chose one, which is a password reset without any of a reset's guarantees.
 */
final class NotAGuestCustomerException extends \RuntimeException
{
}
