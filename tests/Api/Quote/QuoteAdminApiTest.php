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
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\Quote;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * The quotes as the administration reads and moves them: every quote with its draft, filtered by what
 * the shop looks for, and the operations that start, write, send, extend and remind, each refused to a
 * profile that does not hold the right of the quotes at the level it takes.
 */
final class QuoteAdminApiTest extends QuoteApiTestCase
{
    public function testTheAdministrationListsTheQuotesOfEveryAccountWithoutTheirRevisions(): void
    {
        $first = $this->quotedQuote();
        $second = $this->requestedQuote();

        $response = $this->jsonRequest('GET', '/api/admin/quotes', token: $this->adminToken());

        self::assertJsonResponseSuccessful($response);
        self::assertEqualsCanonicalizing([(int) $first['quote']->getId(), (int) $second['quote']->getId()], $this->memberIds($response));
        $member = $this->body($response)['hydra:member'][0];
        self::assertArrayNotHasKey('revisions', $member);
        self::assertArrayHasKey('customerId', $member);
        self::assertArrayHasKey('remindedAt', $this->withReminder($first['quote']));
    }

    public function testAQuoteIsReadWithItsDraftAndTheAdministratorWhoWroteIt(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $admin = $this->createFixtureFactory()->admin(['password' => 'password']);
        $token = $this->authenticateAsAdmin($admin);

        $draft = $this->jsonRequest('PUT', '/api/admin/quotes/'.$quote->getId().'/draft', ['lines' => [], 'merchantNote' => 'Thinking about it'], $token, 'json');
        self::assertSame(409, $draft->getStatusCode(), 'A quote that was sent takes no draft until the buyer answers.');

        $this->quotes->reject($quote, $scenario['buyer'], 2);
        $reader = $this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId(), token: $token);

