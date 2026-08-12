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

namespace Thelia\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Thelia\Domain\Customer\Service\CustomerCodeManager;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Customer;

/**
 * "Send me the activation code again" is reachable by anyone who knows an address,
 * so the number of emails it can trigger has to be capped per address and per caller.
 */
final class CustomerCodeManagerTest extends TestCase
{
    public function testCodeRequestsForOneAddressStopBeingSentOnceTheLimitIsReached(): void
    {
        $mailerFactory = $this->createMock(MailerFactory::class);
        $mailerFactory->expects(self::exactly(3))->method('sendEmailToCustomer');

        $manager = new CustomerCodeManager(
            $mailerFactory,
            $this->limiter(3),
            $this->limiter(100),
            $this->requestStackWithClientIp('203.0.113.7'),
        );

        $customer = $this->customer('pending@example.com');

        self::assertTrue($manager->requestCode($customer));
        self::assertTrue($manager->requestCode($customer));
        self::assertTrue($manager->requestCode($customer));
        self::assertFalse($manager->requestCode($customer));
        self::assertFalse($manager->requestCode($customer));
    }

    public function testTheAddressLimitIgnoresCaseSoItCannotBeSidesteppedByRetyping(): void
    {
        $mailerFactory = $this->createMock(MailerFactory::class);
        $mailerFactory->expects(self::exactly(2))->method('sendEmailToCustomer');

        $manager = new CustomerCodeManager(
            $mailerFactory,
            $this->limiter(2),
            $this->limiter(100),
            $this->requestStackWithClientIp('203.0.113.7'),
        );

        self::assertTrue($manager->requestCode($this->customer('pending@example.com')));
        self::assertTrue($manager->requestCode($this->customer('Pending@Example.COM')));
        self::assertFalse($manager->requestCode($this->customer('PENDING@EXAMPLE.COM')));
    }

    public function testOneCallerWalkingManyAddressesIsStoppedByTheClientLimit(): void
    {
        $mailerFactory = $this->createMock(MailerFactory::class);
        $mailerFactory->expects(self::exactly(2))->method('sendEmailToCustomer');

        $manager = new CustomerCodeManager(
            $mailerFactory,
            $this->limiter(100),
            $this->limiter(2),
            $this->requestStackWithClientIp('203.0.113.7'),
        );

        self::assertTrue($manager->requestCode($this->customer('first@example.com')));
        self::assertTrue($manager->requestCode($this->customer('second@example.com')));
        self::assertFalse($manager->requestCode($this->customer('third@example.com')));
    }

    public function testWithoutARequestOnlyTheAddressLimitApplies(): void
    {
        $mailerFactory = $this->createMock(MailerFactory::class);
        $mailerFactory->expects(self::exactly(2))->method('sendEmailToCustomer');

        // A command line caller (module, cron, install script) has no client IP:
        // the per-client limit must be skipped instead of blocking the send. Here it
        // would stop everything after the first mail if it were applied.
        $manager = new CustomerCodeManager(
            $mailerFactory,
            $this->limiter(2),
            $this->limiter(1),
            new RequestStack(),
        );

        $customer = $this->customer('pending@example.com');

        self::assertTrue($manager->requestCode($customer));
        self::assertTrue($manager->requestCode($customer));
        self::assertFalse($manager->requestCode($customer));
    }

    private function limiter(int $limit): RateLimiterFactoryInterface
    {
        return new RateLimiterFactory(
            [
                'id' => 'test',
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => '1 hour',
            ],
            new InMemoryStorage(),
        );
    }

    private function requestStackWithClientIp(string $clientIp): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/customer/send-code', server: ['REMOTE_ADDR' => $clientIp]));

        return $requestStack;
    }

    private function customer(string $email): Customer
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getEmail')->willReturn($email);
        $customer->method('setConfirmationTokenWithExpiry')->willReturn('123456');

        return $customer;
    }
}
