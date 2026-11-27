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
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * What reading the quotes costs: the list never reads a line, a quote reads the revision it stands at and
 * no line, and one revision reads its lines and the counter-proposals on them in a fixed number of
 * statements, however many lines it has.
 */
final class QuoteReadQueryCountApiTest extends QuoteApiTestCase
{
    use RecordsSqlQueries;

    public function testTheListReadsNoRevisionAndNoLine(): void
    {
        $buyer = $this->customer();

        for ($quote = 0; $quote < 3; ++$quote) {
            $this->requestedQuote($buyer);
        }

        $token = $this->token($buyer);
        $response = null;
        $statements = $this->recordSqlQueries(function () use (&$response, $token): void {
            $response = $this->jsonRequest('GET', '/api/front/account/quotes', token: $token);
        });

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(3, $response);
        self::assertSame(0, self::countSqlQueriesSelectingFrom($this->withoutTheQuoteSelects($statements), 'quote_revision'));
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'quote_item'));
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'quote_item_request'));
    }

    public function testOneQuoteIsReadWithoutItsRevisionsNorItsLines(): void
    {
        $buyer = $this->customer();
        $token = $this->token($buyer);
        $counts = [];

        foreach ([3, 20] as $lines) {
            $quote = $this->quoteOfLines($buyer, $lines);
            $response = null;
            $statements = $this->recordSqlQueries(function () use (&$response, $quote, $token): void {
                $response = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $token);
            });

            self::assertJsonResponseSuccessful($response);
            self::assertArrayNotHasKey('revisions', $this->body($response));
            $counts[$lines] = [
                'revisions' => self::countSqlQueriesSelectingFrom($this->withoutTheQuoteSelects($statements), 'quote_revision'),
                'items' => self::countSqlQueriesSelectingFrom($statements, 'quote_item'),
                'counter-proposals' => self::countSqlQueriesSelectingFrom($statements, 'quote_item_request'),
            ];
        }

        self::assertSame(['revisions' => 1, 'items' => 0, 'counter-proposals' => 0], $counts[3], 'One statement for the revision the quote stands at, no line.');
        self::assertSame($counts[3], $counts[20], 'A line costs no statement.');
    }

    public function testOneRevisionReadsItsLinesInOneStatementWhateverTheirNumber(): void
    {
        $buyer = $this->customer();
        $token = $this->token($buyer);
        $counts = [];

        foreach ([1, 20] as $lines) {
            $quote = $this->quoteOfLines($buyer, $lines);
            $response = null;
            $statements = $this->recordSqlQueries(function () use (&$response, $quote, $token): void {
                $response = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions/1', token: $token);
            });

            self::assertJsonResponseSuccessful($response);
            self::assertCount($lines, $this->body($response)['items']);
            $counts[$lines] = [
                'revisions' => self::countSqlQueriesSelectingFrom($this->withoutTheQuoteSelects($statements), 'quote_revision'),
                'items' => self::countSqlQueriesSelectingFrom($statements, 'quote_item'),
                'counter-proposals' => self::countSqlQueriesSelectingFrom($statements, 'quote_item_request'),
            ];
        }

        self::assertSame(['revisions' => 1, 'items' => 1, 'counter-proposals' => 1], $counts[1]);
        self::assertSame($counts[1], $counts[20], 'A line costs no statement.');
    }

    public function testTheRevisionsOfAQuoteAreListedWithoutAnyLine(): void
    {
        $buyer = $this->customer();
        $token = $this->token($buyer);
        $quote = $this->quoteOfLines($buyer, 20);
        $response = null;
        $statements = $this->recordSqlQueries(function () use (&$response, $quote, $token): void {
            $response = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions', token: $token);
        });

        self::assertJsonResponseSuccessful($response);
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'quote_item'));
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'quote_item_request'));
    }

    /**
     * The statements that read the quotes carry the total of the latest revision sent as a subquery:
     * what is counted here is the statements that read the revisions themselves.
     *
     * @param list<string> $statements
     *
     * @return list<string>
     */
    private function withoutTheQuoteSelects(array $statements): array
    {
        return array_values(array_filter($statements, static fn (string $statement): bool => !str_contains($statement, 'FROM `quote`')));
    }

    private function quoteOfLines(\Thelia\Model\Customer $buyer, int $lines): \Thelia\Model\Quote
    {
        $lineSpecs = [];

        for ($line = 0; $line < $lines; ++$line) {
            $lineSpecs[] = [$this->product('10', 'Product '.$line), 1.0, '10'];
        }

        $quote = $this->quotes->requestFromCart($this->cartOf($buyer, $lineSpecs), $buyer, new QuoteRequestInput());
        // Nothing to answer with: the draft keeps the lines as asked, one revision more, never shown to the buyer.
        $this->quotes->saveDraft($quote, null, new QuoteDraftInput(
            array_map(
                static fn ($item): QuoteDraftLine => new QuoteDraftLine((int) $item->getId(), (int) $item->getProductSaleElementsId(), (float) $item->getQuantity(), (string) $item->getPrice()),
                [...\Thelia\Model\QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber(1)->endUse()->orderByPosition()->find()],
            ),
        ));

        return $quote;
    }
}
