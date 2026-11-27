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

namespace Thelia\Tests\Integration\Quote;

use Thelia\Domain\Quote\Document\QuoteDocumentContext;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\Exception\UnknownQuoteException;
use Thelia\Domain\Quote\Service\QuoteRevisionGuard;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionQuery;

/**
 * What the shop writes on a draft: the lines, the prices against the catalog, the note, and the
 * revisions that were sent, which nothing writes to again.
 */
final class QuoteDraftTest extends QuoteDomainTestCase
{
    public function testTheShopStartsAQuoteForAnAccountWithNoRequest(): void
    {
        $customer = $this->customer();

        $quote = $this->quotes->createForCustomer($customer, $this->fixtures->admin());

        $stored = $this->reread($quote);
        self::assertSame(QuoteState::Draft->value, $stored->getState());
        self::assertSame(1, $stored->getCurrentRevision());
        self::assertSame($customer->getId(), $stored->getCustomerId());
        self::assertMatchesRegularExpression('/^QUO\d{8}$/', (string) $stored->getRef());

        $first = $this->revisionOf($stored, 1);
        self::assertSame(QuoteRevisionState::Draft->value, $first->getState());
        self::assertSame('admin', $first->getAuthor());
        self::assertNotNull($first->getAdminId());
        self::assertSame([], $this->linesOf($first));
    }

    public function testAGuestCannotHaveAQuote(): void
    {
        try {
            $this->quotes->createForCustomer($this->guest(), null);
            self::fail('The quote was not refused.');
        } catch (QuoteDraftRefusedException $refused) {
            self::assertSame(QuoteDraftRefusedException::CUSTOMER_REFUSED, $refused->reason());
        }
    }

