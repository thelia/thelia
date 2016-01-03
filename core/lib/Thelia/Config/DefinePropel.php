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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class DefinePropel
{
    private $parameterBag;

    public function __construct(ConfigurationInterface $configuration, array $propelConf, array $envParameters)
    {
        $this->parameterBag = new ParameterBag($envParameters);
        $processor = new Processor();
        $processorConfig = $processor->processConfiguration($configuration, $propelConf);
        $this->parameterBag->add($processorConfig["connection"]);
        $this->parameterBag->resolve();
    }

    public function getConfig()
    {
        return array(
            "dsn" => $this->parameterBag->get("dsn"),
            "user" => $this->parameterBag->get("user"),
            "password" => $this->parameterBag->get("password"),
            "classname" => $this->parameterBag->get("classname"),
            'options' => array(
                \PDO::MYSQL_ATTR_INIT_COMMAND => array('value' =>'SET NAMES \'UTF8\''))
        );
    }
}
