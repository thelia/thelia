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
use Thelia\Domain\Customer\Service\CustomerEmailRequestLimiter;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Customer;

/**
 * "Send me the activation code again" is reachable by anyone who knows an address,
 * so the number of emails it can trigger has to be capped. The windows themselves
 * are covered by {@see CustomerEmailRequestLimiterTest}; what matters here is that
 * asking for a code goes through them.
 */
final class CustomerCodeManagerTest extends TestCase
{
    public function testCodeRequestsForOneAddressStopBeingSentOnceTheLimitIsReached(): void
    {
        $mailerFactory = $this->createMock(MailerFactory::class);
        $mailerFactory->expects(self::exactly(3))->method('sendEmailToCustomer');

        $manager = new CustomerCodeManager($mailerFactory, $this->limiterAllowing(3));

        $customer = $this->customer('pending@example.com');

        self::assertTrue($manager->requestCode($customer));
        self::assertTrue($manager->requestCode($customer));
        self::assertTrue($manager->requestCode($customer));
        self::assertFalse($manager->requestCode($customer));
        self::assertFalse($manager->requestCode($customer));
    }

    private function limiterAllowing(int $limit): CustomerEmailRequestLimiter
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/customer/send-code', server: ['REMOTE_ADDR' => '203.0.113.7']));

        return new CustomerEmailRequestLimiter(
            $this->window($limit),
            $this->window(100),
            $requestStack,
        );
    }

    private function window(int $limit): RateLimiterFactoryInterface
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

    private function customer(string $email): Customer
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getEmail')->willReturn($email);
        $customer->method('setConfirmationTokenWithExpiry')->willReturn('123456');

        return $customer;
    }
}
