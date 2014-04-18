<?php
/*************************************************************************************/
/* */
/* Thelia */
/* */
/* Copyright (c) OpenStudio */
/* email : info@thelia.net */
/* web : http://www.thelia.net */
/* */
/* This program is free software; you can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 3 of the License */
/* */
/* This program is distributed in the hope that it will be useful, */
/* but WITHOUT ANY WARRANTY; without even the implied warranty of */
/* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the */
/* GNU General Public License for more details. */
/* */
/* You should have received a copy of the GNU General Public License */
/* along with this program. If not, see <http://www.gnu.org/licenses/>. */
/* */
/*************************************************************************************/

namespace Cbatos\Listener;

use Cbatos\Cbatos;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Action\BaseAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Mailer\MailerFactory;
use Thelia\Core\Template\ParserInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Cbatos\Model\Config;
use Thelia\Core\Translation\Translator;
/**
* Class SendEMail
* @package IciRelais\Listener
* @author Thelia <info@thelia.net>
*/
class SendEMail extends BaseAction implements EventSubscriberInterface
{

    /**
* @var MailerFactory
*/
    protected $mailer;
    /**
* @var ParserInterface
*/
    protected $parser;

    public function __construct(ParserInterface $parser,MailerFactory $mailer)
    {
        $this->parser = $parser;
        $this->mailer = $mailer;
    }

    /**
* @return \Thelia\Mailer\MailerFactory
*/
    public function getMailer()
    {
        return $this->mailer;
    }

    /*
* @params OrderEvent $order
* Checks if order payment module is paypal and if order new status is paid, send an email to the customer.
*/
    public function update_status(OrderEvent $event)
    {
        if ($event->getOrder()->getPaymentModuleId() === Cbatos::getModCode()) {
            if ($event->getOrder()->getStatusId() === OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)->getId()) {
//Mail de la boutique expeditrice
$contact_email = ConfigQuery::read('store_email');

                if ($contact_email) {
                    $message = MessageQuery::create()
                        ->filterByName('mail_atos')
                        ->findOne();

                    if (false === $message) {
                        throw new \Exception("Failed to load message 'mail_atos'.");
                    }

                    $order = $event->getOrder();
                    $customer = $order->getCustomer();
//on assign le retour de la passerelle ATOS afin de faire un jolie ticket de paiement
//assign var atos back, for beautifull receipt payment
//{order_id} Order id
//{order_ref} Order ref
//{merchantid} Merchant ID
//
$transac = Config::read("/Transactions/Order-".$order->getId()."-".$customer->getID().".json");

//ecuperation des valeurs de la transaction
//on decrypte la date
$datetrans = str_split($transac["DATE"], 2);
$timetrans = str_split($transac["TIME"], 2);
$cardnorme = str_replace(".", "XXXXXXXXXX", $transac["CARD"]);
//Conversion des montant
//on recupere le taux de la BCE (banque europenne)
// fichier http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml
$XMLContent= file("http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml");
        foreach ($XMLContent as $line) {
                if (ereg("currency='USD'",$line,$currencyCode)) {
                    if (ereg("rate='([[:graph:]]+)'",$line,$rate)) {

                        $usdtaux = $rate[1];

                    }
                }
        }

//EUR ORIGINETranslator::getInstance()->trans("Access is denied")
$montantpaidEUR = number_format($transac["AMOUNT"]/100, 2, ',', ' ')." EUR";
$montantpaidUSD = number_format($montantpaidEUR/$usdtaux, 2, ',', ' ')." USD";
$montantpaidFRF = number_format($montantpaidEUR*6.55957, 2, ',', ' ')." FRF";
//FRF and //USD{$MONTANT_TRANS_EUR}
//on recup les infos de la config
$store = ConfigQuery::create();
$this->parser->assign('STORE_NAME', $store->read("store_name"));
$this->parser->assign('STORE_LINE1', $store->read("store_address1"));
$storcpville = $store->read("store_zipcode")."".$store->read("store_city");
$this->parser->assign('STORE_CP', $storcpville);

 $this->parser->assign('autorisation', $transac["AUTO"]);
 $this->parser->assign('MERCHANT', $transac["MARCHAND"]);
 $this->parser->assign('CB_CRYPTE', $cardnorme);
 $this->parser->assign('CERTIFICAT', $transac["CERTIFICAT"]);
  $this->parser->assign('TRANS_ID', $transac["REF"]);
 //on fait passer les valeurs de traduction des messages

$this->parser->assign('MESSAGE_HAUT_TICKET_ATOS', Translator::getInstance()->trans("WWW.YOURSITE.COM"));
$this->parser->assign('METHOD_PAID', Translator::getInstance()->trans("CARTE BANCAIRE"));
$this->parser->assign('LE', Translator::getInstance()->trans("LE"));
$this->parser->assign('A', Translator::getInstance()->trans("A"));
  $this->parser->assign('FIN', Translator::getInstance()->trans("FIN"));
  $this->parser->assign('MONT', Translator::getInstance()->trans("MONTANT"));
  $this->parser->assign('INFO', Translator::getInstance()->trans("Pour information"));
  $this->parser->assign('MESSAGE_TICKET_CLIENT', Translator::getInstance()->trans("TICKET CLIENT"));
  $this->parser->assign('CONSERVE', Translator::getInstance()->trans("A CONSERVER"));
  $this->parser->assign('BYE', Translator::getInstance()->trans("Au revoir"));

  $this->parser->assign('MONTANT_TRANS_EUR', $montantpaidEUR);
  $this->parser->assign('MONTANT_TRANS_FRF', $montantpaidFRF);
  $this->parser->assign('MONTANT_TRANS_USD', $montantpaidUSD);

$this->parser->assign('DATE_TRANS', $datetrans[0]."/".$datetrans[2]."/".$datetrans[1]);
$this->parser->assign('TIME_TRANS', $timetrans[0].":".$timetrans[1].":".$timetrans[2]);
$this->parser->assign('card', $transac["CARD"]);

                    $this->parser->assign('order_id', $order->getId());
                    $this->parser->assign('order_ref', $order->getRef());

                    $message
                        ->setLocale($order->getLang()->getLocale());

                    $instance = \Swift_Message::newInstance()
                        ->addTo($customer->getEmail(), $customer->getFirstname()." ".$customer->getLastname())
                        ->addFrom($contact_email, ConfigQuery::read('store_name'))
                    ;

                    // Build subject and body
                    $message->buildMessage($this->parser, $instance);

                    $this->getMailer()->send($instance);

                }
            }
        }

    }

    /**
* Returns an array of event names this subscriber wants to listen to.
*
* The array keys are event names and the value can be:
*
* * The method name to call (priority defaults to 0)
* * An array composed of the method name to call and the priority
* * An array of arrays composed of the method names to call and respective
* priorities, or 0 if unset
*
* For instance:
*
* * array('eventName' => 'methodName')
* * array('eventName' => array('methodName', $priority))
* * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
*
* @return array The event names to listen to
*
* @api
*/
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS => array("update_status", 128)
        );
    }

}
