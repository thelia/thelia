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

use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\Part\MessagePart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\DigestPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\RawMessage;

/**
 * Keeps the files of the server out of the queued mails.
 *
 * A mail part may name a file, read only when the mail is sent: queued as it is, the
 * mail holds a path, and whoever writes a mail into the queue has the worker mail any
 * file of the server to any address. An attachment given by its path is queued with
 * its content instead, and a queued mail naming a file, in any of its parts or in a
 * mail it carries, or of a class or holding a part the shop does not send mails with,
 * is never read back.
 */
final class QueuedMailFiles
{
    /**
     * The mails the shop sends.
     */
    private const MAILS = [
        RawMessage::class,
        Message::class,
        Email::class,
    ];

    /**
     * The templated mails, queued once rendered: the worker then renders nothing.
     */
    private const TEMPLATED_MAILS = [
        TemplatedEmail::class,
        NotificationEmail::class,
    ];

    /**
     * The parts made of other parts that Symfony builds a mail with.
     */
    private const MULTIPARTS = [
        AlternativePart::class,
        DigestPart::class,
        FormDataPart::class,
        MixedPart::class,
        RelatedPart::class,
    ];

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
     * True when sending the mail would read a file of the server, or when it is or holds
     * a class the shop does not send: a class of its own may keep a file anywhere.
     */
    public static function readsAFile(SendEmailMessage $message): bool
    {
        try {
            return self::mailReadsAFile($message->getMessage());
        } catch (\Throwable) {
            // A part that cannot even be looked at is not sent.
            return true;
        }
    }

    private static function mailReadsAFile(RawMessage $mail): bool
    {
        // Another class may do more when it is sent than send itself: a templated mail
        // renders the template it names, unless it was rendered before it was queued.
        $renderedTemplatedMail = \in_array($mail::class, self::TEMPLATED_MAILS, true) && $mail instanceof TemplatedEmail && $mail->isRendered();

        if (!$renderedTemplatedMail && !\in_array($mail::class, self::MAILS, true)) {
            return true;
        }

        // A raw message is text already: nothing in it is read when it is sent.
        if (!$mail instanceof Message) {
            return false;
        }

        $parts = [(new \ReflectionProperty(Message::class, 'body'))->getValue($mail)];

        if ($mail instanceof Email) {
            array_push($parts, (new \ReflectionProperty(Email::class, 'cachedBody'))->getValue($mail), ...$mail->getAttachments());
        }

        foreach ($parts as $part) {
            if (null !== $part && self::holdsAFile($part)) {
                return true;
            }
        }

        return false;
    }

    private static function holdsAFile(AbstractPart $part): bool
    {
        // A mail attached to the mail: its own parts are sent with it.
        if (MessagePart::class === $part::class) {
            return self::mailReadsAFile((new \ReflectionProperty(MessagePart::class, 'message'))->getValue($part));
        }

        if (TextPart::class === $part::class || DataPart::class === $part::class) {
            return self::namesAFile($part);
        }

        if ($part instanceof AbstractMultipartPart && \in_array($part::class, self::MULTIPARTS, true)) {
            foreach ($part->getParts() as $child) {
                if (self::holdsAFile($child)) {
                    return true;
                }
            }

            return false;
        }

        return true;
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
