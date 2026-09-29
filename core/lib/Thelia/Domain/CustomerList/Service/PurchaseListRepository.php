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

namespace Thelia\Domain\CustomerList\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\Enum\CustomerListType;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListItemQuery;
use Thelia\Model\CustomerListQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The reads behind the purchase lists. Visibility is applied by the access
 * policy, never written here.
 */
final readonly class PurchaseListRepository
{
    public function __construct(
        private PurchaseListAccessPolicy $accessPolicy,
    ) {
    }

    /**
     * @return list<CustomerList>
     */
    public function findReadableBy(Customer $customer): array
    {
        return array_values(
            $this->readableQuery($customer)
                ->orderByUpdatedAt(Criteria::DESC)
                ->orderById(Criteria::DESC)
                ->find()
                ->getData(),
        );
    }

    public function findOneReadableBy(Customer $customer, int $listId): ?CustomerList
    {
        return $this->readableQuery($customer)->filterById($listId)->findOne();
    }

    public function countOwnedBy(Customer $customer): int
    {
        return CustomerListQuery::create()
            ->filterByCustomerId((int) $customer->getId())
            ->filterByType(CustomerListType::Purchase->value)
            ->count();
    }

    public function linesOf(CustomerList $list): ReferenceQuantityLines
    {
        $items = CustomerListItemQuery::create()
            ->filterByCustomerListId((int) $list->getId())
            ->orderByPosition()
            ->orderById()
            ->find();

        $lines = [];

        foreach ($items as $item) {
            $lines[] = new ReferenceQuantity(
                (string) $item->getRef(),
                (int) $item->getQuantity(),
                null !== $item->getProductSaleElementsId() ? (int) $item->getProductSaleElementsId() : null,
            );
        }

        return new ReferenceQuantityLines($lines);
    }

    /**
     * The lines of a cart, the reference being the one of its sale element.
     */
    public function linesOfCart(Cart $cart): ReferenceQuantityLines
    {
        $items = CartItemQuery::create()
            ->filterByCartId((int) $cart->getId())
            ->joinWithProductSaleElements()
            ->orderById()
            ->find();

        $lines = [];

        foreach ($items as $item) {
            $saleElements = $item->getProductSaleElements();
            $lines[] = new ReferenceQuantity(
                (string) $saleElements->getRef(),
                self::wholeQuantity($item->getQuantity()),
                (int) $saleElements->getId(),
            );
        }

        return new ReferenceQuantityLines($lines);
    }

    /**
     * The lines of an order of this customer, read from what the order kept
     * rather than from the catalog: a sale element deleted since is dropped
     * from the line, its reference stays. Null when the order is not one of
     * the customer's.
     */
    public function linesOfOrder(Customer $customer, int $orderId): ?ReferenceQuantityLines
    {
        $order = OrderQuery::create()
            ->filterById($orderId)
            ->filterByCustomerId((int) $customer->getId())
            ->findOne();

        if (null === $order) {
            return null;
        }

        $orderProducts = OrderProductQuery::create()
            ->filterByOrderId((int) $order->getId())
            ->orderById()
            ->find();

        $saleElementsIds = [];

        foreach ($orderProducts as $orderProduct) {
            $saleElementsIds[] = (int) $orderProduct->getProductSaleElementsId();
        }

        $existing = array_flip(array_map(
            'intval',
            ProductSaleElementsQuery::create()
                ->filterById(array_unique($saleElementsIds))
                ->select(['Id'])
                ->find()
                ->toArray(),
        ));

        $lines = [];

        foreach ($orderProducts as $orderProduct) {
            $saleElementsId = (int) $orderProduct->getProductSaleElementsId();
            $reference = (string) ($orderProduct->getProductSaleElementsRef() ?: $orderProduct->getProductRef());

            $lines[] = new ReferenceQuantity(
                $reference,
                self::wholeQuantity($orderProduct->getQuantity()),
                isset($existing[$saleElementsId]) ? $saleElementsId : null,
            );
        }

        return new ReferenceQuantityLines($lines);
    }

    private function readableQuery(Customer $customer): CustomerListQuery
    {
        return $this->accessPolicy->restrictToReadable(
            CustomerListQuery::create()->filterByType(CustomerListType::Purchase->value),
            $customer,
        );
    }

    /**
     * Cart and order quantities are stored as floats; a list keeps whole units.
     */
    private static function wholeQuantity(float|int|string|null $quantity): int
    {
        return max(1, (int) round((float) $quantity));
    }
}
