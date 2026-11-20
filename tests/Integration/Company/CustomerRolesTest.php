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
use Thelia\Test\Trait\RecordsSqlQueries;

final class CustomerRolesTest extends CompanyDomainTestCase
{
    use RecordsSqlQueries;

    public function testABuyerOfACompanyHasTheRolesOfAnyCustomerAndAsksNothingToGetThem(): void
    {
        $buyer = $this->buyerOf($this->company(), CompanyRoleCode::APPROVER);

        $roles = [];
        $statements = $this->recordSqlQueries(static function () use ($buyer, &$roles): void {
            $roles = $buyer->getRoles();
        });

        self::assertSame(['ROLE_CUSTOMER', 'CUSTOMER'], $roles);
        self::assertSame([], $statements);
    }
}
