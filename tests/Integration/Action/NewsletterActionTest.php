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

use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\NewsletterQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class NewsletterActionTest extends ActionIntegrationTestCase
{
    public function testSubscribePersistsNewEntry(): void
    {
        $event = new NewsletterEvent('john.doe+nltest@example.com', 'en_US');
        $event->setFirstname('John')->setLastname('Doe');

        $this->dispatch($event, TheliaEvents::NEWSLETTER_SUBSCRIBE);

        $newsletter = $event->getNewsletter();
        self::assertNotNull($newsletter);
        self::assertSame('john.doe+nltest@example.com', $newsletter->getEmail());
        self::assertSame(0, (int) $newsletter->getUnsubscribed());
    }

    public function testSubscribeReactivatesAPreviouslyUnsubscribedEmail(): void
    {
        $first = new NewsletterEvent('again+nltest@example.com', 'en_US');
        $first->setFirstname('Jane')->setLastname('Doe');
        $this->dispatch($first, TheliaEvents::NEWSLETTER_SUBSCRIBE);
        $id = $first->getNewsletter()->getId();

        $unsub = new NewsletterEvent('again+nltest@example.com', 'en_US');
        $unsub->setId((string) $id);
        $this->dispatch($unsub, TheliaEvents::NEWSLETTER_UNSUBSCRIBE);

        // Re-subscribe the same email — the listener reuses the existing row.
        $resub = new NewsletterEvent('again+nltest@example.com', 'en_US');
        $resub->setFirstname('Jane')->setLastname('Doe');
        $this->dispatch($resub, TheliaEvents::NEWSLETTER_SUBSCRIBE);

        self::assertSame($id, $resub->getNewsletter()->getId());
        self::assertSame(0, (int) $resub->getNewsletter()->getUnsubscribed());
    }

    public function testUnsubscribeFlipsFlag(): void
    {
        $event = new NewsletterEvent('bye+nltest@example.com', 'en_US');
        $event->setFirstname('Bye')->setLastname('Bye');
        $this->dispatch($event, TheliaEvents::NEWSLETTER_SUBSCRIBE);
        $id = $event->getNewsletter()->getId();

        $unsub = new NewsletterEvent('bye+nltest@example.com', 'en_US');
        $unsub->setId((string) $id);
        $this->dispatch($unsub, TheliaEvents::NEWSLETTER_UNSUBSCRIBE);

        self::assertSame(1, (int) NewsletterQuery::create()->findPk($id)->getUnsubscribed());
    }

    public function testUpdateChangesNameAndResubscribes(): void
    {
        $event = new NewsletterEvent('name+nltest@example.com', 'en_US');
        $event->setFirstname('Old')->setLastname('Name');
        $this->dispatch($event, TheliaEvents::NEWSLETTER_SUBSCRIBE);
        $id = $event->getNewsletter()->getId();

        $update = new NewsletterEvent('name+nltest@example.com', 'en_US');
        $update->setId((string) $id)->setFirstname('New')->setLastname('Name');
        $this->dispatch($update, TheliaEvents::NEWSLETTER_UPDATE);

        $reloaded = NewsletterQuery::create()->findPk($id);
        self::assertSame('New', $reloaded->getFirstname());
    }
}
