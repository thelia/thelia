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

namespace Thelia\Domain\Checkout\Exception;

use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;

/**
 * Products of the cart refuse the carrier: the shop declared it cannot take them.
 *
 * Only products of the buyer's own cart are named.
 */
class DeliveryExcludedByProductException extends InvalidDeliveryException
{
    /**
     * @param array<int, string> $products      product id => title, the products that refuse the carrier
     * @param bool               $noCarrierLeft true when no carrier at all can take them
     */
    public function __construct(
        public readonly array $products,
        public readonly bool $noCarrierLeft = false,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        CheckoutException::__construct(
            $noCarrierLeft
                ? 'No carrier can ship %products. Remove it from your cart to complete your order.'
                : 'The chosen carrier cannot ship %products. Choose another carrier.',
            $code,
            $previous,
            ['%products' => implode(', ', $products)],
        );
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::DeliveryExcludedByProduct->value;
    }

    public function violationDetails(): array
    {
        return ['productIds' => array_keys($this->products), 'noCarrierLeft' => $this->noCarrierLeft];
    }
}
