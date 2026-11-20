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

namespace Thelia\Domain\Company\PersonalData;

use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\Customer;

/**
 * What the shop holds about a customer as the buyer of a company: which company, under which
 * role, with which limits. The note the administrators keep about the company is not the
 * customer's and is not exported.
 *
 * Nothing is erased here: the link and the shared addresses of a buyer are settled by the
 * anonymization itself, before their address book goes.
 */
final readonly class CompanyMembershipPersonalDataProvider implements CustomerPersonalDataProviderInterface
{
    public const SECTION_NAME = 'company';

    public function __construct(private CompanyMembershipReader $membershipReader)
    {
    }

    public function getPersonalDataSectionName(): string
    {
        return self::SECTION_NAME;
    }

    public function exportPersonalData(Customer $customer): array
    {
        $membership = $this->membershipReader->membershipOf($customer);

        if (null === $membership) {
            return [];
        }

        /** @var array{CompanyName: string, Siret: string|null, VatNumber: string|null, Active: int|string, CreatedAt: \DateTimeInterface|string|null}|null $row */
        $row = CompanyCustomerQuery::create()
            ->filterByCustomerId((int) $customer->getId())
            ->joinCompany()
            ->withColumn('Company.Name', 'CompanyName')
            ->withColumn('Company.Siret', 'Siret')
            ->withColumn('Company.VatNumber', 'VatNumber')
            ->withColumn('Company.Active', 'Active')
            ->select(['CompanyName', 'Siret', 'VatNumber', 'Active', 'CreatedAt'])
            ->findOne();

        if (null === $row) {
            return [];
        }

        $joinedAt = \is_string($row['CreatedAt']) ? new \DateTimeImmutable($row['CreatedAt']) : $row['CreatedAt'];

        return [
            'company' => [
                'name' => $row['CompanyName'],
                'siret' => $row['Siret'],
                'vat_number' => $row['VatNumber'],
                'active' => 1 === (int) $row['Active'],
            ],
            'role' => $membership->roleCode,
            'permissions' => $membership->permissions,
            'order_limit' => $membership->orderLimit,
            'period_limit' => $membership->periodLimit,
            'period' => $membership->period,
            'joined_at' => $joinedAt instanceof \DateTimeInterface ? $joinedAt->format(\DATE_ATOM) : null,
        ];
    }

    public function anonymizePersonalData(Customer $customer): void
    {
    }
}
