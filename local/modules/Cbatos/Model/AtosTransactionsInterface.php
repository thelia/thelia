<?php
namespace Cbatos\Model;

interface AtosTransactionsInterface
{
    // Data access
    public function write($file=null);
    public static function read($file=null);

    // variables setters

    /*
     * @return Cbatos\Model\ConfigInterface
     */

    public function setMARCHAND($MARCHAND);
    public function setDATE($DATE);
 public function setTIME($TIME);
 public function setCARD($CARD);
public function setAUTO($AUTO);
public function setAMOUNT($AMOUNT);
public function setREF($REF);
public function setCURRENCY($CURRENCY);
public function setIPCUSTOMER($IPCUSTOMER);
public function setEMAILCUSTOMER($EMAILCUSTOMER);
public function setORDERID($ORDERID);
public function setCUSTOMERID($CUSTOMERID);
public function setBANKRESPONSESCODE($BANKRESPONSESCODE);
public function setCERTIFICAT($CERTIFICAT);
public function setETP($ETP);
public function setCVVCODE($CVVCODE);

}
