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

namespace Thelia\Model;

use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Model\Base\OrderHistory as BaseOrderHistory;

class OrderHistory extends BaseOrderHistory
{
    public function isVisibleToCustomer(): bool
    {
        return 1 === $this->getVisibleToCustomer();
    }

    /**
     * The stored payload, as the array it was written from.
     *
     * A row written by a module may hold anything, so an unreadable payload reads as
     * an empty one rather than breaking whoever displays the history.
     *
     * @return array<string, mixed>
     */
    public function getDecodedPayload(): array
    {
        $payload = $this->getPayload();

        if (null === $payload || '' === $payload) {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * The actor kind as an enum, or null when the row carries a value the core
     * does not know — a module is free to write its own.
     */
    public function getActorTypeEnum(): ?OrderHistoryActorType
    {
        return OrderHistoryActorType::tryFrom((string) $this->getActorType());
    }
}
