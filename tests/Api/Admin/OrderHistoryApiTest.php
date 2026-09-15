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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Test\ApiTestCase;

/**
 * The whole journal of one order, read from the back-office side.
 *
 * This is the opposite end of what the customer reads: everything the shop
 * recorded about the order, internal notes included, newest first. The only
 * thing that decides who may read it is the permission that already decides who
 * may read the order itself.
 */
final class OrderHistoryApiTest extends ApiTestCase
{
    private const int DEFAULT_PAGE_SIZE = 20;
    private const int PAGE_SIZE_CAP = 100;

    public function testTheCollectionReturnsTheLinesOfThatOrderNewestFirst(): void
    {
        $order = $this->createFixtureFactory()->order();

        $this->line($order, OrderHistoryEventType::ORDER_CREATED, comment: 'first');
        $this->line($order, OrderHistoryEventType::STATUS_CHANGED, comment: 'second');
        $this->line($order, OrderHistoryEventType::INVOICE_REF_ALLOCATED, comment: 'third');

        $members = $this->historyOf($order)['hydra:member'];

        self::assertCount(3, $members);
        self::assertSame(['third', 'second', 'first'], array_column($members, 'comment'));
    }

    /**
     * The customer side of the journal filters on both the event type and the
     * visibility flag. The administration side filters on neither: an internal
     * note is exactly what a back-office reader is here for.
     */
    public function testAnInternalNoteIsPartOfTheAdministrationTimeline(): void
    {
        $order = $this->createFixtureFactory()->order();

        $this->line(
            $order,
            OrderHistoryEventType::NOTE,
            comment: 'Buyer sounded unhappy on the phone, watch this one.',
            visibleToCustomer: false,
        );

        $members = $this->historyOf($order)['hydra:member'];

        self::assertCount(1, $members);
        self::assertSame('Buyer sounded unhappy on the phone, watch this one.', $members[0]['comment']);
        self::assertFalse($members[0]['visibleToCustomer']);
    }

    public function testALineCarriesItsAuthorAndItsDecodedPayload(): void
    {
        $order = $this->createFixtureFactory()->order();

        $this->line(
            $order,
            OrderHistoryEventType::STATUS_CHANGED,
            payload: '{"from":"not_paid","to":"paid"}',
            comment: null,
        );

        $line = $this->historyOf($order)['hydra:member'][0];

        self::assertSame(OrderHistoryEventType::STATUS_CHANGED->value, $line['eventType']);
        self::assertSame(OrderHistoryActorType::ADMIN->value, $line['actorType']);
        self::assertSame('shop-manager', $line['actorLabel']);
        self::assertSame(['from' => 'not_paid', 'to' => 'paid'], $line['payload']);
        self::assertNotEmpty($line['createdAt']);
    }

    /**
     * The shape of a line is what an integration and the back-office screen both
     * read, and what the order history must never grow past: nothing of the
     * request that wrote it, nothing of the mail it announced.
     */
    public function testALineExposesExactlyTheDeclaredFields(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();

        $this->line(
            $order,
            OrderHistoryEventType::NOTE,
            payload: '{"reason":"stock"}',
            comment: 'Waiting for the supplier.',
            visibleToCustomer: true,
            adminId: $factory->admin()->getId(),
        );

        $line = $this->historyOf($order)['hydra:member'][0];
        $keys = array_keys($line);
        sort($keys);

        self::assertSame(
            [
                '@id',
                '@type',
                'actorLabel',
                'actorType',
                'adminId',
                'comment',
                'createdAt',
                'eventType',
                'id',
                'payload',
                'visibleToCustomer',
            ],
            $keys,
        );
    }

    /**
     * The API skips null values everywhere, and this collection is no exception:
     * a line nobody commented and nobody signed carries neither key, rather than
     * a key holding null. Stated here because a reader of the shape above would
     * otherwise expect every field on every line.
     */
    public function testAFieldThatHoldsNothingIsLeftOutRatherThanSentAsNull(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->line($order, OrderHistoryEventType::ORDER_CREATED);

        $line = $this->historyOf($order)['hydra:member'][0];

        self::assertArrayNotHasKey('comment', $line);
        self::assertArrayNotHasKey('adminId', $line);
        self::assertSame([], $line['payload']);
    }

    public function testTheLinesOfAnotherOrderNeverAppear(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();
        $otherOrder = $factory->order();

        $this->line($order, OrderHistoryEventType::ORDER_CREATED, comment: 'mine');
        $this->line($otherOrder, OrderHistoryEventType::ORDER_CREATED, comment: 'somebody else');

        $members = $this->historyOf($order)['hydra:member'];

        self::assertCount(1, $members);
        self::assertSame('mine', $members[0]['comment']);
    }

    public function testThePageHoldsTwentyLinesAndTheNextOneHoldsTheRest(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->lines($order, 25);

        $firstPage = $this->historyOf($order);
        $secondPage = $this->historyOf($order, '?page=2');

        self::assertSame(25, $firstPage['hydra:totalItems']);
        self::assertCount(self::DEFAULT_PAGE_SIZE, $firstPage['hydra:member']);
        self::assertCount(5, $secondPage['hydra:member']);
        self::assertSame(
            [],
            array_intersect(
                array_column($firstPage['hydra:member'], 'id'),
                array_column($secondPage['hydra:member'], 'id'),
            ),
        );
    }

