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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Domain\Customer\Service\CustomerGuestConversionService;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Someone who ordered without an account has no account to sign into, so the tracking
 * link is the whole of the authentication: whoever holds it sees the order, and nobody
 * else may reach it.
 *
 * The link therefore has to be unforgeable, tied to the one order it was issued for,
 * short-lived, and dead the moment the account behind it changes — including the
 * moment the guest turns it into a real account and starts signing in instead.
 */
final class GuestOrderAccessServiceTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private GuestOrderAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->service = $this->getService(GuestOrderAccessService::class);
    }

    public function testTheLinkLeadsBackToTheOrderItWasIssuedFor(): void
    {
        $order = $this->guestOrder();

        $found = $this->service->findOrderForToken($this->service->createToken($order));

        self::assertInstanceOf(Order::class, $found);
        self::assertSame($order->getId(), $found->getId());
    }

    public function testAnExpiredLinkIsRejected(): void
    {
        $order = $this->guestOrder();

        self::assertNull($this->service->findOrderForToken($this->service->createToken($order, -1)));
    }

    /**
     * The expiry is in the link, so it must be signed along with the rest: otherwise
     * anyone holding an expired link could simply move the date forward.
     */
    public function testALinkGivenALongerLifeThanItWasSignedForIsRejected(): void
    {
        $order = $this->guestOrder();
        [$orderId, $expiresAt, $signature] = explode('.', $this->service->createToken($order));

        $stretched = \sprintf('%s.%d.%s', $orderId, (int) $expiresAt + 86400, $signature);

        self::assertNull($this->service->findOrderForToken($stretched));
    }

    public function testALinkPointedAtAnotherOrderIsRejected(): void
    {
        $order = $this->guestOrder();
        $other = $this->guestOrder();
        [, $expiresAt, $signature] = explode('.', $this->service->createToken($order));

        $moved = \sprintf('%d.%s.%s', $other->getId(), $expiresAt, $signature);

        self::assertNull($this->service->findOrderForToken($moved));
    }

    /**
     * Choosing a password opens nothing: the record stays a guest until the activation
     * code is answered, so the buyer can neither sign in nor be sent a reset link. The
     * link has to carry them through that gap — otherwise a code that never arrives puts
     * the order out of reach for good.
     */
    public function testTheLinkOutlivesThePasswordBeingChosen(): void
    {
        $guest = $this->guest();
        $order = $this->factory->order($guest);
        $token = $this->service->createToken($order);

        $this->getService(CustomerGuestConversionService::class)->convert($guest, 'a-chosen-password');

        self::assertInstanceOf(
            Order::class,
            $this->service->findOrderForToken($token),
            'Between the password being chosen and the code being answered there is no other way in.',
        );
    }

    /**
     * What retires the link is the account becoming usable, which is what the activation
     * code decides. From then on the order is reached the way every other customer
     * reaches theirs.
     */
    public function testALinkStopsWorkingOnceTheAccountIsOpen(): void
    {
        $guest = $this->guest();
        $order = $this->factory->order($guest);
        $token = $this->service->createToken($order);

        self::assertInstanceOf(Order::class, $this->service->findOrderForToken($token));

        $guest->setIsGuest(0)->setEnable(1)->save();

        self::assertNull(
            $this->service->findOrderForToken($token),
            'A link issued to a guest must not survive the account it belonged to being opened.',
        );
    }

    /**
     * Told apart from a dead link, and from a dead link only: a buyer sent back to their
     * own order has to learn that it is waiting behind a sign-in, while a token nobody
     * was ever issued must learn nothing at all.
     */
    public function testALinkRetiredByAnOpenedAccountIsRecognisedAsSuch(): void
    {
        $guest = $this->guest();
        $order = $this->factory->order($guest);
        $token = $this->service->createToken($order);

        $guest->setIsGuest(0)->setEnable(1)->save();

        $found = $this->service->findOrderNowBehindAnAccount($token);

        self::assertInstanceOf(Order::class, $found);
        self::assertSame($order->getId(), $found->getId());
    }

    public function testAForgedOrExpiredLinkIsNeverTakenForOneRetiredByAnAccount(): void
    {
        $guest = $this->guest();
        $order = $this->factory->order($guest);
        [$orderId, $expiresAt] = explode('.', $this->service->createToken($order));

        $guest->setIsGuest(0)->setEnable(1)->save();

        self::assertNull(
            $this->service->findOrderNowBehindAnAccount(
                \sprintf('%s.%s.%s', $orderId, $expiresAt, bin2hex(random_bytes(32))),
            ),
            'A forged token must say nothing, whatever the state of the account it aims at.',
        );
        self::assertNull(
            $this->service->findOrderNowBehindAnAccount($this->service->createToken($order, -1)),
            'Nor must an expired one.',
        );
    }

    public function testALinkStopsWorkingWhenTheAddressChanges(): void
    {
        $guest = $this->guest();
        $order = $this->factory->order($guest);
        $token = $this->service->createToken($order);

        $guest->setEmail('moved-'.bin2hex(random_bytes(8)).'@example.com')->save();

        self::assertNull($this->service->findOrderForToken($token));
    }

    public function testATokenWhoseSignatureWasReplacedIsRejected(): void
    {
        $order = $this->guestOrder();
        [$orderId, $expiresAt] = explode('.', $this->service->createToken($order));

        $forged = \sprintf('%s.%s.%s', $orderId, $expiresAt, bin2hex(random_bytes(32)));

        self::assertNull($this->service->findOrderForToken($forged));
    }

    /**
     * These name nothing this shop ever issued, so the service must answer null rather
     * than raise: they reach it straight from a URL somebody typed.
     *
     * @return iterable<string, array{string}>
     */
    public static function malformedTokenProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no separator' => ['nonsense'];
        yield 'too few parts' => ['1.2'];
        yield 'too many parts' => ['1.2.3.4'];
        yield 'non numeric order' => ['abc.2000000000.deadbeef'];
        yield 'non numeric expiry' => ['1.later.deadbeef'];
        yield 'unknown order' => ['999999999.2000000000.deadbeef'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedTokenProvider')]
    public function testATokenThisShopDidNotIssueIsRejected(string $token): void
    {
        self::assertNull($this->service->findOrderForToken($token));
    }

    private function guest(): Customer
    {
        return $this->factory->guestCustomer(
            $this->factory->customerTitle(),
            ['email' => 'guest-order-access-'.bin2hex(random_bytes(8)).'@example.com'],
        );
    }

    private function guestOrder(): Order
    {
        return $this->factory->order($this->guest());
    }
}
