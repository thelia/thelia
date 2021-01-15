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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\ParserInterface;

/**
 * Class BaseAction
 * @package Thelia\Tests\Action\ImageTest
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class BaseAction extends TestCase
{
    /**
     * @return EventDispatcherInterface
     */
    protected function getMockEventDispatcher()
    {
        return $this->createMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    /**
     * @return ParserInterface
     */
    protected function getMockParserInterface()
    {
        /** @var ParserInterface $parserInterface */
        $parserInterface = $this->createMock("Thelia\\Core\\Template\\ParserInterface");
        return $parserInterface;
    }

    public function getContainer()
    {
        $container = new ContainerBuilder();

        $container->set("event_dispatcher", $this->getMockEventDispatcher());

        return $container;
    }
}
