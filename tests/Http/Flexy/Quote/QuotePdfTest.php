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

namespace Thelia\Tests\Http\Flexy\Quote;

use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * The document of a quote is served by a route that knows who asks: the buyer who may read the quote gets
 * the revision they name (the one the quote stands at when they name none), and a draft, a revision that
 * was never sent and a quote out of reach answer a 404. The route is limited, since the document is made
 * again at each call.
 */
final class QuotePdfTest extends QuoteFlexyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $template = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();

        if (!is_file($template->getAbsolutePath().'/quote.html.twig')) {
            self::markTestSkipped('The active PDF template has no quote document.');
        }
    }

    public function testTheBuyerGetsTheDocumentOfTheRevisionTheQuoteStandsAt(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $this->get($this->url('account_quote_pdf', ['quoteId' => $scenario['quote']->getId()]));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF', (string) $response->getContent());
        self::assertStringContainsString('attachment; filename='.$scenario['quote']->getRef().'.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function testTheBuyerGetsAnEarlierRevisionThatWasSent(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        foreach ([1, 2] as $revision) {
            $this->get($this->url('account_quote_pdf', ['quoteId' => $scenario['quote']->getId(), 'revision' => $revision]));

            self::assertSame(200, $this->statusCode(), 'revision '.$revision);
            self::assertStringStartsWith('%PDF', (string) $this->client->getResponse()->getContent());
        }
    }

    public function testTheDocumentIsOfTheRevisionNamedAndOfTheCurrentOneWhenNoneIs(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);
        $id = $scenario['quote']->getId();

        $current = $this->textOfTheDocument($this->url('account_quote_pdf', ['quoteId' => $id]));
        $first = $this->textOfTheDocument($this->url('account_quote_pdf', ['quoteId' => $id, 'revision' => 1]));

        self::assertStringContainsString('1620.00', $current, 'The revision the quote stands at: the answer of the shop.');
        self::assertStringNotContainsString('1440.00', $current);
        self::assertStringContainsString('1440.00', $first, 'The request of the buyer, at the catalogue prices.');
        self::assertStringNotContainsString('1620.00', $first);
    }

    public function testARevisionThatDoesNotExistOrWasNeverSentIsNotFound(): void
    {
        $scenario = $this->quotedQuote();
        $revision = $this->negotiateToADraft($scenario);
        $this->signInAs($scenario['buyer']);

        foreach ([99, $revision] as $number) {
            $this->get($this->url('account_quote_pdf', ['quoteId' => $scenario['quote']->getId(), 'revision' => $number]));

            self::assertSame(404, $this->statusCode(), 'revision '.$number);
        }
    }

    public function testTheDocumentOfAnotherCustomerOrAnotherCompanyIsNotFound(): void
    {
        $scenario = $this->quotedQuote();
        $other = $this->quotedQuote($this->buyer($this->company('Globex')));

        $this->signInAs($scenario['buyer']);
        $this->get($this->url('account_quote_pdf', ['quoteId' => $other['quote']->getId()]));
        self::assertSame(404, $this->statusCode());

        $this->signInAs($other['buyer']);
        $this->get($this->url('account_quote_pdf', ['quoteId' => $scenario['quote']->getId()]));
        self::assertSame(404, $this->statusCode());
        self::assertStringNotContainsString('%PDF', (string) $this->client->getResponse()->getContent());
    }

    public function testTheDocumentsAnAccountAsksForAreLimited(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);
        $statuses = [];

        // A revision that does not exist is answered without making a document: the budget is spent all the same.
        for ($attempt = 0; $attempt < 24; ++$attempt) {
            $this->get($this->url('account_quote_pdf', ['quoteId' => $scenario['quote']->getId(), 'revision' => 99]));
            $statuses[] = $this->statusCode();
        }

        self::assertSame(Response::HTTP_NOT_FOUND, $statuses[0]);
        self::assertContains(Response::HTTP_TOO_MANY_REQUESTS, $statuses);
        self::assertSame(20, array_search(Response::HTTP_TOO_MANY_REQUESTS, $statuses, true), 'Twenty documents, then the limit.');
    }

    public function testTheBudgetOfOneAccountIsNotTheOneOfAnother(): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();
        $this->signInAs($first['buyer']);

        for ($attempt = 0; $attempt < 22; ++$attempt) {
            $this->get($this->url('account_quote_pdf', ['quoteId' => $first['quote']->getId(), 'revision' => 99]));
        }
        self::assertSame(429, $this->statusCode());

        $this->signInAs($second['buyer']);
        $this->get($this->url('account_quote_pdf', ['quoteId' => $second['quote']->getId()]));
        self::assertSame(200, $this->statusCode());
    }

    private function textOfTheDocument(string $url): string
    {
        $this->get($url);

        self::assertSame(200, $this->statusCode(), $url);

        // Amounts are grouped by a space, a thin or a no-break one: they are compared without any.
        return (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', (new Parser())->parseContent((string) $this->client->getResponse()->getContent())->getText());
    }

    /**
     * The buyer counters, and the shop saves the next revision without sending it: the number of that draft.
     *
     * @param array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product} $scenario
     */
    private function negotiateToADraft(array $scenario): int
    {
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->filterByNumber(2)->findOne();
        $lines = [];
        $lineId = 0;

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $lineId = $lineId ?: (int) $line->getId();
            $lines[] = new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), (float) $line->getQuantity(), (string) $line->getPrice());
        }

        $this->quotes->counterOffer($scenario['quote'], $scenario['buyer'], 2, [new QuoteItemRequestInput($lineId, null, '80')]);
        $this->quotes->saveDraft($scenario['quote'], null, new QuoteDraftInput($lines, '30', '15'));

        return 3;
    }
}
