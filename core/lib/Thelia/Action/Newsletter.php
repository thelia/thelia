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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\Newsletter as NewsletterModel;

/**
 * Class Newsletter
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Newsletter extends BaseAction implements EventSubscriberInterface
{
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
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::NEWSLETTER_SUBSCRIBE => array('subscribe', 128),
            TheliaEvents::NEWSLETTER_UPDATE => array('update', 128),
            TheliaEvents::NEWSLETTER_UNSUBSCRIBE => array('unsubscribe', 128)
        );
    }
}
