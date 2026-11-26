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

use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Twig\OrderCustomerNoteExtension;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Test\IntegrationTestCase;
use Twig\TwigFunction;

/**
 * What a PDF template reads of an order's history: the notes the shop addressed to the customer, and
 * nothing else of the journal.
 */
final class OrderCustomerNoteExtensionTest extends IntegrationTestCase
{
    public function testTheFunctionIsDeclaredToTwig(): void
    {
        $names = array_map(static fn (TwigFunction $function): string => $function->getName(), (new OrderCustomerNoteExtension())->getFunctions());

        self::assertContains('order_customer_notes', $names);
    }

    public function testAVisibleNoteIsReturnedWithItsTextAndDate(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->entry($order, 'Parcel handed over in person.', OrderHistoryEventType::NOTE, visible: true);

        $notes = (new OrderCustomerNoteExtension())->orderCustomerNotes($order->getId());

        self::assertCount(1, $notes);
        self::assertSame('Parcel handed over in person.', $notes[0]['comment']);
        self::assertInstanceOf(\DateTimeInterface::class, $notes[0]['created_at']);
        self::assertSame(['comment', 'created_at'], array_keys($notes[0]));
    }

    public function testAnInternalNoteIsExcluded(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->entry($order, 'Internal: customer is difficult.', OrderHistoryEventType::NOTE, visible: false);

        self::assertSame([], (new OrderCustomerNoteExtension())->orderCustomerNotes($order->getId()));
    }

    public function testAnEntryThatIsNotANoteIsExcludedEvenWhenMarkedVisible(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->entry($order, 'Status moved to paid.', OrderHistoryEventType::STATUS_CHANGED, visible: true);

        self::assertSame([], (new OrderCustomerNoteExtension())->orderCustomerNotes($order->getId()));
    }

    public function testTheNotesKeepTheOrderTheyWereWrittenIn(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->entry($order, 'First.', OrderHistoryEventType::NOTE, visible: true);
        $this->entry($order, 'Hidden.', OrderHistoryEventType::NOTE, visible: false);
        $this->entry($order, 'Second.', OrderHistoryEventType::NOTE, visible: true);
        $this->entry($order, 'Third.', OrderHistoryEventType::NOTE, visible: true);

        $notes = (new OrderCustomerNoteExtension())->orderCustomerNotes((string) $order->getId());

        self::assertSame(['First.', 'Second.', 'Third.'], array_column($notes, 'comment'));
    }

    public function testTheNotesOfAnotherOrderAreExcluded(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();
        $other = $factory->order();
        $this->entry($order, 'For this order.', OrderHistoryEventType::NOTE, visible: true);
        $this->entry($other, 'For the other order.', OrderHistoryEventType::NOTE, visible: true);

        $notes = (new OrderCustomerNoteExtension())->orderCustomerNotes($order->getId());

        self::assertSame(['For this order.'], array_column($notes, 'comment'));
    }

    public function testNoOrderIdGivesNoNotes(): void
    {
        $extension = new OrderCustomerNoteExtension();

        self::assertSame([], $extension->orderCustomerNotes(null));
        self::assertSame([], $extension->orderCustomerNotes(''));
    }

    private function entry(Order $order, string $comment, OrderHistoryEventType $type, bool $visible): void
    {
        (new OrderHistory())
            ->setOrderId($order->getId())
            ->setEventType($type->value)
            ->setActorType(OrderHistoryActorType::ADMIN->value)
            ->setActorLabel('shop-manager')
            ->setComment($comment)
            ->setVisibleToCustomer($visible ? 1 : 0)
            ->save($this->getPropelConnection());
    }
}
