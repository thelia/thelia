<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace TheliaSmarty\Tests\Template\Plugin;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\Template\ParserContext;
use Thelia\Tests\ContainerAwareTestCase;
use TheliaSmarty\Template\SmartyParser;

/**
 * Class SmartyPluginTestCase
 * @package TheliaSmarty\Tests\Template\Plugin
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class SmartyPluginTestCase extends ContainerAwareTestCase
{
    /** @var SmartyParser */
    protected $smarty;

    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $this->smarty = new SmartyParser(
            $container->get("request"),
            $container->get("event_dispatcher"),
            new ParserContext($container->get("request"))
        );

        $this->smarty->addPlugins($this->getPlugin($container));
        $this->smarty->registerPlugins();
    }

    protected function render($template)
    {
        return $this->smarty->fetch(__DIR__.DS."fixture".DS.$template);
    }

    /**
     * @return \TheliaSmarty\Template\AbstractSmartyPlugin
     */
    abstract protected function getPlugin(ContainerBuilder $container);
}
