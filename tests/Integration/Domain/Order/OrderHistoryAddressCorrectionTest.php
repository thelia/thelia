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

use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A correction of an order address is a gesture of its own: two corrections are two
 * lines, each with its author, and a form submitted again with nothing changed is not a
 * correction at all.
 */
final class OrderHistoryAddressCorrectionTest extends ActionIntegrationTestCase
{
    public function testTwoDifferentCorrectionsOfTheDeliveryAddressLeaveTwoLines(): void
    {
        $order = $this->factory->order();
        $orderAddress = $this->deliveryAddressOf($order);

        $this->correct($order, $orderAddress, 'FirstCorrection');
        $this->correct($order, $orderAddress, 'SecondCorrection');

        self::assertSame('SecondCorrection', OrderAddressQuery::create()->findPk($orderAddress->getId())?->getLastname());
        self::assertCount(2, $this->linesOf($order));
    }

    public function testTwoCorrectionsByTwoAdministratorsKeepBothAuthors(): void
    {
        $order = $this->factory->order();
        $orderAddress = $this->deliveryAddressOf($order);
        $firstAdmin = $this->factory->admin();
        $secondAdmin = $this->factory->admin();
        $securityContext = $this->getService(SecurityContext::class);

        $securityContext->setAdminUser($firstAdmin);
        $this->correct($order, $orderAddress, 'FirstCorrection');
        $securityContext->setAdminUser($secondAdmin);
        $this->correct($order, $orderAddress, 'SecondCorrection');

        $lines = $this->linesOf($order);
        self::assertCount(2, $lines);
        self::assertSame($firstAdmin->getLogin(), $lines[0]->getActorLabel());
        self::assertSame($secondAdmin->getLogin(), $lines[1]->getActorLabel());
    }

    public function testTheSameFormSubmittedTwiceLeavesASingleLine(): void
    {
        $order = $this->factory->order();
        $orderAddress = $this->deliveryAddressOf($order);

        $this->correct($order, $orderAddress, 'Corrected');
        $this->correct($order, $orderAddress, 'Corrected');

        self::assertCount(1, $this->linesOf($order));
    }

    public function testAFormSubmittedWithNothingChangedWritesNothing(): void
    {
        $order = $this->factory->order();
        $orderAddress = $this->deliveryAddressOf($order);

        $this->correct($order, $orderAddress, $orderAddress->getLastname());

        self::assertCount(0, $this->linesOf($order));
    }

    public function testAnEmptyPhoneSentForAColumnThatHoldsNullIsNotACorrection(): void
    {
        $order = $this->factory->order();
        $orderAddress = $this->deliveryAddressOf($order);
        $orderAddress->setPhone(null)->save($this->getPropelConnection());

        // The back-office form posts an empty string for a field left blank.
        $this->correct($order, $orderAddress, $orderAddress->getLastname(), phone: '');

        self::assertCount(0, $this->linesOf($order));
    }

    public function testAnAdminCorrectingAnAddressIsNamedByLogin(): void
    {
        $order = $this->factory->order();
        $admin = $this->factory->admin();
        $this->getService(SecurityContext::class)->setAdminUser($admin);

        $this->correct($order, $this->deliveryAddressOf($order), 'Corrected');

        $lines = $this->linesOf($order);
        self::assertCount(1, $lines);
        self::assertSame('admin', $lines[0]->getActorType());
        self::assertSame($admin->getLogin(), $lines[0]->getActorLabel());
    }

    public function testThePayloadStaysFreeOfPersonalData(): void
    {
        $order = $this->factory->order();
        $orderAddress = $this->deliveryAddressOf($order);

        $this->correct($order, $orderAddress, 'Corrected');

        self::assertSame(
            ['order_address_id' => $orderAddress->getId(), 'address_type' => 'delivery'],
            $this->linesOf($order)[0]->getDecodedPayload(),
        );
    }

    private function deliveryAddressOf(Order $order): OrderAddress
    {
        $orderAddress = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());
        self::assertNotNull($orderAddress);

        return $orderAddress;
    }

    private function correct(Order $order, OrderAddress $orderAddress, string $lastname, ?string $phone = null): void
    {
        $event = new OrderAddressEvent(
            $orderAddress->getCustomerTitleId(),
            $orderAddress->getFirstname(),
            $lastname,
            $orderAddress->getAddress1(),
            $orderAddress->getAddress2(),
            $orderAddress->getAddress3(),
            $orderAddress->getZipcode(),
            $orderAddress->getCity(),
            $orderAddress->getCountryId(),
            $phone ?? $orderAddress->getPhone(),
            $orderAddress->getCompany(),
            $orderAddress->getCellphone(),
            $orderAddress->getStateId(),
        );
        $event->setOrderAddress($orderAddress);
        $event->setOrder($order);

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_ADDRESS);
    }

    /**
     * @return list<OrderHistory>
     */
    private function linesOf(Order $order): array
    {
        return array_values(
            OrderHistoryQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByEventType(OrderHistoryEventType::ADDRESS_UPDATED->value)
                ->orderById()
                ->find()
                ->getData(),
        );
    }
}
