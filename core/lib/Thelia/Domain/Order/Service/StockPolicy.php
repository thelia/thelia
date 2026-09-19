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

use Thelia\Domain\Order\Exception\StockShortageException;

readonly class StockPolicy
{
    public function shouldCheckAvailability(bool $checkAvailableStock, bool $useStock): bool
    {
        return $checkAvailableStock && $useStock;
    }

    /**
     * The product is named rather than a whole message passed in: every shortage of the
     * core is one exception class with one wording, so a caller branches on the type and
     * never on the sentence.
     *
     * @throws StockShortageException
     */
    public function assertStockIsAvailable(float $requestedQuantity, float $availableQuantity, ?string $productReference): void
    {
        if ($requestedQuantity > $availableQuantity) {
            throw new StockShortageException($productReference);
        }
    }

    public function shouldDecrementStock(bool $manageStockOnCreation, bool $useStock): bool
    {
        return $useStock && $manageStockOnCreation;
    }

    /**
     * @deprecated since 3.0, use StockDecrementer::decrement() instead: computing the new
     *             quantity in PHP from a previously read value is subject to concurrent
     *             lost updates
     */
    public function computeNewQuantity(float $currentQuantity, float $requestedQuantity, int $allowNegativeStock): float
    {
        $newQuantity = $currentQuantity - $requestedQuantity;
        if ($newQuantity < 0 && 0 === $allowNegativeStock) {
            return 0;
        }

        return $newQuantity;
    }
}
