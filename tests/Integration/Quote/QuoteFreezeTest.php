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
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteRevisionAddressQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * What a revision keeps once it is sent: the amounts, the validity and the carrier it proposes, the
 * copy of the two addresses and whether the buyer owes the VAT himself, all written on it so that
 * it reads back as it was proposed; and what a draft does not touch while it waits to be sent.
 */
final class QuoteFreezeTest extends QuoteDomainTestCase
{
    use RecordsSqlQueries;

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testADraftKeepsItsValidityAndItsCarrierAndTheQuoteIsNotWritten(): void
    {
        $scenario = $this->quotedScenarioWithCarrier();
        $quote = $scenario['quote'];
        $this->clock->modify('2027-01-02 09:00:00');
        $before = $this->reread($quote);

        $draft = $this->quotes->saveDraft($quote, null, $this->draftFrom(
            $this->revisionOf($quote, 2),
            deliveryModuleId: $this->otherCarrierId(),
            validUntil: new \DateTimeImmutable('2027-01-31'),
        ));

        self::assertSame('2027-01-31', $draft->getValidUntil('Y-m-d'));
        self::assertSame($this->otherCarrierId(), $draft->getDeliveryModuleId());

        $after = $this->reread($quote);
        self::assertSame('2026-12-31', $after->getValidUntil('Y-m-d'), 'the quote keeps the validity of what the buyer was sent');
        self::assertSame($this->carrierId(), $after->getDeliveryModuleId(), 'and its carrier');
        self::assertSame($before->getState(), $after->getState());
    }

