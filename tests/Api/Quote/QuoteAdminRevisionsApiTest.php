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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Model\Admin;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The revisions and the lines of a quote as the administration reads them under the quote: every
 * revision, the draft of the shop included, at the right of the quotes, in a number of statements
 * that does not depend on how many there are.
 */
final class QuoteAdminRevisionsApiTest extends QuoteApiTestCase
{
    use RecordsSqlQueries;

    private Admin $admin;

    public function testTheRevisionsOfAQuoteAreListedWithTheDraftAndWithoutTheirLines(): void
    {
        $scenario = $this->negotiatedQuote();
        $quote = $scenario['quote'];

        $response = $this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId().'/revisions', token: $this->adminToken());

        self::assertJsonResponseSuccessful($response);
        $members = $this->body($response)['hydra:member'];
        self::assertSame([1, 2, 3], array_column($members, 'number'));
        self::assertSame(['customer', 'admin', 'admin'], array_column($members, 'author'));
        self::assertSame(['sent', 'sent', 'draft'], array_column($members, 'state'));
        self::assertSame('1620.000000', $members[1]['totalTaxed']);
        self::assertNull($members[2]['totalTaxed'] ?? null, 'a draft is not worked out');
        self::assertSame((int) $this->admin->getId(), $members[1]['adminId'], 'the administration knows who wrote it');

