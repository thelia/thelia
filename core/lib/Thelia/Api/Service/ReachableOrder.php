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

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

/**
 * The order an admin API path names, for the operations that hang under an order
 * (`/admin/orders/{orderId}/...`) and read it themselves. An order of a sale unit the
 * administrator is restricted away from is no order of theirs: it answers the same 404
 * as an order that does not exist, the way the order itself does.
 */
final readonly class ReachableOrder
{
    public function __construct(
        private AdminSaleUnitScope $adminSaleUnitScope,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     *
     * @throws NotFoundHttpException when the order does not exist or is out of reach
     */
    public function fromUriVariables(array $uriVariables, string $variable = 'orderId'): Order
    {
        $orderId = filter_var($uriVariables[$variable] ?? null, \FILTER_VALIDATE_INT);
        $order = false === $orderId || $orderId < 1 ? null : OrderQuery::create()->findPk($orderId);

        if (!$order instanceof Order || !$this->adminSaleUnitScope->allows($order->getSaleUnitId())) {
            throw new NotFoundHttpException('No such order.');
        }

        return $order;
    }
}
