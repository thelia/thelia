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

namespace Thelia\Tests\Action;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\ParserInterface;

/**
 * Class BaseAction
 * @package Thelia\Tests\Action\ImageTest
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class BaseAction extends \PHPUnit_Framework_TestCase
{
    /**
     * @return EventDispatcherInterface
     */
    protected function getMockEventDispatcher()
    {
        return $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    /**
     * @return ParserInterface
     */
    protected function getMockParserInterface()
    {
        return $this->getMock("Thelia\\Core\\Template\\ParserInterface");
    }

    public function getContainer()
    {
        $container = new ContainerBuilder();

        $container->set("event_dispatcher", $this->getMockEventDispatcher());

        return $container;
    }
}
