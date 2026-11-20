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

namespace Thelia\Tests\Http\Flexy;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\Customer;

/**
 * An administrator who may do it is still refused when the form does not carry the token of their
 * session: every write of the company pages is a POST that a page of another site could send.
 */
final class CompanyAntiReplayTokenTest extends CompanyAccountTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function badTokens(): iterable
    {
        yield 'no token' => [''];
        yield 'a token that is not theirs' => ['forged'];
    }

    #[DataProvider('badTokens')]
    public function testTheCompanyDetailsAreNotChangedWithoutTheToken(string $token): void
    {
        $company = $this->company('Acme');
        $this->signInAs($this->buyer($company, CompanyRoleCode::ADMINISTRATOR));

        $this->client->request('POST', '/account/company', ['_token' => $token, 'name' => 'Hijacked']);

        self::assertSame(403, $this->statusCode());
        self::assertSame('Acme', CompanyQuery::create()->findPk($company->getId())?->getName());
    }

    #[DataProvider('badTokens')]
    public function testTheRoleAndTheLimitsOfABuyerAreNotChangedWithoutTheToken(string $token): void
    {
        $company = $this->company();
        $this->signInAs($this->buyer($company, CompanyRoleCode::ADMINISTRATOR));
        $buyer = $this->buyer($company);
        $row = $this->rowOf($buyer);

        $this->client->request('POST', $this->url('account_company_buyer_update', ['buyerId' => $row]), [
            '_token' => $token,
            'role' => CompanyRoleCode::ADMINISTRATOR,
            'order_limit' => '500',
            'period_limit' => '2000',
            'period' => 'month',
        ]);

        self::assertSame(403, $this->statusCode());
        $stored = CompanyCustomerQuery::create()->findPk($row);
        self::assertSame(CompanyRoleCode::BUYER, $stored?->getCompanyRole()?->getCode());
        self::assertNull($stored->getOrderLimit());
    }

    #[DataProvider('badTokens')]
    public function testABuyerIsNotDetachedWithoutTheToken(string $token): void
    {
        $company = $this->company();
        $this->signInAs($this->buyer($company, CompanyRoleCode::ADMINISTRATOR));
        $buyer = $this->buyer($company);

        $this->client->request('POST', $this->url('account_company_buyer_detach', ['buyerId' => $this->rowOf($buyer)]), ['_token' => $token]);

        self::assertSame(403, $this->statusCode());
        self::assertNotNull(CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->findOne());
    }

    #[DataProvider('badTokens')]
    public function testNoInvitationIsSentWithoutTheToken(string $token): void
    {
        $company = $this->company();
        $this->signInAs($this->buyer($company, CompanyRoleCode::ADMINISTRATOR));

        $this->client->request('POST', '/account/company/invitations', ['_token' => $token, 'email' => 'new.buyer@example.com', 'role' => CompanyRoleCode::BUYER]);

        self::assertSame(403, $this->statusCode());
        self::assertEmailCount(0);
    }

    private function rowOf(Customer $buyer): int
    {
        return (int) CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->findOne()?->getId();
    }
}
