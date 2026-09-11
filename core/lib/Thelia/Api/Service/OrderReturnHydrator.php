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
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturnReason;
use Thelia\Model\OrderReturnReasonI18nQuery;
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
                $data->setReasonTitle($this->reasonTitleFor($reason, $order));
            }
        }

        if ($data->getIncludePostage()) {
            $this->eligibility->assertPostageNotAlreadyReturned($order);
            $total += (float) $order->getPostage() + (float) $order->getPostageTax();
        }

        $data->setRefundAmount(round($total, 2));
    }

    /**
     * The wording of the reason as it is kept on the return.
     *
     * `reason_id` is set to NULL when the merchant deletes the reason, so this
     * snapshot is the only place the customer's answer survives: an empty one
     * loses it for good. A merchant who adds a reason without translating it
     * into every language of the shop must not produce returns with no reason,
     * so the wording is looked up in the language of the order, then in the
     * default language of the shop, then in whichever language the reason does
     * have - anything rather than nothing.
     */
    private function reasonTitleFor(OrderReturnReason $reason, Order $order): ?string
    {
        $locales = [$order->getLang()?->getLocale()];

        try {
            $locales[] = Lang::getDefaultLanguage()->getLocale();
        } catch (\RuntimeException) {
            // A shop with no default language is a broken install, not a reason
            // to lose the wording: the last fallback below still answers.
        }

        foreach (array_filter($locales) as $locale) {
            $title = $reason->setLocale($locale)->getTitle();

            if (null !== $title && '' !== trim($title)) {
                return $title;
            }
        }

        foreach (OrderReturnReasonI18nQuery::create()->filterById($reason->getId())->orderByLocale()->find() as $translation) {
            $title = $translation->getTitle();

            if (null !== $title && '' !== trim($title)) {
                return $title;
            }
        }

        return null;
    }
}
