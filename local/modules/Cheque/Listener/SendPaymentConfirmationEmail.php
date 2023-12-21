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

namespace Cheque\Listener;

use Cheque\Cheque;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Action\BaseAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Mailer\MailerFactory;

/**
 * Class SendEMail.
 *
 * @author Thelia <info@thelia.net>
 */
class SendPaymentConfirmationEmail extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var MailerFactory
     */
    protected $mailer;

    public function __construct(MailerFactory $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @param orderEvent $event
     *
     * Check if we're the payment module, and send the payment confirmation email to the customer if it's the case
     */
    public function sendConfirmationEmail(OrderEvent $event): void
    {
        if ($event->getOrder()->getPaymentModuleId() === Cheque::getModuleId()) {
            if ($event->getOrder()->isPaid()) {
                $order = $event->getOrder();

                $this->mailer->sendEmailToCustomer(
                    'order_confirmation_cheque',
                    $order->getCustomer(),
                    [
                        'order_id' => $order->getId(),
                        'order_ref' => $order->getRef(),
                    ]
                );
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::ORDER_UPDATE_STATUS => ['sendConfirmationEmail', 128],
        ];
    }
}
