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

namespace Thelia\Mailer\EventListener;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;
use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;

/**
 * Sends a queued mail once, even when the queue hands it to a worker twice.
 *
 * A worker acknowledges a job after handling it: when it stops between the moment
 * the mail server accepts the mail and that acknowledgement (killed, out of memory,
 * the server restarted), the queue hands the job out again once its redelivery delay
 * is over, and the customer would receive the mail twice. {@see \Thelia\Mailer\MailerFactory}
 * gives every mail an identifier, in a header that travels with it through the queue;
 * in a worker, the identifier of a mail the server accepted is kept for a week, and a
 * mail carrying one already kept is not sent again. The header is taken off before the
 * mail leaves, so the customer never receives it.
 *
 * Without a queue, a mail is sent once in the request that asked for it, and nothing
 * is kept. The identifiers live in an application cache pool: workers on several
 * servers share them when THELIA_CACHE_DSN points to a cache server they share. A
 * mail sent with the mailer directly, not through the factory, carries no identifier
 * and is not guarded.
 */
final class SentMailGuardListener
{
    public const MAIL_ID_HEADER = 'X-Thelia-Mail-Id';

    /**
     * Longer than a worker is ever expected to stay down: a job taken by a worker that
     * died is handed out again by the next worker to start.
     */
    public const KEPT_FOR_SECONDS = 7 * 24 * 3600;

    private bool $inWorker = false;

    /** @var \WeakMap<RawMessage, string> */
    private \WeakMap $pending;

    public function __construct(
        #[Autowire(service: 'thelia.cache.sent_mails')]
        private readonly CacheItemPoolInterface $sentMails,
    ) {
        $this->pending = new \WeakMap();
    }

    /**
     * An identifier of 32 hexadecimal characters, for a mail about to be queued.
     */
    public static function newMailId(): string
    {
        return bin2hex(random_bytes(16));
    }

    #[AsEventListener]
    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->inWorker = true;
    }

    #[AsEventListener]
    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->inWorker = false;
    }

    #[AsEventListener]
    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $this->inWorker = false;
    }

    /**
     * Before every other listener, so a mail that is not sent again is not signed,
     * recorded or altered either.
     */
    #[AsEventListener(priority: 2048)]
    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();

        // Queued: the header has to travel with the mail to the worker.
        if ($event->isQueued() || !$message instanceof Message) {
            return;
        }

        $headers = $message->getHeaders();
        $mailId = $headers->get(self::MAIL_ID_HEADER)?->getBodyAsString() ?? '';
        $headers->remove(self::MAIL_ID_HEADER);

        if (!$this->inWorker || 1 !== preg_match('/^[0-9a-f]{32}$/', $mailId)) {
            return;
        }

        try {
            if ($this->sentMails->hasItem(self::keyOf($mailId))) {
                Tlog::getInstance()->addInfo(\sprintf('The mail %s was accepted by the mail server already: it is not sent again.', $mailId));
                $event->reject();

                return;
            }
        } catch (\Throwable $exception) {
            // Better a mail twice than a mail never sent.
            Tlog::getInstance()->addWarning(\sprintf('Whether the mail %s was sent already could not be read, it is sent: %s', $mailId, JobFailureMessage::forLog($exception)));
        }

        $this->pending[$message] = $mailId;
    }

    #[AsEventListener]
    public function onSentMessage(SentMessageEvent $event): void
    {
        $message = $event->getMessage()->getOriginalMessage();
        $mailId = $this->pending[$message] ?? null;

        if (null === $mailId) {
            return;
        }

        unset($this->pending[$message]);

        try {
            $item = $this->sentMails->getItem(self::keyOf($mailId));
            $item->set(true);
            $item->expiresAfter(self::KEPT_FOR_SECONDS);
            $this->sentMails->save($item);
        } catch (\Throwable $exception) {
            // The mail is out: letting this through would report it as not sent, and
            // a worker would send it again.
            Tlog::getInstance()->addWarning(\sprintf('The mail %s left, but it could not be kept as sent: %s', $mailId, JobFailureMessage::forLog($exception)));
        }
    }

    private static function keyOf(string $mailId): string
    {
        return 'sent_mail.'.$mailId;
    }
}
