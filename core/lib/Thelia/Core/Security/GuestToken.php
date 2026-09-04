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

namespace Thelia\Core\Security;

/**
 * What a guest checkout JWT is made of.
 *
 * A guest holds a real customer row, so the token it carries is an ordinary customer
 * JWT in every respect but two: the role it grants, and the single cart it is allowed
 * to touch. Both are named here so that the endpoint that mints the token, the
 * listener that reads it back and the voters that act on it cannot drift apart.
 */
final class GuestToken
{
    /**
     * Deliberately outside every hierarchy: ROLE_GUEST must never imply ROLE_CUSTOMER,
     * or the /api/front/account endpoints — orders, addresses, the account itself —
     * would open to someone who never chose a password.
     */
    public const ROLE = 'ROLE_GUEST';

    /**
     * The cart the token was issued for, as a JWT claim.
     *
     * A guest row is reused across visits when the same address orders twice, so the
     * customer id alone does not say "this cart is mine": whoever registers as a guest
     * with an address someone else already used lands on the same row. The claim pins
     * the token to the one cart it was handed out for.
     */
    public const CART_CLAIM = 'cart_id';

    /**
     * Where the claim above is kept on the authenticated security token, for the
     * voters and the ownership service to read without parsing the JWT again.
     */
    public const CART_TOKEN_ATTRIBUTE = 'thelia.guest_cart_id';

    /**
     * How long a guest token is accepted, in seconds, unless the shop says otherwise.
     *
     * Short on purpose: it is handed to a visitor the shop knows nothing about, and it
     * only has to outlive a checkout.
     */
    public const DEFAULT_LIFETIME_IN_SECONDS = 3600;

    /**
     * Overrides the lifetime above, in seconds, when the shop has an opinion.
     */
    public const LIFETIME_CONFIG_KEY = 'guest_checkout_token_lifetime';
}
