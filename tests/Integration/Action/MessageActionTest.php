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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Message\MessageCreateEvent;
use Thelia\Core\Event\Message\MessageDeleteEvent;
use Thelia\Core\Event\Message\MessageUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\MessageQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class MessageActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsMessage(): void
    {
        $event = new MessageCreateEvent();
        $event
            ->setMessageName('test_welcome')
            ->setLocale('en_US')
            ->setTitle('Welcome email')
            ->setSecured(false);

        $this->dispatch($event, TheliaEvents::MESSAGE_CREATE);

        $message = $event->getMessage();
        self::assertNotNull($message);
        self::assertSame('test_welcome', $message->getName());
        self::assertSame('Welcome email', $message->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesMessageBody(): void
    {
        $existing = $this->dispatch(
            (new MessageCreateEvent())
                ->setMessageName('updatable_msg')
                ->setLocale('en_US')
                ->setTitle('Old')
                ->setSecured(false),
            TheliaEvents::MESSAGE_CREATE,
        )->getMessage();

        $event = new MessageUpdateEvent($existing->getId());
        $event
            ->setMessageName('updatable_msg')
            ->setLocale('en_US')
            ->setTitle('New')
            ->setSecured(false)
            ->setSubject('Hi')
            ->setTextMessage('text body')
            ->setHtmlMessage('<p>html body</p>')
            ->setHtmlLayoutFileName('')
            ->setHtmlTemplateFileName('')
            ->setTextLayoutFileName('')
            ->setTextTemplateFileName('');

        $this->dispatch($event, TheliaEvents::MESSAGE_UPDATE);

        $reloaded = MessageQuery::create()->findPk($existing->getId());
        self::assertSame('text body', $reloaded->getTextMessage());
        self::assertStringContainsString('html body', $reloaded->getHtmlMessage());
    }

    public function testDeleteRemovesMessage(): void
    {
        $existing = $this->dispatch(
            (new MessageCreateEvent())
                ->setMessageName('temporary_msg')
                ->setLocale('en_US')
                ->setTitle('Temporary')
                ->setSecured(false),
            TheliaEvents::MESSAGE_CREATE,
        )->getMessage();
        $messageId = $existing->getId();

        $this->dispatch(new MessageDeleteEvent($messageId), TheliaEvents::MESSAGE_DELETE);

        self::assertNull(MessageQuery::create()->findPk($messageId));
    }
}
