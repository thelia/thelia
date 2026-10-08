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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Resource\OrderPaymentSummary;
use Thelia\Domain\Payment\Service\PaymentCaptureService;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

final readonly class OrderPaymentSummaryProvider implements ProviderInterface
{
    public function __construct(
        private PaymentTransactionTotalsReader $totalsReader,
        private PaymentCaptureService $captureService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrderPaymentSummary
    {
        $order = $this->order($uriVariables);
        $totals = $this->totalsReader->forOrder((int) $order->getId());

        $summary = new OrderPaymentSummary();
        $summary->orderId = (int) $order->getId();
        $summary->transactionRef = $order->getTransactionRef();
        $summary->paymentModuleId = $order->getPaymentModuleId();
        $summary->paymentModuleCode = $this->paymentModuleCodeOf($order);
        $summary->authorized = $totals->authorized;
        $summary->captured = $totals->captured;
        $summary->voided = $totals->voided;
        $summary->refunded = $totals->refunded;
        $summary->remainingToCapture = $totals->remainingToCapture;
        $summary->supportsCapture = $this->captureService->supportsCapture($order);

        return $summary;
    }

    private function order(array $uriVariables): Order
    {
        $orderId = filter_var($uriVariables['orderId'] ?? null, \FILTER_VALIDATE_INT);
        $order = false === $orderId || $orderId < 1 ? null : OrderQuery::create()->findPk($orderId);

        if (!$order instanceof Order) {
            throw new NotFoundHttpException('No such order.');
        }

        return $order;
    }

    private function paymentModuleCodeOf(Order $order): ?string
    {
        try {
            return $order->getPaymentModuleInstance()->getCode();
        } catch (\Throwable) {
            return $order->getPaymentModuleTitle();
        }
    }
}