        self::assertJsonResponseSuccessful($reader);
        $body = $this->body($reader);
        self::assertSame('rejected', $body['state']);
        self::assertSame([1, 2], array_column($body['revisions'], 'number'));
    }

    public function testTheDraftOfTheShopIsReadByTheAdministrationAndNeverByTheBuyer(): void
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];
        $admin = $this->createFixtureFactory()->admin(['password' => 'password']);
        $token = $this->authenticateAsAdmin($admin);

        $response = $this->jsonRequest('PUT', '/api/admin/quotes/'.$quote->getId().'/draft', [
            'lines' => [['productSaleElementsId' => $this->saleElementId($scenario['c']), 'quantity' => 3, 'price' => '19.5']],
            'merchantNote' => 'Internal thoughts',
        ], $token, 'json');

        self::assertSame(200, $response->getStatusCode());
        $body = $this->body($response);
        self::assertSame('requested', $body['state'], 'Writing a draft moves nothing.');
        self::assertSame([1, 2], array_column($body['revisions'], 'number'));
        self::assertSame('draft', $body['revisions'][1]['state']);
        self::assertSame((int) $admin->getId(), $body['revisions'][1]['adminId']);
        self::assertSame('Internal thoughts', $body['revisions'][1]['merchantNote']);

        $buyer = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $this->token($scenario['buyer']));
        $seen = $this->body($buyer);
        self::assertArrayNotHasKey('revisions', $seen);
        $history = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions', token: $this->token($scenario['buyer']));
        self::assertSame([1], array_column($this->body($history)['hydra:member'], 'number'), 'The buyer does not know there is a draft.');
        self::assertStringNotContainsString('Internal thoughts', (string) $history->getContent());
        self::assertStringNotContainsString('Internal thoughts', (string) $buyer->getContent());
        self::assertStringNotContainsString('adminId', (string) $buyer->getContent());
        self::assertArrayNotHasKey('customerId', $seen);
        self::assertArrayNotHasKey('remindedAt', $seen);
        self::assertSame('requested', $seen['state']);
    }

    public function testTheListIsFilteredByStateCustomerCompanyAndValidity(): void
    {
        $company = $this->company('Acme');
        $ofTheCompany = $this->quotedQuote($this->buyerOf($company))['quote'];
        $quoted = $this->quotedQuote()['quote'];
        $requested = $this->requestedQuote()['quote'];
        $quoted->setValidUntil('2031-06-30')->save($this->getPropelConnection());
        $ofTheCompany->setValidUntil('2031-01-15')->save($this->getPropelConnection());
        $token = $this->adminToken();

        self::assertEqualsCanonicalizing([(int) $quoted->getId(), (int) $ofTheCompany->getId()], $this->ids('state=quoted', $token));
        self::assertSame([(int) $requested->getId()], $this->ids('state=requested', $token));
        self::assertEqualsCanonicalizing([(int) $requested->getId(), (int) $ofTheCompany->getId(), (int) $quoted->getId()], $this->ids('state[]=requested&state[]=quoted', $token));
        self::assertSame([(int) $ofTheCompany->getId()], $this->ids('state[]=requested&state[]=quoted&companyId='.$company->getId(), $token));
        self::assertSame([(int) $ofTheCompany->getId()], $this->ids('companyId='.$company->getId(), $token));
        self::assertSame([(int) $quoted->getId()], $this->ids('customerId='.$quoted->getCustomerId(), $token));
        self::assertSame([(int) $quoted->getId()], $this->ids('validUntil[after]=2031-03-01', $token));
        self::assertSame([(int) $ofTheCompany->getId()], $this->ids('validUntil[before]=2031-03-01', $token));
        self::assertSame([(int) $quoted->getId()], $this->ids('ref='.$quoted->getRef(), $token));
        self::assertSame([], $this->ids('state=expired', $token));
    }

    public function testTheStateFilterAnswersTheStateAQuoteReadsIn(): void
    {
        $waiting = $this->quotedQuote()['quote'];
        $overdue = $this->quotedQuote()['quote'];
        $written = $this->quotedQuote()['quote'];
        $waiting->setValidUntil((new \DateTimeImmutable('+10 days'))->format('Y-m-d'))->save($this->getPropelConnection());
        $overdue->setValidUntil((new \DateTimeImmutable('-1 day'))->format('Y-m-d'))->save($this->getPropelConnection());
        $written->setState('expired')->setValidUntil((new \DateTimeImmutable('-5 days'))->format('Y-m-d'))->save($this->getPropelConnection());
        $token = $this->adminToken();

        self::assertSame([(int) $waiting->getId()], $this->ids('state=quoted', $token));
        self::assertEqualsCanonicalizing([(int) $overdue->getId(), (int) $written->getId()], $this->ids('state=expired', $token));
        self::assertSame('expired', $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$overdue->getId(), token: $token))['state']);
        self::assertSame([], $this->ids('state=nonsense', $token));
    }

    public function testAnAdministratorStartsAQuoteForACustomer(): void
    {
        $customer = $this->customer();
        $admin = $this->createFixtureFactory()->admin(['password' => 'password']);

        $response = $this->jsonRequest('POST', '/api/admin/quotes', ['customerId' => $customer->getId()], $this->authenticateAsAdmin($admin), 'json');

        self::assertSame(201, $response->getStatusCode());
        $body = $this->body($response);
        self::assertSame('draft', $body['state']);
        self::assertSame((int) $customer->getId(), $body['customerId']);
        self::assertSame('admin', $body['revisions'][0]['author']);
        self::assertSame('draft', $body['revisions'][0]['state']);
        self::assertSame((int) $admin->getId(), $body['revisions'][0]['adminId']);
        self::assertStringStartsWith('QUO', $body['ref']);
    }

    public function testAQuoteCannotBeStartedForSomeoneWhoCannotHaveOne(): void
    {
        $guest = $this->fixtures->guestCustomer($this->fixtures->customerTitle());
        $token = $this->adminToken();

        foreach ([['customerId' => 987654321], ['customerId' => $guest->getId()]] as $body) {
            $response = $this->jsonRequest('POST', '/api/admin/quotes', $body, $token, 'json');

            self::assertSame(422, $response->getStatusCode());
            self::assertSame('customer_cannot_have_quotes', $this->reason($response));
        }

        self::assertSame(422, $this->jsonRequest('POST', '/api/admin/quotes', ['customerId' => 'x'], $token, 'json')->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', '/api/admin/quotes', ['customerId' => 1, 'state' => 'accepted'], $token, 'json')->getStatusCode());
        self::assertSame(0, QuoteQuery::create()->count());
    }

    public function testAStateIsNeverWrittenByTheAdministrationEither(): void
    {
        $quote = $this->requestedQuote()['quote'];
        $token = $this->adminToken();

        foreach (['PATCH' => 'merge-patch+json', 'PUT' => 'json'] as $method => $format) {
            $response = $this->jsonRequest($method, '/api/admin/quotes/'.$quote->getId(), ['state' => 'accepted'], $token, $format);

            self::assertContains($response->getStatusCode(), [404, 405], $method);
        }

        self::assertSame('requested', $this->stateOf($quote));
    }

    public function testTheDraftIsWrittenAsAWholeAndSentThenTheQuoteIsQuoted(): void
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];
        $token = $this->adminToken();
        $first = $this->lineIdsOfQuote($quote, 1);

        $response = $this->jsonRequest('PUT', '/api/admin/quotes/'.$quote->getId().'/draft', [
            'lines' => [
                ['quoteItemId' => $first[0], 'productSaleElementsId' => $this->saleElementId($scenario['a']), 'quantity' => 12, 'price' => '90'],
                ['quoteItemId' => $first[1], 'productSaleElementsId' => $this->saleElementId($scenario['b']), 'quantity' => 4, 'price' => 45],
                ['productSaleElementsId' => $this->saleElementId($scenario['c']), 'quantity' => 5, 'price' => '20.00'],
            ],
            'discount' => '30',
            'postage' => 15,
            'merchantNote' => 'Thank you for your <script>alert(1)</script>trust',
            'validUntil' => '2031-12-16',
        ], $token, 'json');

        self::assertSame(200, $response->getStatusCode());
        $draft = $this->body($response)['revisions'][1];
        self::assertSame('draft', $draft['state']);
        self::assertSame([12.0, 4.0, 5.0], array_column($draft['items'], 'quantity'));
        self::assertSame('100.000000', $draft['items'][0]['catalogPrice']);
        self::assertSame('20.000000', $draft['items'][2]['catalogPrice'], 'A line the shop adds is set against its catalogue price.');
        self::assertStringNotContainsString('<script>', (string) $draft['merchantNote']);
        self::assertNull($draft['totalTaxed'] ?? null, 'Nothing is frozen before it is sent.');

        $sent = $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/send', token: $token, format: 'json');

        self::assertSame(200, $sent->getStatusCode());
        $body = $this->body($sent);
        self::assertSame('quoted', $body['state']);
        self::assertSame(2, $body['currentRevision']);
        self::assertSame('2031-12-16', substr($body['validUntil'], 0, 10));
        self::assertSame('1620.000000', $body['revisions'][1]['totalTaxed']);
        self::assertSame('sent', $body['revisions'][1]['state']);
    }

    public function testTheNoteOfTheShopIsPlainTextForTheShopAndForTheBuyer(): void
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];
        $token = $this->adminToken();
        $first = $this->lineIdsOfQuote($quote, 1);

        $draft = $this->jsonRequest('PUT', '/api/admin/quotes/'.$quote->getId().'/draft', [
            'lines' => [
                ['quoteItemId' => $first[0], 'productSaleElementsId' => $this->saleElementId($scenario['a']), 'quantity' => 10, 'price' => '90'],
            ],
            'merchantNote' => "Prix valable jusqu'à l'inventaire <b>x</b> &amp; plus",
        ], $token, 'json');

        self::assertSame(200, $draft->getStatusCode());
        self::assertSame("Prix valable jusqu'à l'inventaire x & plus", $this->body($draft)['revisions'][1]['merchantNote']);
        self::assertSame(200, $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/send', token: $token, format: 'json')->getStatusCode());

        self::assertSame("Prix valable jusqu'à l'inventaire x & plus", $this->frontRevision($quote, 2, $this->token($scenario['buyer']))['merchantNote']);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('draftsTheShopRefuses')]
    public function testADraftTheShopRefusesIsRefusedWithItsReason(array $body, int $status, ?string $reason): void
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];
        $before = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count();

        $response = $this->jsonRequest('PUT', '/api/admin/quotes/'.$quote->getId().'/draft', $this->resolve($body, $scenario), $this->adminToken(), 'json');

        self::assertSame($status, $response->getStatusCode());

        if (null !== $reason) {
            self::assertSame($reason, $this->reason($response));
        }

        self::assertSame($before, QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: int, 2: string|null}>
     */
    public static function draftsTheShopRefuses(): iterable
    {
        yield 'no lines' => [[], 422, null];
        yield 'a state' => [['lines' => [], 'state' => 'quoted'], 422, null];
        yield 'a total' => [['lines' => [], 'totalTaxed' => '1'], 422, null];
        yield 'a line that is not an object' => [['lines' => ['x']], 422, null];
        yield 'a line without a price' => [['lines' => [['productSaleElementsId' => '{c}', 'quantity' => 1]]], 422, null];
        yield 'a price that is not a decimal' => [['lines' => [['productSaleElementsId' => '{c}', 'quantity' => 1, 'price' => 'free']]], 422, null];
        yield 'a quantity of zero' => [['lines' => [['productSaleElementsId' => '{c}', 'quantity' => 0, 'price' => '1']]], 422, 'draft_invalid_quantity'];
        yield 'a negative price' => [['lines' => [['productSaleElementsId' => '{c}', 'quantity' => 1, 'price' => '-1']]], 422, 'draft_invalid_price'];
        yield 'a sale element that does not exist' => [['lines' => [['productSaleElementsId' => 987654321, 'quantity' => 1, 'price' => '1']]], 422, 'draft_unknown_sale_element'];
        yield 'a line of another quote' => [['lines' => [['quoteItemId' => 987654321, 'productSaleElementsId' => '{c}', 'quantity' => 1, 'price' => '1']]], 422, 'draft_unknown_line'];
        yield 'a negative discount' => [['lines' => [], 'discount' => '-1'], 422, 'draft_invalid_discount'];
        yield 'a validity in the past' => [['lines' => [], 'validUntil' => '2001-01-01'], 422, 'validity_in_the_past'];
        yield 'a validity that is not a day' => [['lines' => [], 'validUntil' => 'tomorrow'], 422, null];
        yield 'a carrier that does not exist' => [['lines' => [], 'deliveryModuleId' => 987654321], 422, 'draft_unknown_delivery_module'];
    }

    public function testAQuoteThatNothingAnswersToIsNotFoundWhateverTheOperation(): void
    {
        $token = $this->adminToken();

        foreach ([['GET', ''], ['DELETE', ''], ['PUT', '/draft'], ['POST', '/send'], ['POST', '/extend'], ['POST', '/remind']] as [$method, $suffix]) {
            $response = $this->jsonRequest($method, '/api/admin/quotes/987654321'.$suffix, ['lines' => [], 'validUntil' => '2031-01-01'], $token, 'json');

            self::assertSame(404, $response->getStatusCode(), $method.' '.$suffix);
        }
    }

    public function testASendWithoutADraftAndASecondSendAreRefused(): void
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];
        $token = $this->adminToken();

        $first = $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/send', token: $token, format: 'json');
        self::assertSame(422, $first->getStatusCode());
        self::assertSame('nothing_to_send', $this->reason($first));

        $this->shopAnswers($quote, $scenario);
        $again = $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/send', token: $token, format: 'json');
        self::assertSame(409, $again->getStatusCode());
        self::assertSame('transition_refused', $this->reason($again));
        self::assertSame('quoted', $this->stateOf($quote));
    }

    public function testAnExpiredQuoteIsExtendedAndAcceptableAgain(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $quote->setValidUntil((new \DateTimeImmutable('-1 day'))->format('Y-m-d'))->save($this->getPropelConnection());
        $token = $this->adminToken();
        $day = (new \DateTimeImmutable('+7 days'))->format('Y-m-d');

        self::assertSame(409, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $this->token($scenario['buyer']), 'json')->getStatusCode());

        $response = $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/extend', ['validUntil' => $day], $token, 'json');

        self::assertSame(200, $response->getStatusCode());
        $body = $this->body($response);
        self::assertSame('quoted', $body['state']);
        self::assertSame($day, substr($body['validUntil'], 0, 10));
        self::assertSame(200, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $this->token($scenario['buyer']), 'json')->getStatusCode());
    }

    public function testAnExtensionIntoThePastOrOfAQuoteThatIsDecidedIsRefused(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $token = $this->adminToken();

        $past = $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/extend', ['validUntil' => '2001-01-01'], $token, 'json');
        self::assertSame(422, $past->getStatusCode());
        self::assertSame('validity_in_the_past', $this->reason($past));
        self::assertSame(422, $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/extend', [], $token, 'json')->getStatusCode());

        $shortened = $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/extend', ['validUntil' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d')], $token, 'json');
        self::assertSame(422, $shortened->getStatusCode(), 'a quote that is still valid for thirty days is not extended to a nearer day');
        self::assertSame('validity_not_extended', $this->reason($shortened));

        $this->quotes->reject($quote, $scenario['buyer'], 2);
        $decided = $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/extend', ['validUntil' => '2031-01-01'], $token, 'json');
        self::assertSame(409, $decided->getStatusCode());
        self::assertSame('rejected', $this->stateOf($quote));
    }

    public function testTheBuyerIsRemindedOfAQuoteThatWaitsForThemAndOfNothingElse(): void
    {
        $waiting = $this->quotedQuote()['quote'];
        $requested = $this->requestedQuote()['quote'];
        $token = $this->adminToken();

        $response = $this->jsonRequest('POST', '/api/admin/quotes/'.$waiting->getId().'/remind', token: $token, format: 'json');

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($this->body($response)['remindedAt']);
        self::assertSame(409, $this->jsonRequest('POST', '/api/admin/quotes/'.$requested->getId().'/remind', token: $token, format: 'json')->getStatusCode());
    }

    public function testACustomerTokenReachesNothingOfTheAdministration(): void
    {
        $scenario = $this->quotedQuote();
        $token = $this->token($scenario['buyer']);
        $id = $scenario['quote']->getId();

        foreach ([['GET', '/api/admin/quotes'], ['GET', '/api/admin/quotes/'.$id], ['DELETE', '/api/admin/quotes/'.$id], ['POST', '/api/admin/quotes'], ['PUT', '/api/admin/quotes/'.$id.'/draft'], ['POST', '/api/admin/quotes/'.$id.'/send'], ['POST', '/api/admin/quotes/'.$id.'/extend'], ['POST', '/api/admin/quotes/'.$id.'/remind']] as [$method, $uri]) {
            self::assertContains($this->jsonRequest($method, $uri, ['customerId' => 1, 'lines' => [], 'validUntil' => '2031-01-01'], $token, 'json')->getStatusCode(), [401, 403], $method.' '.$uri);
        }

        self::assertSame('quoted', $this->stateOf($scenario['quote']));
    }

    public function testAnonymousCallersReachNothingOfTheAdministration(): void
    {
        $id = $this->quotedQuote()['quote']->getId();

        foreach ([['GET', '/api/admin/quotes'], ['GET', '/api/admin/quotes/'.$id], ['DELETE', '/api/admin/quotes/'.$id], ['POST', '/api/admin/quotes'], ['PUT', '/api/admin/quotes/'.$id.'/draft'], ['POST', '/api/admin/quotes/'.$id.'/send'], ['POST', '/api/admin/quotes/'.$id.'/extend'], ['POST', '/api/admin/quotes/'.$id.'/remind']] as [$method, $uri]) {
            self::assertSame(401, $this->jsonRequest($method, $uri, format: 'json')->getStatusCode(), $method.' '.$uri);
        }
    }

    public function testEachOperationAsksForTheLevelOfAccessItTakes(): void
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];
        $customer = $this->customer();

        $viewOnly = $this->authenticateAsAdmin($this->profileWith([AccessManager::VIEW]));
        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/quotes', token: $viewOnly)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId(), token: $viewOnly)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/quotes', ['customerId' => $customer->getId()], $viewOnly, 'json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PUT', '/api/admin/quotes/'.$quote->getId().'/draft', ['lines' => []], $viewOnly, 'json')->getStatusCode());

        // The transitions are changes of the quote, not creations: a profile that creates and does not update is refused.
        $creates = $this->authenticateAsAdmin($this->profileWith([AccessManager::VIEW, AccessManager::CREATE]));
        $this->shopAnswers($quote, $scenario);
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/extend', ['validUntil' => '2031-01-01'], $creates, 'json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/remind', token: $creates, format: 'json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/send', token: $creates, format: 'json')->getStatusCode());
        self::assertNull($this->withReminder($quote)['remindedAt'] ?? null);

        $updates = $this->authenticateAsAdmin($this->profileWith([AccessManager::VIEW, AccessManager::UPDATE]));
        self::assertSame(200, $this->jsonRequest('POST', '/api/admin/quotes/'.$quote->getId().'/remind', token: $updates, format: 'json')->getStatusCode());

        $none = $this->authenticateAsAdmin($this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]));
        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/quotes', token: $none)->getStatusCode());
    }

    /**
     * @param list<string> $accesses
     */
    private function profileWith(array $accesses): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([AdminResources::QUOTE => $accesses]);
    }

    /**
     * @param array<string, mixed>                                                               $body
     * @param array{buyer: object, quote: Quote, a: object, b: object, c: \Thelia\Model\Product} $scenario
     *
     * @return array<string, mixed>
     */
    private function resolve(array $body, array $scenario): array
    {
        return json_decode(str_replace('"{c}"', (string) $this->saleElementId($scenario['c']), json_encode($body, \JSON_THROW_ON_ERROR)), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<int>
     */
    private function ids(string $query, string $token): array
    {
        $response = $this->jsonRequest('GET', '/api/admin/quotes?'.$query, token: $token);
        self::assertJsonResponseSuccessful($response);

        return $this->memberIds($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function withReminder(Quote $quote): array
    {
        QuoteTableMap::clearInstancePool();
        $row = QuoteQuery::create()->findPk($quote->getId());

        return ['remindedAt' => $row->getRemindedAt()];
    }

    /**
     * @return list<int>
     */
    private function lineIdsOfQuote(Quote $quote, int $number): array
    {
        return array_map(
            static fn ($line): int => (int) $line->getId(),
            [...\Thelia\Model\QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber($number)->endUse()->orderByPosition()->find()],
        );
    }

    private function stateOf(Quote $quote): string
    {
        QuoteTableMap::clearInstancePool();

        return (string) QuoteQuery::create()->findPk($quote->getId())->getState();
    }
}
