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

namespace Thelia\Tests\Integration\Quote\PersonalData;

use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Customer\Service\CustomerPersonalDataExporter;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\PersonalData\QuotePersonalDataProvider;
use Thelia\Model\Customer;
use Thelia\Model\Quote;
use Thelia\Model\QuoteRevisionAddressQuery;
use Thelia\Tests\Integration\Quote\QuoteDomainTestCase;

/**
 * The quotes of a customer are exported with the customer's data, and survive their anonymization
 * for the commercial record without what identifies the person: their comment and the addresses.
 */
final class QuotePersonalDataTest extends QuoteDomainTestCase
{
    public function testTheExportListsTheQuotesOfTheCustomerWithTheirLines(): void
    {
        [$buyer, $quote] = $this->quoteWithAnAddress('Please ship to the back door');

        $data = (new QuotePersonalDataProvider())->exportPersonalData($buyer);

        self::assertCount(1, $data);
        self::assertSame($quote->getRef(), $data[0]['ref']);
        self::assertSame('requested', $data[0]['state']);
        self::assertSame('Please ship to the back door', $data[0]['buyer_comment']);
        self::assertSame('Product A', $data[0]['revisions'][0]['lines'][0]['title']);
        self::assertSame(1, $data[0]['revisions'][0]['number']);
    }

    public function testTheExportOfOneCustomerNeverHoldsTheQuoteOfAnother(): void
    {
        [$first, $firstQuote] = $this->quoteWithAnAddress('First');
        [$second, $secondQuote] = $this->quoteWithAnAddress('Second');

        $provider = new QuotePersonalDataProvider();

        self::assertSame([$firstQuote->getRef()], array_column($provider->exportPersonalData($first), 'ref'));
        self::assertSame([$secondQuote->getRef()], array_column($provider->exportPersonalData($second), 'ref'));
    }

    public function testTheSectionIsPartOfTheExportOfTheCustomer(): void
    {
        [$buyer, $quote] = $this->quoteWithAnAddress('Hello');

        $export = $this->getService(CustomerPersonalDataExporter::class)->export($buyer);

        self::assertSame([$quote->getRef()], array_column($export['quotes'], 'ref'));
    }

    public function testAnonymizingEmptiesTheCommentAndDetachesTheAddressesButKeepsTheQuote(): void
    {
        [$buyer, $quote] = $this->quoteWithAnAddress('My phone is 0102030405');

        $this->getService(CustomerAnonymizer::class)->anonymize($buyer);

        $kept = $this->reread($quote);
        self::assertNull($kept->getBuyerComment());
        self::assertNull($kept->getInvoiceAddressId());
        self::assertNull($kept->getDeliveryAddressId());
        self::assertSame($quote->getRef(), $kept->getRef());
        self::assertSame('requested', $kept->getState());
        self::assertSame(2.0, (float) $this->linesOf($this->revisionOf($kept, 1))[0]->getQuantity(), 'the lines are the record');
    }

    public function testTheProviderDetachesTheAddressesItself(): void
    {
        [$buyer, $quote] = $this->quoteWithAnAddress('Mine');

        (new QuotePersonalDataProvider())->anonymizePersonalData($buyer);

        $kept = $this->reread($quote);
        self::assertNull($kept->getBuyerComment());
        self::assertNull($kept->getInvoiceAddressId());
        self::assertNull($kept->getDeliveryAddressId());
    }

    public function testAnonymizingOneCustomerLeavesTheQuoteOfAnotherAlone(): void
    {
        [$buyer] = $this->quoteWithAnAddress('Mine');
        [, $other, $otherAddressId] = $this->quoteWithAnAddress('Theirs');

        (new QuotePersonalDataProvider())->anonymizePersonalData($buyer);

        $untouched = $this->reread($other);
        self::assertSame('Theirs', $untouched->getBuyerComment());
        self::assertSame($otherAddressId, $untouched->getInvoiceAddressId());
        self::assertSame($otherAddressId, $untouched->getDeliveryAddressId());
    }