    public function testAQuoteOfTheShopIsWrittenSentAndStartsAtTheFirstRevision(): void
    {
        $customer = $this->customer();
        $product = $this->product('100', 'Product A');
        $quote = $this->quotes->createForCustomer($customer, null);

        $draft = $this->quotes->saveDraft($quote, null, new QuoteDraftInput([new QuoteDraftLine(null, $this->saleElementId($product), 3.0, '95')], postage: '10'));

        self::assertSame(1, $draft->getNumber(), 'the draft of a quote that has no request is the first revision');
        self::assertSame(['100.000000'], array_map(static fn ($line): ?string => $line->getCatalogPrice(), $this->linesOf($draft)));

        $sent = $this->quotes->send($quote, null);

        self::assertSame(QuoteState::Quoted->value, $sent->getState());
        self::assertSame(1, $sent->getCurrentRevision());
        self::assertSame(1, QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testRewritingTheDraftKeepsTheIdsOfItsLinesAndDropsTheLinesLeftOut(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $first = $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $lines = $this->linesOf($first);

        $again = $this->quotes->saveDraft($quote, null, new QuoteDraftInput([
            new QuoteDraftLine((int) $lines[2]->getId(), (int) $lines[2]->getProductSaleElementsId(), 7.0, '19'),
            new QuoteDraftLine((int) $lines[0]->getId(), (int) $lines[0]->getProductSaleElementsId(), 12.0, '88'),
        ]));

        self::assertSame($first->getId(), $again->getId(), 'still the same draft');
        self::assertSame(2, QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count());
        $kept = $this->linesOf($again);
        self::assertSame([(int) $lines[2]->getId(), (int) $lines[0]->getId()], array_map(static fn ($line): ?int => $line->getId(), $kept));
        self::assertSame([1, 2], array_map(static fn ($line): ?int => $line->getPosition(), $kept));
        self::assertSame(['19.000000', '88.000000'], array_map(static fn ($line): ?string => $line->getPrice(), $kept));
        self::assertSame(['20.000000', '100.000000'], array_map(static fn ($line): ?string => $line->getCatalogPrice(), $kept));
        self::assertSame(0, QuoteItemQuery::create()->filterById((int) $lines[1]->getId())->count(), 'the line left out is gone');
    }

    public function testARevisionThatWasSentIsNeverWrittenAgain(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef())->getId(), 11.0, null)]);
        $sent = $this->revisionOf($quote, 2);
        $before = [$sent->toArray(), array_map(static fn ($line): array => $line->toArray(), $this->linesOf($sent))];
        $sentLine = $this->lineOf($sent, $scenario['a']->getRef());

        // The draft names a line of the revision that was sent, and changes everything about it.
        $draft = $this->quotes->saveDraft($quote, null, new QuoteDraftInput(
            [new QuoteDraftLine((int) $sentLine->getId(), (int) $sentLine->getProductSaleElementsId(), 99.0, '1')],
            discount: '5',
            postage: '5',
            merchantNote: 'Rewritten',
        ));

        self::assertSame(3, $draft->getNumber());
        self::assertNotSame($sentLine->getId(), $this->linesOf($draft)[0]->getId(), 'the line of the draft is a new one');
        self::assertSame($before, [$this->revisionOf($quote, 2)->toArray(), array_map(static fn ($line): array => $line->toArray(), $this->linesOf($sent))], 'the revision that was sent is as it was');
    }

    public function testTheGuardRefusesAnyWriteToARevisionThatWasSent(): void
    {
        $scenario = $this->requestedScenario();
        $guard = new QuoteRevisionGuard();

        // The first revision is the one the buyer sent.
        try {
            $guard->assertDraft($this->revisionOf($scenario['quote'], 1));
            self::fail('A revision that was sent was accepted for writing.');
        } catch (QuoteDraftRefusedException $refused) {
            self::assertSame(QuoteDraftRefusedException::REVISION_LOCKED, $refused->reason());
        }

        $guard->assertDraft(new QuoteRevision());
        $guard->assertDraft($this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom($this->revisionOf($scenario['quote'], 1))));
    }

    public function testALineTheShopAddsIsSetAgainstThePriceTheBuyerWouldPay(): void
    {
        $scenario = $this->requestedScenario();
        $group = $this->fixtures->customerGroup(discount: 10.0);
        $this->fixtures->assignCustomerGroup($scenario['buyer'], $group);
        $input = $this->draftFrom($this->revisionOf($scenario['quote'], 1), added: [new QuoteDraftLine(null, $this->saleElementId($scenario['c']), 5.0, '17')]);

        $draft = $this->quotes->saveDraft($scenario['quote'], null, $input);

        self::assertSame('18.000000', $this->lineOf($draft, $scenario['c']->getRef())->getCatalogPrice(), '20 less the 10 % of the group of the buyer');
        self::assertSame('17.000000', $this->lineOf($draft, $scenario['c']->getRef())->getPrice());
        self::assertSame('100.000000', $this->lineOf($draft, $scenario['a']->getRef())->getCatalogPrice(), 'the line of the request keeps the price it was asked against');
    }

    public function testTheNoteOfTheShopIsKeptAsTextWithNoMarkup(): void
    {
        $scenario = $this->requestedScenario();

        $draft = $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom(
            $this->revisionOf($scenario['quote'], 1),
            merchantNote: '<p onclick="steal()">Terms <b>apply</b> jusqu&#039;à l\'inventaire</p><script>alert(1)</script>',
        ));

        self::assertSame("Terms apply jusqu'à l'inventaire", QuoteRevisionQuery::create()->findPk($draft->getId())?->getMerchantNote());
    }

    public function testTheNoteOfTheShopReadsBackAsTextInTheDocumentOfTheRevision(): void
    {
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom(
            $this->revisionOf($scenario['quote'], 1),
            merchantNote: "Prix valable jusqu'à l'inventaire <b>x</b>",
        ));
        $this->quotes->send($this->reread($scenario['quote']), null);

        $context = $this->getService(QuoteDocumentContext::class)->build($this->reread($scenario['quote']), $this->revisionOf($scenario['quote'], 2), 'en_US');

        self::assertSame("Prix valable jusqu'à l'inventaire x", $context['merchant_note']);
    }

    public function testWhatMakesNoSenseIsRefusedBeforeAnythingIsWritten(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $revision = $this->revisionOf($quote, 1);
        $line = $this->linesOf($revision)[0];
        $sale = (int) $line->getProductSaleElementsId();
        $other = $this->requestedScenario();
        $foreignLine = $this->linesOf($this->revisionOf($other['quote'], 1))[0];
        $base = static fn (array $lines, string $discount = '0', string $postage = '0', ?int $module = null, ?\DateTimeImmutable $validUntil = null) => new QuoteDraftInput($lines, $discount, $postage, $module, null, $validUntil);

        foreach ([
            QuoteDraftRefusedException::INVALID_QUANTITY => $base([new QuoteDraftLine(null, $sale, 0.0, '1')]),
            'negative quantity' => $base([new QuoteDraftLine(null, $sale, -2.0, '1')]),
            'not a number' => $base([new QuoteDraftLine(null, $sale, \NAN, '1')]),
            QuoteDraftRefusedException::INVALID_PRICE => $base([new QuoteDraftLine(null, $sale, 1.0, '-1')]),
            'price as text' => $base([new QuoteDraftLine(null, $sale, 1.0, 'free')]),
            QuoteDraftRefusedException::INVALID_DISCOUNT => $base([], '-1'),
            QuoteDraftRefusedException::INVALID_POSTAGE => $base([], '0', '1e3'),
            QuoteDraftRefusedException::UNKNOWN_SALE_ELEMENT => $base([new QuoteDraftLine(null, 999999999, 1.0, '1')]),
            QuoteDraftRefusedException::UNKNOWN_LINE => $base([new QuoteDraftLine((int) $foreignLine->getId(), $sale, 1.0, '1')]),
            'a line used twice' => $base([new QuoteDraftLine((int) $line->getId(), $sale, 1.0, '1'), new QuoteDraftLine((int) $line->getId(), $sale, 1.0, '1')]),
            QuoteDraftRefusedException::UNKNOWN_DELIVERY_MODULE => $base([], '0', '0', (int) ModuleQuery::create()->findOneByCode('Cheque')?->getId()),
            QuoteDraftRefusedException::VALIDITY_IN_THE_PAST => $base([], '0', '0', null, new \DateTimeImmutable('2026-11-30')),
        ] as $name => $input) {
            try {
                $this->quotes->saveDraft($quote, null, $input);
                self::fail('The draft was not refused: '.$name);
            } catch (QuoteDraftRefusedException $refused) {
                self::assertMatchesRegularExpression('/^[a-z_]+$/', $refused->reason());

                if (str_contains((string) $name, '_')) {
                    self::assertSame($name, $refused->reason());
                }
            }
        }

        self::assertSame(1, QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count(), 'no revision was written');
    }

    public function testADraftIsWrittenOnlyWhereTheShopCanAnswer(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);

        // Quoted: the buyer is looking at it.
        $this->assertDraftRefused($quote, QuoteState::Quoted);

        $this->quotes->reject($quote, $scenario['buyer'], 2);
        $this->assertDraftRefused($quote, QuoteState::Rejected);
    }

    public function testAQuoteThatDoesNotExistIsRefused(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $ghost = clone $quote;
        $ghost->setId(999999999);

        $this->expectException(UnknownQuoteException::class);
        $this->quotes->saveDraft($ghost, null, new QuoteDraftInput([]));
    }

    public function testADeliveryModuleOfTheShopIsKeptOnTheDraftAndNotOnTheQuote(): void
    {
        $scenario = $this->requestedScenario();
        $module = ModuleQuery::create()->findOneByCode('CustomDelivery');
        self::assertNotNull($module);

        $draft = $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom($this->revisionOf($scenario['quote'], 1), deliveryModuleId: (int) $module->getId()));

        self::assertSame($module->getId(), $draft->getDeliveryModuleId());
        self::assertNull($this->reread($scenario['quote'])->getDeliveryModuleId(), 'the quote says what the buyer was sent, not what is being written');
    }

    private function assertDraftRefused(Quote $quote, QuoteState $state): void
    {
        try {
            $this->quotes->saveDraft($quote, null, new QuoteDraftInput([]));
            self::fail('A draft was accepted for a quote that is '.$state->value);
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame('draft_refused', $refused->reason());
            self::assertSame($state, $refused->from());
        }
    }
}
