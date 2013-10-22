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

namespace Thelia\Config;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class DefinePropel
{
    private $processorConfig;

    public function __construct(ConfigurationInterface $configuration, array $propelConf)
    {
        $processor = new Processor();
        $this->processorConfig = $processor->processConfiguration($configuration, $propelConf);
    }

    public function getConfig()
    {
        $connection = $this->processorConfig["connection"];

        return $conf = array(
            "dsn" => $connection["dsn"],
            "user" => $connection["user"],
            "password" => $connection["password"],
            "classname" => $connection["classname"],
            'options' => array(
                \PDO::MYSQL_ATTR_INIT_COMMAND => array('value' =>'SET NAMES \'UTF8\''))
        );
    }
}
