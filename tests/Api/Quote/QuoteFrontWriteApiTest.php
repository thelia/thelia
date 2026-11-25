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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CurrencyTableMap;
use Thelia\Model\Map\QuoteItemTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteItemRequestQuery;
use Thelia\Model\QuoteQuery;

/**
 * What a buyer writes on the quotes over HTTP: asking for a quote from a cart, countering a revision,
 * turning it down, accepting it. The body is read field by field and the quote facade judges the rest.
 */
final class QuoteFrontWriteApiTest extends QuoteApiTestCase
{
    public function testABuyerAsksForAQuoteFromTheirCartAndTheCartStaysAsItIs(): void
    {
        $buyer = $this->customer();
        $cart = $this->cartOf($buyer, [[$this->product('100', 'Product A'), 10.0, '100'], [$this->product('50', 'Product B'), 4.0, '50']]);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes', [
            'cartId' => $cart->getId(),
            'comment' => 'We need <b>fast</b> delivery',
            'neededBy' => (new \DateTimeImmutable('+40 days'))->format('Y-m-d'),
        ], $this->token($buyer), 'json');

        self::assertSame(201, $response->getStatusCode());
        $body = $this->body($response);
        self::assertSame('requested', $body['state']);
        self::assertSame(1, $body['currentRevision']);
        self::assertSame('We need fast delivery', $body['buyerComment']);
        self::assertArrayNotHasKey('revisions', $body);

        $revision = $this->body($this->jsonRequest('GET', '/api/front/account/quotes/'.$body['id'].'/revisions/1', token: $this->token($buyer)));
        self::assertSame('customer', $revision['author']);
        self::assertSame('sent', $revision['state']);
        self::assertEquals([[10.0, '100.000000', '100.000000'], [4.0, '50.000000', '50.000000']], array_map(
            static fn (array $line): array => [$line['quantity'], $line['catalogPrice'], $line['price']],
            $revision['items'],
        ));
        self::assertSame(2, CartItemQuery::create()->filterByCartId($cart->getId())->count(), 'The cart is left as it is.');
        self::assertSame((int) $buyer->getId(), QuoteQuery::create()->findPk($body['id'])->getCustomerId());
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[DataProvider('requestsThatTakeWhatTheShopWrites')]
    public function testARequestThatNamesAnythingButItsOwnFieldsIsRefused(array $extra): void
    {
        $buyer = $this->customer();
        $cart = $this->cartOf($buyer, [[$this->product('100', 'Product A'), 1.0, '100']]);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $cart->getId()] + $extra, $this->token($buyer), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, QuoteQuery::create()->count());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function requestsThatTakeWhatTheShopWrites(): iterable
    {
        yield 'a state' => [['state' => 'accepted']];
        yield 'a reference' => [['ref' => 'QUO00000001']];
        yield 'revisions with prices' => [['revisions' => [['items' => [['price' => '1']]]]]];
        yield 'a customer' => [['customerId' => 1]];
        yield 'a wrong type' => [['comment' => ['not', 'a', 'string']]];
        yield 'a day that is not one' => [['neededBy' => '31/12/2030']];
        yield 'a day that does not exist' => [['neededBy' => '2031-02-30']];
    }

    public function testABodyThatIsNotJsonIsABadRequest(): void
    {
        $buyer = $this->customer();
        $this->client->request('POST', '/api/front/account/quotes', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token($buyer),
        ], content: '{not json');

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testAShopThatTakesNoQuoteAnswersWithTheReasonInTheBody(): void
    {
        ConfigQuery::write('quote_mode', 'disabled');
        ConfigQuery::resetCache();
        $buyer = $this->customer();
        $cart = $this->cartOf($buyer, [[$this->product('100', 'Product A'), 1.0, '100']]);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $cart->getId()], $this->token($buyer), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('quote_disabled', $this->reason($response));
        self::assertSame('This shop does not take requests for quotes.', $this->body($response)['detail']);
    }

    public function testTheReasonIsInTheBodyOfAJsonLdErrorToo(): void
    {
        $scenario = $this->requestedQuote();

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$scenario['quote']->getId().'/reject', ['revision' => 1], $this->token($scenario['buyer']));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('transition_refused', $this->body($response)['reason']);
    }

    public function testABuyerCountersLinesOfTheRevisionAndTheShopIsWaitedFor(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $lines = $this->linesOfRevision($quote, 2);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', [
            'revision' => 2,
            'lines' => [
                ['quoteItemId' => $lines[0], 'price' => '85'],
                ['quoteItemId' => $lines[1], 'quantity' => 6, 'price' => 40.5],
            ],
        ], $this->token($scenario['buyer']), 'json');

        self::assertSame(200, $response->getStatusCode());
        $body = $this->body($response);
        self::assertSame('countered', $body['state']);
        self::assertSame(2, $body['currentRevision'], 'A counter-proposal creates no revision.');
        self::assertArrayNotHasKey('revisions', $body);
        $items = $this->frontRevision($quote, 2, $this->token($scenario['buyer']))['items'];
        self::assertSame(['quantity' => null, 'price' => '85.000000'], $items[0]['counterOffer']);
        self::assertEquals(['quantity' => 6.0, 'price' => '40.500000'], $items[1]['counterOffer']);
        self::assertArrayNotHasKey('counterOffer', $items[2], 'A line nobody countered carries none.');
        self::assertSame('90.000000', $items[0]['price'], 'The price of the shop is not touched.');
        self::assertSame(3, QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber(2)->endUse()->count(), 'No line was added or removed.');
    }

    /**
     * @param array<string, mixed> $line
     */
    #[DataProvider('counterOffersThatWriteMoreThanTheirOwnFields')]
    public function testACounterProposalWritesTheQuantityAndThePriceAskedForAndNothingElse(array $line): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $first = $this->linesOfRevision($quote, 2)[0];
        $before = $this->snapshotOfLines($quote);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', [
            'revision' => 2,
            'lines' => [['quoteItemId' => $first] + $line],
        ], $this->token($scenario['buyer']), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame($before, $this->snapshotOfLines($quote), 'No price of the shop was written.');
        self::assertSame('quoted', $this->stateOf($quote));
        self::assertSame(0, QuoteItemRequestQuery::create()->count());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function counterOffersThatWriteMoreThanTheirOwnFields(): iterable
    {
        yield 'the proposed price' => [['proposedPrice' => '1']];
        yield 'the catalog price' => [['catalogPrice' => '1', 'price' => '80']];
        yield 'the title' => [['title' => 'Free', 'price' => '80']];
        yield 'the sale element' => [['productSaleElementsId' => 1, 'price' => '80']];
        yield 'a price that is not a number' => [['price' => 'cheap']];
        yield 'a negative price' => [['price' => '-5']];
        yield 'a negative quantity' => [['quantity' => -1]];
        yield 'a quantity as a string' => [['quantity' => '3']];
        yield 'nothing asked' => [[]];
    }

    public function testNeitherANewLineNorTheOneOfAnotherRevisionCanBeCountered(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $before = $this->snapshotOfLines($quote);

        $ofTheRequest = $this->linesOfRevision($quote, 1)[0];
        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', [
            'revision' => 2,
            'lines' => [['quoteItemId' => $ofTheRequest, 'price' => '1']],
        ], $this->token($scenario['buyer']), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('counter_unknown_line', $this->reason($response));

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', [
            'revision' => 2,
            'lines' => [['quoteItemId' => 999999999, 'quantity' => 1]],
        ], $this->token($scenario['buyer']), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame($before, $this->snapshotOfLines($quote));
        self::assertSame(0, QuoteItemRequestQuery::create()->count());
    }

    public function testARevisionThatIsNoLongerTheCurrentOneIsRefusedWithItsReason(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];

        foreach (['counter-offer' => ['revision' => 1, 'lines' => [['quoteItemId' => $this->linesOfRevision($quote, 2)[0], 'price' => '80']]], 'accept' => ['revision' => 1], 'reject' => ['revision' => 1]] as $operation => $body) {
            $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/'.$operation, $body, $this->token($scenario['buyer']), 'json');

            self::assertSame(422, $response->getStatusCode(), $operation);
            self::assertSame('revision_stale', $this->reason($response), $operation);
        }

        self::assertSame('quoted', $this->stateOf($quote));
    }

    public function testAQuoteThatIsNotWaitingForTheBuyerRefusesEachAnswerAsAConflict(): void
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];
        $token = $this->token($scenario['buyer']);

        foreach ([
            'counter-offer' => ['revision' => 1, 'lines' => [['quoteItemId' => $this->linesOfRevision($quote, 1)[0], 'price' => '80']]],
            'accept' => ['revision' => 1],
            'reject' => ['revision' => 1],
        ] as $operation => $body) {
            $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/'.$operation, $body, $token, 'json');

            self::assertSame(409, $response->getStatusCode(), $operation);
            self::assertSame('transition_refused', $this->reason($response), $operation);
        }

        self::assertSame('requested', $this->stateOf($quote));
        self::assertSame(0, CartQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testACounteredQuoteCannotBeCounteredAgainOrAcceptedBeforeTheShopSendsAgain(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $token = $this->token($scenario['buyer']);
        $line = $this->linesOfRevision($quote, 2)[0];

        self::assertSame(200, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', ['revision' => 2, 'lines' => [['quoteItemId' => $line, 'price' => '80']]], $token, 'json')->getStatusCode());

        self::assertSame(409, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', ['revision' => 2, 'lines' => [['quoteItemId' => $line, 'price' => '70']]], $token, 'json')->getStatusCode());
        self::assertSame(409, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $token, 'json')->getStatusCode());
        self::assertSame('countered', $this->stateOf($quote));
        self::assertSame(1, QuoteItemRequestQuery::create()->count());
    }

    public function testABuyerTurnsAQuoteDownAndItStaysDown(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $token = $this->token($scenario['buyer']);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/reject', ['revision' => 2], $token, 'json');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('rejected', $this->body($response)['state']);
        self::assertSame(409, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $token, 'json')->getStatusCode());
        self::assertSame(409, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/reject', ['revision' => 2], $token, 'json')->getStatusCode());
        self::assertSame('rejected', $this->stateOf($quote));
    }

    public function testAcceptingNamesARevisionAndNeverAnAmount(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $token = $this->token($scenario['buyer']);

        foreach ([['revision' => 2, 'price' => '1'], ['revision' => 2, 'total' => '1'], ['revision' => 2, 'lines' => []], ['revision' => '2'], []] as $body) {
            self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', $body, $token, 'json')->getStatusCode(), json_encode($body));
        }

        self::assertSame('quoted', $this->stateOf($quote));
        self::assertSame(0, CartQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testAcceptingGivesTheCartOfTheQuoteAtTheAgreedPrices(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $this->token($scenario['buyer']), 'json');

        self::assertSame(200, $response->getStatusCode());
        $cart = CartQuery::create()->filterByQuoteId($quote->getId())->findOne();
        self::assertNotNull($cart);
        self::assertSame((int) $cart->getId(), $this->body($response)['id']);
        self::assertSame('accepted', $this->stateOf($quote));
        self::assertSame(
            [['12', '90.000000'], ['4', '45.000000'], ['5', '20.000000']],
            array_map(
                static fn ($line): array => [(string) (float) $line->getQuantity(), (string) $line->getPrice()],
                [...CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find()],
            ),
        );

        $again = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $this->token($scenario['buyer']), 'json');
        self::assertSame(200, $again->getStatusCode());
        self::assertSame((int) $cart->getId(), $this->body($again)['id'], 'The buyer who comes back is given the same cart.');
    }

    public function testTheCartOfAQuoteIsNotTurnedIntoANewRequest(): void
    {
        $scenario = $this->quotedQuote();
        $token = $this->token($scenario['buyer']);
        $accepted = $this->jsonRequest('POST', '/api/front/account/quotes/'.$scenario['quote']->getId().'/accept', ['revision' => 2], $token, 'json');
        self::assertSame(200, $accepted->getStatusCode());
        $quotesBefore = QuoteQuery::create()->count();

        $response = $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $this->body($accepted)['id']], $token, 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('quote_cart', $this->reason($response));
        self::assertSame($quotesBefore, QuoteQuery::create()->count());
    }

    public function testAQuoteWhoseLastValidDayIsPastIsExpiredAndCannotBeAccepted(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $quote->setValidUntil((new \DateTimeImmutable('-1 day'))->format('Y-m-d'))->save($this->getPropelConnection());
        $token = $this->token($scenario['buyer']);

        self::assertSame('expired', $this->body($this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $token))['state']);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $token, 'json');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('transition_refused', $this->reason($response));
        self::assertSame(0, CartQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testALineThatCannotBeSoldRefusesTheAcceptanceAndNamesItsReason(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $this->getPropelConnection()->exec('DELETE FROM `product` WHERE `id` = '.(int) $scenario['b']->getId());

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $this->token($scenario['buyer']), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('quote_lines_unavailable', $this->reason($response));
        self::assertSame('quoted', $this->stateOf($quote));
    }

    public function testAQuoteInACurrencyThatIsGoneRefusesTheAcceptanceAndNamesItsReason(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $gone = $this->fixtures->currency(['code' => 'XQG', 'symbol' => 'XG', 'rate' => 1.0]);
        $connection = $this->getPropelConnection();
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
        $connection->exec('UPDATE `quote` SET `currency_id` = '.(int) $gone->getId().' WHERE `id` = '.(int) $quote->getId());
        $connection->exec('DELETE FROM `currency` WHERE `id` = '.(int) $gone->getId());
        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        // The pool still holds the row the statement above took away.
        CurrencyTableMap::clearInstancePool();

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $this->token($scenario['buyer']), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('quote_currency_unavailable', $this->reason($response));
    }

    #[DataProvider('operationsThatDoNotExist')]
    public function testThePricesOfAQuoteCannotBeWrittenFromTheStorefront(string $method, string $uri, string $format): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $before = $this->snapshotOfLines($quote);

        $response = $this->jsonRequest($method, str_replace('{id}', (string) $quote->getId(), $uri), ['price' => '1', 'revisions' => [['items' => [['price' => '1']]]], 'state' => 'accepted'], $this->token($scenario['buyer']), $format);

        self::assertContains($response->getStatusCode(), [401, 403, 404, 405], $method.' '.$uri.' '.$response->getStatusCode());
        self::assertSame($before, $this->snapshotOfLines($quote));
        self::assertSame('quoted', $this->stateOf($quote));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function operationsThatDoNotExist(): iterable
    {
        yield 'put the quote' => ['PUT', '/api/front/account/quotes/{id}', 'json'];
        yield 'patch the quote' => ['PATCH', '/api/front/account/quotes/{id}', 'merge-patch+json'];
        yield 'delete the quote' => ['DELETE', '/api/front/account/quotes/{id}', 'json'];
        yield 'write the draft' => ['PUT', '/api/front/account/quotes/{id}/draft', 'json'];
        yield 'send as the shop' => ['POST', '/api/front/account/quotes/{id}/send', 'json'];
        yield 'reach the administration' => ['POST', '/api/admin/quotes/{id}/send', 'json'];
        yield 'write a line' => ['PATCH', '/api/front/account/quote_items/1', 'merge-patch+json'];
    }

    /**
     * @return list<int>
     */
    private function linesOfRevision(Quote $quote, int $number): array
    {
        return array_map(
            static fn ($line): int => (int) $line->getId(),
            [...QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber($number)->endUse()->orderByPosition()->find()],
        );
    }

    /**
     * @return list<array{0: int, 1: string, 2: string, 3: float}>
     */
    private function snapshotOfLines(Quote $quote): array
    {
        QuoteItemTableMap::clearInstancePool();

        return array_map(
            static fn ($line): array => [(int) $line->getId(), (string) $line->getPrice(), (string) $line->getCatalogPrice(), (float) $line->getQuantity()],
            [...QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->endUse()->orderById()->find()],
        );
    }

    private function stateOf(Quote $quote): string
    {
        QuoteTableMap::clearInstancePool();

        return (string) QuoteQuery::create()->findPk($quote->getId())->getState();
    }
}
