<?php
namespace Cbatos\Model;

interface ConfigInterface
{
    // Data access
    public function write($file=null);
    public static function read($file=null);

    // variables setters

    /*
     * @return Cbatos\Model\ConfigInterface
     */
    public function setCBATOSMERCHANTID($CBATOS_MERCHANTID);
    public function setCBATOSURLRETOUR($CBATOS_URLRETOUR);
 public function setCBATOSURLAUTOMATIC($CBATOS_URLAUTOMATIC);
 public function setCBATOSCAPTUREDAYS($CBATOS_CAPTUREDAYS);
public function setCBATOSDEVISES($CBATOS_DEVISES);
public function setCBATOSCUSTOMERMAIL($CBATOS_CUSTOMERMAIL);
public function setCBATOSCUSTOMERID($CBATOS_CUSTOMERID);
public function setCBATOSCUSTOMERIP($CBATOS_CUSTOMERIP);
public function setCBATOSPATHBIN($CBATOS_PATHBIN);
public function setCBATOSMODEDEBUG($CBATOS_MODEDEBUG);

}
