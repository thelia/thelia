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

namespace Thelia\Domain\Pricing\PriceVisibility;

use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

/**
 * Who sees the prices of a product, and who may buy it.
 *
 * The shop sets a default (configuration variable price_display_mode) and a product may
 * override it. VISIBLE is the default and is what Thelia has always done: an unknown value
 * falls back on it so that no shop loses its prices by a typo.
 */
enum PriceDisplayMode: string
{
    /** Every visitor sees the prices and may buy. */
    case Visible = 'visible';

    /** Only an identified customer sees the prices and may buy. */
    case LoggedIn = 'logged_in';

    /** Nobody sees a price: the product is offered through a price request. */
    case OnRequest = 'on_request';

    public const CONFIG_KEY = 'price_display_mode';

    /**
     * Whether the prices show to this visitor: a customer is a visitor who signed in.
     */
    public function showsPrice(?Customer $customer): bool
    {
        return match ($this) {
            self::Visible => true,
            self::LoggedIn => null !== $customer,
            self::OnRequest => false,
        };
    }

    /**
     * Whether this visitor may put the product in a cart: nobody buys what they are not
     * shown the price of.
     */
    public function purchasable(?Customer $customer): bool
    {
        return $this->showsPrice($customer);
    }

    /**
     * The mode a product states for itself, null when it follows the shop (no value, or a
     * value this version does not know).
     */
    public static function fromProductValue(?string $value): ?self
    {
        return null === $value || '' === $value ? null : self::tryFrom($value);
    }

    /**
     * The mode the shop configured, Visible when the variable is unset or holds a value
     * this version does not know.
     */
    public static function fromShopConfiguration(): self
    {
        return self::tryFrom((string) ConfigQuery::read(self::CONFIG_KEY, self::Visible->value))
            ?? self::Visible;
    }
}
