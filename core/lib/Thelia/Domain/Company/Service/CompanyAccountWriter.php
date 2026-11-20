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

namespace Thelia\Domain\Company\Service;

use Thelia\Domain\Company\CompanyFacade;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\DTO\CompanyData;
use Thelia\Domain\Company\Exception\CompanyBuyerNotFoundException;
use Thelia\Domain\Company\Exception\CompanyException;
use Thelia\Domain\Company\Exception\CompanyGrantRefusedException;
use Thelia\Domain\Company\Exception\CompanyIdentifiersLockedException;
use Thelia\Domain\Company\Exception\UnknownCompanyException;
use Thelia\Domain\Company\Exception\UnknownCompanyRoleException;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomer;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\CompanyRole;
use Thelia\Model\Customer;
use Thelia\Model\OrderQuery;

/**
 * What a buyer of a company writes about it from their account, whichever front asks: the details
 * of the company, the role and the limits of a colleague, the detachment of a colleague. The API
 * of the account and the pages of the theme call it, so that the rules are written once.
 *
 * The caller is judged here, not by the front: the company they edit is their own, the colleagues
 * they change are those of their company, and nobody is given, nor taken from, more than the
 * caller holds themselves (see {@see CompanyGrantPolicy}). A refusal is a {@see CompanyException}.
 */
