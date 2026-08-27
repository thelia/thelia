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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Contact\ContactEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;

/**
 * Sends the message a visitor wrote in the contact form to the shop.
 *
 * The message has no template of its own: it carries the three fields of the form and
 * nothing else, so it is built here rather than stored in the message table, and the
 * visitor's address is set as the reply-to so that answering it reaches them.
 */
class Contact extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerFactory $mailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function sendMessageToStore(ContactEvent $event): void
    {
        $storeEmail = ConfigQuery::getStoreEmail();

        if (empty($storeEmail)) {
            Tlog::getInstance()->addError("Can't send the contact message: the store email address is not defined.");

            return;
        }

        $lines = [
            $this->translator->trans('Sender name: %name%', ['%name%' => $event->getName()]),
            $this->translator->trans("Sender's e-mail address: %email%", ['%email%' => $event->getEmail()]),
            $this->translator->trans('Message content: %message%', ['%message%' => $event->getMessage()]),
        ];

        // The three lines are visitor input: escape them, or a message body carrying markup
        // is rendered as markup in the shop manager's mail client.
        $htmlBody = '<p>'.implode("</p>\n<p>", array_map(
            static fn (string $line): string => nl2br(htmlspecialchars($line, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')),
            $lines,
        )).'</p>';

        $this->mailer->sendSimpleEmailMessage(
            [$storeEmail => ConfigQuery::getStoreName()],
            [$storeEmail => ConfigQuery::getStoreName()],
            $event->getSubject(),
            $htmlBody,
            implode("\n\n", $lines),
            [],
            [],
            [$event->getEmail() => $event->getName()],
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CONTACT_SUBMIT => ['sendMessageToStore', 128],
        ];
    }
}
