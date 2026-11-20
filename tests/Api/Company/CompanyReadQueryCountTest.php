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

namespace Thelia\Tests\Api\Company;

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * What the company costs the reads it bounds: an account that belongs to no company is read the way
 * it always was, at the price of the one lookup of its membership, and what a company adds is a
 * fixed number of statements for the page, never one per order, address or buyer.
 */
final class CompanyReadQueryCountTest extends CompanyApiTestCase
{
    use ForgetsPooledModels;
    use RecordsSqlQueries;

    public function testAnAccountWithoutCompanyReadsItsOrdersAndAddressesWithTheMembershipLookupOnly(): void
    {
        $loner = $this->customer();
        $token = $this->token($loner);

        foreach (['orders', 'addresses'] as $collection) {
            for ($row = 0; $row < 3; ++$row) {
                'orders' === $collection ? $this->orderOf($loner) : $this->addressOf($loner);
            }

            $response = null;
            $statements = $this->recordSqlQueriesWithoutPooledModels(function () use (&$response, $collection, $token): void {
                $response = $this->jsonRequest('GET', '/api/front/account/'.$collection, token: $token);
            });

            self::assertJsonResponseSuccessful($response);
            self::assertHydraTotalItems(3, $response);
            self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'company_customer'), $collection.': the membership is read once.');
            self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'company'), $collection.': no company is read.');
            self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'company_role'), $collection.': no role is read.');
        }
    }

    public function testTheStatementsOnTheCompanyDoNotGrowWithTheRowsOfTheCollections(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $token = $this->token($administrator);
        $counts = [];

        $made = 0;

        foreach ([2, 6] as $rows) {
            for (; $made < $rows; ++$made) {
                $buyer = $this->buyerOf($company);
                $this->orderOf($buyer, $company, $buyer);
                $this->addressOf($buyer, $company);
            }

            foreach (['orders', 'addresses', 'company/customers'] as $collection) {
                $statements = $this->recordSqlQueriesWithoutPooledModels(function () use ($collection, $token): void {
                    $this->jsonRequest('GET', '/api/front/account/'.$collection, token: $token);
                });
                // The buyers also name their account: one read of the accounts of the page, not one each.
                $counts[$collection][$rows] = \count(array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/(FROM|JOIN) `company(_customer|_role)?`/', $statement) || ('company/customers' === $collection && str_contains($statement, 'FROM `customer`'))));
            }
        }

        foreach ($counts as $collection => $byRows) {
            self::assertSame($byRows[2], $byRows[6], $collection.': one more row costs no more statement on the company.');
        }
    }

    public function testTheBuyersOfAPageAreReadWithTheirRolesAndNamesInOneStatementEach(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);

        foreach ([CompanyRoleCode::BUYER, CompanyRoleCode::APPROVER, CompanyRoleCode::BUYER] as $role) {
            $this->buyerOf($company, $role);
        }

        $token = $this->token($administrator);
        $statements = $this->recordSqlQueriesWithoutPooledModels(function () use ($token): void {
            $this->jsonRequest('GET', '/api/front/account/company/customers', token: $token);
        });

        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'company_role'), 'The roles of the page are read at once.');
        self::assertSame(1, $this->selectsOfTheAccountsOfThePage($statements), 'The accounts of the page are read at once.');
    }

    /**
     * @param list<string> $statements
     */
    private function selectsOfTheAccountsOfThePage(array $statements): int
    {
        return \count(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'FROM `customer`') && str_contains($statement, '`firstname`') && str_contains($statement, ' IN (')));
    }
}
