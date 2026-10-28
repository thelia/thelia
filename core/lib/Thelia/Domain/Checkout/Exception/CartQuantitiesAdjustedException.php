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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Core\Event\Cart\CartItemQuantityAdjustedEvent;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;

/**
 * Lines of the cart no longer met the rules of their combination and were just brought
 * down or removed: the buyer sees the cart again before paying for quantities they did
 * not ask for. The next check finds the cart in order.
 */
#[Exclude]
class CartQuantitiesAdjustedException extends CheckoutException
{
    /**
     * @param list<CartItemQuantityAdjustedEvent> $adjustments
     */
    public function __construct(private readonly array $adjustments)
    {
        parent::__construct('Quantities of your cart were adjusted to what the products are sold in. Check your cart before ordering.');
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::CartQuantitiesAdjusted->value;
    }

    /**
     * @return array{lines: list<array{productSaleElementsId: int, requested: float, quantity: float|null, message: string}>}
     */
    public function violationDetails(): array
    {
        return ['lines' => array_map(
            static fn (CartItemQuantityAdjustedEvent $adjustment): array => [
                'productSaleElementsId' => (int) $adjustment->getCartItem()->getProductSaleElementsId(),
                'requested' => $adjustment->getDecision()->requested,
                'quantity' => $adjustment->getDecision()->quantity,
                'message' => $adjustment->getMessage(),
            ],
            $this->adjustments,
        )];
    }
}
