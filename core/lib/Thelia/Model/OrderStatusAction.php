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

use Thelia\Model\Base\OrderStatusAction as BaseOrderStatusAction;

class OrderStatusAction extends BaseOrderStatusAction
{
    /**
     * The action parameters, stored as JSON. A missing or unreadable payload is empty.
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
     * @param array<string, mixed> $payload
     */
    public function setDecodedPayload(array $payload): static
    {
        return $this->setPayload([] === $payload ? null : json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
