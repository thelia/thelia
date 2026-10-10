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

use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderQuery;

// 3.3.0 brings the payment journal, which refunds read to know what an order collected.
// Orders paid before it have none: each paid, processing or sent order without a journal
// gets the capture it was paid with, its total under the reference the provider gave it.
// Refunded and cancelled orders are left alone: what they collected was given back, or
// never taken.
//
// The total is the one Order computes, with the rounding of the order. Orders that already
// have a line are skipped, so replaying this script changes nothing.

$pdo = $database->getConnection();

$paidStatusIds = array_map(
    'intval',
    $pdo->query("SELECT `id` FROM `order_status` WHERE `code` IN ('paid', 'processing', 'sent')")->fetchAll(PDO::FETCH_COLUMN),
);

if ($paidStatusIds === []) {
    return;
}

$lastOrderId = 0;

do {
    $orderIds = array_map('intval', $pdo->query(sprintf(
        'SELECT o.`id` FROM `order` o
          WHERE o.`status_id` IN (%s) AND o.`id` > %d
            AND NOT EXISTS (SELECT 1 FROM `order_payment_transaction` t WHERE t.`order_id` = o.`id`)
          ORDER BY o.`id` LIMIT 200',
        implode(', ', $paidStatusIds),
        $lastOrderId,
    ))->fetchAll(PDO::FETCH_COLUMN));

    foreach ($orderIds as $orderId) {
        $lastOrderId = $orderId;
        $order = OrderQuery::create()->findPk($orderId);

        if ($order === null) {
            continue;
        }

        // Both columns hold 100 characters: the reference fits as it is.
        $reference = trim((string) $order->getTransactionRef());
        $reference = $reference === '' ? null : $reference;

        (new OrderPaymentTransaction())
            ->setOrderId($orderId)
            ->setType(PaymentTransactionType::CAPTURE->value)
            ->setState(PaymentTransactionState::SUCCEEDED->value)
            ->setAmount((string) $order->getTotalAmount())
            ->setCurrencyId((int) $order->getCurrencyId())
            ->setPspReference($reference)
            ->setPaymentModuleId($order->getPaymentModuleId())
            ->setActorType(OrderHistoryActorType::SYSTEM->value)
            ->setActorLabel('Thelia 3.3.0 update')
            ->setCreatedAt($order->getInvoiceDate() ?? $order->getCreatedAt())
            ->save();
    }

    OrderTableMap::clearInstancePool();
} while ($orderIds !== []);
