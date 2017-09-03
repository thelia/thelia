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

namespace Colissimo\Listener;

use Colissimo\Colissimo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderStatus;
use Thelia\Module\PaymentModuleInterface;


/**
 * Class SendMail
 * @package Colissimo\Listener
 * @author Manuel Raynaud <manu@raynaud.io>
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
        $colissimo = new Colissimo();
        
        if ($order->isSent() && $order->getDeliveryModuleId() == $colissimo->getModuleModel()->getId()) {
            $contact_email = ConfigQuery::getStoreEmail();
            
            if ($contact_email) {
                $order = $event->getOrder();
                $customer = $order->getCustomer();
                
                $this->mailer->sendEmailToCustomer(
                    'mail_colissimo',
                    $customer,
                    [
                        'customer_id' => $customer->getId(),
                        'order_ref' => $order->getRef(),
                        'order_date' => $order->getCreatedAt(),
                        'update_date' => $order->getUpdatedAt(),
                        'package' => $order->getDeliveryRef()
                    ]
                );
                
                Tlog::getInstance()->debug("Colissimo shipping message sent to customer ".$customer->getEmail());
            } else {
                $customer = $order->getCustomer();
                Tlog::getInstance()->debug("Colissimo shipping message no contact email customer_id", $customer->getId());
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
