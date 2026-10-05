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
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Message;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Log\Tlog;

/**
 * Adds a mail to the history of the order it is about once the mail server has taken it.
 *
 * A mail is queued long before it leaves when the shop has a worker, and it may never
 * leave: the history is therefore written on the transport's own word that the mail
 * is out, in the request when there is no queue and in the worker when there is one.
 * {@see \Thelia\Mailer\MailerFactory} names the order and the message code in two
 * headers of the mail; nothing else of the mail is read.
 */
final readonly class OrderEmailHistoryListener
{
    public const ORDER_ID_HEADER = 'X-Thelia-Order-Id';
    public const MESSAGE_CODE_HEADER = 'X-Thelia-Message-Code';

    public function __construct(
        private OrderHistoryRecorder $orderHistoryRecorder,
    ) {
    }

    #[AsEventListener]
    public function onSentMessage(SentMessageEvent $event): void
    {
        $message = $event->getMessage()->getOriginalMessage();

        if (!$message instanceof Message) {
            return;
        }

        $headers = $message->getHeaders();
        $orderId = $headers->get(self::ORDER_ID_HEADER)?->getBodyAsString() ?? '';
        $messageCode = $headers->get(self::MESSAGE_CODE_HEADER)?->getBodyAsString() ?? '';

        if (!ctype_digit($orderId) || '' === $messageCode) {
            return;
        }

        try {
            $this->orderHistoryRecorder->recordEmailSent((int) $orderId, $messageCode);
        } catch (\Throwable $exception) {
            // The mail is out. Letting this through would report it as not sent, and
            // a worker would send it again.
            Tlog::getInstance()->addError(\sprintf('The mail %s about order %s left, but its order history line could not be written: %s', $messageCode, $orderId, $exception->getMessage()));
        }
    }
}
