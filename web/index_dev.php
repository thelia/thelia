<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/


use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Request;

//use Symfony\Component\DependencyInjection;

$env = 'dev';
require __DIR__ . '/../core/bootstrap.php';

// List of allowed IP
$trustedIp = array(
  '::1',
  '127.0.0.1',
  '192.168.56.1'
);

$request = Request::createFromGlobals();
$thelia = new Thelia("dev", true);

if ( false === in_array($request->getClientIp(), $trustedIp)) {
    // Redirect 401 Unauthorized
    $response = new Response('Unauthorized', 401);
    $thelia->terminate($request, $response);
}

$response = $thelia->handle($request)->prepare($request)->send();

$thelia->terminate($request, $response);