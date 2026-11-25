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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\Quote;

/**
 * A quote the buyer accepted whose payment did not go through: the order was cancelled and gave the quote back, or the
 * cart of the quote was lost on the way. The page of the quote offers the payment again, with the same acceptance and the
 * same token, to whoever could have accepted it, and the buyer lands in the checkout with the cart of the quote.
 */
final class QuoteResumePaymentTest extends QuoteFlexyTestCase
{
    private const RESUME = '[data-testid="quote-resume-form"]';

    public function testAQuoteAcceptedWithNoOrderOffersToResumeTheSamePostAsTheAcceptance(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $this->signInAs($scenario['buyer']);

        $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

        $form = $page->filter(self::RESUME);
        self::assertCount(1, $form);
        self::assertSame($this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), $form->attr('action'));
        self::assertSame('post', strtolower((string) $form->attr('method')));
        self::assertSame('2', $form->filter('input[name="revision"]')->attr('value'));
        self::assertNotSame('', (string) $form->filter('input[name="_token"]')->attr('value'));
        self::assertSame([], $form->filter('input')->reduce(static fn (Crawler $input): bool => !\in_array($input->attr('name'), ['_token', 'revision'], true))->each(static fn (Crawler $input): string => (string) $input->attr('name')), 'No amount.');
        self::assertSame('Resume the payment', trim($form->filter('button[type="submit"]')->text()));
        foreach (['quote-accept-form', 'quote-counter-offer-zone', 'quote-reject-form'] as $answer) {
            self::assertCount(0, $page->filter('[data-testid="'.$answer.'"]'), $answer.': what the quote no longer waits for is not offered.');
        }
    }

    public function testTheQuoteIsNotOfferedAnotherPaymentOnceAnOrderStandsForIt(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $this->signInAs($scenario['buyer']);
        $quoteUrl = $this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]);
        self::assertCount(1, $this->get($quoteUrl)->filter(self::RESUME), 'Offered while nothing stands.');

        // The quote reads accepted and an order that is not cancelled stands for it all the same: nothing to pay twice.
        $order = $this->orderOf($scenario, quoteStillReadsAccepted: true);

        self::assertCount(0, $this->get($quoteUrl)->filter(self::RESUME), 'An order that stands.');
        self::assertCount(0, $this->get($quoteUrl)->filter('[data-testid="quote-reply"]'));

        $order->setCancelled($this->getService(EventDispatcherInterface::class));

        self::assertCount(1, $this->get($quoteUrl)->filter(self::RESUME), 'A cancelled order is no order.');
    }

    public function testANoLongerAcceptedQuoteOffersNothing(): void
    {
        foreach (['ordered', 'rejected', 'expired', 'quoted', 'countered'] as $state) {
            $scenario = $this->quotedQuoteReadyToPay();
            $this->reread($scenario['quote'])->setState($state)->save($this->getPropelConnection());
            $this->signInAs($scenario['buyer']);

            $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

            self::assertCount(0, $page->filter(self::RESUME), $state);
        }
    }

    public function testACancelledOrderGivesTheQuoteBackAndTheButtonLeadsBackToThePaymentWithTheSameCart(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $cart = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $order = $this->orderOf($scenario);
        $order->setCancelled($this->getService(EventDispatcherInterface::class));
        self::assertSame('accepted', $this->reread($scenario['quote'])->getState(), 'The cancellation gave the quote back.');
        $this->signInAs($scenario['buyer']);

        $this->resume($scenario['quote']);

        self::assertResponseRedirects('/checkout/payment');
        \Thelia\Model\Map\CartTableMap::clearInstancePool();
        self::assertSame([$cart->getId()], array_values(CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->find()->getPrimaryKeys()), 'The cart that was to be paid.');
        $payment = $this->get('/checkout/payment');
        self::assertSame(200, $this->statusCode());
        self::assertCount(1, $payment->filter('[data-testid="quote-cart-exit"]'), 'The cart in hand is the cart of the quote.');
        self::assertMatchesRegularExpression('/Total\s*€1,620\.00/', preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $payment->filter('.Summary')->first()->text()) ?? '');
    }

    public function testACartPurgedOnTheWayIsBuiltAgainFromTheQuote(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $this->getPropelConnection()->exec('DELETE FROM `cart` WHERE `quote_id` = '.(int) $scenario['quote']->getId());
        \Thelia\Model\Map\CartTableMap::clearInstancePool();
        self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
        $this->signInAs($scenario['buyer']);

        $this->resume($scenario['quote']);

        self::assertResponseRedirects('/checkout/payment');
        \Thelia\Model\Map\CartTableMap::clearInstancePool();
        self::assertSame(1, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count(), 'The quote gave its cart back.');
        $this->get('/checkout/payment');
        self::assertSame(200, $this->statusCode());
    }

    public function testOnlyWhoCouldHaveAcceptedIsOfferedToResume(): void
    {
        $company = $this->company('Acme');
        $author = $this->buyer($company);
        $administrator = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $approver = $this->buyer($company, CompanyRoleCode::APPROVER);
        $this->roleOf('reader', [CompanyPermission::ORDER_VIEW_COMPANY, CompanyPermission::QUOTE_REQUEST, CompanyPermission::ORDER_VIEW_OWN]);
        $reader = $this->buyer($company, 'reader');
        $quote = $this->quotedQuote($author)['quote'];
        $this->quotes->accept($quote, $author, 2);

        $expected = [
            'author' => [$author, 1, 200],
            'administrator' => [$administrator, 1, 200],
            'approver, who may not answer' => [$approver, 0, 200],
            'reader, who answers but may not place orders' => [$reader, 0, 200],
        ];

        foreach ($expected as $who => [$customer, $offered, $status]) {
            $this->signInAs($customer);
            $page = $this->get($this->url('account_quote', ['quoteId' => $quote->getId()]));

            self::assertSame($status, $this->statusCode(), $who);
            self::assertCount($offered, $page->filter(self::RESUME), $who);
        }
    }

    public function testAnotherAccountCannotResumeTheQuoteOfAnotherInEitherDirection(): void
    {
        $first = $this->quotedQuoteReadyToPay();
        $second = $this->quotedQuoteReadyToPay();
        $this->quotes->accept($first['quote'], $first['buyer'], 2);
        $this->quotes->accept($second['quote'], $second['buyer'], 2);

        foreach ([[$first['buyer'], $second['quote'], 'A resumes the quote of B'], [$second['buyer'], $first['quote'], 'B resumes the quote of A']] as [$buyer, $quote, $what]) {
            $token = $this->tokenForTheSession();
            $this->signInAs($buyer);
            $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $quote->getId()]), ['_token' => $token, 'revision' => '2']);

            self::assertSame(404, $this->statusCode(), $what);
        }

        // And the page of the quote of the other offers nothing: it is not found.
        $this->signInAs($first['buyer']);
        $this->get($this->url('account_quote', ['quoteId' => $second['quote']->getId()]));
        self::assertSame(404, $this->statusCode());
    }

    /**
     * What the button of the page sends: the form is read off the page, token and revision included.
     */
    private function resume(Quote $quote): void
    {
        $form = $this->get($this->url('account_quote', ['quoteId' => $quote->getId()]))->filter(self::RESUME);
        self::assertCount(1, $form, 'The button is on the page.');
        $this->client->request('POST', (string) $form->attr('action'), ['_token' => (string) $form->filter('input[name="_token"]')->attr('value'), 'revision' => (string) $form->filter('input[name="revision"]')->attr('value')]);
    }

    /**
     * An order placed for the quote, which the quote reads as ordered, or still as accepted when the test wants an order the quote
     * does not know of.
     *
     * @param array{buyer: Customer, quote: Quote} $scenario
     */
    private function orderOf(array $scenario, bool $quoteStillReadsAccepted = false): Order
    {
        $order = $this->factory->order($scenario['buyer']);
        $order->setQuoteId($scenario['quote']->getId())->save($this->getPropelConnection());

        if (!$quoteStillReadsAccepted) {
            $this->reread($scenario['quote'])->setState('ordered')->save($this->getPropelConnection());
        }

        return $order;
    }
}
