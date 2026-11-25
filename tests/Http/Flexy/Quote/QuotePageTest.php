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

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Model\ConfigQuery;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionQuery;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The page of a quote as its buyer reads it: the lines with the catalogue price beside the negotiated
 * one, the figures of the revision the quote stands at, the word of the shop as text, the state the quote
 * reads in, and the history that comes only when it is opened.
 */
final class QuotePageTest extends QuoteFlexyTestCase
{
    use RecordsSqlQueries;

    public function testTheLinesShowTheCatalogueAndTheNegotiatedPricesSideBySide(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        $headers = $page->filter('[data-testid="quote-lines"] thead th')->each(static fn (Crawler $header): string => trim($header->text()));
        self::assertSame(['Product', 'Quantity', 'Catalogue price', 'Negotiated price'], $headers);

        $lines = $page->filter('[data-testid="quote-line"]');
        self::assertCount(3, $lines, 'The line the shop added is there.');
        $a = $lines->eq(0);
        self::assertStringContainsString('Product A', $a->text());
        self::assertSame('12', trim($a->filter('[data-testid="quote-line-quantity"]')->text()));
        self::assertStringContainsString('100.00', $a->filter('[data-testid="quote-line-catalogue-price"]')->text());
        self::assertStringContainsString('90.00', $a->filter('[data-testid="quote-line-price"]')->text());
        self::assertStringContainsString('45.00', $lines->eq(1)->filter('[data-testid="quote-line-price"]')->text());
        self::assertStringContainsString('20.00', $lines->eq(2)->filter('[data-testid="quote-line-price"]')->text());
    }

    public function testTheFiguresAreTheOnesTheShopSent(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        self::assertStringContainsString('30.00', $page->filter('[data-testid="quote-discount"]')->text());
        self::assertStringContainsString('15.00', $page->filter('[data-testid="quote-postage"]')->text());
        self::assertStringContainsString('1,350.00', $page->filter('[data-testid="quote-total-untaxed"]')->text());
        self::assertStringContainsString('1,620.00', $page->filter('[data-testid="quote-total-taxed"]')->text());
        self::assertSame('2', trim($page->filter('[data-testid="quote-revision-number"]')->text()));
        self::assertSame('quoted', $page->filter('[data-testid="quote-state"]')->attr('data-state'));
        self::assertNotSame('', trim($page->filter('[data-testid="quote-valid-until"]')->text()));
    }

    public function testTheDiscountGrantedOnTheLinesIsTheGapToTheCataloguePricesOfTheLines(): void
    {
        $scenario = $this->quotedQuote();
        // 12 of A at 90 against 100, 4 of B at 45 against 50, 5 of C at its catalogue price: 120 + 20 + 0 = 140 over 1 500.
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        $granted = $page->filter('[data-testid="quote-granted-discount"]');
        self::assertCount(1, $granted);
        self::assertStringContainsString('140.00', $granted->text());
        self::assertStringContainsString('9.33%', preg_replace('/\s+/u', '', $granted->text()) ?? '');
    }

    public function testWhatTheBuyerAskedForHasNoNegotiatedColumnYet(): void
    {
        $scenario = $this->requestedQuote(null, 'Before the 15th, please.');
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        self::assertCount(0, $page->filter('[data-testid="quote-line-price"]'));
        self::assertCount(0, $page->filter('[data-testid="quote-granted-discount"]'), 'Nothing is granted before the shop answers.');
        self::assertCount(2, $page->filter('[data-testid="quote-line"]'));
        self::assertSame('requested', $page->filter('[data-testid="quote-state"]')->attr('data-state'));
        self::assertSame('Before the 15th, please.', trim($page->filter('[data-testid="quote-buyer-comment"]')->text()));
        self::assertStringContainsString('1,440.00', $page->filter('[data-testid="quote-total-taxed"]')->text());
    }

    public function testTheWordOfTheShopIsTextAndNeverMarkup(): void
    {
        // The core keeps the word of the shop as text; a row written before that, tags included, is still printed as text.
        $scenario = $this->quotedQuote(null, 'Thank you');
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->filterByNumber(2)->findOne() ?? self::fail('No revision.');
        $revision->setMerchantNote("Thank you <b>Bob</b>.\n<script>window.hacked = 1</script> A & B")->save($this->getPropelConnection());
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());
        $html = (string) $this->client->getResponse()->getContent();

