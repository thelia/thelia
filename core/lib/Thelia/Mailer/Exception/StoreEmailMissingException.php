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

namespace Thelia\Mailer\Exception;

/**
 * The shop has no address to send from: what an administrator fixes in the store
 * information, and what the back office tells them when they test a mail.
 */
final class StoreEmailMissingException extends EmailNotSentException
{
}
