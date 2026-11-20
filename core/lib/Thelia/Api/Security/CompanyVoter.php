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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Model\Address;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomer;
use Thelia\Model\Customer;
use Thelia\Model\Order;

/**
 * What a signed-in customer may do for a company, asked of the API the way it is asked of the
 * rest of the shop: through the permission checker, which is the only judge.
 *
 * - COMPANY_MEMBER: the customer belongs to a company (to the one the subject names, when it names one).
 * - COMPANY_PERMISSION: the customer holds the permission, named by a string for the company they
 *   belong to, or by a pair [permission, company, buyer or resource of those] for that company only.
 * - ORDER_READ: the order is one the customer placed, or one of their company they may read.
 * - ADDRESS_USE, ADDRESS_EDIT: the address is one the customer may choose, or may change.
 *
 * A guest, an administrator and an anonymous caller are never granted any of them.
 */
final class CompanyVoter extends Voter
{
    public const MEMBER = 'COMPANY_MEMBER';

    public const PERMISSION = 'COMPANY_PERMISSION';

    public const ORDER_READ = 'ORDER_READ';

    public const ADDRESS_USE = 'ADDRESS_USE';

    public const ADDRESS_EDIT = 'ADDRESS_EDIT';

    public const array ATTRIBUTES = [self::MEMBER, self::PERMISSION, self::ORDER_READ, self::ADDRESS_USE, self::ADDRESS_EDIT];

    public function __construct(
        private readonly CompanyPermissionChecker $permissionChecker,
        private readonly CompanyMembershipReader $membershipReader,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::MEMBER => null === $subject || \is_object($subject),
            self::PERMISSION => \is_string($subject) || $this->isPermissionOfACompany($subject),
            self::ORDER_READ => null !== $this->modelOf($subject, Order::class),
            self::ADDRESS_USE, self::ADDRESS_EDIT => null !== $this->modelOf($subject, Address::class),
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $customer = $token->getUser();

        if (!$customer instanceof Customer || $customer->isGuest()) {
            return false;
        }

        return match ($attribute) {
            self::MEMBER => $this->isMember($customer, $subject),
            self::PERMISSION => $this->holds($customer, $subject),
            self::ORDER_READ => $this->permissionChecker->canAccessOrder($customer, $this->modelOf($subject, Order::class) ?? throw new \LogicException('An order is expected.')),
            self::ADDRESS_USE => $this->permissionChecker->canUseAddress($customer, $this->modelOf($subject, Address::class) ?? throw new \LogicException('An address is expected.')),
            self::ADDRESS_EDIT => $this->permissionChecker->canEditAddress($customer, $this->modelOf($subject, Address::class) ?? throw new \LogicException('An address is expected.')),
            default => false,
        };
    }

    private function isMember(Customer $customer, mixed $subject): bool
    {
        $membership = $this->membershipReader->membershipOf($customer);

        if (null === $membership) {
            return false;
        }

        $companyId = $this->companyIdOf($subject);

        return null === $companyId || $companyId === $membership->companyId;
    }

    private function holds(Customer $customer, mixed $subject): bool
    {
        if (\is_string($subject)) {
            return $this->permissionChecker->can($customer, $subject);
        }

        if (!$this->isPermissionOfACompany($subject)) {
            return false;
        }

        $companyId = $this->companyIdOf($subject[1]);

        return null !== $companyId && $this->permissionChecker->can($customer, $subject[0], $companyId);
    }

    /**
     * @phpstan-assert-if-true array{0: string, 1: object} $subject
     */
    private function isPermissionOfACompany(mixed $subject): bool
    {
        return \is_array($subject) && 2 === \count($subject) && \is_string($subject[0] ?? null) && \is_object($subject[1] ?? null);
    }

    /**
     * The company a subject belongs to: the company itself, a buyer's link, or the resource of one.
     */
    private function companyIdOf(mixed $subject): ?int
    {
        $model = $subject instanceof PropelResourceInterface ? $subject->getPropelModel() : $subject;

        return match (true) {
            $model instanceof Company => (int) $model->getId(),
            $model instanceof CompanyCustomer => (int) $model->getCompanyId(),
            default => null,
        };
    }

    /**
     * @template T of ActiveRecordInterface
     *
     * @param class-string<T> $modelClass
     *
     * @return T|null
     */
    private function modelOf(mixed $subject, string $modelClass): ?ActiveRecordInterface
    {
        $model = $subject instanceof PropelResourceInterface ? $subject->getPropelModel() : $subject;

        return $model instanceof $modelClass ? $model : null;
    }
}
