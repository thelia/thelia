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

    const DIR_CONF = 'local/config';
    const DIR_LOG  = 'log';
    const DIR_CACHE = 'cache';

    private $directories = array();
    private $validation = array();
    private $valid = true;

    public function __construct($verifyInstall = true)
    {


        $this->directories = array(
            self::DIR_CONF => THELIA_ROOT . 'local/config',
            self::DIR_LOG => THELIA_ROOT . 'DIR_LOG',
            self::DIR_CACHE => THELIA_ROOT . 'DIR_CACHE'
        );

        $this->validation = array(
            self::DIR_CONF => array(
                "text" => sprintf("config directory(%s)...", $this->directories[self::DIR_CONF]),
                "status" => true
            ),
            self::DIR_LOG => array(
                "text" => sprintf("DIR_CACHE directory(%s)...", $this->directories[self::DIR_LOG]),
                "status" => true
            ),
            self::DIR_CACHE => array(
                "text" => sprintf("DIR_LOG directory(%s)...", $this->directories[self::DIR_CACHE]),
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