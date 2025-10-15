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

use Thelia\Exception\TheliaProcessException;

readonly class StockPolicy
{
    public function shouldCheckAvailability(bool $checkAvailableStock, bool $useStock): bool
    {
        return $checkAvailableStock && $useStock;
    }

    public function assertStockIsAvailable(int $requestedQuantity, int $availableQuantity, string $message): void
    {
        if ($requestedQuantity > $availableQuantity) {
            throw new TheliaProcessException($message);
        }
    }

    public function shouldDecrementStock(bool $manageStockOnCreation, bool $useStock): bool
    {
        return $useStock && $manageStockOnCreation;
    }

    public function computeNewQuantity(int $currentQuantity, int $requestedQuantity, int $allowNegativeStock): int
    {
        $newQuantity = $currentQuantity - $requestedQuantity;
        if ($newQuantity < 0 && 0 === $allowNegativeStock) {
            return 0;
        }

        return $newQuantity;
    }
}
