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

use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpKernel\HttpCache\HttpCache;

//use Symfony\Component\DependencyInjection;

$env = 'dev';
require __DIR__ . '/../core/bootstrap.php';

// List of allowed IP
$trustedIp = array(
  '::1',
  '127.0.0.1',
);
// Allowed Network
$trustedNetwork = '192.168.0.0/24'; 


/**
 * Check if an IP is in a network
 * @param string $IP
 * @param string $network
 * @return boolean
 */
function ip_in_network ($IP, $network) {
    if (!$IP or !$network or !strpos($network,'/')) return false;
    list ($net, $mask) = explode ("/", $network);   
    $ip_net = ip2long ($net);
    $ip_mask = ~((1 << (32 - $mask)) - 1);
    $ip_ip = ip2long ($IP);
    $ip_ip_net = $ip_ip & $ip_mask;
    return ($ip_ip_net == $ip_net);
}
  
  
$request = Request::createFromGlobals();
$thelia = new Thelia("dev", true);

if ( (false === in_array($request->getClientIp(), $trustedIp)) and
     (false === ($trustedNetwork and ip_in_network($request->getClientIp(), $trustedNetwork)))) {
    $response = Response::create('Forbidden', 403)->send();
    $thelia->terminate($request, $response);
} else {
    $response = $thelia->handle($request)->prepare($request)->send();
    $thelia->terminate($request, $response);

}
