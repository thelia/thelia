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

namespace Thelia\Domain\Order\Service;

use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;

final readonly class EditableOrderStatuses
{
    public const CONFIG_NAME = 'order_editable_status_codes';

    private const DEFAULT_CODES = [
        OrderStatus::CODE_NOT_PAID,
        OrderStatus::CODE_PAID,
        OrderStatus::CODE_PROCESSING,
    ];

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        $configured = ConfigQuery::read(self::CONFIG_NAME);

        if (null === $configured) {
            return self::DEFAULT_CODES;
        }

        return array_values(array_filter(array_map('trim', explode(',', $configured)), static fn (string $code): bool => '' !== $code));
    }

    public function allowsEditing(Order $order): bool
    {
        $code = $order->getOrderStatus()?->getCode();

        return null !== $code && \in_array($code, $this->codes(), true);
    }
}
