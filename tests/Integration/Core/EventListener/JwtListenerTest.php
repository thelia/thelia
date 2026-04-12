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

namespace Thelia\Tests\Integration\Core\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Thelia\Core\EventListener\JwtListener;
use Thelia\Model\Admin;
use Thelia\Model\Customer;
use Thelia\Test\IntegrationTestCase;

final class JwtListenerTest extends IntegrationTestCase
{
    private JwtListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new JwtListener();
    }

    public function testOnJwtCreatedAddsAdminTypeToPayload(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        $event = new JWTCreatedEvent(['username' => $admin->getLogin()], $admin, []);
        $this->listener->onJWTCreated($event);

        $data = $event->getData();
        self::assertArrayHasKey('type', $data);
        self::assertSame(Admin::class, $data['type']);
    }

    public function testOnJwtCreatedAddsCustomerTypeToPayload(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        $event = new JWTCreatedEvent(['username' => $customer->getEmail()], $customer, []);
        $this->listener->onJWTCreated($event);

        $data = $event->getData();
        self::assertArrayHasKey('type', $data);
        self::assertSame(Customer::class, $data['type']);
    }

    public function testOnJwtCreatedIgnoresNonActiveRecordUsers(): void
    {
        // Create a mock user that is NOT an ActiveRecordInterface.
        $user = new class implements \Symfony\Component\Security\Core\User\UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'test';
            }
        };

        $event = new JWTCreatedEvent(['username' => 'test'], $user, []);
        $this->listener->onJWTCreated($event);

        $data = $event->getData();
        self::assertArrayNotHasKey('type', $data);
    }
}
