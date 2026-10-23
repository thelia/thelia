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
use Thelia\Tools\TerminalText;

// 3.3.0 brings the payment journal, which refunds read to know what an order collected.
// Orders paid before it have none: each paid, processing or sent order without a journal
// gets the capture it was paid with, its total under the reference the provider gave it.
// Refunded and cancelled orders are left alone: what they collected was given back, or
// never taken.
//
// The total is the one Order computes, with the rounding of the order. Orders that already
// have a line are skipped, so replaying this script changes nothing.

$pdo = $database->getConnection();

// A status is read for what it stands for, as Order::isPaid(false) reads it: a custom status
// declaring itself equivalent to paid, processing or sent (partially_shipped, a merchant's own)
// collected the money too. A protected status stands for itself.
$paidStatusIds = array_map(
    'intval',
    $pdo->query("SELECT `id` FROM `order_status`
        WHERE (CASE WHEN `protected_status` = 1 THEN `code` ELSE COALESCE(NULLIF(`equivalent_code`, ''), `code`) END) IN ('paid', 'processing', 'sent')")->fetchAll(PDO::FETCH_COLUMN),
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

        // Both columns hold 100 characters: the reference fits as it is. One carrying a line break
        // or a character that hides text is left out, as the journal refuses it from a module: the
        // line is written without it.
        $reference = trim((string) $order->getTransactionRef());
        $reference = $reference === '' || TerminalText::singleLine($reference) !== $reference ? null : $reference;

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
