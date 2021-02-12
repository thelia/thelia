<?php

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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Newsletter as NewsletterModel;
use Thelia\Model\NewsletterQuery;

/**
 * Class Newsletter.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Newsletter extends BaseAction implements EventSubscriberInterface
{
    /** @var MailerFactory */
    protected $mailer;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    public function __construct(MailerFactory $mailer, EventDispatcherInterface $dispatcher)
    {
        $this->mailer = $mailer;
        $this->dispatcher = $dispatcher;
    }

    public function subscribe(NewsletterEvent $event)
    {
        // test if the email is already registered and unsubscribed
        if (null === $newsletter = NewsletterQuery::create()->findOneByEmail($event->getEmail())) {
            $newsletter = new NewsletterModel();
        }

        $newsletter
            ->setEmail($event->getEmail())
            ->setFirstname($event->getFirstname())
            ->setLastname($event->getLastname())
            ->setLocale($event->getLocale())
            ->setUnsubscribed(false)
            ->save();

        $event->setNewsletter($newsletter);

        if (ConfigQuery::getNotifyNewsletterSubscription()) {
            $this->dispatcher->dispatch($event, TheliaEvents::NEWSLETTER_CONFIRM_SUBSCRIPTION);
        }
    }

    public function unsubscribe(NewsletterEvent $event)
    {
        if (null !== $nl = NewsletterQuery::create()->findPk($event->getId())) {
            $nl
                ->setUnsubscribed(true)
                ->save();

            $event->setNewsletter($nl);
        }
    }

    public function update(NewsletterEvent $event)
    {
        if (null !== $nl = NewsletterQuery::create()->findPk($event->getId())) {
            $nl->setEmail($event->getEmail())
                ->setFirstname($event->getFirstname())
                ->setLastname($event->getLastname())
                ->setUnsubscribed(false)
                ->setLocale($event->getLocale())
                ->save();

            $event->setNewsletter($nl);
        }
    }

    /**
     * @since 2.3.0-alpha2
     */
    public function confirmSubscription(NewsletterEvent $event)
    {
        $this->mailer->sendEmailMessage(
            'newsletter_subscription_confirmation',
            [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
            [$event->getEmail() => $event->getFirstname().' '.$event->getLastname()],
            [
                'email' => $event->getEmail(),
                'firstname' => $event->getFirstname(),
                'lastname' => $event->getLastname(),
            ],
            $event->getLocale()
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::NEWSLETTER_SUBSCRIBE => ['subscribe', 128],
            TheliaEvents::NEWSLETTER_UPDATE => ['update', 128],
            TheliaEvents::NEWSLETTER_UNSUBSCRIBE => ['unsubscribe', 128],
            TheliaEvents::NEWSLETTER_CONFIRM_SUBSCRIPTION => ['confirmSubscription', 128],
        ];
    }
}