        self::assertCount(1, $page->filter('[data-testid="quote-merchant-note"]'));
        self::assertStringContainsString('Thank you', $page->filter('[data-testid="quote-merchant-note"]')->text());
        self::assertCount(0, $page->filter('[data-testid="quote-merchant-note"] b, [data-testid="quote-merchant-note"] script'), 'No element comes out of the word of the shop.');
        self::assertStringNotContainsString('<script>window.hacked', $html);
        self::assertStringContainsString('Thank you <b>Bob</b>.', $page->filter('[data-testid="quote-merchant-note"]')->text(), 'The tags are read as the characters they are.');
        self::assertStringContainsString('A & B', $page->filter('[data-testid="quote-merchant-note"]')->text(), 'An ampersand comes out once, not as an entity.');
    }

    public function testTheValidityIsTheOneOfTheRevisionThatWasSent(): void
    {
        $scenario = $this->quotedQuote(validUntil: new \DateTimeImmutable('2031-03-14'));
        $scenario['quote']->setValidUntil(new \DateTimeImmutable('2031-09-09'))->save($this->getPropelConnection());
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        $text = $page->filter('[data-testid="quote-valid-until"]')->text();
        self::assertStringContainsString('2031', $text);
        self::assertMatchesRegularExpression('/Mar|03|14/', $text, 'The day of the revision.');
        self::assertDoesNotMatchRegularExpression('/Sep|09\/09|9, 2031/', $text, 'Not the day written on the quote.');
    }

    public function testTheAddressesAreTheCopiesTheRevisionWasSentWith(): void
    {
        $buyer = $this->customer();
        $address = $this->factory->address($buyer, null, null, ['label' => 'Warehouse', 'address1' => '12 Dock Road', 'city' => 'Lyon']);
        $scenario = $this->requestedQuote($buyer, address: $address);
        $this->shopAnswers($scenario['quote'], $scenario);
        // The address book moves on; what the quote was sent with does not.
        $address->setAddress1('99 Another Street')->setCity('Nantes')->save($this->getPropelConnection());
        $this->signInAs($buyer);

        $page = $this->openQuote($scenario['quote']->getId());

        $addresses = $page->filter('[data-testid="quote-addresses"]')->text();
        self::assertStringContainsString('12 Dock Road', $addresses);
        self::assertStringContainsString('Lyon', $addresses);
        self::assertStringNotContainsString('Another Street', $addresses);
        self::assertStringNotContainsString('Nantes', $addresses);
    }

    public function testTheReverseChargeOfTheRevisionIsSaidAndOnlyWhenItApplies(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->filterByNumber(2)->findOne() ?? self::fail('No revision.');

        self::assertCount(0, $this->openQuote($scenario['quote']->getId())->filter('[data-testid="quote-reverse-charge"]'));

        $revision->setReverseCharge(1)->save($this->getPropelConnection());

        $page = $this->openQuote($scenario['quote']->getId());
        self::assertCount(1, $page->filter('[data-testid="quote-reverse-charge"]'));
        self::assertStringContainsString('Reverse charge', $page->filter('[data-testid="quote-reverse-charge"]')->text());
        self::assertStringContainsString('€1,620.00', $page->filter('[data-testid="quote-total-taxed"]')->text(), 'The amounts keep their taxes.');
    }

    public function testAQuoteThatWasOrderedLeadsToItsOrder(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);
        $page = $this->openQuote($scenario['quote']->getId());
        self::assertCount(0, $page->filter('[data-testid="quote-order"]'), 'Nothing to lead to yet.');

        $order = $this->factory->order($scenario['buyer']);
        $order->setQuoteId($scenario['quote']->getId())->save($this->getPropelConnection());
        $this->reread($scenario['quote'])->setState('ordered')->save($this->getPropelConnection());

        $link = $this->openQuote($scenario['quote']->getId())->filter('[data-testid="quote-order"] a');

        self::assertCount(1, $link);
        self::assertSame($this->url('account_order', ['orderId' => $order->getId()]), $link->attr('href'));
        self::assertStringContainsString((string) $order->getRef(), $link->text());
    }

    public function testTheCommentOfTheBuyerIsTextAndNeverMarkupEvenWhenTheStoredOneHoldsTags(): void
    {
        $scenario = $this->requestedQuote();
        $scenario['quote']->setBuyerComment('Before <b>Friday</b> <img src=x onerror=alert(1)>')->save($this->getPropelConnection());
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        self::assertCount(0, $page->filter('[data-testid="quote-buyer-comment"] b, [data-testid="quote-buyer-comment"] img'));
        self::assertStringNotContainsString('<img src=x', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Before', $page->filter('[data-testid="quote-buyer-comment"]')->text());
    }

    public function testAnExpiredQuoteSaysSoAndOffersAnExtensionByMailButNoAnswer(): void
    {
        $scenario = $this->quotedQuote();
        $this->expire($scenario['quote']);
        ConfigQuery::write('store_email', 'shop@example.com');
        ConfigQuery::resetCache();
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        self::assertSame('expired', $page->filter('[data-testid="quote-state"]')->attr('data-state'));
        self::assertCount(1, $page->filter('[data-testid="quote-expired"]'));
        $link = (string) $page->filter('[data-testid="quote-extension-link"]')->attr('href');
        self::assertStringStartsWith('mailto:shop@example.com?subject=', $link);
        self::assertStringContainsString(rawurlencode((string) $scenario['quote']->getRef()), $link, 'The reference of the quote is the subject.');
        self::assertCount(0, $page->filter('[data-testid="quote-actions"] form, [data-testid="quote-actions"] button, [data-testid="quote-actions"] a'), 'Nothing can be accepted.');
    }

    public function testAnExpiredQuoteOfAShopWithoutAnAddressSaysHowToGetAnExtension(): void
    {
        $scenario = $this->quotedQuote();
        $this->expire($scenario['quote']);
        ConfigQuery::write('store_email', '');
        ConfigQuery::resetCache();
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        self::assertCount(0, $page->filter('[data-testid="quote-extension-link"]'));
        self::assertStringContainsString('To get an extension, contact the shop', $page->filter('[data-testid="quote-extension-contact"]')->text());
    }

    public function testAQuoteThatHasNotExpiredIsNotOfferedAnExtension(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        self::assertCount(0, $page->filter('[data-testid="quote-expired"]'));
        self::assertCount(0, $page->filter('[data-testid="quote-extension-link"]'));
        self::assertCount(0, $page->filter('[data-testid="quote-extension-contact"]'));
    }

    public function testTheCounterProposalOfTheBuyerIsShownBesideTheLine(): void
    {
        $scenario = $this->quotedQuote();
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->filterByNumber(2)->findOne();
        $line = QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->filterByProductRef($scenario['a']->getRef())->findOne();
        $this->quotes->counterOffer($scenario['quote'], $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $line->getId(), null, '85')]);
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        self::assertSame('countered', $page->filter('[data-testid="quote-state"]')->attr('data-state'));
        $counters = $page->filter('[data-testid="quote-line-counter-offer"]');
        self::assertStringContainsString('85.00', $counters->eq(0)->text());
        self::assertSame('', trim($counters->eq(1)->text()), 'A line that was not countered carries nothing.');
    }

    public function testTheHistoryIsNotInThePageButInTheFragmentItLoadsWhenOpened(): void
    {
        $scenario = $this->quotedQuote();
        $current = $this->negotiate($scenario);
        self::assertSame(3, $current);
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        $frame = $page->filter('details[data-testid="quote-history"] turbo-frame#quote-history');
        self::assertSame('lazy', $frame->attr('loading'));
        self::assertCount(1, $page->filter('details[data-testid="quote-history"][data-turbo="true"]'), 'Turbo is only loaded on a page with a zone that asks for it.');
        self::assertSame($this->url('account_quote_history', ['quoteId' => $scenario['quote']->getId()]), $frame->attr('src'));
        self::assertCount(0, $page->filter('[data-testid="quote-revision"]'), 'No revision of the history is in the page.');
        self::assertCount(1, $page->filter('[data-testid="quote-lines"]'), 'The page carries the lines of the current revision only.');
        self::assertSame('3', trim($page->filter('[data-testid="quote-revision-number"]')->text()));
        self::assertStringContainsString('87.00', $page->filter('[data-testid="quote-line-price"]')->first()->text());

        $fragment = $this->get($this->url('account_quote_history', ['quoteId' => $scenario['quote']->getId()]));

        self::assertSame(200, $this->statusCode());
        self::assertCount(1, $fragment->filter('turbo-frame#quote-history'));
        $numbers = $fragment->filter('[data-testid="quote-revision"]')->each(static fn (Crawler $revision): string => (string) $revision->attr('data-number'));
        self::assertSame(['3', '2', '1'], $numbers, 'Three revisions, the latest first.');
        self::assertCount(0, $fragment->filter('[data-testid="quote-lines"]'), 'The history lists the revisions: their lines come when one is opened.');
        $lazyFrames = $fragment->filter('[data-testid="quote-revision"] turbo-frame[loading="lazy"]')->each(static fn (Crawler $frame): string => (string) $frame->attr('src'));
        self::assertSame([
            $this->url('account_quote_revision', ['quoteId' => $scenario['quote']->getId(), 'number' => 3]),
            $this->url('account_quote_revision', ['quoteId' => $scenario['quote']->getId(), 'number' => 2]),
            $this->url('account_quote_revision', ['quoteId' => $scenario['quote']->getId(), 'number' => 1]),
        ], $lazyFrames);

        $second = $this->get($this->url('account_quote_revision', ['quoteId' => $scenario['quote']->getId(), 'number' => 2]));

        self::assertSame(200, $this->statusCode());
        self::assertCount(1, $second->filter('turbo-frame#quote-revision-2'));
        self::assertCount(3, $second->filter('[data-testid="quote-line"]'));
        self::assertStringContainsString('85.00', $second->filter('[data-testid="quote-line-counter-offer"]')->text(), 'The counter-proposal on the revision it discussed.');
    }

    public function testTheFirstRenderOfThePageReadsNeitherTheHistoryNorTheLinesOfTheOlderRevisions(): void
    {
        $withTwo = $this->quotedQuote();
        $withFive = $this->quotedQuote();
        $this->negotiate($withFive);
        $this->negotiate($withFive, '80', '82');
        $this->negotiate($withFive, '75', '77');
        $reads = [];

        foreach ([$withTwo, $withFive] as $scenario) {
            $this->signInAs($scenario['buyer']);
            $statements = $this->recordSqlQueries(function () use ($scenario): void {
                $this->openQuote($scenario['quote']->getId());
            });
            $reads[] = self::countSqlQueriesSelectingFrom($statements, 'quote_item');
        }

        self::assertGreaterThan(0, $reads[0], 'The lines of the current revision are read.');
        self::assertSame($reads[0], $reads[1], 'Three more revisions cost the page no read of lines.');

        $this->signInAs($withFive['buyer']);
        $statements = $this->recordSqlQueries(function () use ($withFive): void {
            $this->get($this->url('account_quote_history', ['quoteId' => $withFive['quote']->getId()]));
        });
        self::assertSame(200, $this->statusCode());
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'quote_item'), 'Opening the history reads the revisions, not their lines.');
    }

    public function testARevisionThatDoesNotExistOrIsTheDraftOfTheShopIsNotFound(): void
    {
        $scenario = $this->quotedQuote();
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->filterByNumber(2)->findOne();
        $line = QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->filterByProductRef($scenario['a']->getRef())->findOne();
        $this->quotes->counterOffer($scenario['quote'], $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $line->getId(), null, '85')]);
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfThe($revision, '60'));
        $this->signInAs($scenario['buyer']);
        $id = $scenario['quote']->getId();

        foreach ([3 => 'the draft', 9 => 'a number that does not exist'] as $number => $what) {
            $this->get($this->url('account_quote_revision', ['quoteId' => $id, 'number' => $number]));

            self::assertSame(404, $this->statusCode(), $what);
        }

        $this->get($this->url('account_quote_revision', ['quoteId' => $id, 'number' => 2]));
        self::assertSame(200, $this->statusCode());
    }

    public function testTheShopDraftOfTheNextRevisionIsNotInThePageNorInTheHistory(): void
    {
        $scenario = $this->quotedQuote();
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->filterByNumber(2)->findOne();
        $line = QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->filterByProductRef($scenario['a']->getRef())->findOne();
        $this->quotes->counterOffer($scenario['quote'], $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $line->getId(), null, '85')]);
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfThe($revision, '60'));
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());
        self::assertSame('2', trim($page->filter('[data-testid="quote-revision-number"]')->text()));
        self::assertStringNotContainsString('60.00', $page->filter('[data-testid="quote-lines"]')->text());

        $fragment = $this->get($this->url('account_quote_history', ['quoteId' => $scenario['quote']->getId()]));
        $numbers = $fragment->filter('[data-testid="quote-revision"]')->each(static fn (Crawler $revision): string => (string) $revision->attr('data-number'));
        self::assertSame(['2', '1'], $numbers);
        self::assertStringNotContainsString('60.00', $fragment->filter('turbo-frame')->text());
    }

    public function testAQuoteTheShopStartedAndHasNotSentIsNotThere(): void
    {
        $buyer = $this->customer();
        $draft = $this->quotes->createForCustomer($buyer, null);
        $this->signInAs($buyer);

        foreach (['account_quote', 'account_quote_history', 'account_quote_pdf'] as $route) {
            $this->get($this->url($route, ['quoteId' => $draft->getId()]));

            self::assertSame(404, $this->statusCode(), $route);
        }

        self::assertStringNotContainsString((string) $draft->getRef(), $this->get('/account/quotes')->filter('body')->text());
    }

    public function testTheRouteOfTheQuoteIsNamedAccountQuoteAndEndsWithItsNumber(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);
        $id = $scenario['quote']->getId();

        self::assertSame('/account/quote/'.$id, $this->url('account_quote', ['quoteId' => $id]));
        $this->get('/account/quote/'.$id);
        self::assertSame(200, $this->statusCode());
    }

    public function testTheAddressesOfTheQuoteAreShown(): void
    {
        $buyer = $this->customer();
        $delivery = $this->factory->address($buyer, null, null, ['label' => 'Warehouse', 'address1' => '12 Dock Road', 'city' => 'Lyon']);
        $this->signInAs($buyer);
        $quote = $this->quotes->requestFromCart(
            $this->cartOf($buyer, [[$this->product('10', 'Product S'), 1.0, '10']]),
            $buyer,
            new QuoteRequestInput(null, null, null, (int) $delivery->getId()),
        );

        $page = $this->openQuote($quote->getId());

        self::assertStringContainsString('12 Dock Road', $page->filter('[data-testid="quote-addresses"]')->text());
        self::assertStringContainsString('Lyon', $page->filter('[data-testid="quote-addresses"]')->text());
    }

    public function testNoPageOfTheQuotesIsMeantForASharedCache(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInWithCart($scenario['buyer'], $this->cartOf($scenario['buyer'], [[$this->product('10', 'Product S'), 1.0, '10']]));
        $id = $scenario['quote']->getId();

        foreach ([
            '/account/quotes',
            '/account/quote/request',
            $this->url('account_quote', ['quoteId' => $id]),
            $this->url('account_quote_history', ['quoteId' => $id]),
        ] as $path) {
            $this->get($path);

            self::assertSame(200, $this->statusCode(), $path);
            $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
            self::assertStringContainsString('private', $cacheControl, $path);
            self::assertStringNotContainsString('public', $cacheControl, $path);
            self::assertStringNotContainsString('s-maxage', $cacheControl, $path);
        }
    }

    public function testThePageOffersTheDocumentOfTheQuote(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $page = $this->openQuote($scenario['quote']->getId());

        self::assertSame($this->url('account_quote_pdf', ['quoteId' => $scenario['quote']->getId()]), $page->filter('[data-testid="quote-pdf"]')->attr('href'));
    }

    private function openQuote(int $quoteId): Crawler
    {
        $page = $this->get($this->url('account_quote', ['quoteId' => $quoteId]));

        self::assertSame(200, $this->statusCode());

        return $page;
    }

    private function draftOfThe(QuoteRevision $revision, string $aPrice): QuoteDraftInput
    {
        $lines = [];

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $lines[] = new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), (float) $line->getQuantity(), 'Product A' === $line->getTitle() ? $aPrice : (string) $line->getPrice());
        }

        return new QuoteDraftInput($lines, '30', '15');
    }
}
