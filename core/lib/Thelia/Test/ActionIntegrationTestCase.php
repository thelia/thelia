<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Test;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for tests that drive a Thelia\Action\* listener by
 * dispatching one of its TheliaEvents::* events.
 *
 * Wires the real kernel dispatcher and a fresh FixtureFactory in
 * setUp() so every child test can go straight to the interesting
 * part:
 *
 *   $this->dispatch(new BrandCreateEvent()->setTitle('x'), TheliaEvents::BRAND_CREATE);
 *
 * Every child test is still wrapped in the transaction rollback
 * provided by {@see IntegrationTestCase}.
 */
abstract class ActionIntegrationTestCase extends IntegrationTestCase
{
    protected EventDispatcherInterface $dispatcher;

    protected FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->factory = $this->createFixtureFactory();
    }

    /**
     * @template T of Event
     *
     * @param T $event
     *
     * @return T
     */
    protected function dispatch(Event $event, string $eventName): Event
    {
        $this->dispatcher->dispatch($event, $eventName);

        return $event;
    }
}
