<?php

namespace Cbatos\Model;

use Cbatos\Cbatos;
use Thelia\Core\Translation\Translator;

class Config implements ConfigInterface
{
    protected $CBATOS_MERCHANTID=null;
    protected $CBATOS_URLRETOUR=null;

    public function __construct()
    {
        $config=null;
        try {
            $config=$this->read();
        } catch (\Exception $e) {}
        if ($config !== null) {
            foreach ($config as $key=>$val) {
                try {
                    $this->__set($key,$val);
                } catch (\Exception $e) {}
            }
        }
    }

    public function write($file=null)
    {
        $path = __DIR__."/../".$file;
        if ((file_exists($path) ? is_writable($path):is_writable(__DIR__."/../Config/"))) {
            $vars= get_object_vars($this);
            $cond = true;
            foreach($vars as $key=>$var)
                $cond &= !empty($var);
            if ($cond) {
                $file = fopen($path, 'w');
                fwrite($file, json_encode($vars));
                fclose($file);
            }
        } else {
            throw new \Exception(Translator::getInstance()->trans("Can't write file ").$file.". ".
                Translator::getInstance()->trans("Please change the rights on the file and/or directory."));

        }
    }
    /**
     * @return array
     */
    public static function read($file=null)
    {
        $path = __DIR__."/../".$file;
        $ret = null;
        if (is_readable($path)) {
            $json = json_decode(file_get_contents($path), true);
            if ($json !== null) {
                $ret = $json;
            } else {
                throw new \Exception(Translator::getInstance()->trans("Can't read file ").$file.". ".
                    Translator::getInstance()->trans("The file is corrupted."));
            }
        } elseif (!file_exists($path)) {
            throw new \Exception(Translator::getInstance()->trans("The file ").$file.
                                Translator::getInstance()->trans(" doesn't exist. You have to create it in order to use this module. Please see module's configuration page."));
        } else {
            throw new \Exception(Translator::getInstance()->trans("Can't read file ").$file.". ".
                                Translator::getInstance()->trans("Please change the rights on the file."));

        }

        return $ret;
    }

    public function setCBATOSMERCHANTID($CBATOS_MERCHANTID)
    {
        $this->CBATOS_MERCHANTID = $CBATOS_MERCHANTID;

        return $this;
    }
 public function setCBATOSURLRETOUR($CBATOS_URLRETOUR)
{
$this->CBATOS_URLRETOUR = $CBATOS_URLRETOUR;
return $this;
}

 public function setCBATOSURLAUTOMATIC($CBATOS_URLAUTOMATIC)
{
$this->CBATOS_URLAUTOMATIC = $CBATOS_URLAUTOMATIC;
return $this;
}

 public function setCBATOSCAPTUREDAYS($CBATOS_CAPTUREDAYS)
{
$this->CBATOS_CAPTUREDAYS = $CBATOS_CAPTUREDAYS;
return $this;
}

 public function setCBATOSDEVISES($CBATOS_DEVISES)
{
$this->CBATOS_DEVISES = $CBATOS_DEVISES;
return $this;
}

 public function setCBATOSCUSTOMERMAIL($CBATOS_CUSTOMERMAIL)
{
$this->CBATOS_CUSTOMERMAIL = $CBATOS_CUSTOMERMAIL;
return $this;
}

public function setCBATOSCUSTOMERID($CBATOS_CUSTOMERID)
{
$this->CBATOS_CUSTOMERID = $CBATOS_CUSTOMERID;
return $this;
}

public function setCBATOSCUSTOMERIP($CBATOS_CUSTOMERIP)
{
$this->CBATOS_CUSTOMERIP = $CBATOS_CUSTOMERIP;
return $this;
}

public function setCBATOSPATHBIN($CBATOS_PATHBIN)
{
$this->CBATOS_PATHBIN = $CBATOS_PATHBIN;
return $this;
}

public function setCBATOSMODEDEBUG($CBATOS_MODEDEBUG)
{
$this->CBATOS_MODEDEBUG = $CBATOS_MODEDEBUG;
return $this;
}

public function setMARCHAND($MARCHAND)
{
$this->MARCHAND = $MARCHAND;
return $this;
}
}
