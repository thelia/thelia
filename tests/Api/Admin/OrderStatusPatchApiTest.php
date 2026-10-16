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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ApiTestCase;

/**
 * The status of an order written through `PATCH /api/admin/orders/{id}`, over real HTTP.
 *
 * The processor tells what the client asked for by comparing the status of the resource
 * with the one of the previous data API Platform keeps, a shallow clone of what the
 * provider read: were the two to share the status object, a deserialization that changes
 * it in place would make the change look like no change, and the status would be ignored
 * without an error.
 */
final class OrderStatusPatchApiTest extends ApiTestCase
{
    /** @var list<int> the status ids carried by the ORDER_UPDATE_STATUS events dispatched */
    private array $statusEvents = [];

    public function testAPatchThatChangesTheStatusMovesItThroughTheEvent(): void
    {
        $order = $this->orderWithItsLines();
        $this->listenToTheStatusEvents();
        $cancelled = $this->statusId(OrderStatus::CODE_CANCELED);

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'orderStatus' => '/api/admin/order_statutes/'.$cancelled,
            'orderProducts' => $this->lineIris($order),
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([$cancelled], $this->statusEvents, 'One status event, for the status asked.');
        self::assertSame($cancelled, $this->storedStatusId($order), 'The status is written.');
    }

    public function testAPatchThatDoesNotSpeakOfTheStatusLeavesItAloneAndRaisesNoEvent(): void
    {
        $order = $this->orderWithItsLines();
        $before = $this->storedStatusId($order);
        $this->listenToTheStatusEvents();

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'deliveryRef' => 'TRACK-42',
            'orderProducts' => $this->lineIris($order),
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([], $this->statusEvents, 'No status event for a status nobody asked for.');
        self::assertSame($before, $this->storedStatusId($order), 'The status is left as it was.');
        self::assertSame('TRACK-42', OrderQuery::create()->findPk($order->getId(), $this->getPropelConnection())?->getDeliveryRef());
    }

    private function orderWithItsLines(): Order
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();
        $line = $factory->orderProduct($order);
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('2')->save($this->getPropelConnection());

        return $order;
    }

    /**
     * @return list<string>
     */
    private function lineIris(Order $order): array
    {
        $ids = [];

        foreach ($order->getOrderProducts() as $line) {
            $ids[] = '/api/admin/order_products/'.$line->getId();
        }

        self::assertNotSame([], $ids);

        return $ids;
    }

    private function listenToTheStatusEvents(): void
    {
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::ORDER_UPDATE_STATUS, function (OrderEvent $event): void {
            $this->statusEvents[] = (int) $event->getStatus();
        }, 200);
    }

    private function statusId(string $code): int
    {
        return (int) (OrderStatusQuery::create()->findOneByCode($code, $this->getPropelConnection())?->getId() ?? throw new \RuntimeException('No status '.$code));
    }

    private function storedStatusId(Order $order): int
    {
        $statement = $this->getPropelConnection()->prepare('SELECT `status_id` FROM `order` WHERE `id` = ?');
        $statement->execute([(int) $order->getId()]);

        return (int) $statement->fetchColumn();
    }
}
