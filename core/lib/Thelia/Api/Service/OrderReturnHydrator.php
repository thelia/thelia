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
use Thelia\Domain\OrderReturn\Service\OrderReturnComposer;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\Customer;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturn as OrderReturnModel;
use Thelia\Model\OrderReturnReasonQuery;

/**
 * Fills the server-owned fields of a return being created through the API: the
 * customer and the initial status never come from the request body, the
 * eligibility of every line is enforced, and the refundable amount and the
 * restock/reason snapshots are computed from the order.
 *
 * The rules themselves live in {@see OrderReturnComposer}, shared with the
 * ORDER_RETURN_CREATE listener; this class only carries their results onto the
 * API resource the bridge is about to persist.
 */
final readonly class OrderReturnHydrator
{
    /**
     * Not the words of the denormalizer's refusal: this one is only reached by
     * a line that got past it (or never went through it, on the administration),
     * so no request that works as intended can tell the two apart.
     */
    public const EXISTING_LINE_MESSAGE = 'A return line must be a new line: this one already exists.';

    public function __construct(
        private ReturnEligibilityChecker $eligibility,
        private OrderReturnComposer $composer,
    ) {
    }

    /**
     * A return is opened with new lines only. FrontOrderReturnRelationDenormalizer
     * refuses a line that names an existing one before reading it on the front;
     * a line that reaches a processor loaded all the same would be moved into
     * the return being opened when it is saved. Nothing is written.
     *
     * @throws ReturnNotAllowedException
     */
    public static function assertNewLines(OrderReturnResource $data): void
    {
        foreach ($data->getOrderReturnLines() as $line) {
            if (null !== $line->getId() || null !== $line->getPropelModel()) {
                throw new ReturnNotAllowedException(self::EXISTING_LINE_MESSAGE);
            }
        }
    }

    /**
     * The order and the order product lines a return resource is about, read
     * from the request alone: OrderReturnWriteTransaction locks them before the
     * transaction reads anything, so they cannot come from the database.
     *
     * @return array{int, list<int>}
     *
     * @throws ReturnNotAllowedException
     */
    public static function rowsToLock(OrderReturnResource $data): array
    {
        // The NotBlank constraints of the write groups do not see a missing
        // field: the operations carry no validationContext, so the validation
        // runs in the Default group while the constraints are declared in the
        // serialization groups, and orderReturnLines has no #[Valid] either.
        // A missing field is refused here, where reading the uninitialized typed
        // property would be an Error, a 500.
        if (!isset($data->order)) {
            throw new ReturnNotAllowedException('A return must name the order it is about.');
        }

        $orderProductIds = [];

        foreach ($data->getOrderReturnLines() as $line) {
            if (!isset($line->orderProduct)) {
                throw new ReturnNotAllowedException('A return line must name the order product it is about.');
            }

            if (!isset($line->quantity)) {
                throw new ReturnNotAllowedException('A return line must name the quantity to return.');
            }

            $orderProductIds[] = (int) $line->getOrderProduct()->getId();
        }

        return [(int) $data->getOrder()->getId(), $orderProductIds];
    }

    /**
     * Meant to be called inside the transaction that writes the return, opened
     * by OrderReturnWriteTransaction::run() on the rows rowsToLock() names: the
     * order and its lines are then locked before anything here reads them.
     *
     * @throws ReturnNotAllowedException
     */
    public function hydrate(OrderReturnResource $data, Customer $customer, bool $byAdmin): void
    {
        $data->setCustomer((new CustomerResource())->setId($customer->getId()));
        $data->setCreatedByAdmin($byAdmin);
        $data->setExpectedExchange($this->expectedExchange($data));

        $requestedStatusId = $this->composer->initialStatusId();
        if (null !== $requestedStatusId) {
            $data->setOrderReturnStatus((new OrderReturnStatusResource())->setId($requestedStatusId));
        }

        $order = OrderQuery::create()->findPk($data->getOrder()->getId());

        if (null === $order) {
            // The front answers a missing order as a foreign one, see
            // OrderReturnFrontCreateProcessor; the admin path names a real
            // order, so a missing one stays a precise data error.
            throw new ReturnNotAllowedException($byAdmin ? 'The order does not exist.' : ReturnEligibilityChecker::ORDER_NOT_USABLE_MESSAGE);
        }

        // A customer-opened return goes through the full opening gate (feature
        // on, ownership, paid, window); the merchant-initiated path does not.
        if (!$byAdmin) {
            $this->eligibility->assertOrderReturnable($order, $customer);
        }

        $resourceLines = [];
        $requested = [];

        foreach ($data->getOrderReturnLines() as $line) {
            $orderProduct = OrderProductQuery::create()->findPk($line->getOrderProduct()->getId());

            if (null === $orderProduct) {
                throw new ReturnNotAllowedException($byAdmin ? 'Unknown order product in a return line.' : ReturnEligibilityChecker::ORDER_PRODUCT_NOT_USABLE_MESSAGE);
            }

            $resourceLines[] = [$line, $orderProduct];
            $requested[] = ['order_product' => $orderProduct, 'quantity' => $line->getQuantity()];
        }

        $refunds = $this->composer->priceRequestedLines($order, $customer, $requested);

        $total = 0.0;

        foreach ($resourceLines as $index => [$line, $orderProduct]) {
            $line->setProductSaleElementsId($orderProduct->getProductSaleElementsId());
            $line->setRefundAmount($refunds[$index]);
            $total += $refunds[$index];
        }

        if (null !== $data->getOrderReturnReason()) {
            $reason = OrderReturnReasonQuery::create()->findPk($data->getOrderReturnReason()->getId());
            if (null !== $reason) {
                $data->setReasonTitle($this->composer->reasonTitleFor($reason, $order));
            }
        }

        if ($data->getIncludePostage()) {
            $total += $this->composer->postageRefund($order);
        }

        $data->setRefundAmount(round($total, 2));
    }

    /**
     * The wish of the customer, trimmed, kept on an exchange only: under a refund or a
     * credit note it would be shown as something the shop is asked to send.
     *
     * @throws ReturnNotAllowedException when it is longer than a return keeps
     */
    private function expectedExchange(OrderReturnResource $data): ?string
    {
        $wish = trim((string) $data->getExpectedExchange());

        if ('' === $wish || OrderReturnModel::RESOLUTION_EXCHANGE !== $data->getExpectedResolution()) {
            return null;
        }

        if (mb_strlen($wish) > OrderReturnResource::MAX_EXPECTED_EXCHANGE_LENGTH) {
            throw new ReturnNotAllowedException('The wished exchange is too long.');
        }

        return $wish;
    }
}
