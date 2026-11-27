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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Thelia\Api\Service\Quote\QuoteWriteLimiter;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Customer;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemRequest;
use Thelia\Model\QuoteItemRequestQuery;

/**
 * The counter-proposal, as the buyer makes it: a table of the lines of the revision the quote stands at, two
 * fields a line, sent in one request. The component is driven the way the browser drives it, through its action
 * over HTTP, with the token the page of the quote carries.
 */
final class QuoteCounterOfferTest extends QuoteFlexyTestCase
{
    use InteractsWithLiveComponents;

    private const COMPONENT = 'Organisms:QuoteCounterOffer:Base';

    public function testTheTableHasTheLinesOfTheRevisionWithTwoLabelledFieldsEach(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));
        $form = $page->filter('form[data-testid="quote-counter-offer"]');

        $rows = $form->filter('[data-testid="quote-counter-offer-line"]');
        self::assertCount(3, $rows, 'One row for each line of the revision, the line the shop added included.');
        self::assertSame('submit', $form->attr('data-live-action-param'));
        self::assertNotSame('', (string) $form->attr('data-live-token-param'));

        foreach ($rows as $row) {
            $row = new Crawler($row);
            $lineId = $row->attr('data-line-id');

            foreach (['quantity', 'price'] as $field) {
                $input = $row->filter('input[name="counter_'.$field.'_'.$lineId.'"]');
                self::assertCount(1, $input, $field);
                self::assertCount(1, $row->filter('label[for="'.$input->attr('id').'"]'), 'Every field has a real label.');
                self::assertSame('norender|requests.'.$lineId.'.'.$field, $input->attr('data-model'), 'Typing re-renders nothing and calls nothing.');
            }
        }

        // The page of the table makes no other call: nothing in it is bound to update the server as the buyer types.
        self::assertSame(0, $form->filter('[data-model]')->reduce(static fn (Crawler $field): bool => !str_starts_with((string) $field->attr('data-model'), 'norender|'))->count());
        self::assertSame(0, $form->filter('[data-live-action-param="addRow"], [data-live-action-param="removeRow"]')->count(), 'No line can be added or removed.');
    }

    public function testACounterProposalOnOneLineIsRecordedAndTheOthersAreNotDiscussed(): void
    {
        $scenario = $this->quotedQuote();
        $ids = $this->lineIdsOf($scenario['quote'], 2);
        $a = $ids[(string) $scenario['a']->getRef()];

        $answer = $this->answer($scenario, [$a => ['price' => '85']]);

        self::assertTrue($answer->response()->isRedirect($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]).'?countered=1'));
        $recorded = $this->recorded($scenario['quote']);
        self::assertCount(1, $recorded, 'The two lines left empty are not discussed.');
        self::assertSame($a, $recorded[0]->getQuoteItemId());
        self::assertNull($recorded[0]->getQuantity());
        self::assertSame('85.000000', (string) $recorded[0]->getPrice());
        self::assertSame('countered', $this->reread($scenario['quote'])->getState());
        self::assertSame(2, (int) $this->reread($scenario['quote'])->getCurrentRevision(), 'An answer adds no revision.');
    }

    public function testAQuantityAndAPriceOnTwoLinesAreRecordedWithTheirValues(): void
    {
        $scenario = $this->quotedQuote();
        $ids = $this->lineIdsOf($scenario['quote'], 2);
        [$a, $b, $c] = [$ids[(string) $scenario['a']->getRef()], $ids[(string) $scenario['b']->getRef()], $ids[(string) $scenario['c']->getRef()]];

        // A comma passes for the decimal point, and spaces around a figure are not part of it.
        $this->answer($scenario, [$a => ['quantity' => '15', 'price' => '85,5'], $b => ['quantity' => ' 2.5 ', 'price' => ''], $c => ['quantity' => '', 'price' => '']]);

        $byLine = [];

        foreach ($this->recorded($scenario['quote']) as $request) {
            $byLine[$request->getQuoteItemId()] = [$request->getQuantity(), null === $request->getPrice() ? null : (string) $request->getPrice()];
        }

        self::assertEquals([$a => [15.0, '85.500000'], $b => [2.5, null]], $byLine);
    }

    public function testTheTableHasTheLinesOfTheRevisionItWasDrawnFor(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $firstRevision = $this->table($scenario['quote'], 1)->refresh()->render()->crawler();
        $secondRevision = $this->table($scenario['quote'], 2)->refresh()->render()->crawler();

        self::assertCount(2, $firstRevision->filter('[data-testid="quote-counter-offer-line"]'), 'What the buyer asked for: two lines.');
        self::assertCount(3, $secondRevision->filter('[data-testid="quote-counter-offer-line"]'), 'What the shop answered: three lines.');
    }

    public function testTheCounterProposalIsOnlyAskedAtTheSubmission(): void
    {
        $scenario = $this->quotedQuote();
        $ids = $this->lineIdsOf($scenario['quote'], 2);
        $this->signInAs($scenario['buyer']);
        $component = $this->table($scenario['quote'], 2);

        $component->set('requests', [$ids[(string) $scenario['a']->getRef()] => ['quantity' => '', 'price' => '85']]);

        self::assertSame([], $this->recorded($scenario['quote']), 'Setting a field is not an answer.');
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testNothingTypedIsRefusedWithoutAnswering(): void
    {
        $scenario = $this->quotedQuote();

        $answer = $this->answer($scenario, []);

        self::assertFalse($answer->response()->isRedirect());
        self::assertStringContainsString('must ask for a quantity or a price on at least one line', $answer->render()->crawler()->filter('[data-testid="quote-counter-offer-error"]')->text());
        self::assertSame([], $this->recorded($scenario['quote']));
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testAValueThatIsNotAFigureIsRefusedOnItsLineAndNothingIsRecorded(): void
    {
        $scenario = $this->quotedQuote();
        $ids = $this->lineIdsOf($scenario['quote'], 2);
        [$a, $b] = [$ids[(string) $scenario['a']->getRef()], $ids[(string) $scenario['b']->getRef()]];

        foreach (['abc', '-5', '0', '12e3', '1.2.3', '1 000'] as $typed) {
            $answer = $this->answer($scenario, [$a => ['price' => '85'], $b => ['price' => $typed]]);

            self::assertFalse($answer->response()->isRedirect(), $typed);
            $page = $answer->render()->crawler();
            self::assertCount(1, $page->filter('[data-testid="quote-counter-offer-error"]'), $typed);
            self::assertStringContainsString('Type a price above zero', $page->filter('[data-line-id="'.$b.'"]')->text(), $typed);
            self::assertStringNotContainsString('Type a price above zero', $page->filter('[data-line-id="'.$a.'"]')->text(), $typed);
            self::assertSame([], $this->recorded($scenario['quote']), $typed.': the line that was right is not recorded alone.');
        }

        $answer = $this->answer($scenario, [$a => ['quantity' => 'many']]);
        self::assertStringContainsString('Type a quantity above zero', $answer->render()->crawler()->filter('[data-line-id="'.$a.'"]')->text());
        self::assertSame([], $this->recorded($scenario['quote']));
    }

    public function testWhatWasTypedComesBackInTheFieldsWhenTheAnswerIsRefused(): void
    {
        $scenario = $this->quotedQuote();
        $a = $this->lineIdsOf($scenario['quote'], 2)[(string) $scenario['a']->getRef()];

        $page = $this->answer($scenario, [$a => ['quantity' => '15', 'price' => 'oops']])->render()->crawler();

        self::assertSame('oops', $page->filter('input[name="counter_price_'.$a.'"]')->attr('value'));
        self::assertSame('15', $page->filter('input[name="counter_quantity_'.$a.'"]')->attr('value'));
    }

    public function testALineOfAnotherQuoteIsRefusedAndNothingIsRecorded(): void
    {
        $scenario = $this->quotedQuote();
        $other = $this->quotedQuote();
        $aLineOfAnother = $this->lineIdsOf($other['quote'], 2)[(string) $other['a']->getRef()];

        $answer = $this->answer($scenario, [$aLineOfAnother => ['price' => '1']]);

        self::assertFalse($answer->response()->isRedirect());
        self::assertStringContainsString('only discuss the lines of the revision it answers', $answer->render()->crawler()->filter('[data-testid="quote-counter-offer-error"]')->text());
        self::assertSame([], $this->recorded($scenario['quote']));
        self::assertSame([], $this->recorded($other['quote']));
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testALineThatDoesNotExistIsNotAddedToTheQuote(): void
    {
        $scenario = $this->quotedQuote();
        $before = \count($this->lineIdsOf($scenario['quote'], 2));

        $answer = $this->answer($scenario, [987654321 => ['quantity' => '3', 'price' => '10']]);

        self::assertFalse($answer->response()->isRedirect());
        self::assertSame([], $this->recorded($scenario['quote']));
        self::assertCount($before, $this->lineIdsOf($scenario['quote'], 2));
    }

    public function testAnAnswerToARevisionTheQuoteHasMovedPastIsRefused(): void
    {
        $scenario = $this->quotedQuote();
        $firstRevisionLine = $this->lineIdsOf($scenario['quote'], 1)[(string) $scenario['a']->getRef()];

        // The table was drawn for revision 1, which the quote moved past when the shop answered.
        $this->signInAs($scenario['buyer']);
        $token = $this->tokenOfThePage($scenario['quote']);
        $component = $this->table($scenario['quote'], 1);
        $component->set('requests', [$firstRevisionLine => ['quantity' => '', 'price' => '85']]);
        $component->call('submit', ['token' => $token]);

        self::assertFalse($component->response()->isRedirect());
        self::assertStringContainsString('more recent revision', $component->render()->crawler()->filter('[data-testid="quote-counter-offer-error"]')->text());
        self::assertSame([], $this->recorded($scenario['quote']));
    }

    public function testAQuoteThatIsNotWaitingForTheBuyerRefusesTheAnswer(): void
    {
        $expired = $this->quotedQuote();
        $this->expire($expired['quote']);
        $countered = $this->quotedQuote();
        $line = $this->lineIdsOf($countered['quote'], 2)[(string) $countered['a']->getRef()];
        $this->quotes->counterOffer($countered['quote'], $countered['buyer'], 2, [new \Thelia\Domain\Quote\DTO\QuoteItemRequestInput($line, null, '80')]);
        $requested = $this->requestedQuote();
        $rejected = $this->quotedQuote();
        $this->quotes->reject($rejected['quote'], $rejected['buyer'], 2);

        foreach (['expired' => $expired, 'countered' => $countered, 'requested' => $requested, 'rejected' => $rejected] as $state => $scenario) {
            $revision = (int) $this->reread($scenario['quote'])->getCurrentRevision();
            $lineId = array_values($this->lineIdsOf($scenario['quote'], $revision))[0];
            $before = \count($this->recorded($scenario['quote']));

            $token = $this->tokenForTheSession();
            $this->signInAs($scenario['buyer']);
            $component = $this->table($scenario['quote'], $revision);
            $component->set('requests', [$lineId => ['quantity' => '', 'price' => '70']]);
            $component->call('submit', ['token' => $token]);

            self::assertFalse($component->response()->isRedirect(), $state);
            self::assertStringContainsString('not in a state that allows this', $component->render()->crawler()->filter('[data-testid="quote-counter-offer-error"]')->text(), $state);
            self::assertCount($before, $this->recorded($scenario['quote']), $state);
        }
    }

    public function testAnAnswerWithoutTheTokenOfTheAccountIsRefused(): void
    {
        $scenario = $this->quotedQuote();
        $a = $this->lineIdsOf($scenario['quote'], 2)[(string) $scenario['a']->getRef()];
        $this->signInAs($scenario['buyer']);

        foreach (['', 'forged', 'quote_action'] as $token) {
            $component = $this->table($scenario['quote'], 2);
            $component->set('requests', [$a => ['quantity' => '', 'price' => '85']]);

            try {
                $component->call('submit', ['token' => $token]);
                self::fail('The answer was taken without a valid token: '.$token);
            } catch (AccessDeniedHttpException) {
                self::assertSame([], $this->recorded($scenario['quote']), $token);
            }
        }

        $component = $this->table($scenario['quote'], 2);
        $component->set('requests', [$a => ['quantity' => '', 'price' => '85']]);

        try {
            $component->call('submit');
            self::fail('The answer was taken without a token.');
        } catch (AccessDeniedHttpException) {
            self::assertSame([], $this->recorded($scenario['quote']));
        }
    }

    public function testACustomerWhoReadsNoQuoteOfTheirsCannotAnswerAnother(): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();

        // A answers the quote of B, then B answers the quote of A: the same refusal, nothing recorded.
        foreach ([[$first['buyer'], $second], [$second['buyer'], $first]] as [$stranger, $target]) {
            $line = $this->lineIdsOf($target['quote'], 2)[(string) $target['a']->getRef()];
            $token = $this->tokenForTheSession();
            $this->signInAs($stranger);
            $component = $this->table($target['quote'], 2);
            $component->set('requests', [$line => ['quantity' => '', 'price' => '85']]);

            try {
                $component->call('submit', ['token' => $token]);
                self::fail('A customer answered the quote of another.');
            } catch (NotFoundHttpException) {
                self::assertSame([], $this->recorded($target['quote']));
                self::assertSame('quoted', $this->reread($target['quote'])->getState());
            }
        }
    }

    public function testACompanyCannotAnswerTheQuoteOfAnotherCompanyInEitherDirection(): void
    {
        $first = $this->company('Acme');
        $firstAdministrator = $this->buyer($first, CompanyRoleCode::ADMINISTRATOR);
        $firstScenario = $this->quotedQuote($this->buyer($first));
        $second = $this->company('Globex');
        $secondAdministrator = $this->buyer($second, CompanyRoleCode::ADMINISTRATOR);
        $secondScenario = $this->quotedQuote($this->buyer($second));

        // S1 answers the quote of S2, and S2 answers the quote of S1.
        foreach ([[$firstAdministrator, $secondScenario], [$secondAdministrator, $firstScenario]] as [$who, $target]) {
            $line = $this->lineIdsOf($target['quote'], 2)[(string) $target['a']->getRef()];
            $token = $this->tokenForTheSession();
            $this->signInAs($who);
            $component = $this->table($target['quote'], 2);
            $component->set('requests', [$line => ['quantity' => '', 'price' => '85']]);

            try {
                $component->call('submit', ['token' => $token]);
                self::fail('A buyer of another company answered the quote.');
            } catch (NotFoundHttpException) {
                self::assertSame([], $this->recorded($target['quote']));
                self::assertSame('quoted', $this->reread($target['quote'])->getState());
            }
        }

        // And the administrator of a company does answer the quote of their own.
        $line = $this->lineIdsOf($firstScenario['quote'], 2)[(string) $firstScenario['a']->getRef()];
        $this->answer(['buyer' => $firstAdministrator, 'quote' => $firstScenario['quote']], [$line => ['price' => '85']]);
        self::assertCount(1, $this->recorded($firstScenario['quote']));
    }

    public function testAColleagueWhoReadsTheQuoteWithoutTheRightToAnswerCannotAnswerIt(): void
    {
        $company = $this->company('Acme');
        $author = $this->buyer($company);
        $approver = $this->buyer($company, CompanyRoleCode::APPROVER);
        $scenario = $this->quotedQuote($author);
        $line = $this->lineIdsOf($scenario['quote'], 2)[(string) $scenario['a']->getRef()];

        $token = $this->tokenForTheSession();
        $this->signInAs($approver);
        $component = $this->table($scenario['quote'], 2);
        $component->set('requests', [$line => ['quantity' => '', 'price' => '85']]);

        try {
            $component->call('submit', ['token' => $token]);
            self::fail('A buyer whose role does not answer quotes answered one.');
        } catch (AccessDeniedHttpException) {
            self::assertSame([], $this->recorded($scenario['quote']));
        }

        // The author, who holds the right, can.
        $this->answer($scenario, [$line => ['quantity' => '', 'price' => '85']]);
        self::assertCount(1, $this->recorded($scenario['quote']));
    }

    public function testAVisitorCannotAnswer(): void
    {
        $scenario = $this->quotedQuote();
        $line = $this->lineIdsOf($scenario['quote'], 2)[(string) $scenario['a']->getRef()];
        $token = $this->tokenForTheSession();
        $this->signInAs($scenario['buyer']);
        $component = $this->table($scenario['quote'], 2);
        $component->set('requests', [$line => ['quantity' => '', 'price' => '85']]);
        // The visitor is a session of no account: the one of the buyer is not theirs.
        $this->signInAs(null);
        $this->client->getCookieJar()->clear();

        $this->expectException(AccessDeniedHttpException::class);

        $component->call('submit', ['token' => $token]);
    }

    public function testTheBudgetOfCounterProposalsIsSpentAndTheBuyerIsToldWhenItIsOver(): void
    {
        $scenario = $this->quotedQuote();
        $a = $this->lineIdsOf($scenario['quote'], 2)[(string) $scenario['a']->getRef()];
        $limiter = $this->getService(QuoteWriteLimiter::class);

        for ($spent = 0; $spent < 200; ++$spent) {
            try {
                $limiter->spendCounterOffer((int) $scenario['buyer']->getId());
            } catch (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
                break;
            }
        }

        self::assertLessThan(200, $spent, 'The limiter of counter-proposals is bounded.');

        $answer = $this->answer($scenario, [$a => ['quantity' => '', 'price' => '85']]);

        self::assertFalse($answer->response()->isRedirect());
        self::assertStringContainsString('Too many counter-proposals', $answer->render()->crawler()->filter('[data-testid="quote-counter-offer-error"]')->text());
        self::assertSame([], $this->recorded($scenario['quote']));
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testTheBuyerLandsOnTheQuoteWhichNowWaitsForTheShop(): void
    {
        $scenario = $this->quotedQuote();
        $a = $this->lineIdsOf($scenario['quote'], 2)[(string) $scenario['a']->getRef()];

        $this->answer($scenario, [$a => ['quantity' => '', 'price' => '85']]);

        $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]).'?countered=1');
        self::assertCount(1, $page->filter('[data-testid="quote-countered"]'));
        self::assertSame('countered', $page->filter('[data-testid="quote-state"]')->attr('data-state'));
        self::assertCount(0, $page->filter('[data-testid="quote-reply"]'), 'Nothing more to answer.');
        self::assertStringContainsString('85.00', $page->filter('[data-testid="quote-line-counter-offer"]')->text(), 'The counter-proposal is read back beside the lines.');
    }

    /**
     * The buyer of the scenario sends what they typed, with the token the page of the quote carries.
     *
     * @param array{buyer: Customer, quote: Quote} $scenario
     * @param array<int, array<string, string>>    $typed    by line id
     */
    private function answer(array $scenario, array $typed): TestLiveComponent
    {
        $this->signInAs($scenario['buyer']);
        $token = $this->tokenOfThePage($scenario['quote']);
        $component = $this->table($scenario['quote'], (int) $this->reread($scenario['quote'])->getCurrentRevision());
        $component->set('requests', $typed + $this->emptyRequestsOf($scenario['quote']));
        $component->call('submit', ['token' => $token]);

        return $component;
    }

    /**
     * @return array<int, array{quantity: string, price: string}>
     */
    private function emptyRequestsOf(Quote $quote): array
    {
        $empty = [];

        foreach ($this->lineIdsOf($quote, (int) $this->reread($quote)->getCurrentRevision()) as $id) {
            $empty[$id] = ['quantity' => '', 'price' => ''];
        }

        return $empty;
    }

    private function table(Quote $quote, int $revision): TestLiveComponent
    {
        $lineIds = array_values($this->lineIdsOf($quote, $revision));

        return $this->createLiveComponent(self::COMPONENT, ['quoteId' => (int) $quote->getId(), 'revisionNumber' => $revision, 'lineIds' => $lineIds], $this->client);
    }

    private function tokenOfThePage(Quote $quote): string
    {
        $page = $this->get($this->url('account_quote', ['quoteId' => $quote->getId()]));

        return (string) $page->filter('form[data-testid="quote-counter-offer"]')->attr('data-live-token-param');
    }

    /**
     * @return list<QuoteItemRequest>
     */
    private function recorded(Quote $quote): array
    {
        $ids = [];

        foreach ([1, 2, 3] as $revision) {
            if (\Thelia\Model\QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->filterByNumber($revision)->exists()) {
                $ids = [...$ids, ...array_values($this->lineIdsOf($quote, $revision))];
            }
        }

        return [] === $ids ? [] : [...QuoteItemRequestQuery::create()->filterByQuoteItemId($ids)->orderById()->find()];
    }
}