        foreach ($members as $member) {
            self::assertArrayNotHasKey('items', $member, 'the list leaves the lines out');
        }
    }

    public function testOneRevisionIsReadWithItsLinesAndTheCounterProposalsOnThem(): void
    {
        $scenario = $this->negotiatedQuote();
        $quote = $scenario['quote'];
        $token = $this->adminToken();

        $sent = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId().'/revisions/2', token: $token));

        self::assertSame(2, $sent['number']);
        self::assertSame('sent', $sent['state']);
        self::assertEquals([12.0, 4.0, 5.0], array_column($sent['items'], 'quantity'));
        self::assertSame(['90.000000', '45.000000', '20.000000'], array_column($sent['items'], 'price'));
        self::assertSame(['quantity' => null, 'price' => '85.000000'], $sent['items'][0]['counterOffer'], 'what the buyer asked for on the first line');
        self::assertNull($sent['items'][1]['counterOffer'] ?? null);

        $draft = $this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId().'/revisions/3', token: $token);

        self::assertJsonResponseSuccessful($draft);
        self::assertSame('draft', $this->body($draft)['state'], 'the draft is read like the rest');
        self::assertEquals([7.0], array_column($this->body($draft)['items'], 'quantity'));
    }

    public function testTheLinesOfARevisionAreListedInTheOrderOfTheRevision(): void
    {
        $scenario = $this->negotiatedQuote();
        $quote = $scenario['quote'];
        $token = $this->adminToken();

        $response = $this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId().'/revisions/2/items', token: $token);

        self::assertJsonResponseSuccessful($response);
        $members = $this->body($response)['hydra:member'];
        self::assertSame([1, 2, 3], array_column($members, 'position'));
        self::assertSame([$scenario['a']->getRef(), $scenario['b']->getRef(), $scenario['c']->getRef()], array_column($members, 'productRef'));
        self::assertSame('100.000000', $members[0]['catalogPrice']);
        self::assertSame('85.000000', $members[0]['counterOffer']['price']);
        self::assertArrayNotHasKey('quoteId', $members[0], 'what the address is made of is not a field of the line');

        $draft = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId().'/revisions/3/items', token: $token))['hydra:member'];

        self::assertEquals([7.0], array_column($draft, 'quantity'), 'the lines of the draft too');
    }

    public function testAQuoteThatHasNoSuchRevisionOrNoSuchNumberIsNotFound(): void
    {
        $scenario = $this->negotiatedQuote();
        $other = $this->requestedQuote()['quote'];
        $token = $this->adminToken();
        $id = $scenario['quote']->getId();

        foreach ([
            '/api/admin/quotes/987654321/revisions',
            '/api/admin/quotes/987654321/revisions/1',
            '/api/admin/quotes/987654321/revisions/1/items',
            '/api/admin/quotes/'.$id.'/revisions/9',
            '/api/admin/quotes/'.$id.'/revisions/9/items',
            '/api/admin/quotes/'.$id.'/revisions/0',
            '/api/admin/quotes/'.$other->getId().'/revisions/2',
            '/api/admin/quotes/'.$other->getId().'/revisions/2/items',
        ] as $uri) {
            self::assertSame(404, $this->jsonRequest('GET', $uri, token: $token)->getStatusCode(), $uri);
        }
    }

    public function testARevisionIsNeverReadUnderAnotherQuote(): void
    {
        $first = $this->quotedQuote()['quote'];
        $second = $this->quotedQuote()['quote'];
        $token = $this->adminToken();

        $ofFirst = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$first->getId().'/revisions/2/items', token: $token))['hydra:member'];
        $ofSecond = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$second->getId().'/revisions/2/items', token: $token))['hydra:member'];

        self::assertSame($this->lineIdsOfRevision($first, 2), array_column($ofFirst, 'id'));
        self::assertSame($this->lineIdsOfRevision($second, 2), array_column($ofSecond, 'id'));
        self::assertSame([], array_intersect(array_column($ofFirst, 'id'), array_column($ofSecond, 'id')));
    }

    public function testTheAddressOfEveryMemberResolvesToTheSameResource(): void
    {
        $quote = $this->negotiatedQuote()['quote'];
        $token = $this->adminToken();
        $base = '/api/admin/quotes/'.$quote->getId().'/revisions';

        foreach (['/revisions' => $base, '/revisions/2/items' => $base.'/2/items', '/revisions/3/items' => $base.'/3/items'] as $suffix => $uri) {
            $members = $this->body($this->jsonRequest('GET', $uri, token: $token))['hydra:member'];
            self::assertNotSame([], $members, $suffix);

            foreach ($members as $member) {
                self::assertStringStartsWith($base, $member['@id'], $suffix);
                $read = $this->jsonRequest('GET', $member['@id'], token: $token);

                self::assertSame(200, $read->getStatusCode(), $member['@id']);
                self::assertSame($member['@id'], $this->body($read)['@id']);
                self::assertSame($member['id'] ?? $member['number'], $this->body($read)['id'] ?? $this->body($read)['number']);
            }
        }
    }

    public function testALineIsNeverReadThroughAnotherQuoteOrAnotherRevision(): void
    {
        $first = $this->quotedQuote()['quote'];
        $second = $this->quotedQuote()['quote'];
        $token = $this->adminToken();
        $lineOfSecond = $this->lineIdsOfRevision($second, 2)[0];
        $lineOfFirstRequest = $this->lineIdsOfRevision($first, 1)[0];

        foreach ([
            '/api/admin/quotes/'.$first->getId().'/revisions/2/items/'.$lineOfSecond,
            '/api/admin/quotes/'.$first->getId().'/revisions/2/items/'.$lineOfFirstRequest,
            '/api/admin/quotes/'.$first->getId().'/revisions/9/items/'.$lineOfSecond,
            '/api/admin/quotes/987654321/revisions/2/items/'.$lineOfSecond,
            '/api/admin/quotes/'.$first->getId().'/revisions/2/items/987654321',
        ] as $uri) {
            self::assertSame(404, $this->jsonRequest('GET', $uri, token: $token)->getStatusCode(), $uri);
        }

        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/quotes/'.$second->getId().'/revisions/2/items/'.$lineOfSecond, token: $token)->getStatusCode());
    }

    public function testEveryReadAsksForTheRightOfTheQuotes(): void
    {
        $quote = $this->negotiatedQuote()['quote'];
        $uris = [
            '/api/admin/quotes/'.$quote->getId().'/revisions',
            '/api/admin/quotes/'.$quote->getId().'/revisions/2',
            '/api/admin/quotes/'.$quote->getId().'/revisions/2/items',
            '/api/admin/quotes/'.$quote->getId().'/revisions/2/items/'.$this->lineIdsOfRevision($quote, 2)[0],
        ];

        $viewOnly = $this->authenticateAsAdmin($this->profileWith([AccessManager::VIEW]));
        $other = $this->authenticateAsAdmin($this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]));
        $writesOnly = $this->authenticateAsAdmin($this->profileWith([AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE]));

        foreach ($uris as $uri) {
            self::assertSame(200, $this->jsonRequest('GET', $uri, token: $viewOnly)->getStatusCode(), 'view: '.$uri);
            self::assertSame(403, $this->jsonRequest('GET', $uri, token: $other)->getStatusCode(), 'another right: '.$uri);
            self::assertSame(403, $this->jsonRequest('GET', $uri, token: $writesOnly)->getStatusCode(), 'no view: '.$uri);
            self::assertSame(401, $this->jsonRequest('GET', $uri)->getStatusCode(), 'anonymous: '.$uri);
            self::assertContains($this->jsonRequest('GET', $uri, token: $this->token($this->customer()))->getStatusCode(), [401, 403], 'a customer: '.$uri);
        }
    }

    public function testNothingIsWrittenThroughTheRevisionsOfAQuote(): void
    {
        $quote = $this->negotiatedQuote()['quote'];
        $token = $this->adminToken();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            foreach (['/revisions', '/revisions/3', '/revisions/3/items'] as $suffix) {
                $format = 'PATCH' === $method ? 'merge-patch+json' : 'json';

                self::assertContains($this->jsonRequest($method, '/api/admin/quotes/'.$quote->getId().$suffix, ['state' => 'sent'], $token, $format)->getStatusCode(), [404, 405], $method.' '.$suffix);
            }
        }

        self::assertSame('draft', $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId().'/revisions/3', token: $token))['state']);
    }

    public function testTheRevisionsAndTheLinesAreReadInAFixedNumberOfStatements(): void
    {
        $token = $this->adminToken();
        $counts = [];

        foreach ([1, 4] as $revisions) {
            $quote = $this->quoteOfRevisions($revisions, 3 * $revisions);
            $statements = [];

            foreach (['list' => '/revisions', 'one' => '/revisions/1', 'items' => '/revisions/1/items'] as $name => $suffix) {
                $statements[$name] = $this->recordSqlQueries(function () use ($quote, $token, $suffix): void {
                    self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId().$suffix, token: $token));
                });
            }

            foreach ($statements as $name => $recorded) {
                $counts[$revisions][$name] = [
                    'revisions' => self::countSqlQueriesSelectingFrom(array_values(array_filter($recorded, static fn (string $statement): bool => !str_contains($statement, 'FROM `quote`'))), 'quote_revision'),
                    'items' => self::countSqlQueriesSelectingFrom($recorded, 'quote_item'),
                    'counter-proposals' => self::countSqlQueriesSelectingFrom($recorded, 'quote_item_request'),
                ];
            }
        }

        self::assertSame($counts[1], $counts[4], 'A revision and a line cost no statement.');
        self::assertSame(0, $counts[4]['list']['items'], 'the list reads no line');
        self::assertSame(1, $counts[4]['one']['items']);
        self::assertSame(1, $counts[4]['items']['items']);
    }

    public function testAQuoteIsReadInAFixedNumberOfStatementsWhateverItsRevisions(): void
    {
        $token = $this->adminToken();
        $counts = [];

        foreach ([1, 4] as $revisions) {
            $quote = $this->quoteOfRevisions($revisions, 3 * $revisions);
            $statements = $this->recordSqlQueries(function () use ($quote, $token): void {
                self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId(), token: $token));
            });
            $counts[$revisions] = [
                'revisions' => self::countSqlQueriesSelectingFrom(array_values(array_filter($statements, static fn (string $statement): bool => !str_contains($statement, 'FROM `quote`'))), 'quote_revision'),
                'items' => self::countSqlQueriesSelectingFrom($statements, 'quote_item'),
                'counter-proposals' => self::countSqlQueriesSelectingFrom($statements, 'quote_item_request'),
            ];
        }

        self::assertSame(['revisions' => 1, 'items' => 1, 'counter-proposals' => 1], $counts[1]);
        self::assertSame($counts[1], $counts[4], 'A revision costs no statement.');
    }

    /**
     * The quote answered, countered on its first line, and a draft of the shop after that: revision 1
     * the request, 2 the answer, 3 the draft with one line.
     *
     * @return array{buyer: \Thelia\Model\Customer, quote: Quote, a: \Thelia\Model\Product, b: \Thelia\Model\Product, c: \Thelia\Model\Product}
     */
    private function negotiatedQuote(): array
    {
        $this->admin = $this->createFixtureFactory()->admin();
        $scenario = $this->quotedQuote(null, $this->admin);
        $quote = $scenario['quote'];
        $lines = $this->lineIdsOfRevision($quote, 2);
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput($lines[0], null, '85')]);
        $this->quotes->saveDraft($quote, null, new QuoteDraftInput([new QuoteDraftLine(null, $this->saleElementId($scenario['c']), 7.0, '19')]));

        return $scenario;
    }

    private function quoteOfRevisions(int $revisions, int $lines): Quote
    {
        $buyer = $this->customer();
        $lineSpecs = [];

        for ($line = 0; $line < $lines; ++$line) {
            $lineSpecs[] = [$this->product('10', 'Product '.$line), 1.0, '10'];
        }

        $quote = $this->quotes->requestFromCart($this->cartOf($buyer, $lineSpecs), $buyer, new QuoteRequestInput());

        for ($number = 1; $number < $revisions; ++$number) {
            $current = [...QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber($number)->endUse()->orderByPosition()->find()];
            $this->quotes->saveDraft($quote, null, new QuoteDraftInput(array_map(
                static fn ($item): QuoteDraftLine => new QuoteDraftLine((int) $item->getId(), (int) $item->getProductSaleElementsId(), (float) $item->getQuantity(), (string) $item->getPrice()),
                $current,
            )));
            $this->quotes->send($quote, null);
            // The buyer answers, so that the shop may write the next one.
            $sent = $this->lineIdsOfRevision($quote, $number + 1);
            $this->quotes->counterOffer($quote, $buyer, $number + 1, [new QuoteItemRequestInput($sent[0], 2.0, null)]);
        }

        return $quote;
    }

    /**
     * @return list<int>
     */
    private function lineIdsOfRevision(Quote $quote, int $number): array
    {
        return array_map(
            static fn ($line): int => (int) $line->getId(),
            [...QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber($number)->endUse()->orderByPosition()->find()],
        );
    }

    /**
     * @param list<string> $accesses
     */
    private function profileWith(array $accesses): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([AdminResources::QUOTE => $accesses]);
    }
}
