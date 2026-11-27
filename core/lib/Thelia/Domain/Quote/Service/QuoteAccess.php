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

namespace Thelia\Domain\Quote\Service;

use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;
use Thelia\Model\Quote;

/**
 * Who reads and answers a quote on the buyer side: the one place the API, the storefront and the
 * facade ask.
 *
 * A quote is read by the account it belongs to, and by the buyers of its company who may read the
 * orders of the company. It is answered (counter-proposal, refusal) by whoever reads it and holds
 * the right to ask for quotes; an account with no company answers its own quotes, always.
 */
final readonly class QuoteAccess
{
    public function __construct(
        private CompanyPermissionChecker $permissionChecker,
        private CompanyMembershipReader $membershipReader,
    ) {
    }

    public function canRead(Customer|int $customer, Quote $quote): bool
    {
        $customerId = $this->idOf($customer);

        if ($customerId <= 0) {
            return false;
        }

        if ((int) $quote->getCustomerId() === $customerId) {
            return true;
        }

        $companyId = $quote->getCompanyId();

        return null !== $companyId
            && $this->permissionChecker->can($customerId, CompanyPermission::ORDER_VIEW_COMPANY, (int) $companyId);
    }

    public function canAnswer(Customer|int $customer, Quote $quote): bool
    {
        $customerId = $this->idOf($customer);

        if (!$this->canRead($customerId, $quote)) {
            return false;
        }

        $membership = $this->membershipReader->membershipOf($customerId);

        if (null === $membership) {
            return (int) $quote->getCustomerId() === $customerId;
        }

        return $membership->has(CompanyPermission::QUOTE_REQUEST);
    }

    /**
     * Whether the account may turn a cart into a request: an account with no company always may,
     * a buyer of a company needs the right to ask for quotes. A guest never may: there is no
     * account to find the quote again.
     */
    public function canRequest(Customer $customer): bool
    {
        if ($customer->isGuest() || 0 === (int) $customer->getId()) {
            return false;
        }

        $membership = $this->membershipReader->membershipOf($customer);

        return null === $membership || $membership->has(CompanyPermission::QUOTE_REQUEST);
    }

    /**
     * Whether the account belongs to a company that is suspended: its buyers still read their
     * quotes and refuse them, and ask for nothing and answer nothing.
     */
    public function isCompanySuspended(Customer|int $customer): bool
    {
        $membership = $this->membershipReader->membershipOf($customer);

        return null !== $membership && !$membership->companyActive;
    }

    /**
     * The company whose quotes the account reads next to its own, or null when it reads its own
     * only: the company of a buyer who may read the orders of the company.
     */
    public function visibleCompanyId(Customer|int $customer): ?int
    {
        return $this->permissionChecker->visibleCompanyIdFor($customer, CompanyPermission::ORDER_VIEW_COMPANY);
    }

    /**
     * The company a quote asked for by this account is written for: the company the account
     * belongs to, when its role lets it ask for quotes.
     */
    public function companyIdFor(Customer $customer): ?int
    {
        $membership = $this->membershipReader->membershipOf($customer);

        return null !== $membership && $membership->has(CompanyPermission::QUOTE_REQUEST) ? $membership->companyId : null;
    }

    /**
     * Whether the account may put this address on a quote: one of its own, or one shared with
     * the company it belongs to.
     */
    public function canUseAddressId(Customer $customer, int $addressId): bool
    {
        $address = AddressQuery::create()->findPk($addressId);

        return null !== $address && $this->permissionChecker->canUseAddress($customer, $address);
    }

    public function defaultAddressIdOf(Customer $customer): ?int
    {
        return $customer->getDefaultAddress()?->getId();
    }

    private function idOf(Customer|int $customer): int
    {
        return $customer instanceof Customer ? (int) $customer->getId() : $customer;
    }
}
