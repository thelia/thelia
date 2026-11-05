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
use Thelia\Model\ConfigQuery;
use Thelia\Model\NewsletterQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class NewsletterActionTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

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

    /**
     * An anonymous subscription (email only, e.g. a footer form with no name
     * fields) never calls setFirstname()/setLastname(). subscribe() reads
     * both unconditionally to fill the Newsletter row, and used to crash on
     * the uninitialized typed properties instead of storing an empty name.
     */
    public function testSubscribeWithNoNameDoesNotCrash(): void
    {
        $event = new NewsletterEvent('anonymous+nltest@example.com', 'en_US');

        $this->dispatch($event, TheliaEvents::NEWSLETTER_SUBSCRIBE);

        $newsletter = $event->getNewsletter();
        self::assertNotNull($newsletter);
        self::assertSame('', $newsletter->getFirstname());
        self::assertSame('', $newsletter->getLastname());
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

    public function testTheSettingIsOnByDefault(): void
    {
        self::assertTrue(ConfigQuery::isNewsletterEnabled());
    }

    public function testSubscribeRecordsNothingAndStopsPropagationWhenTheNewsletterIsOff(): void
    {
        $this->switchNewsletter(false);
        $laterListenerCalled = false;
        $this->dispatcher->addListener(
            TheliaEvents::NEWSLETTER_SUBSCRIBE,
            static function () use (&$laterListenerCalled): void {
                $laterListenerCalled = true;
            },
            0,
        );

        $event = new NewsletterEvent('off+nltest@example.com', 'en_US');
        $event->setFirstname('Off')->setLastname('Shop');
        $this->dispatch($event, TheliaEvents::NEWSLETTER_SUBSCRIBE);

        self::assertNull(NewsletterQuery::create()->findOneByEmail('off+nltest@example.com'));
        self::assertTrue($event->isPropagationStopped());
        self::assertFalse($laterListenerCalled, 'A listener behind the sign-up must not act on an address that was not kept.');
    }

    public function testAnUnsubscribedAddressStaysUnsubscribedOnANewSignUpWhenTheNewsletterIsOff(): void
    {
        $id = $this->subscribeThenUnsubscribe('left+nltest@example.com');
        $this->switchNewsletter(false);

        $again = new NewsletterEvent('left+nltest@example.com', 'en_US');
        $again->setFirstname('Left')->setLastname('Shop');
        $this->dispatch($again, TheliaEvents::NEWSLETTER_SUBSCRIBE);

        self::assertSame(1, (int) NewsletterQuery::create()->findPk($id)->getUnsubscribed());
    }

    public function testUpdateDoesNotResubscribeWhenTheNewsletterIsOffButStillSavesTheRest(): void
    {
        $id = $this->subscribeThenUnsubscribe('stale+nltest@example.com');
        $this->switchNewsletter(false);

        $update = new NewsletterEvent('stale+nltest@example.com', 'en_US');
        $update->setId((string) $id)->setFirstname('Renamed')->setLastname('Shop');
        $this->dispatch($update, TheliaEvents::NEWSLETTER_UPDATE);

        $reloaded = NewsletterQuery::create()->findPk($id);
        self::assertSame(1, (int) $reloaded->getUnsubscribed());
        self::assertSame('Renamed', $reloaded->getFirstname());
    }

    public function testUnsubscribeStillWorksWhenTheNewsletterIsOff(): void
    {
        $event = new NewsletterEvent('quit+nltest@example.com', 'en_US');
        $event->setFirstname('Quit')->setLastname('Shop');
        $this->dispatch($event, TheliaEvents::NEWSLETTER_SUBSCRIBE);
        $id = $event->getNewsletter()->getId();
        $this->switchNewsletter(false);

        $unsub = new NewsletterEvent('quit+nltest@example.com', 'en_US');
        $unsub->setId((string) $id);
        $this->dispatch($unsub, TheliaEvents::NEWSLETTER_UNSUBSCRIBE);

        self::assertSame(1, (int) NewsletterQuery::create()->findPk($id)->getUnsubscribed());
    }

    public function testSignUpWorksAgainWhenTheNewsletterIsBackOn(): void
    {
        $id = $this->subscribeThenUnsubscribe('back+nltest@example.com');
        $this->switchNewsletter(false);
        $this->switchNewsletter(true);

        $again = new NewsletterEvent('back+nltest@example.com', 'en_US');
        $again->setFirstname('Back')->setLastname('Shop');
        $this->dispatch($again, TheliaEvents::NEWSLETTER_SUBSCRIBE);

        self::assertSame($id, $again->getNewsletter()->getId());
        self::assertSame(0, (int) $again->getNewsletter()->getUnsubscribed());
    }

    private function switchNewsletter(bool $enabled): void
    {
        ConfigQuery::write('newsletter_enabled', $enabled ? '1' : '0');
    }

    private function subscribeThenUnsubscribe(string $email): int
    {
        $event = new NewsletterEvent($email, 'en_US');
        $event->setFirstname('Some')->setLastname('One');
        $this->dispatch($event, TheliaEvents::NEWSLETTER_SUBSCRIBE);
        $id = $event->getNewsletter()->getId();

        $unsub = new NewsletterEvent($email, 'en_US');
        $unsub->setId((string) $id);
        $this->dispatch($unsub, TheliaEvents::NEWSLETTER_UNSUBSCRIBE);

        return $id;
    }
}
