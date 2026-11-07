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

namespace Thelia\Domain\Accounting;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\OrderPostageTaxQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;

/**
 * The amounts an order froze when it was placed: the unit price and taxes of each line, at
 * the promotional price when the line was in promotion, and the shares of its postage by rule.
 * Read as plain rows, a batch of orders in three queries, without models to keep in memory.
 */
final readonly class FrozenOrderAmounts
{
    /**
     * @param list<array{quantity: float, unitPrice: float, unitTaxes: list<float>}> $lines
     * @param list<array{untaxed: float, tax: float}>                                $postageShares
     */
    public function __construct(
        public array $lines,
        public array $postageShares,
    ) {
    }

    /**
     * @param list<int> $orderIds
     *
     * @return array<int, self> by order id, an order without line having no lines
     */
    public static function ofOrders(array $orderIds): array
    {
        if ([] === $orderIds) {
            return [];
        }

        $lineRows = OrderProductQuery::create()
            ->filterByOrderId($orderIds, Criteria::IN)
            ->orderById()
            ->select(['Id', 'OrderId', 'Quantity', 'Price', 'PromoPrice', 'WasInPromo'])
            ->find()
            ->getData();

        $taxRows = [] === $lineRows ? [] : OrderProductTaxQuery::create()
            ->filterByOrderProductId(array_map(static fn (array $row): int => (int) $row['Id'], $lineRows), Criteria::IN)
            ->orderById()
            ->select(['OrderProductId', 'Amount', 'PromoAmount'])
            ->find()
            ->getData();

        $taxesByLine = [];

        foreach ($taxRows as $row) {
            $taxesByLine[(int) $row['OrderProductId']][] = $row;
        }

        $lines = array_fill_keys($orderIds, []);

        foreach ($lineRows as $row) {
            $promo = 1 === (int) $row['WasInPromo'];
            $lines[(int) $row['OrderId']][] = [
                'quantity' => (float) $row['Quantity'],
                'unitPrice' => (float) ($promo ? $row['PromoPrice'] : $row['Price']),
                'unitTaxes' => array_map(
                    static fn (array $tax): float => (float) ($promo ? $tax['PromoAmount'] : $tax['Amount']),
                    $taxesByLine[(int) $row['Id']] ?? [],
                ),
            ];
        }

        $postageShares = array_fill_keys($orderIds, []);

        foreach (OrderPostageTaxQuery::create()->filterByOrderId($orderIds, Criteria::IN)->orderById()->select(['OrderId', 'UntaxedAmount', 'Amount'])->find()->getData() as $row) {
            $postageShares[(int) $row['OrderId']][] = ['untaxed' => (float) $row['UntaxedAmount'], 'tax' => (float) $row['Amount']];
        }

        $amounts = [];

        foreach ($orderIds as $orderId) {
            $amounts[$orderId] = new self($lines[$orderId], $postageShares[$orderId]);
        }

        return $amounts;
    }
}
