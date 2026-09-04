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

namespace Thelia\Api\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Thelia\Api\Resource\Cart;

/**
 * Keeps a guest token to the one cart it was issued for.
 *
 * Matching the customer id is not enough here. A guest row is reused when the same
 * address orders twice, so registering as a guest with an address somebody already
 * used lands on their row — and, without this, on their carts. The cart named in the
 * token is what actually belongs to the caller.
 *
 * Silent for everyone else: a customer who signed in is judged on the ownership rule
 * alone, exactly as before.
 */
final class GuestCartScopeVoter extends Voter
{
    public const SCOPE = 'THELIA_GUEST_CART_SCOPE';

    public function __construct(
        private readonly GuestTokenClaims $guestTokenClaims,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::SCOPE === $attribute && $subject instanceof Cart;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$this->guestTokenClaims->isGuest()) {
            return true;
        }

        $claimedCartId = $this->guestTokenClaims->cartId();

        return null !== $claimedCartId && $claimedCartId === $subject->getId();
    }
}
