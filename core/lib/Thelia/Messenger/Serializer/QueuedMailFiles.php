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

namespace Thelia\Messenger\Serializer;

use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Keeps the files of the server out of the queued mails.
 *
 * A mail part may name a file, read only when the mail is sent: queued as it is, the
 * mail holds a path, and whoever writes a mail into the queue has the worker mail any
 * file of the server to any address. An attachment given by its path is queued with
 * its content instead, and a queued mail naming a file is never read back.
 */
final class QueuedMailFiles
{
    /**
     * The envelope with every attachment of its mail given by its content.
     */
    public static function inline(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();

        if (!$message instanceof SendEmailMessage || !$message->getMessage() instanceof Email) {
            return $envelope;
        }

        $email = $message->getMessage();
        $attachments = $email->getAttachments();

        if ([] === array_filter($attachments, self::namesAFile(...))) {
            return $envelope;
        }

        $inlined = clone $email;
        (new \ReflectionProperty(Email::class, 'attachments'))->setValue($inlined, array_map(self::withContent(...), $attachments));
        (new \ReflectionProperty(Email::class, 'cachedBody'))->setValue($inlined, null);

        $stamps = [];
        foreach ($envelope->all() as $stampsOfAClass) {
            array_push($stamps, ...$stampsOfAClass);
        }

        return new Envelope(new SendEmailMessage($inlined, $message->getEnvelope()), $stamps);
    }

    /**
     * True when sending the mail would read a file of the server.
     */
    public static function readsAFile(SendEmailMessage $message): bool
    {
        $mail = $message->getMessage();

        if (!$mail instanceof Message) {
            return false;
        }

        $parts = [(new \ReflectionProperty(Message::class, 'body'))->getValue($mail)];

        if ($mail instanceof Email) {
            array_push($parts, (new \ReflectionProperty(Email::class, 'cachedBody'))->getValue($mail), ...$mail->getAttachments());
        }

        foreach ($parts as $part) {
            if (self::holdsAFile($part)) {
                return true;
            }
        }

        return false;
    }

    private static function holdsAFile(mixed $part): bool
    {
        if ($part instanceof TextPart) {
            return self::namesAFile($part);
        }

        if ($part instanceof AbstractMultipartPart) {
            foreach ($part->getParts() as $child) {
                if (self::holdsAFile($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function namesAFile(TextPart $part): bool
    {
        return (new \ReflectionProperty(TextPart::class, 'body'))->getValue($part) instanceof File;
    }

    private static function withContent(DataPart $part): DataPart
    {
        if (!self::namesAFile($part)) {
            return $part;
        }

        $inlined = new DataPart($part->getBody(), $part->getFilename(), $part->getContentType());

        if ('inline' === $part->getDisposition()) {
            $inlined->asInline();
        }

        if ($part->hasContentId()) {
            $inlined->setContentId($part->getContentId());
        }

        return $inlined;
    }
}
