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

namespace Thelia\Tests\Integration\Quote\Pdf;

use Smalot\PdfParser\Parser;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Quote\Exception\QuoteDocumentUnavailableException;
use Thelia\Domain\Quote\Pdf\QuotePdfService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Quote;
use Thelia\Tests\Integration\Quote\QuoteDomainTestCase;

/**
 * The printed quote, read back from the PDF itself: the shop identity and legal mentions, both
 * prices of each line, the totals, the validity, and what a draft or a hostile text cannot do.
 *
 * The document belongs to the PDF template package, released on its own cycle: a package that has
 * no quote document yet is reported as skipped rather than failed.
 */
final class QuotePdfTest extends QuoteDomainTestCase
{
    private string $capturedHtml = '';

    protected function setUp(): void
    {
        parent::setUp();

        $directory = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate()->getAbsolutePath();

        if (!is_file($directory.\DIRECTORY_SEPARATOR.'quote.html.twig') || !is_file($directory.\DIRECTORY_SEPARATOR.'legal_identifiers.html.twig')) {
            self::markTestSkipped('The installed PDF template has no quote document yet.');
        }

        ConfigQuery::write('store_name', 'Acme Shop');
        ConfigQuery::write('store_siret', '123 456 789 00012');
        ConfigQuery::write('store_vat_intracom', 'FR12345678901');
        ConfigQuery::write('store_legal_mentions', 'SAS au capital de 1000 EUR');
        ConfigQuery::resetCache();

        $this->capturedHtml = '';
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::GENERATE_PDF, function (PdfEvent $event): void {
            $this->capturedHtml = (string) $event->getContent();
        }, 1000);
    }

    public function testTheDocumentCarriesTheLegalMentionsOfTheShop(): void
    {
        $text = $this->textOf($this->sent());

        self::assertStringContainsString('Acme Shop', $text);
        self::assertStringContainsString('123 456 789 00012', $text);
        self::assertStringContainsString('FR12345678901', $text);
        self::assertStringContainsString('SAS au capital de 1000 EUR', $text);
    }

    public function testEachLineShowsTheCatalogPriceNextToTheNegotiatedOne(): void
    {
        $quote = $this->sent();
        $text = $this->textOf($quote);

        self::assertStringContainsString((string) $quote->getRef(), $text);
        self::assertMatchesRegularExpression('/Product A.*?12.*?100[.,]00.*?90[.,]00.*?1[\s.,]?080[.,]00/s', $text);
        self::assertMatchesRegularExpression('/Product B.*?4.*?50[.,]00.*?45[.,]00.*?180[.,]00/s', $text);
        self::assertMatchesRegularExpression('/Product C.*?5.*?20[.,]00.*?20[.,]00.*?100[.,]00/s', $text);
    }

    public function testTheTotalsAreTheOnesOfTheExample(): void
    {
        $text = $this->textOf($this->sent());

        self::assertMatchesRegularExpression('/1 ?620[.,]00/', $text, 'the total with taxes');
        self::assertMatchesRegularExpression('/1 ?360[.,]00/', $text, 'the lines without taxes');
        self::assertMatchesRegularExpression('/\b15[.,]00/', $text, 'the postage');
        self::assertMatchesRegularExpression('/\b30[.,]00/', $text, 'the discount');
    }

    public function testTheValidityIsPrinted(): void
    {
        $quote = $this->sent();
        $locale = (string) $this->reread($quote)->getLocale();
        $expected = $this->getService(FormatService::class)->date(new \DateTimeImmutable('2026-12-16'), null, 'date', $locale);

        self::assertStringContainsString($expected, $this->textOf($quote));
    }

    public function testTheNoteOfTheShopIsPrintedAsTextAndNeverAsMarkup(): void
    {
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom(
            $this->revisionOf($scenario['quote'], 1),
            merchantNote: '<p>Delivery in <b>two</b> weeks</p><script>alert(1)</script>',
            validUntil: new \DateTimeImmutable('2026-12-16'),
        ));
        $this->quotes->send($this->reread($scenario['quote']), null);

        $text = $this->textOf($scenario['quote']);

        self::assertStringContainsString('Delivery in two weeks', $text);
        self::assertStringNotContainsString('<b>', $this->capturedHtml);
        self::assertStringNotContainsString('<script', $this->capturedHtml);
    }

    public function testATitleThatLooksLikeMarkupIsEscaped(): void
    {
        $quote = $this->sent();
        $this->linesOf($this->revisionOf($quote, 2))[0]->setTitle('<img src=x onerror=alert(2)>Product A')->save();

        $this->service()->render($this->reread($quote), 2);

        self::assertStringNotContainsString('<img src=x', $this->capturedHtml);
        self::assertStringContainsString('&lt;img src=x', $this->capturedHtml);
    }

    public function testADraftIsNeverPrinted(): void
    {
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));

        $this->expectException(QuoteDocumentUnavailableException::class);
        $this->expectExceptionMessage('Only a revision that was sent can be printed.');

        $this->service()->render($this->reread($scenario['quote']), 2);
    }

    public function testARevisionThatDoesNotExistIsNotPrinted(): void
    {
        $quote = $this->sent();

        try {
            $this->service()->render($quote, 9);
            self::fail('There is no revision 9.');
        } catch (QuoteDocumentUnavailableException $exception) {
            self::assertSame(QuoteDocumentUnavailableException::REVISION_NOT_FOUND, $exception->reason());
        }
    }

    public function testNothingIsKeptBetweenTwoPrints(): void
    {
        $quote = $this->sent();
        $first = $this->service()->render($quote, 2);
        $this->linesOf($this->revisionOf($quote, 2))[0]->setTitle('Renamed line')->save();
        $second = $this->service()->render($this->reread($quote), 2);

        self::assertNotSame($first, $second);
        self::assertStringContainsString('Renamed line', $this->capturedHtml);
    }

    public function testARevisionSentIsPrintedAsItWasSentWhateverTheTaxRulesAndTheAddressBookSayAfterwards(): void
    {
        $scenario = $this->requestedScenario();
        $buyer = $scenario['buyer'];
        $country = \Thelia\Model\Country::getDefaultCountry();
        $invoice = $this->fixtures->address($buyer, $country, null, ['address1' => '1 Invoice Street', 'city' => 'Lyon']);
        $delivery = $this->fixtures->address($buyer, $country, null, ['address1' => '2 Delivery Road', 'city' => 'Nantes']);
        $quote = $scenario['quote']->setInvoiceAddressId($invoice->getId())->setDeliveryAddressId($delivery->getId());
        $quote->save($this->getPropelConnection());
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($this->reread($quote), null);

        $before = $this->textOf($quote);
        self::assertMatchesRegularExpression('/1 ?620[.,]00/', $before);
        self::assertStringContainsString('1 Invoice Street', $before);
        self::assertStringContainsString('Nantes', $before);

        $tax = $this->vatRule()->getTaxRuleCountries()->getFirst()->getTax();
        $tax->setRequirements(['percent' => '5.5'])->save($this->getPropelConnection());
        \Thelia\Model\TaxRuleQuery::resetCache();
        $this->getService('services_resetter')->reset();
        \Thelia\Model\AddressQuery::create()->filterById([$invoice->getId(), $delivery->getId()])->delete($this->getPropelConnection());
        \Thelia\Model\Map\AddressTableMap::clearInstancePool();
        \Thelia\Model\Map\QuoteTableMap::clearInstancePool();

        self::assertSame($before, $this->textOf($quote), 'the document says what it said');
    }

    public function testTheDocumentSaysWhenTheBuyerOwesTheVatHimselfAndOnlyThen(): void
    {
        ConfigQuery::write(\Thelia\Domain\Taxation\Enum\VatExemptionMode::CONFIG_KEY, \Thelia\Domain\Taxation\Enum\VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) \Thelia\Model\CountryQuery::create()->findOneByIsoalpha2('FR')->getId());
        ConfigQuery::resetCache();

        $exempted = $this->sentTo('BE');
        $taxed = $this->sentTo('FR');
        $mention = ['en_US' => 'Reverse charge: VAT due by the customer', 'fr_FR' => 'Autoliquidation : TVA due par le preneur'];
        $locale = (string) $this->reread($exempted)->getLocale();
        self::assertArrayHasKey($locale, $mention, 'the test knows the mention of the language of the buyer');

        $text = $this->textOf($exempted);
        self::assertStringContainsString($mention[$locale], $text);
        self::assertStringContainsString('BE0123456789', $text);
        self::assertStringNotContainsString($mention[$locale], $this->textOf($taxed));
    }

    /**
     * The first answer of the shop to a buyer billed in a country.
     */
    private function sentTo(string $isoAlpha2): Quote
    {
        $buyer = $this->customer();
        $invoice = $this->fixtures->address($buyer, \Thelia\Model\CountryQuery::create()->findOneByIsoalpha2($isoAlpha2));
        $invoice->setCompany('Acme')->setVatNumber($isoAlpha2.'0123456789')->setVatVerifiedAt(new \DateTime('-10 days'))->save($this->getPropelConnection());

        $quote = $this->quotes->requestFromCart(
            $this->cartOf($buyer, [[$this->product('100', 'Product A'), 2.0, '100']]),
            $buyer,
            new \Thelia\Domain\Quote\DTO\QuoteRequestInput(invoiceAddressId: (int) $invoice->getId()),
        );
        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1)));
        $this->quotes->send($quote, null);

        return $quote;
    }

    /**
     * The second revision of the example, valid until the 16th.
     */
    private function sent(): Quote
    {
        $scenario = $this->requestedScenario();
        $draft = $this->draftOfTheShop($scenario);
        $this->quotes->saveDraft($scenario['quote'], null, new \Thelia\Domain\Quote\DTO\QuoteDraftInput($draft->lines, $draft->discount, $draft->postage, $draft->deliveryModuleId, null, new \DateTimeImmutable('2026-12-16')));
        $this->quotes->send($this->reread($scenario['quote']), null);

        return $this->reread($scenario['quote']);
    }

    private function service(): QuotePdfService
    {
        return $this->getService(QuotePdfService::class);
    }

    private function textOf(Quote $quote, int $revision = 2): string
    {
        $pdf = $this->service()->render($this->reread($quote), $revision);

        self::assertStringStartsWith('%PDF', $pdf);

        $text = (new Parser())->parseContent($pdf)->getText();

        return (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text);
    }
}
