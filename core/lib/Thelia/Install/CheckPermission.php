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

namespace Thelia\Install;


/**
 * Class CheckPermission
 * @package Thelia\Install
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CheckPermission extends BaseInstall
{
    const CONF = "const";
    const LOG  = "log";
    const CACHE = "cache";

    private $directories = array();
    private $validation = array();
    private $valid = true;

    public function __construct($verifyInstall = true)
    {


        $this->directories = array(
            self::CONF => THELIA_ROOT . "local/config",
            self::LOG => THELIA_ROOT . "log",
            self::CACHE => THELIA_ROOT . "cache"
        );

        $this->validation = array(
            self::CONF => array(
                "text" => sprintf("config directory(%s)...", $this->directories[self::CONF]),
                "status" => true
            ),
            self::LOG => array(
                "text" => sprintf("cache directory(%s)...", $this->directories[self::LOG]),
                "status" => true
            ),
            self::CACHE => array(
                "text" => sprintf("log directory(%s)...", $this->directories[self::CACHE]),
                "status" => true
            )
        );
        parent::__construct($verifyInstall);
    }

    public function exec()
    {
        foreach ($this->directories as $key => $directory) {
            if(is_writable($directory) === false) {
                $this->valid = false;
                $this->validation[$key]["status"] = false;
            }
        }
    }
}