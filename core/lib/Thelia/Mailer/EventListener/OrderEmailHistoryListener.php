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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;

/**
 * Adds a mail to the history of the order it is about once the mail server has taken it.
 *
 * A mail is queued long before it leaves when the shop has a worker, and it may never
 * leave: the history is therefore written on the transport's own word that the mail
 * is out, in the request when there is no queue and in the worker when there is one.
 * {@see \Thelia\Mailer\MailerFactory} names the order and the message code in two
 * headers of the mail, which travel with it through the queue. They are taken off
 * right before the mail is handed to the mail server, so the customer never receives
 * them, and kept aside against the envelope of that delivery until it is confirmed.
 */
final class OrderEmailHistoryListener
{
    public const ORDER_ID_HEADER = 'X-Thelia-Order-Id';
    public const MESSAGE_CODE_HEADER = 'X-Thelia-Message-Code';

    /** @var \WeakMap<Envelope, array{int, string}> */
    private \WeakMap $pending;

    /** @var \WeakMap<RawMessage, array{int, string}> the same, for a listener that swaps the envelope */
    private \WeakMap $pendingByMessage;

    public function __construct(
        private readonly OrderHistoryRecorder $orderHistoryRecorder,
    ) {
        $this->pending = new \WeakMap();
        $this->pendingByMessage = new \WeakMap();
    }

    /**
     * Before a signing listener, so the signature covers the mail as it leaves.
     */
    #[AsEventListener(priority: 1024)]
    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();

        // Queued: the headers have to travel with the mail to the worker.
        if ($event->isQueued() || !$message instanceof Message) {
            return;
        }

        $headers = $message->getHeaders();
        $orderId = $headers->get(self::ORDER_ID_HEADER)?->getBodyAsString() ?? '';
        $messageCode = $headers->get(self::MESSAGE_CODE_HEADER)?->getBodyAsString() ?? '';
        $headers->remove(self::ORDER_ID_HEADER);
        $headers->remove(self::MESSAGE_CODE_HEADER);

        if (ctype_digit($orderId) && '' !== $messageCode) {
            $this->pending[$event->getEnvelope()] = [(int) $orderId, $messageCode];
            $this->pendingByMessage[$message] = [(int) $orderId, $messageCode];
        }
    }

    #[AsEventListener]
    public function onSentMessage(SentMessageEvent $event): void
    {
        $envelope = $event->getMessage()->getEnvelope();
        $message = $event->getMessage()->getOriginalMessage();
        $pending = $this->pending[$envelope] ?? $this->pendingByMessage[$message] ?? null;

        if (null === $pending) {
            return;
        }

        unset($this->pending[$envelope], $this->pendingByMessage[$message]);
        [$orderId, $messageCode] = $pending;

        try {
            $this->orderHistoryRecorder->recordEmailSent($orderId, $messageCode);
        } catch (\Throwable $exception) {
            // The mail is out. Letting this through would report it as not sent, and
            // a worker would send it again.
            Tlog::getInstance()->addError(\sprintf('The mail %s about order %d left, but its order history line could not be written: %s', $messageCode, $orderId, JobFailureMessage::forLog($exception)));
        }
    }
}