    public function testTheExportListsTheCopiesOfTheAddressesKeptOnEachRevision(): void
    {
        [$buyer, $quote] = $this->quoteWithAnAddress('Mine');

        $data = (new QuotePersonalDataProvider())->exportPersonalData($buyer);

        $addresses = $data[0]['revisions'][0]['addresses'];
        self::assertSame(['delivery', 'invoice'], array_column($addresses, 'kind'));
        self::assertSame('John Doe', $addresses[0]['name']);
        self::assertSame('Paris', $addresses[0]['city']);
        self::assertNotSame('', (string) $addresses[0]['address1']);
        self::assertSame($quote->getRef(), $data[0]['ref']);
    }

    public function testAnonymizingEmptiesTheCopiesOfTheAddressesAndKeepsWhichKindTheyAre(): void
    {
        [$buyer, $quote] = $this->quoteWithAnAddress('Mine');
        $revisionId = $this->revisionOf($quote, 1)->getId();

        (new QuotePersonalDataProvider())->anonymizePersonalData($buyer);

        $copies = [...QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($revisionId)->orderByKind()->find()];
        self::assertSame(['delivery', 'invoice'], array_map(static fn ($copy): ?string => $copy->getKind(), $copies), 'the revision keeps its two copies');

        foreach ($copies as $copy) {
            self::assertNull($copy->getFirstname());
            self::assertNull($copy->getLastname());
            self::assertNull($copy->getCompany());
            self::assertNull($copy->getAddress1());
            self::assertNull($copy->getAddress2());
            self::assertNull($copy->getAddress3());
            self::assertNull($copy->getZipcode());
            self::assertNull($copy->getCity());
            self::assertNull($copy->getVatNumber());
        }
    }

    public function testAnonymizingOneCustomerLeavesTheCopiesOfAnotherAlone(): void
    {
        [$buyer] = $this->quoteWithAnAddress('Mine');
        [, $other] = $this->quoteWithAnAddress('Theirs');

        (new QuotePersonalDataProvider())->anonymizePersonalData($buyer);

        foreach (QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($this->revisionOf($other, 1)->getId())->find() as $copy) {
            self::assertSame('John', $copy->getFirstname());
            self::assertSame('Paris', $copy->getCity());
        }
    }

    public function testAQuoteTheShopHasNotSentIsNotExported(): void
    {
        [$buyer, $sent] = $this->quoteWithAnAddress('Sent one');
        $notSent = $this->quotes->createForCustomer($buyer, null);
        $this->quotes->saveDraft($notSent, null, new \Thelia\Domain\Quote\DTO\QuoteDraftInput([], merchantNote: 'The shop is still thinking'));

        $data = (new QuotePersonalDataProvider())->exportPersonalData($buyer);

        self::assertSame([$sent->getRef()], array_column($data, 'ref'), 'the buyer does not know the other one exists');
        self::assertStringNotContainsString('still thinking', json_encode($data, \JSON_THROW_ON_ERROR));
    }

    public function testTheDraftOfTheNextRevisionIsNotExported(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef())->getId(), null, '85')]);
        $this->quotes->saveDraft($this->reread($quote), null, $this->draftFrom($this->revisionOf($quote, 2), merchantNote: 'Secret margin of the shop'));

        $data = (new QuotePersonalDataProvider())->exportPersonalData($scenario['buyer']);

        self::assertSame([1, 2], array_column($data[0]['revisions'], 'number'));
        self::assertSame(['sent', 'sent'], array_column($data[0]['revisions'], 'state'));
        self::assertStringNotContainsString('Secret margin', json_encode($data, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{Customer, Quote, int}
     */
    private function quoteWithAnAddress(string $comment): array
    {
        $buyer = $this->customer();
        $address = $this->fixtures->address($buyer);
        $quote = $this->request($buyer, null, new QuoteRequestInput(comment: $comment, invoiceAddressId: (int) $address->getId(), deliveryAddressId: (int) $address->getId()));

        return [$buyer, $quote, (int) $address->getId()];
    }
}
