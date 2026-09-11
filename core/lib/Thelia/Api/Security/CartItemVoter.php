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
use Thelia\Api\Resource\CartItem;
use Thelia\Model\CartItem as CartItemModel;

/**
 * Second lock on the front cart item operations, behind
 * {@see \Thelia\Api\Bridge\Propel\Extension\CartItemOwnershipExtension}.
 *
 * The extension keeps foreign rows out of the query; this voter answers on the
 * row that did come back, so a read path that ever bypasses the extension — a
 * custom provider, a dropped service tag — still cannot serve somebody else's
 * cart line.
 */
final class CartItemVoter extends Voter
{
    public const OWNER = 'THELIA_CART_ITEM_OWNER';

    /**
     * Owning the line is not enough to change it: a line a promotion offered
     * belongs to that promotion, which puts it there and takes it back on its
     * own. This is the API side of the lock Action\Cart holds on the event
     * side — the front PUT and DELETE go through PropelPersistProcessor and
     * PropelRemoveProcessor, which dispatch no cart event at all.
     */
    public const MUTABLE = 'THELIA_CART_ITEM_MUTABLE';

    public function __construct(
        private readonly CartOwnership $cartOwnership,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::OWNER, self::MUTABLE], true) && $subject instanceof CartItem;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $model = $subject->getPropelModel();

        if (!$model instanceof CartItemModel) {
            return false;
        }

        $cart = $model->getCart();

        if (null === $cart) {
            return false;
        }

        if (!$this->cartOwnership->ownsCart($cart->getCustomerId(), $cart->getId())) {
            return false;
        }

        return self::MUTABLE !== $attribute || 1 !== (int) $model->getIsOffered();
    }
}
