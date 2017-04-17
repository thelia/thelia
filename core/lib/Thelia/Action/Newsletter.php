<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\Newsletter as NewsletterModel;

/**
 * Class Newsletter
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Newsletter extends BaseAction implements EventSubscriberInterface
{
    /** @var  MailerFactory */
    protected $mailer;

    /** @var EventDispatcherInterface  */
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
            $this->dispatcher->dispatch(TheliaEvents::NEWSLETTER_CONFIRM_SUBSCRIPTION, $event);
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
            [ ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName() ],
            [ $event->getEmail() => $event->getFirstname()." ".$event->getLastname() ],
            [
                'email' => $event->getEmail(),
                'firstname' => $event->getFirstname(),
                'lastname' => $event->getLastname()
            ],
            $event->getLocale()
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::NEWSLETTER_SUBSCRIBE => array('subscribe', 128),
            TheliaEvents::NEWSLETTER_UPDATE => array('update', 128),
            TheliaEvents::NEWSLETTER_UNSUBSCRIBE => array('unsubscribe', 128),
            TheliaEvents::NEWSLETTER_CONFIRM_SUBSCRIPTION => array('confirmSubscription', 128)
        );
    }
}
