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

        return array(
            "dsn" => $connection["dsn"],
            "user" => $connection["user"],
            "password" => $connection["password"],
            "classname" => $connection["classname"],
            'options' => array(
                \PDO::MYSQL_ATTR_INIT_COMMAND => array('value' =>'SET NAMES \'UTF8\''))
        );
    }
}
