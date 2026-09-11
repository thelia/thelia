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

namespace Thelia\Api\Service;

use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Resource\OrderReturnStatus as OrderReturnStatusResource;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\RefundAmountCalculator;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\Customer;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturnReasonQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;

/**
 * Fills the server-owned fields of a return being created through the API: the
 * customer and the initial status never come from the request body, the
 * eligibility of every line is enforced, and the refundable amount and the
 * restock/reason snapshots are computed from the order.
 */
final readonly class OrderReturnHydrator
{
    public function __construct(
        private ReturnEligibilityChecker $eligibility,
        private RefundAmountCalculator $refundCalculator,
    ) {
    }

    /**
     * Meant to be called inside the transaction that writes the return: it
     * locks every order product line it checks, and a lock outside a
     * transaction is released as soon as it is taken.
     *
     * @throws ReturnNotAllowedException
     */
    public function hydrate(OrderReturnResource $data, Customer $customer, bool $byAdmin): void
    {
        $data->setCustomer((new CustomerResource())->setId($customer->getId()));
        $data->setCreatedByAdmin($byAdmin);

        $requestedStatusId = OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_REQUESTED);
        if (null !== $requestedStatusId) {
            $data->setOrderReturnStatus((new OrderReturnStatusResource())->setId($requestedStatusId));
        }

        $order = OrderQuery::create()->findPk($data->getOrder()->getId());

        if (null === $order) {
            throw new ReturnNotAllowedException('The order does not exist.');
        }

        // A customer-opened return goes through the full opening gate (feature
        // on, ownership, paid, window); the merchant-initiated path does not.
        if (!$byAdmin) {
            $this->eligibility->assertOrderReturnable($order, $customer);
        }

        $total = 0.0;

        // What the request itself has already claimed on each order product.
        // The eligibility gate reads what other returns hold from the database,
        // where the lines of this request are not written yet: without the
        // running total, a request splitting one ordered unit over ten lines
        // passes the gate ten times and returns ten.
        $claimedByOrderProduct = [];

        foreach ($data->getOrderReturnLines() as $line) {
            $orderProduct = OrderProductQuery::create()->findPk($line->getOrderProduct()->getId());

            if (null === $orderProduct) {
                throw new ReturnNotAllowedException('Unknown order product in a return line.');
            }

            $orderProductId = (int) $orderProduct->getId();
            $claimed = ($claimedByOrderProduct[$orderProductId] ?? 0.0) + $line->getQuantity();

            $this->eligibility->lockLine($orderProduct);
            $this->eligibility->assertReturnable($order, $customer, $orderProduct, $claimed);

            $claimedByOrderProduct[$orderProductId] = $claimed;

            $line->setProductSaleElementsId($orderProduct->getProductSaleElementsId());
            $lineRefund = $this->refundCalculator->lineRefundForProduct($orderProduct, $line->getQuantity());
            $line->setRefundAmount($lineRefund);
            $total += $lineRefund;
        }

        if (null !== $data->getOrderReturnReason()) {
            $reason = OrderReturnReasonQuery::create()->findPk($data->getOrderReturnReason()->getId());
            if (null !== $reason) {
                $locale = $order->getLang()?->getLocale();
                if (null !== $locale) {
                    $reason->setLocale($locale);
                }
                $data->setReasonTitle($reason->getTitle());
            }
        }

        if ($data->getIncludePostage()) {
            $this->eligibility->assertPostageNotAlreadyReturned($order);
            $total += (float) $order->getPostage() + (float) $order->getPostageTax();
        }

        $data->setRefundAmount(round($total, 2));
    }
}
