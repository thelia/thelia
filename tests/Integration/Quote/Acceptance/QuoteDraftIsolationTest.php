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

namespace Thelia\Tests\Integration\Quote\Acceptance;

use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Model\ModuleQuery;

/**
 * What the shop writes while it prepares the next revision does not reach what the buyer can do with
 * the one they were sent: not its validity, not its carrier.
 */
final class QuoteDraftIsolationTest extends QuoteAcceptanceTestCase
{
    public function testAnExpiredQuoteStaysClosedToTheBuyerWhateverTheDraftSays(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $this->clock->modify('2027-01-02 09:00:00');

        // No date at all, as a screen that leaves the field empty posts it.
        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2)));
        $this->assertTheBuyerCannotAccept($scenario);

        // A date ahead: the quote is not prolonged until the draft is sent.
        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2), validUntil: new \DateTimeImmutable('2027-01-31')));
        $this->assertTheBuyerCannotAccept($scenario);

        self::assertSame('2026-12-31', $this->reread($quote)->getValidUntil('Y-m-d'));
        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState(), 'nothing was written on the quote');
    }

    public function testTheCarrierOfADraftDoesNotChangeTheCartTheBuyerGets(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $this->clock->modify('2027-01-02 09:00:00');

        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2), deliveryModuleId: $this->otherCarrierId()));
        $this->quotes->extend($quote, new \DateTimeImmutable('2027-01-10'));

        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);

        self::assertSame($this->deliveryModuleId(), $cart->getDeliveryModuleId(), 'the carrier of the revision the buyer accepted, not the one of the draft');
    }

    public function testTheCarrierOfADraftIsTheOneOfTheCartOnceTheDraftIsSent(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $this->clock->modify('2027-01-02 09:00:00');

        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2), deliveryModuleId: $this->otherCarrierId(), validUntil: new \DateTimeImmutable('2027-01-31')));
        $this->quotes->send($quote, null);

        $cart = $this->quotes->accept($this->reread($quote), $scenario['buyer'], 3);

        self::assertSame($this->otherCarrierId(), $cart->getDeliveryModuleId());
    }

    /**
     * @param array{buyer: \Thelia\Model\Customer, quote: \Thelia\Model\Quote} $scenario
     */
    private function assertTheBuyerCannotAccept(array $scenario): void
    {
        try {
            $this->quotes->accept($this->reread($scenario['quote']), $scenario['buyer'], 2);
            self::fail('The buyer accepted a quote that is past its validity.');
        } catch (QuoteTransitionRefusedException $refusal) {
            self::assertSame(QuoteState::Expired, $refusal->from());
        }
    }

    private function otherCarrierId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId();
    }
}
