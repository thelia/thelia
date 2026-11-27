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

use Propel\Runtime\Connection\ConnectionWrapper;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * Two things the shop does in one gesture and which stand or fall as one: a copy of a quote, whose
 * draft is the revision the original stands at, and the saving of a draft that is sent at once.
 */
final class QuoteDuplicationTest extends QuoteDomainTestCase
{
    public function testTheCopyIsADraftForTheSameAccountAndTheCompanyItBelongsToTodayWithTheLinesAsTheyStand(): void
    {
        $company = $this->company('Acme');
        $other = $this->company('Globex');
        $scenario = $this->sentScenario($this->buyerOf($company));
        $source = $scenario['quote']->setCompanyId($other->getId());
        $source->save($this->getPropelConnection());

        $copy = $this->quotes->duplicate($source, null)->quote;

        self::assertNotSame($source->getId(), $copy->getId());
        self::assertSame(QuoteState::Draft->value, $copy->getState());
        self::assertSame($source->getCustomerId(), $copy->getCustomerId());
        self::assertSame($company->getId(), $copy->getCompanyId(), 'the company the account belongs to today, not the one the original was written for');
        self::assertNotSame($source->getRef(), $copy->getRef());

        $revision = $this->revisionOf($copy, 1);
        self::assertSame(QuoteRevisionState::Draft->value, $revision->getState());
        self::assertSame(
            [[$scenario['a']->getRef(), 12.0, '90.000000'], [$scenario['b']->getRef(), 4.0, '45.000000'], [$scenario['c']->getRef(), 5.0, '20.000000']],
            array_map(static fn ($line): array => [$line->getProductRef(), (float) $line->getQuantity(), (string) $line->getPrice()], $this->linesOf($revision)),
        );
        self::assertSame('30.000000', $revision->getDiscount());
        self::assertSame('15.000000', $revision->getPostage());
    }

    public function testTheCopyOfAnAccountThatLeftItsCompanyBelongsToNoCompanyAndTheFormerColleaguesDoNotReadIt(): void
    {
        $company = $this->company('Acme');
        $leaver = $this->buyerOf($company);
        $colleague = $this->buyerOf($company, 'company_admin');
        $scenario = $this->sentScenario($leaver);
        $original = $scenario['quote'];
        self::assertSame($company->getId(), $original->getCompanyId());
        self::assertTrue($this->quoteAccess->canRead($colleague, $original));

        $this->facade->detach($leaver);
        $copy = $this->quotes->duplicate($original, null)->quote;

        self::assertNull($copy->getCompanyId());
        self::assertSame($leaver->getId(), $copy->getCustomerId());
        self::assertTrue($this->quoteAccess->canRead($leaver, $copy));
        self::assertFalse($this->quoteAccess->canRead($colleague, $copy), 'the former colleagues do not read what the account asks for now');
        self::assertTrue($this->quoteAccess->canRead($colleague, $original), 'the original stays as it was');
    }

    public function testTheCopyDoesNotCarryAnAddressSharedWithACompanyTheAccountLeft(): void
    {
        $company = $this->company('Acme');
        $leaver = $this->buyerOf($company);
        $colleague = $this->buyerOf($company, 'company_admin');
        $own = $this->fixtures->address($leaver, null, null, ['label' => 'Own']);
        $own->setIsDefault(1)->save($this->getPropelConnection());
        $shared = $this->addressOf($colleague, $company, 'Shared');
        $kept = $this->addressOf($leaver, null, 'Kept');
        $scenario = $this->sentScenario($leaver);
        $original = $scenario['quote']->setInvoiceAddressId($shared->getId())->setDeliveryAddressId($kept->getId());
        $original->save($this->getPropelConnection());

        $withTheCompany = $this->quotes->duplicate($original, null)->quote;
        self::assertSame($shared->getId(), $withTheCompany->getInvoiceAddressId(), 'a member of the company still uses its shared address');

        $this->facade->detach($leaver);
        $copy = $this->quotes->duplicate($original, null)->quote;

        self::assertSame($own->getId(), $copy->getInvoiceAddressId(), 'the shared address gives way to the default address');
        self::assertSame($kept->getId(), $copy->getDeliveryAddressId(), 'an address of the account itself is kept');
    }

