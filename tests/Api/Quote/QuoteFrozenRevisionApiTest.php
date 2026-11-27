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

use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * What a revision that was sent says over the API: the amounts, the validity, the carrier and the
 * two addresses as they were frozen when it was sent, to the buyer and to the shop; and what a
 * draft says, to the shop only.
 */
final class QuoteFrozenRevisionApiTest extends QuoteApiTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheBuyerReadsTheAmountsTheValidityTheCarrierAndTheAddressesOfTheRevisionSent(): void
    {
        $scenario = $this->quotedQuoteWithAddresses();
        $quote = $scenario['quote'];

        $token = $this->token($scenario['buyer']);
        $body = $this->body($this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $token));
        $answer = $this->frontRevision($quote, 2, $token);

        self::assertSame('1360.000000', $answer['itemsUntaxed']);
        self::assertSame('272.000000', $answer['itemsTax']);
        self::assertSame('15.000000', $answer['postage']);
        self::assertSame('3.000000', $answer['postageTax']);
        self::assertSame('30.000000', $answer['discount']);
        self::assertSame('1350.000000', $answer['totalUntaxed']);
        self::assertSame('1620.000000', $answer['totalTaxed']);
        self::assertFalse($answer['reverseCharge']);
        self::assertSame((new \DateTimeImmutable('+30 days'))->format('Y-m-d'), substr($answer['validUntil'], 0, 10));
        self::assertSame($this->carrierId(), $answer['deliveryModuleId']);
        self::assertSame('1 Invoice Street', $answer['invoiceAddress']['address1']);
        self::assertSame('Lyon', $answer['invoiceAddress']['city']);
        self::assertSame('Analytical Engines', $answer['invoiceAddress']['company']);
        self::assertSame('2 Delivery Road', $answer['deliveryAddress']['address1']);
        self::assertSame('Nantes', $answer['deliveryAddress']['city']);
        self::assertNotSame('', (string) $answer['deliveryAddress']['country']);

        self::assertFalse($body['reverseCharge'], 'the quote says it for the revision it stands at');
        self::assertSame($this->carrierId(), $body['deliveryModuleId']);
        self::assertSame($answer['validUntil'], $body['validUntil']);
    }

    public function testTheRequestOfTheBuyerCarriesTheAddressesItWasWrittenWithButNoValidity(): void
    {
        $scenario = $this->quotedQuoteWithAddresses();

        $request = $this->frontRevision($scenario['quote'], 1, $this->token($scenario['buyer']));

        self::assertSame('1 Invoice Street', $request['invoiceAddress']['address1']);
        self::assertSame('2 Delivery Road', $request['deliveryAddress']['address1']);
        self::assertSame('1200.000000', $request['itemsUntaxed']);
        self::assertArrayNotHasKey('validUntil', $request, 'left out when there is none');
        self::assertArrayNotHasKey('deliveryModuleId', $request);
    }

    public function testTheAddressesAndTheAmountsReadBackAreThoseOfTheSendingWhateverBecomesOfTheAddressBook(): void
    {
        $scenario = $this->quotedQuoteWithAddresses();
        $quote = $scenario['quote'];
        $token = $this->token($scenario['buyer']);
        $before = $this->frontRevision($quote, 2, $token);

        AddressQuery::create()->findPk($scenario['delivery']->getId())->setAddress1('Somewhere else')->save($this->getPropelConnection());
        AddressQuery::create()->filterById($scenario['invoice']->getId())->delete($this->getPropelConnection());
        \Thelia\Model\Map\AddressTableMap::clearInstancePool();

        $after = $this->frontRevision($quote, 2, $token);

        self::assertSame($before['deliveryAddress'], $after['deliveryAddress']);
        self::assertSame($before['invoiceAddress'], $after['invoiceAddress']);
        self::assertSame($before['totalTaxed'], $after['totalTaxed']);
    }

    public function testTheShopReadsTheSameFrozenValuesAndTheValuesOfTheDraft(): void
    {
        $scenario = $this->quotedQuoteWithAddresses();
        $quote = $scenario['quote'];
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->filterByNumber(2)->findOne();
        $lines = [];

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $lines[] = new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), (float) $line->getQuantity(), (string) $line->getPrice());
        }

        // The quote has to be open to a new revision: past its validity.
        $quote->setValidUntil((new \DateTimeImmutable('-1 day'))->format('Y-m-d'))->save($this->getPropelConnection());
        $this->quotes->saveDraft($quote, null, new QuoteDraftInput($lines, '0', '0', $this->otherCarrierId(), null, new \DateTimeImmutable('+90 days')));

        $body = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken()));

        self::assertCount(3, $body['revisions']);
        self::assertSame('1620.000000', $body['revisions'][1]['totalTaxed']);
        self::assertSame('2 Delivery Road', $body['revisions'][1]['deliveryAddress']['address1']);

        $draft = $body['revisions'][2];
        self::assertSame('draft', $draft['state']);
        self::assertSame($this->otherCarrierId(), $draft['deliveryModuleId']);
        self::assertSame((new \DateTimeImmutable('+90 days'))->format('Y-m-d'), substr($draft['validUntil'], 0, 10));
        self::assertArrayNotHasKey('totalTaxed', $draft, 'a draft is not frozen');
        self::assertArrayNotHasKey('invoiceAddress', $draft, 'its addresses are copied when it is sent');
        self::assertSame($this->carrierId(), $body['deliveryModuleId'], 'the quote says what the buyer was sent');
    }

    public function testTheBuyerNeverReadsTheDraftNorWhatItCarries(): void
    {
        $scenario = $this->quotedQuoteWithAddresses();
        $quote = $scenario['quote'];
        $quote->setValidUntil((new \DateTimeImmutable('-1 day'))->format('Y-m-d'))->save($this->getPropelConnection());
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->filterByNumber(2)->findOne();
        $lines = [];

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $lines[] = new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), (float) $line->getQuantity(), '1');
        }

        $this->quotes->saveDraft($quote, null, new QuoteDraftInput($lines, '0', '0', $this->otherCarrierId(), 'A private word', new \DateTimeImmutable('+90 days')));

        $token = $this->token($scenario['buyer']);
        $response = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $token);
        $body = $this->body($response);
        $history = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions', token: $token);

        self::assertSame([1, 2], array_column($this->body($history)['hydra:member'], 'number'));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions/3', token: $token)->getStatusCode());

        foreach ([$response, $history] as $read) {
            self::assertStringNotContainsString('A private word', (string) $read->getContent());
            self::assertStringNotContainsString((new \DateTimeImmutable('+90 days'))->format('Y-m-d'), (string) $read->getContent());
        }

        self::assertSame($this->carrierId(), $body['deliveryModuleId']);
    }

    public function testTheQuoteSaysWhetherTheBuyerOwesTheVatHimself(): void
    {
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $this->country('FR')->getId());
        ConfigQuery::resetCache();
        $buyer = $this->customer();
        $invoice = $this->fixtures->address($buyer, $this->country('BE'));
        $invoice->setCompany('Acme')->setVatNumber('BE0123456789')->setVatVerifiedAt(new \DateTime('-10 days'))->save($this->getPropelConnection());
        $quote = $this->quotes->requestFromCart(
            $this->cartOf($buyer, [[$this->product('100', 'Product A'), 2.0, '100']]),
            $buyer,
            new QuoteRequestInput(invoiceAddressId: (int) $invoice->getId()),
        );

        $body = $this->body($this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $this->token($buyer)));

        $request = $this->frontRevision($quote, 1, $this->token($buyer));

        self::assertTrue($body['reverseCharge']);
        self::assertTrue($request['reverseCharge']);
        self::assertSame('BE0123456789', $request['invoiceAddress']['vatNumber']);
    }

    public function testTheListCarriesNoReverseChargeNorRevision(): void
    {
        $scenario = $this->quotedQuoteWithAddresses();

        $member = $this->body($this->jsonRequest('GET', '/api/front/account/quotes', token: $this->token($scenario['buyer'])))['hydra:member'][0];

        self::assertArrayNotHasKey('reverseCharge', $member);
        self::assertArrayNotHasKey('revisions', $member);
    }

    /**
     * @return array{buyer: \Thelia\Model\Customer, quote: Quote, invoice: Address, delivery: Address}
     */
    private function quotedQuoteWithAddresses(): array
    {
        $buyer = $this->customer();
        $country = Country::getDefaultCountry();
        $invoice = $this->fixtures->address($buyer, $country, null, ['address1' => '1 Invoice Street', 'city' => 'Lyon', 'zipcode' => '69001']);
        $invoice->setCompany('Analytical Engines')->save($this->getPropelConnection());
        $delivery = $this->fixtures->address($buyer, $country, null, ['address1' => '2 Delivery Road', 'city' => 'Nantes', 'zipcode' => '44000']);
        $a = $this->product('100', 'Product A');
        $b = $this->product('50', 'Product B');
        $c = $this->product('20', 'Product C');
        $quote = $this->quotes->requestFromCart(
            $this->cartOf($buyer, [[$a, 10.0, '100'], [$b, 4.0, '50']]),
            $buyer,
            new QuoteRequestInput(invoiceAddressId: (int) $invoice->getId(), deliveryAddressId: (int) $delivery->getId()),
        );

        $revision = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->findOne();
        $lines = [];

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $isA = $line->getProductRef() === $a->getRef();
            $isB = $line->getProductRef() === $b->getRef();
            $lines[] = new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), $isA ? 12.0 : (float) $line->getQuantity(), $isA ? '90' : ($isB ? '45' : (string) $line->getPrice()));
        }

        $lines[] = new QuoteDraftLine(null, $this->saleElementId($c), 5.0, '20');
        $this->quotes->saveDraft($quote, null, new QuoteDraftInput($lines, '30', '15', $this->carrierId()));
        $this->quotes->send($quote, null);

        return ['buyer' => $buyer, 'quote' => $quote, 'invoice' => $invoice, 'delivery' => $delivery];
    }

    private function country(string $isoAlpha2): Country
    {
        return CountryQuery::create()->findOneByIsoalpha2($isoAlpha2) ?? throw new \LogicException('The test database has no country '.$isoAlpha2);
    }

    private function carrierId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId();
    }

    private function otherCarrierId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId();
    }
}
