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

namespace Thelia\Mailer;

/**
 * Hides the credentials a mail transport puts in the reason it refuses a message.
 *
 * A mailer names the DSN it was configured with when it fails, password included:
 * `smtp://user:s3cr3t@mail.example.com`. A log or a screen is read far more widely
 * than the configuration of a shop, so the userinfo part of any URL is replaced
 * before the reason is shown or written.
 */
final class TransportCredentials
{
    public static function hide(string $message): string
    {
        // Up to the last @ before the path: a password may hold one of its own.
        return preg_replace('#://[^/\s]*@#', '://***@', $message) ?? $message;
    }
}
