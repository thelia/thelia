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

namespace Thelia\Tests\Api\Quote;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Service\API\ResourceMemoizer;
use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Company;
use Thelia\Model\Customer;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Integration\Quote\QuoteDomainTestCase;

/**
 * A page of the theme has no firewall token: its customer lives in the Thelia session. The quotes it
 * reads in process (`resources()`) are bounded the way the API bounds them, by what the session
 * customer reaches through their company, and to nobody when there is no customer, or only a guest.
 * It is the only barrier of that read, so each case fails if the scope goes.
 */
final class QuoteInProcessReadTest extends QuoteDomainTestCase
{
    use LogsInAsCustomer {
        loginAsCustomerInSession as private logsInAsCustomerInSession;
    }

    private DataAccessService $dataAccess;

    private Company $company;

    private Customer $creator;

    private Customer $colleague;

    private Customer $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataAccess = $this->getService(DataAccessService::class);
        $this->company = $this->company();
        $this->creator = $this->buyerOf($this->company);
        $this->colleague = $this->buyerOf($this->company);
        $this->approver = $this->buyerOf($this->company, CompanyRoleCode::APPROVER);
    }

    public function testTheQuotesOfTheCompanyAreListedForTheCustomerWhoReadsThemOnly(): void
    {
        $quote = $this->request($this->creator);
        $own = $this->request($this->colleague);

        $this->loginAsCustomerInSession($this->approver);
        self::assertEqualsCanonicalizing([$quote->getId(), $own->getId()], $this->idsOf($this->dataAccess->resources('/api/front/account/quotes')));

        $this->loginAsCustomerInSession($this->colleague);
        self::assertSame([$own->getId()], $this->idsOf($this->dataAccess->resources('/api/front/account/quotes')));
    }

    public function testTheQuoteOfAColleagueIsAbsentFromAnInProcessItemReadUnlessTheRoleReadsIt(): void
    {
        $quote = $this->request($this->creator);

        $this->loginAsCustomerInSession($this->colleague);
        self::assertNull($this->dataAccess->resources('/api/front/account/quotes/'.$quote->getId()));

        $this->loginAsCustomerInSession($this->approver);
        $read = $this->dataAccess->resources('/api/front/account/quotes/'.$quote->getId());
        self::assertIsArray($read);
        self::assertSame($quote->getId(), $read['id']);
        self::assertArrayNotHasKey('revisions', $read);

        $revision = $this->dataAccess->resources('/api/front/account/quotes/'.$quote->getId().'/revisions/1');
        self::assertIsArray($revision);
        self::assertCount(1, $revision['items']);

        $this->loginAsCustomerInSession($this->colleague);

        $this->expectException(NotFoundHttpException::class);
        $this->dataAccess->resources('/api/front/account/quotes/'.$quote->getId().'/revisions/1');
    }

    public function testNothingIsReadWithoutASessionCustomerOrWithAGuest(): void
    {
        $quote = $this->request($this->creator);

        self::assertSame([], $this->idsOf($this->dataAccess->resources('/api/front/account/quotes')));
        self::assertNull($this->dataAccess->resources('/api/front/account/quotes/'.$quote->getId()));

        $guest = $this->fixtures->guestCustomer($this->fixtures->customerTitle());
        $this->loginAsCustomerInSession($guest);
        self::assertSame([], $this->idsOf($this->dataAccess->resources('/api/front/account/quotes')));
        self::assertNull($this->dataAccess->resources('/api/front/account/quotes/'.$quote->getId()));
    }

    public function testTheDraftOfTheShopIsNotReadInProcessEither(): void
    {
        $draft = $this->quotes->createForCustomer($this->creator, null);
        $quote = $this->request($this->creator);

        $this->loginAsCustomerInSession($this->creator);

        self::assertSame([$quote->getId()], $this->idsOf($this->dataAccess->resources('/api/front/account/quotes')));
        self::assertNull($this->dataAccess->resources('/api/front/account/quotes/'.$draft->getId()));
    }

    private function loginAsCustomerInSession(?Customer $customer = null): Customer
    {
        // What a page read is remembered for the rest of the request, whoever asked for it.
        $this->getService(ResourceMemoizer::class)->clear();

        return $this->logsInAsCustomerInSession($customer);
    }

    /**
     * @return list<int>
     */
    private function idsOf(mixed $collection): array
    {
        if (null === $collection) {
            return [];
        }

        self::assertIsArray($collection);

        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $collection));
    }
}