    public function testTheCopyKeepsTheCurrencyOfTheOriginalWhateverTheDefaultCurrencyIs(): void
    {
        $dollar = $this->fixtures->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 1.1]);
        $scenario = $this->sentScenario(null, $dollar);
        self::assertSame($dollar->getId(), $scenario['quote']->getCurrencyId());
        self::assertNotSame(Currency::getDefaultCurrency()->getId(), $dollar->getId());

        $copy = $this->quotes->duplicate($scenario['quote'], null)->quote;

        self::assertSame($dollar->getId(), $copy->getCurrencyId(), 'amounts that are not converted stay in their currency');
        self::assertSame('90.000000', $this->linesOf($this->revisionOf($copy, 1))[0]->getPrice());
    }

    public function testALineThatCannotBeOrderedAnyMoreIsLeftOutAndSaid(): void
    {
        $scenario = $this->sentScenario();
        $this->lineOf($this->revisionOf($scenario['quote'], 2), $scenario['b']->getRef())->setProductSaleElementsId(null)->save($this->getPropelConnection());

        $duplication = $this->quotes->duplicate($scenario['quote'], null);

        self::assertTrue($duplication->hasOmittedLines());
        self::assertSame([['reference' => $scenario['b']->getRef(), 'title' => 'Product B']], $duplication->omittedLines);
        self::assertSame(
            [$scenario['a']->getRef(), $scenario['c']->getRef()],
            array_map(static fn ($line): string => (string) $line->getProductRef(), $this->linesOf($this->revisionOf($duplication->quote, 1))),
        );
    }

    public function testACopyWithNothingLeftOutSaysNothingWasLeftOut(): void
    {
        $duplication = $this->quotes->duplicate($this->sentScenario()['quote'], null);

        self::assertFalse($duplication->hasOmittedLines());
        self::assertSame([], $duplication->omittedLines);
    }

    public function testAGuestHasNoQuoteToCopy(): void
    {
        $scenario = $this->sentScenario();
        $scenario['buyer']->setIsGuest(1)->save($this->getPropelConnection());
        $quotesBefore = QuoteQuery::create()->count();

        $this->expectException(QuoteDraftRefusedException::class);

        try {
            $this->quotes->duplicate($scenario['quote'], null);
        } finally {
            self::assertSame($quotesBefore, QuoteQuery::create()->count());
        }
    }

    public function testADraftThatCannotBeWrittenLeavesNoQuoteAndUsesNoNumber(): void
    {
        $scenario = $this->sentScenario();
        $connection = $this->getPropelConnection();
        // No catalog price any more: the lines of the copy cannot be set against one.
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElementId($scenario['a']))->delete($connection);
        $numberBefore = $this->numberOf($this->quotes->createForCustomer($scenario['buyer'], null));

        try {
            $this->quotes->duplicate($scenario['quote'], null);
            self::fail('A draft with a line that has no price is refused.');
        } catch (QuoteDraftRefusedException) {
            // What is checked is what follows.
        }

        self::assertFalse($connection instanceof ConnectionWrapper ? $connection->isCommitable() : true, 'the work was one transaction and it was rolled back as one');
        // The rollback of a transaction nested in the one of the test only marks it: the rows stay
        // until the test is over, and the series tells whether the allocation was reached at all.
        self::assertSame($numberBefore + 1, $this->numberOf($this->quotes->createForCustomer($scenario['buyer'], null)), 'no number was used by the copy that failed');
    }

    public function testADraftIsSavedAndSentInOneGesture(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];

        $sent = $this->quotes->saveAndSend($quote, null, $this->draftOfTheShop($scenario));

        self::assertSame(QuoteState::Quoted->value, $sent->getState());
        self::assertSame(2, $sent->getCurrentRevision());
        $revision = $this->revisionOf($quote, 2);
        self::assertSame(QuoteRevisionState::Sent->value, $revision->getState());
        self::assertSame('1620.000000', $revision->getTotalTaxed());
        self::assertCount(3, $this->linesOf($revision));
        self::assertCount(1, $this->eventsOf(QuoteEvents::SENT));
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::SENT][0]['transactionLevel'], 'the shop is told once the transaction is over');
    }

    public function testADraftThatCannotBeSentIsNotLeftSavedAsIfNothingHadHappened(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $connection = $this->getPropelConnection();
        // A discount above what the lines come to: the draft is accepted, the sending is not.
        $draft = $this->draftFrom($this->revisionOf($quote, 1), discount: '999999');

        try {
            $this->quotes->saveAndSend($quote, null, $draft);
            self::fail('A discount above the total is refused at the sending.');
        } catch (QuoteDraftRefusedException) {
            // What is checked is what follows.
        }

        self::assertFalse($connection instanceof ConnectionWrapper ? $connection->isCommitable() : true, 'the draft and the sending are one transaction, rolled back as one');
        self::assertSame([], $this->eventsOf(QuoteEvents::SENT));
        self::assertSame(QuoteState::Requested->value, $this->reread($quote)->getState());
    }

    public function testTheDraftAndTheSendingSeeTheSameQuote(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        // A draft already written, with a line the new one leaves out.
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $lines = $this->linesOf($this->revisionOf($quote, 2));

        $this->quotes->saveAndSend($quote, null, new QuoteDraftInput([new QuoteDraftLine((int) $lines[0]->getId(), (int) $lines[0]->getProductSaleElementsId(), 1.0, '80')]));

        $revisions = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->orderByNumber()->find();
        self::assertCount(2, $revisions, 'the draft that was there is the one that was written over and sent');
        self::assertSame([[1.0, '80.000000']], array_map(static fn ($line): array => [(float) $line->getQuantity(), (string) $line->getPrice()], $this->linesOf($this->revisionOf($quote, 2))));
    }

    /**
     * A quote sent at its second revision: 12 of A at 90, 4 of B at 45 and 5 of C at 20, 30 off, 15 of postage.
     *
     * @return array{buyer: Customer, quote: Quote, a: \Thelia\Model\Product, b: \Thelia\Model\Product, c: \Thelia\Model\Product}
     */
    private function sentScenario(?Customer $buyer = null, ?Currency $currency = null): array
    {
        $buyer ??= $this->customer();
        $currency ??= $this->fixtures->currency();
        $products = [];

        foreach (['a' => ['100', 'Product A'], 'b' => ['50', 'Product B'], 'c' => ['20', 'Product C']] as $key => [$price, $title]) {
            $products[$key] = $this->fixtures->product($this->fixtures->category(), $this->vatRule(), $currency, ['basePrice' => (float) $price, 'baseQuantity' => 100, 'title' => $title]);
        }

        $cart = $this->fixtures->cart($buyer, ['currency' => $currency]);
        $this->fixtures->cartItem($cart, $products['a'], null, ['quantity' => 10.0, 'price' => '100', 'promoPrice' => '100']);
        $this->fixtures->cartItem($cart, $products['b'], null, ['quantity' => 4.0, 'price' => '50', 'promoPrice' => '50']);
        $quote = $this->quotes->requestFromCart($cart, $buyer, new QuoteRequestInput());
        $scenario = ['buyer' => $buyer, 'quote' => $quote, ...$products];

        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);

        return $scenario;
    }

    private function numberOf(Quote $quote): int
    {
        return (int) preg_replace('/\D/', '', (string) $quote->getRef());
    }
}
