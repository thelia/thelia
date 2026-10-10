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

namespace Thelia\Tests\Integration\Install;

use Propel\Runtime\Propel;
use Thelia\Core\Install\Database;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Orders paid before 3.3.0 have no payment journal: the update writes the capture they
 * were paid with, so what they collected can be refunded from Thelia.
 */
final class LegacyPaymentJournalUpdateTest extends ActionIntegrationTestCase
{
    public function testAPaidOrderWithoutJournalGetsTheCaptureItWasPaidWith(): void
    {
        $order = $this->order(OrderStatus::CODE_SENT, 'PSP-2024-118');

        $this->runTheUpdateStep();

        $lines = OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId());
        self::assertCount(1, $lines);
        self::assertSame(PaymentTransactionType::CAPTURE->value, $lines[0]->getType());
        self::assertSame(PaymentTransactionState::SUCCEEDED->value, $lines[0]->getState());
        self::assertSame('PSP-2024-118', $lines[0]->getPspReference());
        self::assertSame((int) $order->getPaymentModuleId(), (int) $lines[0]->getPaymentModuleId());
        self::assertSame((int) $order->getCurrencyId(), (int) $lines[0]->getCurrencyId());
        self::assertSame('system', $lines[0]->getActorType());
        self::assertEqualsWithDelta($order->getTotalAmount(), (float) $lines[0]->getAmount(), 0.000001);
        self::assertSame('84.000000', $this->getService(PaymentTransactionTotalsReader::class)->forOrder((int) $order->getId())->refundable());
    }

    public function testOrdersNotPaidOrAlreadyJournaledAreLeftAlone(): void
    {
        $unpaid = $this->order(OrderStatus::CODE_NOT_PAID);
        $cancelled = $this->order(OrderStatus::CODE_CANCELED);
        $refunded = $this->order(OrderStatus::CODE_REFUNDED);
        $journaled = $this->order(OrderStatus::CODE_PAID);
        $this->getService(PaymentTransactionRecorder::class)->recordCapture($journaled, 84, 'CAP-NEW', moduleCode: 'Cheque');

        $this->runTheUpdateStep();

        foreach ([$unpaid, $cancelled, $refunded] as $order) {
            self::assertCount(0, OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId()), (string) $order->getOrderStatus()->getCode());
        }

        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal((int) $journaled->getId()));
    }

    public function testRunningTheUpdateAgainWritesNothingMore(): void
    {
        $order = $this->order(OrderStatus::CODE_PAID);

        $this->runTheUpdateStep();
        $this->runTheUpdateStep();

        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId()));
    }

    public function testAnOrderPaidWithoutReferenceGetsALineWithoutOne(): void
    {
        $order = $this->order(OrderStatus::CODE_PAID, '  ');

        $this->runTheUpdateStep();

        $lines = OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId());
        self::assertCount(1, $lines);
        self::assertNull($lines[0]->getPspReference());
    }

    private function order(string $statusCode, ?string $transactionRef = null): Order
    {
        $order = $this->factory->order(null, ['postage' => 84, 'statusCode' => $statusCode]);
        $order->setTransactionRef($transactionRef)->save();

        return $order;
    }

    private function runTheUpdateStep(): void
    {
        // What Update::updateToVersion() hands the PHP scripts of setup/update/php.
        $database = new Database(Propel::getConnection(OrderTableMap::DATABASE_NAME));

        include \dirname(__DIR__, 3).'/setup/update/php/3.3.0.php';
    }
}
