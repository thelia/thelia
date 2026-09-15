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

use Thelia\Api\Resource\Addon\OrderCustomerNotesAddon;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Test\ApiTestCase;

/**
 * What of an order's history a customer reads on their account page.
 *
 * The history is an internal journal — status changes, references allocated,
 * mails sent, the administrator who signed in to do it. A customer reads one
 * thing out of it: the notes a human deliberately addressed to them. Everything
 * else stays inside.
 */
final class AccountOrderNotesApiTest extends ApiTestCase
{
    private const ADDON = 'OrderCustomerNotesAddon';

    public function testAVisibleNoteIsReturnedWithItsDateAndText(): void
    {
        [$order, $customer] = $this->orderOfANewCustomer();

        $this->note($order, 'Your parcel leaves tomorrow morning.', visibleToCustomer: true);

        $notes = $this->notesOf($this->readOrderAs($customer, $order));

        self::assertCount(1, $notes);
        self::assertSame('Your parcel leaves tomorrow morning.', $notes[0]['comment']);
        self::assertNotEmpty($notes[0]['createdAt']);
    }

    public function testAnInternalNoteIsNeverReturned(): void
    {
        [$order, $customer] = $this->orderOfANewCustomer();

        $this->note($order, 'Buyer sounded unhappy on the phone, watch this one.', visibleToCustomer: false);

        self::assertSame([], $this->notesOf($this->readOrderAs($customer, $order)));
    }

    public function testTheTwoKindsOfNoteAreToldApartOnTheSameOrder(): void
    {
        [$order, $customer] = $this->orderOfANewCustomer();

        $this->note($order, 'Internal: refund approved by the manager.', visibleToCustomer: false);
        $this->note($order, 'Your refund is on its way.', visibleToCustomer: true);
        $this->note($order, 'Internal: cheque cashed.', visibleToCustomer: false);

        $notes = $this->notesOf($this->readOrderAs($customer, $order));

        self::assertCount(1, $notes);
        self::assertSame('Your refund is on its way.', $notes[0]['comment']);
    }

    /**
     * A status change is a history entry like a note is, and it is the one kind the
     * order already reports through its own status. Marking it visible — which a
     * module is free to do, the column is one column — must still not put it here:
     * the event type is half of the condition, not a detail of it.
     */
    public function testAnEntryThatIsNotANoteIsNeverReturned(): void
    {
        [$order, $customer] = $this->orderOfANewCustomer();

        $entry = new OrderHistory();
        $entry
            ->setOrderId($order->getId())
            ->setEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->setActorType(OrderHistoryActorType::SYSTEM->value)
            ->setPayload('{"from":"not_paid","to":"paid"}')
            ->setVisibleToCustomer(1)
            ->save($this->getPropelConnection());

        self::assertSame([], $this->notesOf($this->readOrderAs($customer, $order)));
    }

    /**
     * The notes travel on the order, so they are only as private as the order is.
     * The guard is the one the operation already carries — `/front/account` behind
     * ROLE_CUSTOMER, plus an ownership check on the order — and this is what proves
     * the addon did not open a way around it.
     */
    public function testTheOrderOfAnotherCustomerStaysOutOfReach(): void
    {
        [$order] = $this->orderOfANewCustomer();
        $this->note($order, 'Your parcel leaves tomorrow morning.', visibleToCustomer: true);

        $factory = $this->createFixtureFactory();
        $intruder = $factory->customer($factory->customerTitle(), ['password' => 'password']);

        $response = $this->jsonRequest(
            'GET',
            '/api/front/account/orders/'.$order->getId(),
            token: $this->authenticateAsCustomer($intruder),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * @return array{0: Order, 1: Customer}
     */
    private function orderOfANewCustomer(): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);

        return [$factory->order($customer, ['statusCode' => 'paid']), $customer];
    }

    private function note(Order $order, string $comment, bool $visibleToCustomer): void
    {
        $entry = new OrderHistory();
        $entry
            ->setOrderId($order->getId())
            ->setEventType(OrderHistoryEventType::NOTE->value)
            ->setActorType(OrderHistoryActorType::ADMIN->value)
            ->setActorLabel('shop-manager')
            ->setComment($comment)
            ->setVisibleToCustomer($visibleToCustomer ? 1 : 0)
            ->save($this->getPropelConnection());
    }

    /**
     * @return array<string, mixed>
     */
    private function readOrderAs(Customer $customer, Order $order): array
    {
        $response = $this->jsonRequest(
            'GET',
            '/api/front/account/orders/'.$order->getId(),
            token: $this->authenticateAsCustomer($customer),
        );

        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{createdAt: string, comment: string}>
     */
    private function notesOf(array $payload): array
    {
        self::assertArrayHasKey(
            self::ADDON,
            $payload,
            'The order payload must carry the notes section, even when the shop wrote no note.',
        );

        return $payload[self::ADDON]['customerNotes'];
    }

    /**
     * Guard on the constant above: the JSON key of an addon is the short name of its
     * class, so renaming the class renames the field a theme reads.
     */
    public function testTheAddonIsPublishedUnderItsClassShortName(): void
    {
        self::assertSame(self::ADDON, (new \ReflectionClass(OrderCustomerNotesAddon::class))->getShortName());
    }
}
