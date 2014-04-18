<?php
namespace Cbatos;

use Cbatos\Model\Config;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\Base\Template;
use Thelia\Model\ModuleImageQuery;
use Thelia\Model\Order;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;
use Thelia\Install\Database;
use Thelia\Model\ModuleQuery;

class Cbatos extends BaseModule implements PaymentModuleInterface
{
const JSON_CONFIG_PATH = "/Config/config.json";
    protected $_sKey;
    protected $_sUsableKey;

    protected $config;

public function postDeactivation(ConnectionInterface $con = null)
{
$database = new Database($con->getWrappedConnection());
$database->insertSql(null, array(__DIR__ . '/Config/theliaremove.sql'));
}

public function postActivation(ConnectionInterface $con = null)
{
        $database = new Database($con->getWrappedConnection());
        $database->insertSql(null, array(__DIR__ . '/Config/thelia.sql'));

    /* insert the images from image folder if first module activation */
        $module = $this->getModuleModel();
        if (ModuleImageQuery::create()->filterByModule($module)->count() == 0) {
            $this->deployImageFolder($module, sprintf('%s/images', __DIR__), $con);
        }

     /* set module title */
        $this->setTitle(
            $module,
            array(
                "en_US" => "Credits cards by atos",
                "fr_FR" => "Carte bancaire par atos",
            )
        );
}

function pay(Order $order)
{
$c = Config::read(Cbatos::JSON_CONFIG_PATH);
//Variable for Atos Call Api
$parm="merchant_id=".$c["CBATOS_MERCHANTID"];
$parm="$parm merchant_country=fr";
$parm="$parm amount=".$order->getTotalAmount()*100;
if ($c["CBATOS_CAPTUREDAYS"] > "0") { $parm="$parm capture_day=".$c["CBATOS_CAPTUREDAYS"];  }
$parm="$parm currency_code=".$c["CBATOS_DEVISES"];
if ($c["CBATOS_CUSTOMERMAIL"] == "2") { $parm="$parm customer_email=".$this->getRequest()->getSession()->getCustomerUser()->getEmail(); }
if ($c["CBATOS_CUSTOMERID"] == "2") { $parm="$parm customer_id=".$this->getRequest()->getSession()->getCustomerUser()->getId(); }
if ($c["CBATOS_CUSTOMERIP"] == "2") { $parm="$parm customer_ip_address=".$_SERVER['REMOTE_ADDR']; }
$parm="$parm language=".$this->getRequest()->getSession()->getLang()->getCode();
$parm="$parm order_id=".$order->getId();
$parm="$parm pathfile=".$c["CBATOS_PATHBIN"]."/parm/pathfile";
$parm="$parm normal_return_url=".$c["CBATOS_URLRETOUR"];
$parm="$parm cancel_return_url=".$c["CBATOS_URLRETOUR"];
$parm="$parm automatic_response_url=".$c["CBATOS_URLAUTOMATIC"];
$parm="$parm transaction_id=".self::harmonise($order->getId(),'numeric',6);
$path_bin = $c["CBATOS_PATHBIN"]."/bin/request";

//Call to Api Request Atos
$result=exec("$path_bin $parm");
$tableau = explode ("!", "$result");
$code = $tableau[1];
$error = $tableau[2];
$message = $tableau[3];
if (( $code == "" ) && ( $error == "" ) ) { $erroratos = "<B>Error to connect API</B><br>Request not found :  $path_bin"; } elseif ($code != 0) { $erroratos = "<b>Error in Api request</b><br><br>Message Atos : $error <br>"; } else {  $formpaiement = $message; }
if ($c["CBATOS_MODEDEBUG"] == "2") { $erroratos = $error; }

//Call Template Page for display Form Atos
$parser = $this->container->get("thelia.parser");
$parser->setTemplateDefinition(
            new TemplateDefinition(
                'knj',
                TemplateDefinition::FRONT_OFFICE
            )
        );
 $render = $parser->render("atos.html",
  array(
           'formulaire' => $message,
           'error' => $erroratos
    ));

        return Response::create($render);

}
public static function HtmlEncode($data)
    {
        $SAFE_OUT_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890._-";
        $result = "";
        for ($i=0; $i<strlen($data); $i++) {
            if (strchr($SAFE_OUT_CHARS, $data{$i})) {
                $result .= $data{$i};
            } elseif (($var = bin2hex(substr($data,$i,1))) <= "7F") {
                $result .= "&#x" . $var . ";";
            } else
                $result .= $data{$i};

        }

        return $result;
    }
public static function harmonise($value, $type, $len)
    {
        switch ($type) {
            case 'numeric':
                $value = (string) $value;
                if(mb_strlen($value, 'utf8') > $len);
                $value = substr($value, 0, $len);
                for ($i = mb_strlen($value, 'utf8'); $i < $len; $i++) {
                    $value = '0' . $value;
                }
                break;
            case 'alphanumeric':
                $value = (string) $value;
                if(mb_strlen($value, 'utf8') > $len);
                $value = substr($value, 0, $len);
                for ($i = mb_strlen($value, 'utf8'); $i < $len; $i++) {
                    $value .= ' ';
                }
                break;
        }

        return $value;
    }
    public function getRequest()
    {
        return $this->container->get('request');
    }
/**
*
* This method is call on Payment loop.
*
* If you return true, the payment method will de display
* If you return false, the payment method will not be display
*
* @return boolean
*/
public function isValidPayment()
    {
        return true;
    }

 public function getCode()
    {
        return 'Cbatos';
    }
public static function getModCode($flag=false)
    {
        $obj = new Cbatos();
        $mod_code = $obj->getCode();
        if($flag) return $mod_code;
        $search = ModuleQuery::create()
            ->findOneByCode($mod_code);

        return $search->getId();
    }
}