    public function testADraftSavedWithNoDateDoesNotBringAnExpiredQuoteBack(): void
    {
        $scenario = $this->quotedScenarioWithCarrier();
        $quote = $scenario['quote'];
        $this->clock->modify('2027-01-02 09:00:00');
        self::assertSame(QuoteState::Expired, $this->quotes->effectiveState($this->reread($quote), $this->clock->now()));

        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2)));

        $reread = $this->reread($quote);
        self::assertSame(QuoteState::Quoted->value, $reread->getState(), 'the console has not written the expiry');
        self::assertSame('2026-12-31', $reread->getValidUntil('Y-m-d'), 'the day was not emptied');
        self::assertSame(QuoteState::Expired, $this->quotes->effectiveState($reread, $this->clock->now()), 'the buyer still cannot answer');
    }

    public function testADraftWithALaterDayDoesNotProlongAnExpiredQuoteUntilItIsSent(): void
    {
        $scenario = $this->quotedScenarioWithCarrier();
        $quote = $scenario['quote'];
        $this->clock->modify('2027-01-02 09:00:00');

        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2), validUntil: new \DateTimeImmutable('2027-01-31')));

        self::assertSame(QuoteState::Expired, $this->quotes->effectiveState($this->reread($quote), $this->clock->now()));

        $this->quotes->send($quote, null);

        $sent = $this->reread($quote);
        self::assertSame(3, $sent->getCurrentRevision());
        self::assertSame('2027-01-31', $sent->getValidUntil('Y-m-d'), 'the validity of the draft passes to the quote when it is sent');
        self::assertSame(QuoteState::Quoted, $this->quotes->effectiveState($sent, $this->clock->now()));
    }

    public function testSendingCopiesTheCarrierOfTheDraftOntoTheQuoteAndKeepsBothOnTheRevision(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1), deliveryModuleId: $this->carrierId()));
        self::assertNull($this->reread($quote)->getDeliveryModuleId());

        $this->quotes->send($quote, null);

        self::assertSame($this->carrierId(), $this->reread($quote)->getDeliveryModuleId());
        self::assertSame($this->carrierId(), $this->revisionOf($quote, 2)->getDeliveryModuleId());
        self::assertSame('2026-12-31', $this->revisionOf($quote, 2)->getValidUntil('Y-m-d'), 'thirty days from the sending, by default');
        self::assertSame('2026-12-31', $this->reread($quote)->getValidUntil('Y-m-d'));
    }

    public function testExtendingMovesTheValidityOfTheRevisionTheBuyerAnswers(): void
    {
        $scenario = $this->quotedScenarioWithCarrier();
        $quote = $scenario['quote'];

        $this->quotes->extend($quote, new \DateTimeImmutable('2027-02-01'));

        self::assertSame('2027-02-01', $this->reread($quote)->getValidUntil('Y-m-d'));
        self::assertSame('2027-02-01', $this->revisionOf($quote, 2)->getValidUntil('Y-m-d'));
        self::assertNull($this->revisionOf($quote, 1)->getValidUntil(), 'the request of the buyer has no validity');
    }

    public function testASentRevisionHoldsEveryAmountItWasSentWith(): void
    {
        $scenario = $this->quotedScenarioWithCarrier();
        $revision = $this->revisionOf($scenario['quote'], 2);

        self::assertSame('1360.000000', $revision->getItemsUntaxed());
        self::assertSame('272.000000', $revision->getItemsTax());
        self::assertSame('15.000000', $revision->getPostage());
        self::assertSame('3.000000', $revision->getPostageTax());
        self::assertSame('30.000000', $revision->getDiscount());
        self::assertSame('1620.000000', $revision->getTotalTaxed());
        self::assertSame('1350.000000', $revision->getTotalUntaxed());

        $totals = $this->quotes->totals($revision);
        self::assertSame(
            ['1360.000000', '272.000000', '15.000000', '3.000000', '30.000000', '1620.000000', '1350.000000'],
            [$totals->itemsUntaxed, $totals->itemsTax, $totals->postageUntaxed, $totals->postageTax, $totals->discount, $totals->totalTaxed, $totals->totalUntaxed],
        );
    }

    public function testThePostageAndTheDiscountAreFrozenToTheCentTheBuyerPays(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1), discount: '30.004', postage: '15.006'));

        $this->quotes->send($quote, null);

        self::assertSame('30.000000', $this->revisionOf($quote, 2)->getDiscount());
        self::assertSame('15.010000', $this->revisionOf($quote, 2)->getPostage());
    }

    public function testASentRevisionIsNotWorkedOutAgainWhenTheTaxRuleOfItsProductsChanges(): void
    {
        $scenario = $this->quotedScenarioWithCarrier();
        $quote = $scenario['quote'];
        $sent = $this->revisionOf($quote, 2);

        $tax = $this->vatRule()->getTaxRuleCountries()->getFirst()->getTax();
        $tax->setRequirements(['percent' => '5.5'])->save($this->getPropelConnection());
        TaxRuleQuery::resetCache();
        $this->getService('services_resetter')->reset();

        self::assertSame('1620.000000', $this->quotes->totals($sent)->totalTaxed, 'frozen');
        self::assertSame('272.000000', $this->quotes->totals($sent)->itemsTax);

        $this->clock->modify('2027-01-02 09:00:00');
        $draft = $this->quotes->saveDraft($this->reread($quote), null, $this->draftFrom($sent, discount: '30', postage: '15'));
        self::assertNotSame('272.000000', $this->quotes->totals($draft)->itemsTax, 'a draft is worked out with the rule of the day: the rule did change');
    }

    public function testADraftIsWorkedOutAndNeverFrozen(): void
    {
        $scenario = $this->requestedScenario();
        $draft = $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));

        self::assertNull($draft->getItemsUntaxed());
        self::assertNull($draft->getItemsTax());
        self::assertNull($draft->getPostageTax());
        self::assertNull($draft->getTotalTaxed());
        self::assertNull($draft->getReverseCharge());
        self::assertSame('1620.000000', $this->quotes->totals($draft)->totalTaxed);
        self::assertSame(0, QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($draft->getId())->count(), 'no address is copied before the sending');
    }

    public function testTheSumsOfARevisionAreWorkedOutOnceWhenItIsSent(): void
    {
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));

        $statements = $this->recordSqlQueries(fn () => $this->quotes->send($scenario['quote'], null));

        $readsOfTheGoods = array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'FROM `product` LEFT JOIN `product_i18n`') && str_contains($statement, '`product`.`id` IN'));
        self::assertCount(1, $readsOfTheGoods, 'the products and the tax rules of the lines are read for one calculation: '.implode("\n", $statements));
    }

    public function testSendingNamesTheCountryInTheLanguageOfTheQuoteWithoutChangingTheLanguageOfTheCountryOthersShare(): void
    {
        $french = LangQuery::create()->findOneByLocale('fr_FR') ?? $this->fixtures->lang(['locale' => 'fr_FR', 'code' => 'fr', 'title' => 'Francais']);
        $buyer = $this->customer();
        $buyer->setLangId($french->getId())->save($this->getPropelConnection());
        $atlantis = (new Country())->setIsocode('997')->setIsoalpha2('ZZ')->setIsoalpha3('ZZZ')->setVisible(1)->setShopCountry(false);
        $atlantis->setLocale('en_US')->setTitle('Atlantis')->setLocale('fr_FR')->setTitle('Atlantide')->save($this->getPropelConnection());

        // The country of the pool is the one every later reader of the process gets: it is in the pool before anything reads it.
        $this->readThroughTheInstancePool();
        $shared = CountryQuery::create()->findPk($atlantis->getId());
        self::assertSame('en_US', $shared?->getLocale());

        $address = $this->fixtures->address($buyer, $atlantis);
        $quote = $this->quotes->requestFromCart(
            $this->cartOf($buyer, [[$this->product('100', 'Product A'), 2.0, '100']]),
            $buyer,
            new QuoteRequestInput(deliveryAddressId: (int) $address->getId()),
        );
        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1)));

        $this->quotes->send($quote, null);

        self::assertSame('Atlantide', $this->copiesOf($quote, 2)['delivery']->getCountryTitle(), 'the language of the buyer');
        self::assertSame('en_US', $shared->getLocale(), 'the country of the pool still speaks the language it was read in');
    }

    public function testTheTwoAddressesOfTheQuoteAreCopiedOntoEveryRevisionSent(): void
    {
        $scenario = $this->quotedScenarioWithAddresses();
        $quote = $scenario['quote'];

        foreach ([1, 2] as $number) {
            $copies = $this->copiesOf($quote, $number);
            self::assertSame(['delivery', 'invoice'], array_keys($copies), 'revision '.$number);
        }

        $invoice = $this->copiesOf($quote, 2)['invoice'];
        self::assertSame('Ada', $invoice->getFirstname());
        self::assertSame('Lovelace', $invoice->getLastname());
        self::assertSame('Analytical Engines', $invoice->getCompany());
        self::assertSame('1 Invoice Street', $invoice->getAddress1());
        self::assertSame('Floor 2', $invoice->getAddress2());
        self::assertSame('Lyon', $invoice->getCity());
        self::assertSame('69001', $invoice->getZipcode());
        self::assertSame(Country::getDefaultCountry()->getId(), $invoice->getCountryId());
        self::assertSame('FR12345678901', $invoice->getVatNumber());
        self::assertNotSame('', (string) $invoice->getCountryTitle());

        $delivery = $this->copiesOf($quote, 2)['delivery'];
        self::assertSame('2 Delivery Road', $delivery->getAddress1());
        self::assertSame('Nantes', $delivery->getCity());
    }

    public function testTheCopyOfTheAddressesSurvivesTheAddressBookAndTheDocumentReadsIt(): void
    {
        $scenario = $this->quotedScenarioWithAddresses();
        $quote = $scenario['quote'];
        $revision = $this->revisionOf($quote, 2);
        $before = $this->getService(QuoteDocumentContext::class)->build($quote, $revision, 'en_US');
        self::assertSame('2 Delivery Road', $before['delivery_address'][1]);
        self::assertSame('1 Invoice Street', $before['invoice_address'][2]);

        $address = AddressQuery::create()->findPk($scenario['delivery']->getId());
        $address->setAddress1('Somewhere else')->setCity('Lille')->save($this->getPropelConnection());
        AddressQuery::create()->filterById($scenario['invoice']->getId())->delete($this->getPropelConnection());
        \Thelia\Model\Map\AddressTableMap::clearInstancePool();
        \Thelia\Model\Map\QuoteTableMap::clearInstancePool();

        $after = $this->getService(QuoteDocumentContext::class)->build($this->reread($quote), $revision, 'en_US');

        self::assertSame($before['delivery_address'], $after['delivery_address']);
        self::assertSame($before['invoice_address'], $after['invoice_address']);
        self::assertNull($this->reread($quote)->getInvoiceAddressId(), 'the address is gone from the quote: the copy is not');
    }

    public function testTheDocumentSaysTheValidityOfTheRevisionNotTheOneOfTheQuote(): void
    {
        $scenario = $this->quotedScenarioWithCarrier();
        $quote = $scenario['quote'];
        $context = $this->getService(QuoteDocumentContext::class);
        $second = $this->revisionOf($quote, 2);
        $format = $this->getService(\Thelia\Core\Template\Helper\FormatService::class);

        self::assertSame($format->date(new \DateTimeImmutable('2026-12-31'), null, 'date', 'en_US'), $context->build($quote, $second, 'en_US')['valid_until']);

        $this->clock->modify('2027-01-02 09:00:00');
        $this->quotes->saveDraft($quote, null, $this->draftFrom($second));
        $this->quotes->send($this->reread($quote), null);
        $quote = $this->reread($quote);

        self::assertSame($format->date(new \DateTimeImmutable('2026-12-31'), null, 'date', 'en_US'), $context->build($quote, $second, 'en_US')['valid_until'], 'the first answer says what it said');
        self::assertSame($format->date(new \DateTimeImmutable('2027-02-01'), null, 'date', 'en_US'), $context->build($quote, $this->revisionOf($quote, 3), 'en_US')['valid_until']);
        self::assertNull($context->build($quote, $this->revisionOf($quote, 1), 'en_US')['valid_until'], 'a request has no validity');
    }

    public function testTheBuyerOwesTheVatHimselfWhenTheRuleOfTheOrderSaysSo(): void
    {
        $this->configureReverseCharge();

        foreach ([
            'a number checked in another member state' => ['BE', '-10 days', true],
            'a verification that is too old' => ['BE', '-91 days', false],
            'a number nobody checked' => ['BE', null, false],
            'a buyer in the country of the shop' => ['FR', '-10 days', false],
            'a buyer outside the Union' => ['CH', '-10 days', false],
        ] as $label => [$iso, $verifiedAgo, $expected]) {
            $address = $this->addressBilledTo($iso, null === $verifiedAgo ? null : new \DateTime($verifiedAgo));
            $quote = $this->quoteBilledTo($address);

            self::assertSame($expected ? 1 : 0, $this->revisionOf($quote, 1)->getReverseCharge(), $label.' (request)');

            $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1)));
            $this->quotes->send($quote, null);

            self::assertSame($expected ? 1 : 0, $this->revisionOf($quote, 2)->getReverseCharge(), $label.' (sent)');
            self::assertSame($expected, $this->cartRuleSays($address), $label.' (the rule of the order)');
        }
    }

    public function testTheBuyerOwesNothingHimselfWhenTheShopDoesNotAskForTheCheck(): void
    {
        $this->configureReverseCharge();
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::DISABLED->value);
        ConfigQuery::resetCache();
        $address = $this->addressBilledTo('BE', new \DateTime('-10 days'));
        $quote = $this->quoteBilledTo($address);

        self::assertSame(0, $this->revisionOf($quote, 1)->getReverseCharge());
        self::assertFalse($this->cartRuleSays($address));
    }

    public function testTheVerdictIsFrozenWhenTheRevisionIsSent(): void
    {
        $this->configureReverseCharge();
        $address = $this->addressBilledTo('BE', new \DateTime('-10 days'));
        $quote = $this->quoteBilledTo($address);
        self::assertSame(1, $this->revisionOf($quote, 1)->getReverseCharge());

        $address->setVatNumber(null)->save($this->getPropelConnection());

        self::assertSame(1, $this->revisionOf($quote, 1)->getReverseCharge(), 'what the buyer was told is not rewritten');
    }

    public function testTheDocumentSaysWhetherTheBuyerOwesTheVatHimself(): void
    {
        $this->configureReverseCharge();
        $address = $this->addressBilledTo('BE', new \DateTime('-10 days'));
        $quote = $this->quoteBilledTo($address);
        $context = $this->getService(QuoteDocumentContext::class)->build($quote, $this->revisionOf($quote, 1), 'en_US');

        self::assertTrue($context['reverse_charge']);
        self::assertSame('BE0123456789', $context['customer_vat_number']);

        $home = $this->quoteBilledTo($this->addressBilledTo('FR', new \DateTime('-10 days')));
        self::assertFalse($this->getService(QuoteDocumentContext::class)->build($home, $this->revisionOf($home, 1), 'en_US')['reverse_charge']);
    }

    /**
     * Sent at its second revision on the 1st of December, valid until the 31st, carried by a carrier
     * of the shop: 1 620.00 with taxes.
     *
     * @return array{buyer: Customer, quote: Quote, a: \Thelia\Model\Product, b: \Thelia\Model\Product, c: \Thelia\Model\Product}
     */
    private function quotedScenarioWithCarrier(): array
    {
        $scenario = $this->requestedScenario();
        $draft = $this->draftOfTheShop($scenario);
        $this->quotes->saveDraft($scenario['quote'], null, new \Thelia\Domain\Quote\DTO\QuoteDraftInput($draft->lines, $draft->discount, $draft->postage, $this->carrierId(), null, null));
        $this->quotes->send($scenario['quote'], null);

        return $scenario;
    }

    /**
     * @return array{buyer: Customer, quote: Quote, invoice: Address, delivery: Address}
     */
    private function quotedScenarioWithAddresses(): array
    {
        $buyer = $this->customer();
        $country = Country::getDefaultCountry();
        $invoice = $this->fixtures->address($buyer, $country, null, ['firstname' => 'Ada', 'lastname' => 'Lovelace', 'address1' => '1 Invoice Street', 'address2' => 'Floor 2', 'city' => 'Lyon', 'zipcode' => '69001']);
        $invoice->setCompany('Analytical Engines')->setVatNumber('FR12345678901')->save($this->getPropelConnection());
        $delivery = $this->fixtures->address($buyer, $country, null, ['address1' => '2 Delivery Road', 'city' => 'Nantes', 'zipcode' => '44000']);

        $quote = $this->quotes->requestFromCart(
            $this->cartOf($buyer, [[$this->product('100', 'Product A'), 2.0, '100']]),
            $buyer,
            new QuoteRequestInput(invoiceAddressId: (int) $invoice->getId(), deliveryAddressId: (int) $delivery->getId()),
        );
        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1), postage: '10'));
        $this->quotes->send($quote, null);

        return ['buyer' => $buyer, 'quote' => $quote, 'invoice' => $invoice, 'delivery' => $delivery];
    }

    /**
     * @return array<string, \Thelia\Model\QuoteRevisionAddress> by kind
     */
    private function copiesOf(Quote $quote, int $number): array
    {
        $copies = [];

        foreach (QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($this->revisionOf($quote, $number)->getId())->orderByKind()->find() as $copy) {
            $copies[(string) $copy->getKind()] = $copy;
        }

        return $copies;
    }

    private function carrierId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId();
    }

    private function otherCarrierId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId();
    }

    private function configureReverseCharge(): void
    {
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $this->countryOf('FR')->getId());
        ConfigQuery::resetCache();
    }

    private function countryOf(string $isoAlpha2): Country
    {
        return CountryQuery::create()->findOneByIsoalpha2($isoAlpha2) ?? throw new \LogicException('The test database has no country '.$isoAlpha2);
    }

    private function addressBilledTo(string $isoAlpha2, ?\DateTime $verifiedAt): Address
    {
        $buyer = $this->customer();
        $address = $this->fixtures->address($buyer, $this->countryOf($isoAlpha2));
        $address->setCompany('Acme')->setVatNumber($isoAlpha2.'0123456789')->setVatVerifiedAt($verifiedAt)->save($this->getPropelConnection());

        return $address;
    }

    private function quoteBilledTo(Address $address): Quote
    {
        $buyer = $address->getCustomer();

        return $this->quotes->requestFromCart(
            $this->cartOf($buyer, [[$this->product('100', 'Product A'), 2.0, '100']]),
            $buyer,
            new QuoteRequestInput(invoiceAddressId: (int) $address->getId()),
        );
    }

    /**
     * What the order says of the same buyer: the rule is asked of a cart billed to the same address.
     */
    private function cartRuleSays(Address $address): bool
    {
        $copy = (new CartAddress())->copyFrom($address);
        $copy->save($this->getPropelConnection());
        $cart = $this->fixtures->cart($address->getCustomer());
        $cart->setAddressInvoiceId($copy->getId())->save($this->getPropelConnection());

        return $this->getService(VatExemptionResolver::class)->isExemptedForCart($cart);
    }
}
