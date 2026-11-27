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

namespace Thelia\Tests\Integration\Quote;

use Thelia\Model\Quote;

/**
 * Who reads a quote and who answers it, for an account alone and for the buyers of a company, in
 * both directions: no account reads the quote of another, and no company reads the quotes of
 * another.
 */
final class QuoteAccessTest extends QuoteDomainTestCase
{
    public function testAnAccountReadsAndAnswersItsOwnQuoteAndNoOneElsesAndNobodyReadsTheirs(): void
    {
        $alice = $this->customer();
        $bob = $this->customer();
        $aliceQuote = $this->request($alice);
        $bobQuote = $this->request($bob);

        self::assertTrue($this->quoteAccess->canRead($alice, $aliceQuote));
        self::assertTrue($this->quoteAccess->canAnswer($alice, $aliceQuote));
        self::assertFalse($this->quoteAccess->canRead($bob, $aliceQuote));
        self::assertFalse($this->quoteAccess->canAnswer($bob, $aliceQuote));
        self::assertFalse($this->quoteAccess->canRead($alice, $bobQuote));
        self::assertFalse($this->quoteAccess->canAnswer($alice, $bobQuote));
        self::assertTrue($this->quoteAccess->canRead($bob->getId(), $bobQuote), 'an id is as good as the account');
        self::assertFalse($this->quoteAccess->canRead(0, $aliceQuote));
    }

    public function testTheBuyersOfACompanyReadAndAnswerAsTheirRoleSays(): void
    {
        $first = $this->company('First');
        $second = $this->company('Second');
        $requester = $this->buyerOf($first);
        $colleagueBuyer = $this->buyerOf($first);
        $colleagueAdmin = $this->buyerOf($first, 'company_admin');
        $colleagueApprover = $this->buyerOf($first, 'approver');
        $otherAdmin = $this->buyerOf($second, 'company_admin');
        $otherBuyer = $this->buyerOf($second);
        $quote = $this->request($requester);
        $otherQuote = $this->request($otherBuyer);

        // Company 1 on the quote of company 1.
        self::assertTrue($this->quoteAccess->canRead($requester, $quote));
        self::assertTrue($this->quoteAccess->canAnswer($requester, $quote));
        self::assertFalse($this->quoteAccess->canRead($colleagueBuyer, $quote), 'a buyer reads their own quotes only');
        self::assertFalse($this->quoteAccess->canAnswer($colleagueBuyer, $quote));
        self::assertTrue($this->quoteAccess->canRead($colleagueAdmin, $quote));
        self::assertTrue($this->quoteAccess->canAnswer($colleagueAdmin, $quote));
        self::assertTrue($this->quoteAccess->canRead($colleagueApprover, $quote), 'the approver reads what the company asked for');
        self::assertFalse($this->quoteAccess->canAnswer($colleagueApprover, $quote), 'and answers none of it');

        // Company 2 on the quote of company 1, and the other way round.
        foreach ([$otherAdmin, $otherBuyer] as $stranger) {
            self::assertFalse($this->quoteAccess->canRead($stranger, $quote));
            self::assertFalse($this->quoteAccess->canAnswer($stranger, $quote));
        }

        foreach ([$requester, $colleagueAdmin, $colleagueApprover, $colleagueBuyer] as $stranger) {
            self::assertFalse($this->quoteAccess->canRead($stranger, $otherQuote));
            self::assertFalse($this->quoteAccess->canAnswer($stranger, $otherQuote));
        }

        self::assertTrue($this->quoteAccess->canRead($otherAdmin, $otherQuote));
    }

    public function testAnAccountThatLeftItsCompanyStillReadsTheQuoteItAskedFor(): void
    {
        $company = $this->company();
        $admin = $this->buyerOf($company, 'company_admin');
        $leaver = $this->buyerOf($company);
        $quote = $this->request($leaver);
        $this->facade->detach($leaver);

        self::assertTrue($this->quoteAccess->canRead($leaver, $quote));
        self::assertTrue($this->quoteAccess->canAnswer($leaver, $quote), 'an account with no company answers its own quotes');
        self::assertTrue($this->quoteAccess->canRead($admin, $quote), 'the company keeps what it asked for');
    }

    public function testOnlyTheAccountsThatMayAskForQuotesDo(): void
    {
        $company = $this->company();

        self::assertTrue($this->quoteAccess->canRequest($this->customer()));
        self::assertTrue($this->quoteAccess->canRequest($this->buyerOf($company)));
        self::assertTrue($this->quoteAccess->canRequest($this->buyerOf($company, 'company_admin')));
        self::assertFalse($this->quoteAccess->canRequest($this->buyerOf($company, 'approver')));
        self::assertFalse($this->quoteAccess->canRequest($this->guest()));
    }

    public function testTheCompanyOfAQuoteIsTheOneOfABuyerWhoMayAskForQuotes(): void
    {
        $company = $this->company();

        self::assertNull($this->quoteAccess->companyIdFor($this->customer()));
        self::assertSame($company->getId(), $this->quoteAccess->companyIdFor($this->buyerOf($company)));
        self::assertNull($this->quoteAccess->companyIdFor($this->buyerOf($company, 'approver')));
    }

    public function testTheCompanyWhoseQuotesAreReadNextToTheOwnIsTheOneOfARoleThatReadsThem(): void
    {
        $company = $this->company();

        self::assertNull($this->quoteAccess->visibleCompanyId($this->customer()));
        self::assertNull($this->quoteAccess->visibleCompanyId($this->buyerOf($company)));
        self::assertSame($company->getId(), $this->quoteAccess->visibleCompanyId($this->buyerOf($company, 'company_admin')));
        self::assertSame($company->getId(), $this->quoteAccess->visibleCompanyId($this->buyerOf($company, 'approver')));
    }

    public function testAQuoteThatIsNotStoredBelongsToNobody(): void
    {
        $quote = new Quote();

        self::assertFalse($this->quoteAccess->canRead($this->customer(), $quote), 'a quote that is not stored belongs to nobody');
    }
}
