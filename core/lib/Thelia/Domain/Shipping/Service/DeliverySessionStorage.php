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

namespace Thelia\Domain\Shipping\Service;

use Thelia\Core\HttpFoundation\Session\Session;

final readonly class DeliverySessionStorage
{
    private const SESSION_PREFIX = 'thelia.delivery.';

    public function __construct(private Session $session)
    {
    }

    public function setDeliveryData(string $key, mixed $value): void
    {
        $this->session->set(self::SESSION_PREFIX.$key, $value);
    }

    public function getDeliveryData(string $key, mixed $default = null): mixed
    {
        return $this->session->get(self::SESSION_PREFIX.$key, $default);
    }

    public function getAllDeliveryData(): array
    {
        $allKeys = array_keys($_SESSION ?? []);
        $deliveryData = [];

        foreach ($allKeys as $key) {
            if (str_starts_with($key, self::SESSION_PREFIX)) {
                $cleanKey = str_replace(self::SESSION_PREFIX, '', $key);
                $deliveryData[$cleanKey] = $this->session->get($key);
            }
        }

        return $deliveryData;
    }

    public function clearDeliveryData(): void
    {
        $allKeys = array_keys($_SESSION ?? []);
        foreach ($allKeys as $key) {
            if (str_starts_with($key, self::SESSION_PREFIX)) {
                $this->session->remove($key);
            }
        }
    }
}
