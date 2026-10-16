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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Model\Customer;
use Thelia\Test\ApiTestCase;

/**
 * A listener tells the creation of a model through the API from its update:
 * something sent once, a welcome e-mail for instance, follows the creation only.
 */
final class ResourcePersistedEventApiTest extends ApiTestCase
{
    public function testAPostIsACreation(): void
    {
        $events = $this->recordEvents(function (): void {
            $response = $this->jsonRequest('POST', '/api/admin/customers', [
                'customerTitle' => '/api/admin/customer_titles/'.$this->createFixtureFactory()->customerTitle()->getId(),
                'firstname' => 'Jane',
                'lastname' => 'Doe',
                'email' => 'persisted-create@example.com',
                'password' => 'Password1!',
            ], $this->authenticateAsAdmin());

            self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        });

        self::assertCount(1, $events);
        self::assertTrue($events[0]->isCreate());
        self::assertFalse($events[0]->isDelete());
        self::assertSame(CustomerResource::class, $events[0]->getResourceClass());
        self::assertInstanceOf(Customer::class, $events[0]->getModel());
        self::assertSame('persisted-create@example.com', $events[0]->getModel()->getEmail());
    }

    public function testAPatchIsNotACreation(): void
    {
        $factory = $this->createFixtureFactory();
        $customerId = (int) $factory->customer($factory->customerTitle())->getId();
        $token = $this->authenticateAsAdmin();

        $events = $this->recordEvents(function () use ($customerId, $token): void {
            $response = $this->jsonRequest('PATCH', '/api/admin/customers/'.$customerId, ['lastname' => 'Patched'], $token, 'merge-patch+json');

            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        });

        self::assertCount(1, $events);
        self::assertFalse($events[0]->isCreate());
        self::assertFalse($events[0]->isDelete());
        self::assertSame($customerId, (int) $events[0]->getModel()->getId());
    }

    /**
     * @return list<ResourcePersistedEvent>
     */
    private function recordEvents(callable $body): array
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        self::assertInstanceOf(SymfonyEventDispatcherInterface::class, $dispatcher);

        $events = [];
        $listener = static function (ResourcePersistedEvent $event) use (&$events): void {
            if (CustomerResource::class === $event->getResourceClass()) {
                $events[] = $event;
            }
        };

        $dispatcher->addListener(ResourcePersistedEvent::class, $listener);

        try {
            $body();
        } finally {
            $dispatcher->removeListener(ResourcePersistedEvent::class, $listener);
        }

        return $events;
    }
}
