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

namespace Thelia\Tests\Integration\Core\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Test\IntegrationTestCase;

final class SessionTest extends IntegrationTestCase
{
    public function testAuthenticatingACustomerRenewsTheSessionId(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('cart.id', 42);
        $anonymousId = $session->getId();

        $session->setCustomerUser($customer);

        self::assertNotSame($anonymousId, $session->getId(), 'the id the browser carried before login must not survive it');
        self::assertSame(42, $session->get('cart.id'), 'the session data follows the new id');

        $loggedInId = $session->getId();
        $session->setCustomerUser($customer);

        self::assertSame($loggedInId, $session->getId(), 'refreshing the same user keeps the id');
    }

    public function testAuthenticatingAnAdminRenewsTheSessionId(): void
    {
        $factory = $this->createFixtureFactory();
        $admin = $factory->admin();

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $anonymousId = $session->getId();

        $session->setAdminUser($admin);

        self::assertNotSame($anonymousId, $session->getId());

        $loggedInId = $session->getId();
        $session->setAdminUser($admin);

        self::assertSame($loggedInId, $session->getId(), 'revalidating the same admin keeps the id');
    }
}
