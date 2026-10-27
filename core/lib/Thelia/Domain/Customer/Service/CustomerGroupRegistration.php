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

namespace Thelia\Domain\Customer\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupHasNoRegistrationLinkException;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Map\CustomerGroupTableMap;

/**
 * The registration link of a customer group: the secret a visitor registers with to join the
 * group instead of the default one.
 *
 * The token is 32 random bytes in lowercase hexadecimal, stored as is: it is a capability, not a
 * password, and is shown again to the administrator who manages the link. A closed link has no
 * token. The default group has no link: it is where an account lands without one.
 *
 * The token is never written to a log nor sent to the storefront; only the administration reads it.
 */
final readonly class CustomerGroupRegistration
{
    public const TOKEN_LENGTH = 64;

    /**
     * Opens the link of the group, and leaves it as it is when it is open already.
     *
     * @return string the token of the open link
     *
     * @throws DefaultCustomerGroupHasNoRegistrationLinkException
     */
    public function open(CustomerGroup $group): string
    {
        $this->refuseTheDefaultGroup($group);

        // Atomic: two administrators opening the same link at once both end up with the token that won.
        CustomerGroupQuery::create()
            ->filterById($group->getId())
            ->filterByRegistrationToken(null, Criteria::ISNULL)
            ->update(['RegistrationToken' => self::newToken()]);
        CustomerGroupTableMap::clearInstancePool();
        $group->reload();

        return (string) $group->getRegistrationToken();
    }

    /**
     * Replaces the token with a new one, which closes every link already handed out; opens the
     * link when it was closed.
     *
     * @return string the new token
     *
     * @throws DefaultCustomerGroupHasNoRegistrationLinkException
     */
    public function regenerate(CustomerGroup $group): string
    {
        $this->refuseTheDefaultGroup($group);

        $token = self::newToken();
        $group->setRegistrationToken($token)->save();

        return $token;
    }

    public function close(CustomerGroup $group): void
    {
        // Straight to the row: the object the caller holds may predate the token.
        CustomerGroupQuery::create()
            ->filterById($group->getId())
            ->update(['RegistrationToken' => null]);
        CustomerGroupTableMap::clearInstancePool();
        $group->reload();
    }

    /**
     * The group a token opens, or null for a token that opens none. A string that cannot be one is
     * answered without a query.
     */
    public function groupForToken(string $token): ?CustomerGroup
    {
        if (1 !== preg_match('/^[0-9a-f]{'.self::TOKEN_LENGTH.'}$/D', $token)) {
            return null;
        }

        return CustomerGroupQuery::create()->filterByRegistrationToken($token)->findOne();
    }

    private function refuseTheDefaultGroup(CustomerGroup $group): void
    {
        if (1 === $group->getIsDefault()) {
            throw DefaultCustomerGroupHasNoRegistrationLinkException::forGroup((int) $group->getId());
        }
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
    }
}
