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

use Thelia\Api\Resource\PurchaseList;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;

/**
 * A purchase list as the account API shows it to a given customer: whether that
 * customer may change it is part of the answer, and asked of the facade.
 */
final readonly class PurchaseListPresenter
{
    public function __construct(
        private PurchaseListFacade $purchaseListFacade,
    ) {
    }

    /**
     * Without their lines, and with every line count read in one query.
     *
     * @param list<CustomerList> $lists
     *
     * @return list<PurchaseList>
     */
    public function presentAll(Customer $customer, array $lists): array
    {
        $counts = $this->purchaseListFacade->countItemsOf($lists);

        return array_map(
            fn (CustomerList $list): PurchaseList => $this->resource($customer, $list, $counts[(int) $list->getId()] ?? 0),
            $lists,
        );
    }

    public function present(Customer $customer, CustomerList $list): PurchaseList
    {
        $items = [];

        foreach ($this->purchaseListFacade->linesToLoad($customer, (int) $list->getId()) as $line) {
            $items[] = [
                'reference' => $line->reference,
                'quantity' => $line->quantity,
                'productSaleElementsId' => $line->productSaleElementsId,
            ];
        }

        $resource = $this->resource($customer, $list, \count($items));
        $resource->items = $items;

        return $resource;
    }

    private function resource(Customer $customer, CustomerList $list, int $itemCount): PurchaseList
    {
        $resource = new PurchaseList();
        $resource->id = (int) $list->getId();
        $resource->title = (string) $list->getTitle();
        $resource->shared = (bool) $list->getShared();
        $resource->canWrite = $this->purchaseListFacade->canWrite($customer, $list);
        $resource->itemCount = $itemCount;
        $resource->createdAt = self::date($list->getCreatedAt());
        $resource->updatedAt = self::date($list->getUpdatedAt());

        return $resource;
    }

    private static function date(mixed $value): ?\DateTimeInterface
    {
        return $value instanceof \DateTimeInterface ? $value : null;
    }
}
