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

namespace Thelia\Tests\Integration\BackOffice;

use BackOfficeDefaultTwigBundle\DTO\Dashboard\DateRange;
use BackOfficeDefaultTwigBundle\Repository\OrderRepository;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Test\IntegrationTestCase;

/**
 * The three order figures of the admin dashboard, against the statuses a shop actually
 * runs. A shop adds statuses of its own and declares which native one each of them
 * stands for, so every one of these figures has to read the code a status answers for
 * and never the code it is named by.
 *
 * The dashboard belongs to the default-twig theme, which ships as its own package on
 * its own release cycle: a theme older than these figures is reported as skipped.
 */
final class DashboardOrderStatisticsTest extends IntegrationTestCase
{
    private OrderRepository $orders;

    private DateRange $range;

    protected function setUp(): void
    {
        parent::setUp();

        if (!method_exists(OrderRepository::class, 'unpaidStatusIds')) {
            self::markTestSkipped('The installed back-office theme predates these dashboard figures.');
        }

        $this->orders = new OrderRepository();
        $this->range = DateRange::fromPreset(DateRange::PRESET_THIRTY_DAYS);
    }

    public function testRevenueLeavesOutTheOrdersTheShopWasNotPaidFor(): void
    {
        $baseline = $this->orders->getRevenue($this->range);

        $this->orderWorth(OrderStatus::CODE_PAID, 100);
        self::assertEqualsWithDelta($baseline + 100.0, $this->orders->getRevenue($this->range), 0.001);

        $this->orderWorth(OrderStatus::CODE_CANCELED, 500);
        $this->orderWorth(OrderStatus::CODE_REFUNDED, 700);
        $this->orderWorth(OrderStatus::CODE_NOT_PAID, 900);

        self::assertEqualsWithDelta(
            $baseline + 100.0,
            $this->orders->getRevenue($this->range),
            0.001,
            'A cancelled, a refunded and an unpaid order are not revenue.',
        );
    }

    public function testRevenueCountsAnOrderInAStatusOfTheShopThatStandsForPaid(): void
    {
        $baseline = $this->orders->getRevenue($this->range);

        $status = $this->createFixtureFactory()->orderStatus([
            'code' => 'handed-to-the-courier',
            'equivalentCode' => OrderStatus::CODE_SENT,
        ]);
        $this->orderWorth((string) $status->getCode(), 250);

        self::assertEqualsWithDelta($baseline + 250.0, $this->orders->getRevenue($this->range), 0.001);
    }

    public function testTheUnpaidAlertCountsAnOrderInAStatusOfTheShopThatStandsForNotPaid(): void
    {
        $baseline = $this->orders->countUnpaidOlderThan(48);

        $status = $this->createFixtureFactory()->orderStatus([
            'code' => 'waiting-for-the-cheque',
            'equivalentCode' => OrderStatus::CODE_NOT_PAID,
        ]);
        $order = $this->orderWorth((string) $status->getCode(), 60);
        $order->setCreatedAt(new \DateTime('-72 hours'))->save($this->getPropelConnection());

        self::assertSame($baseline + 1, $this->orders->countUnpaidOlderThan(48));
    }

    public function testTheShipmentAlertCountsAnOrderInAStatusOfTheShopThatStandsForProcessing(): void
    {
        $baseline = $this->orders->countAwaitingShipment();

        $status = $this->createFixtureFactory()->orderStatus([
            'code' => 'being-packed',
            'equivalentCode' => OrderStatus::CODE_PROCESSING,
        ]);
        $this->orderWorth((string) $status->getCode(), 40);

        self::assertSame($baseline + 1, $this->orders->countAwaitingShipment());
    }

    /**
     * An order with no line and the whole of its total in postage: the amount the
     * dashboard sums up is the total of the order, and postage is part of it.
     */
    private function orderWorth(string $statusCode, int $amount): Order
    {
        return $this->createFixtureFactory()->order(null, [
            'statusCode' => $statusCode,
            'postage' => (string) $amount,
        ]);
    }
}
