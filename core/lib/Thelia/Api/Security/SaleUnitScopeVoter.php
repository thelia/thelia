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
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Thelia\Api\Resource\Order;
use Thelia\Api\Resource\SaleUnit;
use Thelia\Api\Resource\SaleUnitProductPrice;
use Thelia\Api\Resource\SaleUnitProductSaleElements;
use Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope;
use Thelia\Model\Admin;

/**
 * Whether the administrator may write what belongs to a sale unit: an order, a
 * unit price, a unit stock, the unit itself.
 *
 * Reading is narrowed by AdminSaleUnitScopeExtension, which leaves an item out of
 * reach before any write can load it. This vote is what the write operations ask
 * of the state they are about to persist, so a restricted administrator can
 * neither create a row for another unit nor move one there.
 */
final class SaleUnitScopeVoter extends Voter
{
    public const SCOPE = 'THELIA_SALE_UNIT_SCOPE';

    /**
     * Granted to an administrator restricted to no sale unit: creating or deleting a
     * unit changes what every administrator reaches, so a restricted one may not.
     */
    public const UNRESTRICTED = 'THELIA_SALE_UNIT_UNRESTRICTED';

    public function __construct(private readonly AdminSaleUnitScope $scope)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::UNRESTRICTED === $attribute) {
            return true;
        }

        return self::SCOPE === $attribute
            && ($subject instanceof Order
                || $subject instanceof SaleUnit
                || $subject instanceof SaleUnitProductPrice
                || $subject instanceof SaleUnitProductSaleElements);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $admin = $token->getUser();

        if (!$admin instanceof Admin) {
            return false;
        }

        if (self::UNRESTRICTED === $attribute) {
            return !$this->scope->isRestricted($admin);
        }

        return $this->scope->allows(self::saleUnitIdOf($subject), $admin);
    }

    private static function saleUnitIdOf(object $subject): ?int
    {
        if ($subject instanceof Order) {
            return $subject->saleUnitId;
        }

        if ($subject instanceof SaleUnit) {
            return $subject->getId();
        }

        if (($subject instanceof SaleUnitProductPrice || $subject instanceof SaleUnitProductSaleElements)
            && isset($subject->saleUnit)) {
            return $subject->saleUnit->getId();
        }

        return null;
    }
}
