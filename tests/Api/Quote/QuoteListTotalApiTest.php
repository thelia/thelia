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

use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevisionQuery;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The total a list of quotes shows: the one frozen on the latest revision sent, never a draft's, read
 * in a number of statements that does not grow with the list.
 */
final class QuoteListTotalApiTest extends QuoteApiTestCase
{
    use RecordsSqlQueries;

    public function testTheListShowsTheTotalOfTheLatestRevisionSent(): void
    {
        $scenario = $this->quotedQuote();

        $list = $this->jsonRequest('GET', '/api/front/account/quotes', token: $this->token($scenario['buyer']));
        self::assertJsonResponseSuccessful($list);
        self::assertSame('1620.000000', $this->body($list)['hydra:member'][0]['totalTaxed']);

        $item = $this->jsonRequest('GET', '/api/front/account/quotes/'.$scenario['quote']->getId(), token: $this->token($scenario['buyer']));
        self::assertSame('1620.000000', $this->body($item)['totalTaxed']);
    }

    public function testTheTotalMovesWithTheNextRevisionSent(): void
    {
        $scenario = $this->quotedQuote();
        $this->buyerCounters($scenario);
        $this->shopAnswers($scenario['quote'], $scenario, '80');

        $list = $this->jsonRequest('GET', '/api/front/account/quotes', token: $this->token($scenario['buyer']));

        self::assertNotSame('1620.000000', $this->body($list)['hydra:member'][0]['totalTaxed']);
        self::assertSame($this->frozenTotalOf($scenario['quote'], 3), $this->body($list)['hydra:member'][0]['totalTaxed']);
    }

    public function testARequestShowsTheTotalOfTheRequest(): void
    {
        $scenario = $this->requestedQuote();

        $list = $this->jsonRequest('GET', '/api/front/account/quotes', token: $this->token($scenario['buyer']));

        self::assertJsonResponseSuccessful($list);
        self::assertSame('1440.000000', $this->body($list)['hydra:member'][0]['totalTaxed']);
    }

    public function testAQuoteWithOnlyADraftHasNoTotal(): void
    {
        $buyer = $this->customer();
        $product = $this->product('10', 'Product');
        $quote = $this->quotes->createForCustomer($buyer, null);
        $this->quotes->saveDraft($quote, null, new QuoteDraftInput([new QuoteDraftLine(null, $this->saleElementId($product), 2.0, '10')]));

        $list = $this->body($this->jsonRequest('GET', '/api/admin/quotes', token: $this->adminToken()));

        self::assertSame((int) $quote->getId(), $list['hydra:member'][0]['id']);
        self::assertNull($list['hydra:member'][0]['totalTaxed'] ?? null);
    }

    public function testADraftIsNeverCounted(): void
    {
        $scenario = $this->quotedQuote();
        $this->buyerCounters($scenario);
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->orderByNumber('desc')->findOne();
        $lines = array_map(
            static fn ($line): QuoteDraftLine => new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), (float) $line->getQuantity(), '1'),
            [...QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find()],
        );
        $this->quotes->saveDraft($scenario['quote'], null, new QuoteDraftInput($lines));
        self::assertSame('draft', QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->filterByNumber(3)->findOne()?->getState());

        $buyer = $this->body($this->jsonRequest('GET', '/api/front/account/quotes', token: $this->token($scenario['buyer'])));
        $shop = $this->body($this->jsonRequest('GET', '/api/admin/quotes', token: $this->adminToken()));

        self::assertSame('1620.000000', $buyer['hydra:member'][0]['totalTaxed']);
        self::assertSame('1620.000000', $shop['hydra:member'][0]['totalTaxed']);
    }

    public function testTheAdministrationReadsTheTotalOnTheListAndOnTheItem(): void
    {
        $scenario = $this->quotedQuote();

        $list = $this->body($this->jsonRequest('GET', '/api/admin/quotes', token: $this->adminToken()));
        $item = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$scenario['quote']->getId(), token: $this->adminToken()));

        self::assertSame('1620.000000', $list['hydra:member'][0]['totalTaxed']);
        self::assertSame('1620.000000', $item['totalTaxed']);
    }

    public function testTheListCostsTheSameNumberOfStatementsForOneQuoteAndForTwenty(): void
    {
        $buyer = $this->customer();
        $token = $this->token($buyer);
        $product = $this->product('10', 'Product');
        $counts = [];
        // The first request of a token reads the account: not what a quote costs.
        $this->jsonRequest('GET', '/api/front/account/quotes', token: $token);

        foreach ([1, 20] as $target) {
            while (\count($this->quotesOf($buyer)) < $target) {
                $quote = $this->quotes->requestFromCart($this->cartOf($buyer, [[$product, 1.0, '10']]), $buyer, new QuoteRequestInput());
                $this->quotes->saveDraft($quote, null, new QuoteDraftInput([new QuoteDraftLine(null, $this->saleElementId($product), 1.0, '10')]));
                $this->quotes->send($quote, null);
            }

            $response = null;
            $statements = $this->recordSqlQueries(function () use (&$response, $token): void {
                $response = $this->jsonRequest('GET', '/api/front/account/quotes', token: $token);
            });

            self::assertJsonResponseSuccessful($response);
            self::assertHydraTotalItems($target, $response);
            self::assertSame('12.000000', $this->body($response)['hydra:member'][0]['totalTaxed']);
            $counts[$target] = \count($statements);
        }

        self::assertSame($counts[1], $counts[20], 'A quote costs no statement.');
    }

    /**
     * @param array{buyer: \Thelia\Model\Customer, quote: \Thelia\Model\Quote} $scenario
     */
    private function buyerCounters(array $scenario): void
    {
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->orderByNumber('desc')->findOne();
        $line = QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->findOne();

        $this->quotes->counterOffer($scenario['quote'], $scenario['buyer'], (int) $revision->getNumber(), [new QuoteItemRequestInput((int) $line->getId(), null, '85')]);
    }

    /**
     * @return list<\Thelia\Model\Quote>
     */
    private function quotesOf(\Thelia\Model\Customer $buyer): array
    {
        return [...\Thelia\Model\QuoteQuery::create()->filterByCustomerId($buyer->getId())->find()];
    }

    private function frozenTotalOf(\Thelia\Model\Quote $quote, int $number): ?string
    {
        return QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->filterByNumber($number)->findOne()?->getTotalTaxed();
    }
}
