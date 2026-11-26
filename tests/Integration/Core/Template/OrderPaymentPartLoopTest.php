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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderStatus;
use Thelia\Test\IntegrationTestCase;

/**
 * The payment parts of an order, as the invoice reads them.
 */
final class OrderPaymentPartLoopTest extends IntegrationTestCase
{
    public function testThePartsOfAnOrderAreListedInSettlementOrderWithTheirModuleAndAmount(): void
    {
        $order = $this->order();
        $cheque = $this->moduleId('Cheque');
        $balance = $this->part($order, $cheque, '79.900000', OrderPaymentPartState::PENDING, true, 2);
        $instant = $this->part($order, $cheque, '20.000000', OrderPaymentPartState::SETTLED, false, 1);

        $rows = $this->rows(['order' => $order->getId()]);

        self::assertSame([(int) $instant->getId(), (int) $balance->getId()], array_map(static fn (array $row): int => (int) $row['ID'], $rows));
        self::assertSame('Cheque', $rows[0]['PAYMENT_MODULE_CODE']);
        self::assertSame($cheque, (int) $rows[0]['PAYMENT_MODULE_ID']);
        self::assertEqualsWithDelta(20.0, (float) $rows[0]['AMOUNT'], 0.0001);
        self::assertFalse($rows[0]['IS_PRIMARY']);
        self::assertSame(OrderPaymentPartState::SETTLED->value, $rows[0]['STATE']);
        self::assertTrue($rows[1]['IS_PRIMARY']);
        self::assertEqualsWithDelta(79.9, (float) $rows[1]['AMOUNT'], 0.0001);
    }

    public function testAReleasedPartIsLeftOutUnlessItsStateIsAskedFor(): void
    {
        $order = $this->order();
        $cheque = $this->moduleId('Cheque');
        $this->part($order, $cheque, '99.900000', OrderPaymentPartState::PENDING, true, 2);
        $released = $this->part($order, $cheque, '20.000000', OrderPaymentPartState::RELEASED, false, 1);

        self::assertCount(1, $this->rows(['order' => $order->getId()]));

        $rows = $this->rows(['order' => $order->getId(), 'state' => OrderPaymentPartState::RELEASED->value]);

        self::assertSame([(int) $released->getId()], array_map(static fn (array $row): int => (int) $row['ID'], $rows));
    }

    public function testAnOrderPaidWithOneModuleHasNoPart(): void
    {
        $paidByCheque = $this->order();
        $other = $this->order();
        $this->part($other, $this->moduleId('Cheque'), '10.000000', OrderPaymentPartState::SETTLED, true, 1);

        self::assertSame([], $this->rows(['order' => $paidByCheque->getId()]));
    }

    public function testAPartWhoseModuleWasDeletedKeepsTheTitleWrittenOnIt(): void
    {
        $order = $this->order();
        $this->part($order, null, '20.000000', OrderPaymentPartState::SETTLED, false, 1, 'Gift card');

        $rows = $this->rows(['order' => $order->getId()]);

        self::assertEmpty($rows[0]['PAYMENT_MODULE_CODE']);
        self::assertSame('Gift card', $rows[0]['PAYMENT_MODULE_DELETED_TITLE']);
    }

    private function order(): Order
    {
        return $this->createFixtureFactory()->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
    }

    private function moduleId(string $code): int
    {
        return (int) (ModuleQuery::create()->findOneByCode($code) ?? throw new \RuntimeException($code.' is not installed'))->getId();
    }

    private function part(Order $order, ?int $moduleId, string $amount, OrderPaymentPartState $state, bool $primary, int $position, ?string $deletedTitle = null): OrderPaymentPart
    {
        $part = (new OrderPaymentPart())
            ->setOrderId((int) $order->getId())
            ->setPaymentModuleId($moduleId)
            ->setPaymentModuleTitle($deletedTitle)
            ->setAmount($amount)
            ->setState($state->value)
            ->setIsPrimary($primary)
            ->setPosition($position);
        $part->save($this->getPropelConnection());

        return $part;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $arguments): array
    {
        $rows = [];
        foreach ($this->getService(LoopExecutor::class)->execute('order_payment_part', $arguments) as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
    }
}
