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

namespace Thelia\Domain\Order\StatusAction\Effect;

use Thelia\Domain\Order\Exception\InvalidOrderStatusActionPayloadException;
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\Order\StatusAction\OrderStatusActionInterface;
use Thelia\Domain\Promotion\Coupon\Service\OrderCouponUsageManager;

/**
 * Gives back to their coupons the usages this order consumed.
 */
final readonly class ReleaseCouponsAction implements OrderStatusActionInterface
{
    public function __construct(
        private OrderCouponUsageManager $couponUsageManager,
    ) {
    }

    public static function getType(): string
    {
        return 'release_coupons';
    }

    public function describePayload(): array
    {
        return [];
    }

    public function normalizePayload(array $payload): array
    {
        if ([] !== $payload) {
            throw InvalidOrderStatusActionPayloadException::unexpectedFields(self::getType(), $payload, []);
        }

        return [];
    }

    public function execute(OrderStatusActionContext $context): void
    {
        $this->couponUsageManager->release($context->order);
    }
}
