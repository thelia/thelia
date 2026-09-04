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

namespace Thelia\Tests\Api\Front;

use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * Reading an order placed without an account.
 *
 * The signed link is the whole of the authentication — its holder has no account to
 * sign into — so everything the endpoint refuses has to be refused the same way. A 403
 * on a real order and a 404 on a made-up one would answer, one request at a time,
 * which order numbers this shop has issued.
 */
final class GuestOrderTrackingApiTest extends ApiTestCase
{
    use RegistersGuestCustomers;

    public function testTheLinkOpensTheOrderItNames(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));

        $response = $this->jsonRequest('GET', '/api/front/guest-orders/'.$this->trackingToken($order));

        self::assertJsonResponseSuccessful($response);

        $body = json_decode((string) $response->getContent(), true);

        self::assertSame($order->getId(), $body['id'] ?? null);
        self::assertSame($order->getRef(), $body['ref'] ?? null);
    }

    public function testALinkForOneOrderDoesNotOpenAnother(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $mine = $factory->order($factory->guestCustomer($title));
        $someoneElses = $factory->order($factory->guestCustomer($title));

        // The order number swapped, the signature kept: this is the shape an attacker
        // who received one link would try first.
        [, $expiresAt, $signature] = explode('.', $this->trackingToken($mine));

        $response = $this->jsonRequest(
            'GET',
            \sprintf('/api/front/guest-orders/%d.%s.%s', $someoneElses->getId(), $expiresAt, $signature),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAForgedSignatureIsRefusedTheSameWayAsAnOrderThatDoesNotExist(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));

        $forged = $this->jsonRequest('GET', \sprintf(
            '/api/front/guest-orders/%d.%d.%s',
            $order->getId(),
            time() + 3600,
            str_repeat('a', 64),
        ));

        $noSuchOrder = $this->jsonRequest('GET', \sprintf(
            '/api/front/guest-orders/%d.%d.%s',
            999999999,
            time() + 3600,
            str_repeat('a', 64),
        ));

        self::assertSame(404, $forged->getStatusCode());
        self::assertSame(
            $forged->getStatusCode(),
            $noSuchOrder->getStatusCode(),
            'A real order and a made-up one must be refused identically, or the endpoint answers which ids exist.',
        );
    }

    public function testAnExpiredLinkIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));

        $response = $this->jsonRequest(
            'GET',
            '/api/front/guest-orders/'.$this->trackingToken($order, -60),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testTheLinkStopsWorkingOnceTheAccountIsCompleted(): void
    {
        $factory = $this->createFixtureFactory();
        $guest = $factory->guestCustomer($factory->customerTitle());
        $order = $factory->order($guest);
        $token = $this->trackingToken($order);

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/front/guest-orders/'.$token));

        $this->jsonRequest(
            'POST',
            '/api/front/guest-customers/'.$guest->getId().'/convert',
            ['password' => 'a-chosen-password', 'orderToken' => $token],
        );

        $response = $this->jsonRequest('GET', '/api/front/guest-orders/'.$token);

        self::assertSame(
            404,
            $response->getStatusCode(),
            'From the moment the account has a password, its orders are reached by signing in.',
        );
    }

    /**
     * Walking the order numbers spends the budget of the caller, and that budget is
     * about the caller and nothing else — so it is answered honestly, with a 429. It is
     * the only refusal here that is: it names no order, and a caller who is merely too
     * fast has no other way of telling that from a link that stopped working.
     *
     * Everything before it is a plain 404: the budget is spent before the signature is
     * looked at, so a made-up token and a real one cost exactly the same.
     */
    public function testWalkingTheOrderNumbersIsAnsweredAs404UntilTheCallerIsOverItsOwnBudget(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));
        $token = $this->trackingToken($order);

        self::assertJsonResponseSuccessful(
            $this->jsonRequest('GET', '/api/front/guest-orders/'.$token),
            'The link has to work before the walk starts, or this test proves nothing.',
        );

        $statuses = [];

        for ($orderId = 1; $orderId <= 40; ++$orderId) {
            $statuses[] = $this->jsonRequest('GET', \sprintf(
                '/api/front/guest-orders/%d.%d.%s',
                $orderId,
                time() + 3600,
                str_repeat('b', 64),
            ))->getStatusCode();
        }

        self::assertSame(
            404,
            $statuses[0],
            'A made-up token answers like an unknown order, and says nothing about the limiter.',
        );
        self::assertContains(
            429,
            $statuses,
            'A caller walking the order numbers must eventually be told it is going too fast.',
        );
        self::assertSame(
            [404, 429],
            array_values(array_unique($statuses)),
            'Those two answers, and no third one that would tell the caller an order exists.',
        );

        self::assertSame(
            429,
            $this->jsonRequest('GET', '/api/front/guest-orders/'.$token)->getStatusCode(),
            'A caller that walked the order numbers must not still be able to use the link it holds.',
        );
    }

    /**
     * The other budget, and the reason it is not answered the same way. This one hangs
     * off the order number in the token, so a 429 would say "that number is being
     * tracked" — one request at a time, which order numbers this shop has issued.
     */
    public function testHammeringOneOrderNumberStaysA404(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));
        $token = $this->trackingToken($order);

        $statuses = [];

        // Past the per-order window (10) and short of the per-client one (30), so that
        // the only budget spent here is the one attached to this order.
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $statuses[] = $this->jsonRequest('GET', '/api/front/guest-orders/'.$token)->getStatusCode();
        }

        self::assertContains(404, $statuses, 'The order window has to run out inside this loop.');
        self::assertNotContains(
            429,
            $statuses,
            'The budget of one order number must never be admitted to: it would answer that the number exists.',
        );
    }

    private function trackingToken(\Thelia\Model\Order $order, ?int $lifetime = null): string
    {
        return $this->getService(GuestOrderAccessService::class)->createToken($order, $lifetime);
    }
}
