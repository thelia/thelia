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
 * A message the shop asked for that did not leave.
 *
 * Raised only by the "OrFail" methods of the mailer, for the callers that have
 * to know: an order status action journals the failure so the back office can
 * show it. Its text is written to be read by an administrator, so it names the
 * message code and the kind of failure and nothing else — the recipient address
 * and the transport detail stay in the server log.
 */
final class EmailNotSentException extends \RuntimeException
{
    public static function storeEmailMissing(string $messageCode): self
    {
        return new self(\sprintf("Can't send email message %s: store email address is not defined.", $messageCode));
    }

    public static function emptyRecipientList(string $messageCode): self
    {
        return new self(\sprintf('Message %s not sent: recipient list is empty.', $messageCode));
    }

    public static function noShopNotificationRecipient(string $messageCode): self
    {
        return new self(\sprintf('Message %s not sent: no shop notification recipient is configured (store_notification_emails, Configuration > Store information).', $messageCode));
    }

    public static function sendingFailed(string $messageCode, \Throwable $cause): self
    {
        return new self(
            \sprintf('Message %s not sent: the mailer refused it (%s). See the server log for details.', $messageCode, $cause::class),
            0,
            $cause,
        );
    }
}
