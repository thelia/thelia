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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\LostPasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Service\CustomerEmailRequestLimiter;
use Thelia\Model\CustomerQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A lost-password request names an address the caller typed, and the answer to it
 * carries a brand new password: left uncapped, it mails a third party on demand and
 * locks the owner out of the account on every call.
 */
final class CustomerLostPasswordRateLimitTest extends ActionIntegrationTestCase
{
    private RequestStack $requestStack;

    private ?string $previousClientIp = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Give this test its own caller address, so the per-client window it spends is
        // its own and neither the rest of the suite nor an earlier run is in the way.
        $this->requestStack = $this->getService(RequestStack::class);
        $request = $this->requestStack->getMainRequest();
        $this->previousClientIp = $request?->server->get('REMOTE_ADDR');
        $request?->server->set('REMOTE_ADDR', '203.0.113.'.random_int(1, 254));
    }

    protected function tearDown(): void
    {
        $this->requestStack->getMainRequest()?->server->set('REMOTE_ADDR', $this->previousClientIp);

        parent::tearDown();
    }

    public function testAPasswordIsNoLongerResetOnceTheAddressHasAskedTooOften(): void
    {
        $email = 'lost-password-'.bin2hex(random_bytes(8)).'@example.com';
        $customer = $this->factory->customer($this->factory->customerTitle(), ['email' => $email]);
        $storedPassword = $customer->getPassword();

        // Spend the address window through the limiter itself rather than by asking for
        // three passwords: the request below is then the first one over the cap, and no
        // email leaves this test.
        $limiter = $this->getService(CustomerEmailRequestLimiter::class);
        self::assertTrue($limiter->allows($email));
        self::assertTrue($limiter->allows($email));
        self::assertTrue($limiter->allows($email));
        self::assertFalse($limiter->allows($email));

        $this->dispatch(new LostPasswordEvent($email), TheliaEvents::LOST_PASSWORD);

        self::assertSame(
            $storedPassword,
            CustomerQuery::create()->findPk($customer->getId())?->getPassword(),
            'Over the cap, a lost-password request must leave the account alone instead of replacing its password and mailing the new one.',
        );
    }
}
