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

namespace Thelia\Messenger;

use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Thelia\Exception\UserFacingFailure;

/**
 * A job the shop sets aside in the failure transport at once, with a reason written
 * for the administrator: the back office shows it as it is.
 */
final class JobSetAsideException extends UnrecoverableMessageHandlingException implements UserFacingFailure
{
}
