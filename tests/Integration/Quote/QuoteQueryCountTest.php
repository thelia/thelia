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

use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\PersonalData\QuotePersonalDataProvider;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Quote;
use Thelia\Model\QuoteRevision;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * What the sums of a draft and the export of the quotes of a customer cost: a fixed number of
 * statements, whatever the number of lines, of revisions or of quotes they cover.
 */
final class QuoteQueryCountTest extends QuoteDomainTestCase
{
    use RecordsSqlQueries;

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheTotalsOfADraftCostTheSameStatementsForOneLineAndForTwenty(): void
    {
        $this->quotes->totals($this->draftOf(1, 'warm-up'));
        $counts = [];

        foreach ([1, 20] as $lines) {
            $draft = $this->draftOf($lines, 'lines '.$lines);
            $statements = $this->recordSqlQueries(fn () => $this->quotes->totals($draft));
            $counts[$lines] = \count($statements);
        }

        self::assertSame($counts[1], $counts[20], 'a line costs no statement: '.json_encode($counts));
    }

    public function testTheTotalsOfADraftWhoseCarrierTaxFollowsTheGoodsCostTheSameStatementsToo(): void
    {
        ConfigQuery::write('postage_tax_strategy', 'pro_rata');
        ConfigQuery::resetCache();
        $this->quotes->totals($this->draftOf(1, 'warm-up', '15'));
        $counts = [];

        foreach ([1, 20] as $lines) {
            $draft = $this->draftOf($lines, 'lines '.$lines, '15');
            $statements = $this->recordSqlQueries(fn () => $this->quotes->totals($draft));
            $counts[$lines] = \count($statements);
        }

        self::assertSame($counts[1], $counts[20], 'a line costs no statement: '.json_encode($counts));
    }

    public function testTheExportOfTheDataOfACustomerCostsTheSameStatementsForOneQuoteAndForSix(): void
    {
        $counts = [];

        foreach ([1, 6] as $quotes) {
            $buyer = $this->customer();

            for ($quote = 0; $quote < $quotes; ++$quote) {
                $this->sentQuoteOf($buyer, 3);
            }

            $statements = $this->recordSqlQueries(static fn () => (new QuotePersonalDataProvider())->exportPersonalData($buyer));
            $counts[$quotes] = \count($statements);
            self::assertCount($quotes, (new QuotePersonalDataProvider())->exportPersonalData($buyer));
        }

        self::assertSame($counts[1], $counts[6], 'a quote, a revision and a line cost no statement: '.json_encode($counts));
    }

    public function testTheExportReadsEveryRevisionWithItsLinesAndItsAddresses(): void
    {
        $buyer = $this->customer();
        $this->fixtures->address($buyer)->setIsDefault(1)->save($this->getPropelConnection());
        $this->sentQuoteOf($buyer, 3);

        $data = (new QuotePersonalDataProvider())->exportPersonalData($buyer);

        self::assertCount(1, $data);
        self::assertSame([1, 2], array_column($data[0]['revisions'], 'number'));
        self::assertCount(3, $data[0]['revisions'][1]['lines']);
        self::assertCount(3, $data[0]['revisions'][0]['lines']);
    }

    /**
     * A draft of the shop, with this number of lines, on a quote of a customer.
     */
    private function draftOf(int $lines, string $title, string $postage = '0'): QuoteRevision
    {
        $quote = $this->quotes->createForCustomer($this->customer(), null);
        $inputs = [];

        for ($line = 0; $line < $lines; ++$line) {
            $inputs[] = new QuoteDraftLine(null, $this->saleElementId($this->product('10', $title.' '.$line)), 1.0, '10');
        }

        return $this->quotes->saveDraft($quote, null, new QuoteDraftInput($inputs, '0', $postage));
    }

    private function sentQuoteOf(Customer $buyer, int $lines): Quote
    {
        $specs = [];

        for ($line = 0; $line < $lines; ++$line) {
            $specs[] = [$this->product('10', 'Line '.$line), 1.0, '10'];
        }

        $quote = $this->request($buyer, $this->cartOf($buyer, $specs));
        $revision = $this->revisionOf($quote, 1);
        $this->quotes->saveDraft($quote, null, $this->draftFrom($revision, merchantNote: 'Thank you'));
        $this->quotes->send($this->reread($quote), null);

        return $quote;
    }
}
