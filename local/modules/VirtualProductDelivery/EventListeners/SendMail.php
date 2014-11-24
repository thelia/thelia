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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\PaymentModuleInterface;


/**
 * Class SendMail
 * @package VirtualProductDelivery\EventListeners
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SendMail implements EventSubscriberInterface
{

    protected $parser;

    protected $mailer;

    public function __construct(ParserInterface $parser, MailerFactory $mailer)
    {
        $this->parser = $parser;
        $this->mailer = $mailer;
    }

    public function updateStatus(OrderEvent $event)
    {
        $order = $event->getOrder();

        $paidStatusId = OrderStatusQuery::create()
            ->filterByCode(OrderStatus::CODE_PAID)
            ->select('Id')
            ->findOne();

        if ($order->hasVirtualProduct() && $event->getStatus() == $paidStatusId) {

            $contact_email = ConfigQuery::read('store_email');

            if ($contact_email) {

                $message = MessageQuery::create()
                    ->filterByName('mail_virtualproduct')
                    ->findOne();

                if (false === $message) {
                    throw new \Exception("Failed to load message 'mail_virtualproduct'.");
                }

                $order = $event->getOrder();
                $customer = $order->getCustomer();

                $this->parser->assign('customer_id', $customer->getId());
                $this->parser->assign('order_id', $order->getId());
                $this->parser->assign('order_ref', $order->getRef());
                $this->parser->assign('order_date', $order->getCreatedAt());
                $this->parser->assign('update_date', $order->getUpdatedAt());

                $message
                    ->setLocale($order->getLang()->getLocale());

                $instance = \Swift_Message::newInstance()
                    ->addTo($customer->getEmail(), $customer->getFirstname()." ".$customer->getLastname())
                    ->addFrom($contact_email, ConfigQuery::read('store_name'))
                ;

                // Build subject and body

                $message->buildMessage($this->parser, $instance);

                $this->mailer->send($instance);

                Tlog::getInstance()->debug("Virtual product download message sent to customer ".$customer->getEmail());
            } else {
                $customer = $order->getCustomer();

                Tlog::getInstance()->debug(
                    "Virtual product download message no contact email for customer id '{customer_id}'",
                    [
                        "customer_id" => $customer->getId()
                    ]
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
