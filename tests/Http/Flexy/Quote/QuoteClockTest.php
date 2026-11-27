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

use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

/**
 * The day a quote is judged on is the one of the clock of the core, the one the core judges it on: around midnight the
 * page and the core agree on whether the last day of the quote went by.
 */
final class QuoteClockTest extends QuoteFlexyTestCase
{
    private ?ClockInterface $clockBefore = null;

    protected function tearDown(): void
    {
        if (null !== $this->clockBefore) {
            Clock::set($this->clockBefore);
        }

        parent::tearDown();
    }

    public function testTheLastDayOfTheQuoteIsStillOfferedOnThatDayAndNotTheDayAfter(): void
    {
        $scenario = $this->quotedQuote(null, null, new \DateTimeImmutable('2026-12-16'));
        $this->signInAs($scenario['buyer']);

        $this->setTheClockTo('2026-12-16 23:55:00');
        self::assertCount(1, $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]))->filter('[data-testid="quote-reply"]'), 'The last day.');

        $this->setTheClockTo('2026-12-17 00:05:00');
        self::assertCount(0, $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]))->filter('[data-testid="quote-reply"]'), 'The day after: the page agrees with the core.');
    }

    public function testAnAcceptanceRefusedBecauseTheLastDayWentByIsToldAsExpiredOnTheDayOfTheCore(): void
    {
        $scenario = $this->quotedQuote(null, null, new \DateTimeImmutable('2026-12-16'));
        $token = $this->tokenForTheSession();
        $this->signInAs($scenario['buyer']);

        $this->setTheClockTo('2026-12-17 00:05:00');
        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token, 'revision' => '2']);

        self::assertResponseRedirects($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));
        self::assertStringContainsString('has expired', $this->client->followRedirect()->filter('[data-testid="quote-answer-error"]')->text());
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    /**
     * The clock of the core is read from a global the services share: setting it moves every service that takes the
     * clock, and the pages along with them.
     */
    private function setTheClockTo(string $moment): void
    {
        $this->clockBefore ??= Clock::get();
        Clock::set(new MockClock($moment));
    }
}