final readonly class CompanyAccountWriter
{
    public function __construct(
        private CompanyFacade $facade,
        private CompanyMembershipReader $membershipReader,
        private CompanyPermissionChecker $permissionChecker,
        private CompanyRoleResolver $roleResolver,
        private CompanyGrantPolicy $grantPolicy,
    ) {
    }

    /**
     * Changes what a company is called by, and nothing else: the parent company, the note for the
     * administrators and the billing address stay what they are. The SIRET and the VAT number stay
     * too once an order of the company was left without VAT.
     *
     * @throws CompanyGrantRefusedException                                 when the caller belongs to no company or may not edit it
     * @throws CompanyIdentifiersLockedException
     * @throws UnknownCompanyException
     * @throws \Thelia\Domain\Company\Exception\InvalidCompanyDataException
     */
    public function updateCompany(Customer $caller, string $name, ?string $siret, ?string $vatNumber, ?string $notificationEmail): Company
    {
        $membership = $this->membershipReader->membershipOf($caller);

        if (null === $membership || !$this->permissionChecker->can($caller, CompanyPermission::COMPANY_EDIT, $membership->companyId)) {
            throw CompanyGrantRefusedException::issuerCannotEditTheCompany();
        }

        $company = CompanyQuery::create()->findPk($membership->companyId) ?? throw UnknownCompanyException::withId($membership->companyId);

        $identifiersChange = (string) $siret !== (string) $company->getSiret() || (string) $vatNumber !== (string) $company->getVatNumber();

        if ($identifiersChange && $this->hasAnOrderLeftWithoutVat($membership->companyId)) {
            throw CompanyIdentifiersLockedException::forCompany($membership->companyId);
        }

        return $this->facade->update($company, new CompanyData(
            name: $name,
            siret: $siret,
            vatNumber: $vatNumber,
            parentId: $company->getParentId(),
            notificationEmail: $notificationEmail,
            internalNote: $company->getInternalNote(),
            billingAddressId: $company->getBillingAddressId(),
        ));
    }

    /**
     * Gives a colleague the role and the limits asked for: a role left out keeps the current one,
     * limits left out remove them.
     *
     * @throws CompanyBuyerNotFoundException when the buyer is not one of the caller's company, or the caller does not manage buyers
     * @throws CompanyException
     */
    public function changeBuyer(Customer $caller, int $buyerId, ?string $roleCode, ?string $orderLimit, ?string $periodLimit, ?string $period): CompanyCustomer
    {
        return $this->applyChanges($this->buyerOf($caller, $buyerId), $roleCode, $orderLimit, $periodLimit, $period, $caller);
    }

    /**
     * @throws CompanyBuyerNotFoundException when the buyer is not one of the caller's company, or the caller does not manage buyers
     * @throws CompanyException
     */
    public function detachBuyer(Customer $caller, int $buyerId): void
    {
        $buyer = $this->buyerOf($caller, $buyerId);

        $this->grantPolicy->assertIssuerMayManageTheHolderOf($caller, (int) $buyer->getCompanyId(), $buyer->getCompanyRole());

        /** @var Customer|null $buyerCustomer */
        $buyerCustomer = $buyer->getCustomer();

        $this->facade->detach($buyerCustomer ?? throw CompanyBuyerNotFoundException::withId($buyerId));
    }

    /**
     * Applies a role and limits to a buyer through the facade. With an issuer, the buyer must hold
     * nothing the issuer lacks, and what they are given must stay within the issuer's own rights,
     * the new role and the new limits being judged together; with none (the merchant) nothing binds.
     *
     * @throws CompanyException
     */
    public function applyChanges(CompanyCustomer $buyer, ?string $roleCode, ?string $orderLimit, ?string $periodLimit, ?string $period, ?Customer $issuer): CompanyCustomer
    {
        $companyId = (int) $buyer->getCompanyId();
        /** @var Customer|null $buyerCustomer */
        $buyerCustomer = $buyer->getCustomer();
        $customer = $buyerCustomer ?? throw CompanyBuyerNotFoundException::withId((int) $buyer->getId());
        /** @var CompanyRole|null $currentRole */
        $currentRole = $buyer->getCompanyRole();
        $roleCode ??= (string) $currentRole?->getCode();

        $role = $this->roleResolver->resolve($companyId, $roleCode) ?? throw UnknownCompanyRoleException::forCompany($roleCode, $companyId);

        if (null !== $issuer) {
            $this->grantPolicy->assertIssuerMayManageTheHolderOf($issuer, $companyId, $currentRole);
            $this->grantPolicy->assertIssuerMayGrant($issuer, $companyId, $role, $orderLimit, $periodLimit, $period);
        }

        if ((int) $role->getId() !== (int) $buyer->getCompanyRoleId()) {
            $this->facade->changeRole($customer, $roleCode);
        }

        if ($this->limitsDiffer($buyer, $orderLimit, $periodLimit, $period)) {
            $this->facade->changeLimits($customer, $orderLimit, $periodLimit, $period);
        }

        return CompanyCustomerQuery::create()->filterById((int) $buyer->getId())->findOne() ?? $buyer;
    }

    private function buyerOf(Customer $caller, int $buyerId): CompanyCustomer
    {
        $buyer = CompanyCustomerQuery::create()->findPk($buyerId) ?? throw CompanyBuyerNotFoundException::withId($buyerId);

        // A colleague of another company, or one the caller has no right over, is not there for them.
        if (!$this->permissionChecker->can($caller, CompanyPermission::BUYER_MANAGE, (int) $buyer->getCompanyId())) {
            throw CompanyBuyerNotFoundException::withId($buyerId);
        }

        return $buyer;
    }

    private function limitsDiffer(CompanyCustomer $buyer, ?string $orderLimit, ?string $periodLimit, ?string $period): bool
    {
        return $this->amountsDiffer($buyer->getOrderLimit(), $orderLimit)
            || $this->amountsDiffer($buyer->getPeriodLimit(), $periodLimit)
            || $buyer->getPeriod() !== $period;
    }

    private function amountsDiffer(?string $stored, ?string $wanted): bool
    {
        if (null === $stored || null === $wanted) {
            return $stored !== $wanted;
        }

        return CompanyAmount::isValid($wanted) ? 0 !== CompanyAmount::compare($stored, $wanted) : true;
    }

    private function hasAnOrderLeftWithoutVat(int $companyId): bool
    {
        // The exemption is frozen on the invoice address of the order.
        return OrderQuery::create()
            ->filterByCompanyId($companyId)
            ->useOrderAddressRelatedByInvoiceOrderAddressIdQuery()
                ->filterByVatExempted(1)
            ->endUse()
            ->exists();
    }
}
