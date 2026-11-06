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

namespace Thelia\Domain\Cart\Discount;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Model\Cart;

/**
 * What a module takes off a cart under its own name, beside the promotions. A module
 * implements it on a service the container autoconfigures; the core reads every
 * implementation.
 *
 * The core adds the lines to the cart discount, lists them with the promotions in the
 * `discounts` of the cart, and freezes them on the order when it is placed
 * (`order_discount_line`). The module never writes the cart discount itself: the core
 * writes it again at each change of the cart and would drop its amount.
 *
 * The cart discount never exceeds what the goods are worth: when the promotions and the
 * lines together go beyond it, the lines are served first and the promotions share what
 * is left. What the order froze is what the module was granted.
 *
 * When the lines change without the cart changing (the buyer chose to spend a balance),
 * the module dispatches {@see \Thelia\Core\Event\TheliaEvents::CART_DISCOUNT_LINES_CHANGED}
 * with a CartEvent so the core prices the discount again.
 */
#[AutoconfigureTag(self::TAG)]
interface CartDiscountLineProviderInterface
{
    public const TAG = 'thelia.cart.discount_line_provider';

    /**
     * Read at every pricing of the cart: keep it to what the cart and the buyer already hold.
     *
     * @return iterable<CartDiscountLine> the lines for this cart, none when the module takes nothing off
     */
    public function discountLinesFor(Cart $cart): iterable;
}
