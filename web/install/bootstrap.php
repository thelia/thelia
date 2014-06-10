<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
define('THELIA_INSTALL_MODE', true);
include __DIR__ . "/../../core/bootstrap.php";

$thelia = new \Thelia\Core\Thelia("install", false);

        foreach (array('cache' => $thelia->getCacheDir(), 'logs' => $thelia->getLogDir()) as $name => $dir) {
		if (!is_dir($dir)) {
   			if (false === @mkdir($dir, 0777, true)) {
			        $errors[$i] = "Unable to create the $dir directory";
				$i++;
			}
		} elseif (!is_writable($dir)) {
			        $errors[$i] = "Unable to write the $dir directory";
				$i++;
		}
	}
if(!$errors){
    $thelia->boot();
}
