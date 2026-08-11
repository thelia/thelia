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

namespace VirtualProductDelivery\EventListeners;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use VirtualProductDelivery\Events\VirtualProductDeliveryEvents;

/**
 * Class SendMail.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SendMail implements EventSubscriberInterface
{
    /** @var MailerFactory */
    protected $mailer;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    public function __construct(MailerFactory $mailer, EventDispatcherInterface $eventDispatcher)
    {
        $this->mailer = $mailer;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function updateStatus(OrderEvent $event): void
    {
        $order = $event->getOrder();

        // A virtual product without a virtual document has nothing to download, so there is
        // no download notification to send: do not dispatch the event at all in that case.
        if ($order->hasVirtualProductWithDocument() && $order->isPaid(true)) {
            $this->eventDispatcher->dispatch(
                $event,
                VirtualProductDeliveryEvents::ORDER_VIRTUAL_FILES_AVAILABLE
            );
        }
    }

    /**
     * Send email to notify customer that files for virtual products are available.
     *
     * @throws \Exception
     */
    public function sendEmail(OrderEvent $event): void
    {
        $order = $event->getOrder();

        // The event may also be dispatched by a third party: be sure that we have a document
        // to download. Having none is a legitimate case, not something to warn about.
        if (!$order->hasVirtualProductWithDocument()) {
            Tlog::getInstance()->debug(
                'Virtual product download message not sent to customer: order '
                .$order->getRef().' has no document to download'
            );

            return;
        }

        $customer = $order->getCustomer();

        $this->mailer->sendEmailToCustomer(
            'mail_virtualproduct',
            $customer,
            [
                'customer_id' => $customer->getId(),
                'order_id' => $order->getId(),
                'order_ref' => $order->getRef(),
                'order_date' => $order->getCreatedAt(),
                'update_date' => $order->getUpdatedAt(),
            ]
        );
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
        return [
            TheliaEvents::ORDER_UPDATE_STATUS => ['updateStatus', 128],
            VirtualProductDeliveryEvents::ORDER_VIRTUAL_FILES_AVAILABLE => ['sendEmail', 128],
        ];
    }
}
