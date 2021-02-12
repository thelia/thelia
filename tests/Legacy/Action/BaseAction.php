<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Action;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\ParserInterface;

/**
 * Class BaseAction.
 *
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
        $parserInterface = $this->createMock('Thelia\\Core\\Template\\ParserInterface');

        return $parserInterface;
    }

    public function getContainer()
    {
        $container = new ContainerBuilder();

        $container->set('event_dispatcher', $this->getMockEventDispatcher());

        return $container;
    }
}