    public function testTheCallerMayAskForABiggerPage(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->lines($order, 25);

        $page = $this->historyOf($order, '?itemsPerPage=50');

        self::assertCount(25, $page['hydra:member']);
    }

    public function testAnOversizedPageRequestIsCappedWithoutChangingTheTotal(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->lines($order, self::PAGE_SIZE_CAP + 5);

        $page = $this->historyOf($order, '?itemsPerPage=500');

        self::assertSame(self::PAGE_SIZE_CAP + 5, $page['hydra:totalItems']);
        self::assertCount(self::PAGE_SIZE_CAP, $page['hydra:member']);
    }

    public function testAnUnknownOrderIsNotFound(): void
    {
        $response = $this->jsonRequest(
            'GET',
            '/api/admin/orders/99999999/history',
            token: $this->authenticateAsAdmin(),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAnAnonymousCallIsRefused(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->line($order, OrderHistoryEventType::ORDER_CREATED);

        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId().'/history');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testACustomerTokenIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $factory->order($customer);
        $this->line($order, OrderHistoryEventType::ORDER_CREATED);

        $response = $this->jsonRequest(
            'GET',
            '/api/admin/orders/'.$order->getId().'/history',
            token: $this->authenticateAsCustomer($customer),
        );

        self::assertContains($response->getStatusCode(), [401, 403]);
    }

    /**
     * The journal is guarded by the permission that guards the order, so an
     * administrator who may not read orders may not read their history either.
     */
    public function testAnAdministratorWithoutOrderAccessIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();
        $this->line($order, OrderHistoryEventType::ORDER_CREATED);

        $restricted = $factory->restrictedAdmin(
            [AdminResources::CUSTOMER => [AccessManager::VIEW]],
            ['password' => 'password'],
        );

        $response = $this->jsonRequest(
            'GET',
            '/api/admin/orders/'.$order->getId().'/history',
            token: $this->authenticateAsAdmin($restricted),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * A collection-only resource still gets an item route from API Platform, so
     * that its members can carry an @id — the same NotExposed route PaymentModule
     * and GuestCustomer already have. It sits outside /api/admin, so what matters
     * is that it serves nothing, to anybody, ever.
     */
    public function testTheGeneratedItemRouteServesNothing(): void
    {
        $order = $this->createFixtureFactory()->order();
        $line = $this->line($order, OrderHistoryEventType::NOTE, comment: 'Internal.');

        foreach ([null, $this->authenticateAsAdmin()] as $token) {
            $response = $this->jsonRequest('GET', '/api/order_histories/'.$line->getId(), token: $token);

            self::assertSame(404, $response->getStatusCode());
        }
    }

    /**
     * Non-regression on the customer side: the administration collection reads
     * the same rows, and opening it must not have widened what the account page
     * hands out.
     */
    public function testTheCustomerReadStillExposesOnlyTheVisibleNotes(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $factory->order($customer);

        $this->line($order, OrderHistoryEventType::NOTE, comment: 'Your parcel leaves tomorrow.', visibleToCustomer: true);
        $this->line($order, OrderHistoryEventType::NOTE, comment: 'Internal: watch this one.', visibleToCustomer: false);
        $this->line($order, OrderHistoryEventType::STATUS_CHANGED, payload: '{"from":"not_paid","to":"paid"}');

        $response = $this->jsonRequest(
            'GET',
            '/api/front/account/orders/'.$order->getId(),
            token: $this->authenticateAsCustomer($customer),
        );

        self::assertJsonResponseSuccessful($response);

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $notes = $payload['OrderCustomerNotesAddon']['customerNotes'];

        self::assertCount(1, $notes);
        self::assertSame('Your parcel leaves tomorrow.', $notes[0]['comment']);

        foreach (['eventType', 'actorType', 'actorLabel', 'adminId', 'payload', 'visibleToCustomer'] as $internalField) {
            self::assertArrayNotHasKey($internalField, $notes[0]);
        }
    }

    /**
     * @return array{'hydra:member': list<array<string, mixed>>, 'hydra:totalItems': int}
     */
    private function historyOf(Order $order, string $queryString = ''): array
    {
        $response = $this->jsonRequest(
            'GET',
            '/api/admin/orders/'.$order->getId().'/history'.$queryString,
            token: $this->authenticateAsAdmin(),
        );

        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function lines(Order $order, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->line($order, OrderHistoryEventType::STATUS_CHANGED, comment: 'line '.$i);
        }
    }

    private function line(
        Order $order,
        OrderHistoryEventType $eventType,
        ?string $payload = null,
        ?string $comment = null,
        bool $visibleToCustomer = false,
        ?int $adminId = null,
    ): OrderHistory {
        $line = new OrderHistory();
        $line
            ->setOrderId($order->getId())
            ->setEventType($eventType->value)
            ->setActorType(OrderHistoryActorType::ADMIN->value)
            ->setActorLabel('shop-manager')
            ->setAdminId($adminId)
            ->setPayload($payload)
            ->setComment($comment)
            ->setVisibleToCustomer($visibleToCustomer ? 1 : 0)
            ->save($this->getPropelConnection());

        return $line;
    }
}
