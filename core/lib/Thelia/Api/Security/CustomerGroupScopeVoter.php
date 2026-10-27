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
use Thelia\Api\Resource\CustomerGroup;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Model\Customer;

/**
 * Lets a customer read the group it belongs to, and no other.
 *
 * The group is the one the shop prices the customer by, not the one written on the row
 * the token names: a guest row is reusable by e-mail and belongs to the default group
 * whatever an administrator wrote on it, and an account moved to another group is read
 * back from the database rather than from the token.
 */
final class CustomerGroupScopeVoter extends Voter
{
    public const SCOPE = 'THELIA_OWN_CUSTOMER_GROUP';

    public function __construct(
        private readonly CustomerGroupResolver $customerGroupResolver,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::SCOPE === $attribute && $subject instanceof CustomerGroup;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $customer = $token->getUser();

        return $customer instanceof Customer
            && null !== $subject->getId()
            && $this->customerGroupResolver->groupIdOf($customer) === $subject->getId();
    }
}
