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

namespace VirtualProductDelivery\EventListeners;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Class SendMail
 * @package VirtualProductDelivery\EventListeners
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SendMail implements EventSubscriberInterface
{
    protected $mailer;

    public function __construct(MailerFactory $mailer)
    {
        $this->mailer = $mailer;
    }

    public function updateStatus(OrderEvent $event)
    {
        $order = $event->getOrder();

        if ($order->hasVirtualProduct() && $order->isPaid(true)) {
            // Be sure that we have a document to download
            if (OrderProductQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByVirtual(true)
                ->filterByVirtualDocument(null, Criteria::NOT_EQUAL)
                ->count() > 0) {
                $customer = $order->getCustomer();

                $this->mailer->sendEmailToCustomer(
                    'mail_virtualproduct',
                    $customer,
                    [
                        'customer_id' => $customer->getId(),
                        'order_id' => $order->getId(),
                        'order_ref' => $order->getRef(),
                        'order_date' => $order->getCreatedAt(),
                        'update_date' => $order->getUpdatedAt()
                    ]
                );

                Tlog::getInstance()->debug("Virtual product download message sent to customer ".$customer->getEmail());
            } else {
                Tlog::getInstance()->warning(
                    "Virtual product download message not sent to customer: there's nothing to downnload"
                );
            }
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
            TheliaEvents::ORDER_UPDATE_STATUS => array("updateStatus", 128)
        );
    }
}
