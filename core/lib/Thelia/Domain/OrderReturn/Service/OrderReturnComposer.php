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

namespace Thelia\Domain\OrderReturn\Service;

use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturnReason;
use Thelia\Model\OrderReturnReasonI18nQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;

/**
 * The rules that decide what a return being opened is worth and whether it may
 * be opened at all, with no opinion on who writes the row.
 *
 * There are two ways into a return - the API, which hydrates a resource the
 * bridge then persists, and the ORDER_RETURN_CREATE event, which writes the
 * Propel model - and they must not each hold their own copy of the rules. What
 * they share is here; what differs is only where the computed values are set.
 *
 * Meant to be called inside the transaction that writes the return: it locks
 * every order product line it checks, and a lock outside a transaction is
 * released as soon as it is taken.
 */
final readonly class OrderReturnComposer
{
    public function __construct(
        private ReturnEligibilityChecker $eligibility,
        private RefundAmountCalculator $refundCalculator,
    ) {
    }

    /**
     * The status a return is opened in.
     */
    public function initialStatusId(): ?int
    {
        return OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_REQUESTED);
    }

    /**
     * Assert every requested line and return what each is worth, in the order
     * they were given.
     *
     * The eligibility gate reads what other returns hold from the database,
     * where the lines of the request being checked are not written yet: without
     * the running total, one ordered unit split over ten lines passes the gate
     * ten times and comes back ten times.
     *
     * @param list<array{order_product: OrderProduct, quantity: float}> $lines
     *
     * @return list<float>
     *
     * @throws ReturnNotAllowedException
     */
    public function priceRequestedLines(Order $order, Customer $customer, array $lines): array
    {
        $claimedByOrderProduct = [];
        $refunds = [];

        foreach ($lines as $line) {
            $orderProduct = $line['order_product'];
            $orderProductId = (int) $orderProduct->getId();
            $claimed = ($claimedByOrderProduct[$orderProductId] ?? 0.0) + $line['quantity'];

            $this->eligibility->lockLine($orderProduct);
            $this->eligibility->assertReturnable($order, $customer, $orderProduct, $claimed);

            $claimedByOrderProduct[$orderProductId] = $claimed;
            $refunds[] = $this->refundCalculator->lineRefundForProduct($orderProduct, $line['quantity']);
        }

        return $refunds;
    }

    /**
     * What carrying the postage back adds to the refund, once it is established
     * no other open return already carries it.
     *
     * @throws ReturnNotAllowedException
     */
    public function postageRefund(Order $order): float
    {
        $this->eligibility->assertPostageNotAlreadyReturned($order);

        return (float) $order->getPostage() + (float) $order->getPostageTax();
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
    public function reasonTitleFor(OrderReturnReason $reason, Order $order): ?string
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
