<?php

namespace Cbatos\Controller;

use Cbatos\Cbatos;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\OrderQuery;
use Cbatos\Model\Config;
use Cbatos\Model\AtosTransactions;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
class CbatosControllerAnswer extends BaseFrontController
{
protected $config;

function resp()
{
$c = Config::read(Cbatos::JSON_CONFIG_PATH);
$pathinstallmodule = $c["CBATOS_PATHBIN"];
$date = $_POST['DATA'];
$message="message=$date";
$pathfile="pathfile=$pathinstallmodule/parm/pathfile";
$path_bin = "$pathinstallmodule/bin/response";
$result=exec("$path_bin $pathfile $message");
$tableau = explode ("!", $result);
$code = $tableau[1];
$error = $tableau[2];
$response_code = $tableau[11];
$techno = "EMTPV";
 //on enregistre la trace de la transaction dans un fichier
//Nom du fichier Order-IDdecommande-Idduclient.Json
$order_id = $tableau['27'];
$conf = new AtosTransactions();
$conf->setMARCHAND($tableau['3'])
->setDATE("$tableau[10]")
->setTIME("$tableau[9]")
->setCARD("$tableau[15]")
->setAUTO("$tableau[13]")
->setAMOUNT("$tableau[5]")
->setREF("$tableau[6]")
->setCURRENCY("$tableau[14]")
->setIPCUSTOMER("$tableau[29]")
->setEMAILCUSTOMER("$tableau[28]")
->setORDERID("$tableau[27]")
->setCUSTOMERID("$tableau[26]")
->setBANKRESPONSESCODE("$tableau[18]")
->setCERTIFICAT("$tableau[12]")
->setETP("$techno")
->setCVVCODE("$tableau[17]")

->write("/Transactions/Order-".$order_id."-".$tableau[26].".json")
;

if(is_numeric($order_id))
$order_id=(int) $order_id;

$order = OrderQuery::create()->findPk($order_id);

// on regarde que si aucun code ou aucune erreur, cela indique
// une erreur dans la recherche de lexec surement introuvable
if (( $code == "" ) && ( $error == "" ) ) {
$errormsg = "Error to call API RESPONSE ATOS<br>Execitable not found".$path_bin;
echo $errormsg;
}
// exec trouve mais erreur dans les prerequis
// on affiche
else if ($code != 0) {
$errormsg = "Error in Call to API ATOS RESPONSE <br><br> Error :".$error;
echo $errormsg;
}
//ok le responses est valable on execute
else {

// analyse de la reponse de atos
// si code 00 transaction accte
// si autre transaction refuse
if ($response_code == "00") {

            $code = $response_code;
            $msg = "";
            $event = new OrderEvent($order);
        $event->setStatus(OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)->getId());

//on colle la transaction ref deatos sur la commande

$order->setTransactionRef("$tableau[6]");
switch ($code) {

                case "00":
                    $msg = "The payment of the order ".$order->getRef()." has been successfully released. ";
                    $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);

            break;
               default:
           $msg = "Your payment is declined <br> Motif : Code $code , Code banque $bank_response_code";

            }
}
// fin de analyse reponse atos
}
// fin des conditions des erreurs dexecution
//on refuse que la page soit charge avec le template pour des raison de lourdeur
//le retour servant a lotomatisme datos aucune raison de le faire
//permet une reponse rapide
//on affiche un petit message qui indique que tous ce passe bien

print($msg);
exit; //interdit de faire plus loin que cela

}

}
