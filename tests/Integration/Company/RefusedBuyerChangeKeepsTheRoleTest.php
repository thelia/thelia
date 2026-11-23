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

namespace Thelia\Tests\Integration\Company;

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\Exception\InvalidCompanyDataException;
use Thelia\Domain\Company\Service\CompanyAccountWriter;
use Thelia\Domain\Company\Service\CompanyGrantPolicy;
use Thelia\Domain\Company\Service\CompanyRoleResolver;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\Map\CompanyCustomerTableMap;

/**
 * A change of a buyer that is refused changes nothing: the role is not written when the limits
 * asked with it are refused.
 */
final class RefusedBuyerChangeKeepsTheRoleTest extends CompanyDomainTestCase
{
    public function testTheMerchantChangeRefusedForItsLimitsLeavesTheRoleAsItWas(): void
    {
        $company = $this->company('Acme');
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($buyer->getId());
        self::assertNotNull($link);

        $refused = false;

        try {
            // A limit over a period without its period is refused by CompanyLimits::assertValid().
            $this->writer()->applyChanges($link, 'approver', null, '1000', null, null);
        } catch (InvalidCompanyDataException) {
            $refused = true;
        }

        self::assertTrue($refused, 'A period limit without its period is refused.');

        CompanyCustomerTableMap::clearInstancePool();
        self::assertSame(
            'buyer',
            CompanyCustomerQuery::create()->findPk($link->getId())?->getCompanyRole()?->getCode(),
            'The refused change wrote the new role anyway.',
        );
    }

    public function testTheAdministratorChangeRefusedForItsLimitsLeavesTheRoleAsItWas(): void
    {
        $company = $this->company('Acme');
        $this->roleOf('purchaser', $company, ['order.place', 'order.view_own', 'order.view_company']);
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company, 'purchaser');
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($buyer->getId());
        self::assertNotNull($link);

        $refused = false;

        try {
            $this->writer()->changeBuyer($administrator, (int) $link->getId(), CompanyRoleCode::BUYER, null, '1000', null);
        } catch (InvalidCompanyDataException) {
            $refused = true;
        }

        self::assertTrue($refused, 'A period limit without its period is refused.');

        CompanyCustomerTableMap::clearInstancePool();
        self::assertSame(
            'purchaser',
            CompanyCustomerQuery::create()->findPk($link->getId())?->getCompanyRole()?->getCode(),
            'The refused change wrote the new role anyway.',
        );
    }

    private function writer(): CompanyAccountWriter
    {
        return new CompanyAccountWriter(
            $this->facade,
            $this->reader,
            $this->checker,
            new CompanyRoleResolver(),
            new CompanyGrantPolicy($this->reader, $this->checker),
        );
    }
}
