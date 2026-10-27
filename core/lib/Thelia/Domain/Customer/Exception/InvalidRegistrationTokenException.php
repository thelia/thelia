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
 * The registration token a visitor came with opens no group. Unknown, closed, regenerated or
 * malformed: the message is the same for all of them, so that the answer does not tell whether a
 * token ever existed.
 */
final class InvalidRegistrationTokenException extends \RuntimeException
{
    public static function create(): self
    {
        return new self('This registration link is not valid.');
    }
}
