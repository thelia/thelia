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

namespace Thelia\Tests\Support\Order;

use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\Order\StatusAction\OrderStatusActionInterface;

/**
 * An action a module could ship, failing the way a transport does: with a secret in the message.
 */
final class ExplodingAction implements OrderStatusActionInterface
{
    public const LEAKED_DSN = 'smtp://user:secret@mail';

    public static function getType(): string
    {
        return 'exploding_test_action';
    }

    public function describePayload(): array
    {
        return [];
    }

    public function normalizePayload(array $payload): array
    {
        return [];
    }

    public function execute(OrderStatusActionContext $context): void
    {
        throw new \RuntimeException('Connection to '.self::LEAKED_DSN.' failed');
    }
}
