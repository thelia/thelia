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

namespace Thelia\Domain\Order\StatusAction;

use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;

/**
 * The fixed rule of the core reads the stock of an order from its statuses: a paid order
 * has its products out of stock, a cancelled one has them back. A stock action that moved
 * the stock otherwise breaks that reading, and the rule would then put back a stock never
 * taken, or take out a stock already taken.
 *
 * The status lines of the order history keep what the rule would have done each time the
 * stock moved otherwise (`rule_stock`). Summed, these departures tell how far the real
 * stock of the order is from what its statuses suggest, so the rule can be held back.
 */
final readonly class StockRuleDeparture
{
    /**
     * The quantity of the order taken out of stock beyond what its statuses suggest, in
     * times the order: -1 when the rule thinks it out but it never left, 1 the other way.
     */
    public function of(Order $order): int
    {
        if (null === $order->getId()) {
            return 0;
        }

        $departure = 0;
        $lines = OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->find();

        foreach ($lines as $line) {
            $payload = $line->getDecodedPayload();

            if (!isset($payload['rule_stock'], $payload['stock'])) {
                continue;
            }

            $departure += self::takenOut((string) $payload['stock']) - self::takenOut((string) $payload['rule_stock']);
        }

        return $departure;
    }

    /**
     * Whether the operation the rule chose would move a stock that the departures of the
     * order already moved: a put back of a stock that never left, or a second take out.
     */
    public function holdsBack(Order $order, int $ruleOperation): bool
    {
        return match ($ruleOperation) {
            // The rule puts back what it thinks out of stock: the whole order.
            GetStockUpdateOperationOnOrderStatusChangeEvent::INCREASE_STOCK => 1 + $this->of($order) <= 0,
            // The rule takes out what it thinks still in stock: nothing of the order is out.
            GetStockUpdateOperationOnOrderStatusChangeEvent::DECREASE_STOCK => $this->of($order) >= 1,
            default => false,
        };
    }

    private static function takenOut(string $operationName): int
    {
        return match ($operationName) {
            'decrease' => 1,
            'increase' => -1,
            default => 0,
        };
    }
}
