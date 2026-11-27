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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Model\Address;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\Product;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevisionQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Tests\Http\Flexy\CompanyAccountTestCase;

/**
 * What the pages of the quotes of the storefront share: a shop that takes quotes, products at known
 * prices under a tax of 20 %, and the quotes the facade writes, which the tests then open as a browser does.
 *
 * The pages belong to the theme, which ships as its own package on its own release cycle: a theme
 * older than the quote pages is reported as skipped rather than failed.
 */
abstract class QuoteFlexyTestCase extends CompanyAccountTestCase
{
    /** Written as a string on purpose: `::class` on a missing import resolves silently. */
    private const QUOTE_WITNESS = 'FlexyBundle\\Controller\\AccountQuoteController';

    protected QuoteFacade $quotes;

    private ?TaxRule $vatRule = null;

    /** @var list<array{0: string, 1: callable}> */
    private array $listeners = [];

    protected function setUp(): void
    {
        if (!class_exists(self::QUOTE_WITNESS)) {
            self::markTestSkipped('The installed front-office theme has no quote pages.');
        }

        parent::setUp();

        $this->setQuoteMode('alongside');
        ConfigQuery::write('quote_validity_days', '30');
        ConfigQuery::write('taxrule_id_delivery_module', (string) $this->vatRule()->getId());
        ConfigQuery::resetCache();

        $this->quotes = $this->getService(QuoteFacade::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->quotes)) {
            foreach ($this->listeners as [$eventName, $listener]) {
                $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
            }
        }

        parent::tearDown();
    }

    protected function setQuoteMode(string $mode): void
    {
        ConfigQuery::write('quote_mode', $mode);
        ConfigQuery::resetCache();
    }

    protected function vatRule(): TaxRule
    {
        if (null === $this->vatRule) {
            $this->vatRule = $this->factory->taxRule(['isDefault' => false]);
            $tax = $this->factory->tax(['requirements' => ['percent' => '20.000000'], 'title' => 'VAT 20']);
            (new TaxRuleCountry())
                ->setTaxRuleId($this->vatRule->getId())
                ->setCountryId(Country::getDefaultCountry()->getId())
                ->setTaxId($tax->getId())
                ->setPosition(1)
                ->save($this->getPropelConnection());
        }

        return $this->vatRule;
    }

    protected function product(string $price, string $title): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->vatRule(),
            $this->factory->currency(),
            ['basePrice' => (float) $price, 'baseQuantity' => 100, 'title' => $title],
        );
    }

    protected function saleElementId(Product $product): int
    {
        return (int) $product->getProductSaleElementss()->getFirst()->getId();
    }

    /**
     * @param list<array{0: Product, 1: float, 2: string}> $lines product, quantity, unit price without taxes
     */
    protected function cartOf(Customer $customer, array $lines): Cart
    {
        $cart = $this->factory->cart($customer);

        foreach ($lines as [$product, $quantity, $price]) {
            $this->factory->cartItem($cart, $product, null, ['quantity' => $quantity, 'price' => $price, 'promoPrice' => $price]);
        }

        return $cart;
    }

    /**
     * A quote the buyer asked for: 10 of A at 100 and 4 of B at 50, written by the facade.
     *
     * @return array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product}
     */
    protected function requestedQuote(?Customer $buyer = null, string $comment = '', ?Address $address = null): array
    {
        $buyer ??= $this->customer();
        $a = $this->product('100', 'Product A');
        $b = $this->product('50', 'Product B');
        $c = $this->product('20', 'Product C');
        $quote = $this->quotes->requestFromCart(
            $this->cartOf($buyer, [[$a, 10.0, '100'], [$b, 4.0, '50']]),
            $buyer,
            new QuoteRequestInput('' === $comment ? null : $comment, null, $address?->getId(), $address?->getId()),
        );

        return ['buyer' => $buyer, 'quote' => $quote, 'a' => $a, 'b' => $b, 'c' => $c];
    }

    /**
     * The same quote after the shop answered: 12 of A at 90, 4 of B at 45 and 5 of C at 20, a discount of
     * 30 and a postage of 15 (1 620.00 with taxes).
     *
     * @return array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product}
     */
    protected function quotedQuote(?Customer $buyer = null, ?string $merchantNote = null, ?\DateTimeImmutable $validUntil = null): array
    {
        $scenario = $this->requestedQuote($buyer);
        $this->shopAnswers($scenario['quote'], $scenario, merchantNote: $merchantNote, validUntil: $validUntil);

        return $scenario;
    }

    /**
     * The quote of {@see quotedQuote()} written for an address the carrier of the shop serves and with that carrier
     * on it: the checkout asks nothing more of the buyer before the payment.
     *
     * @return array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product}
     */
    protected function quotedQuoteReadyToPay(?Customer $buyer = null): array
    {
        $buyer ??= $this->customer();
        $address = $this->factory->address($buyer, CountryQuery::create()->findOneByIsoalpha2('FR'), $buyer->getCustomerTitle(), ['zipcode' => '69003', 'city' => 'Lyon']);
        $this->servedByTheCarrier($address);
        $scenario = $this->requestedQuote($buyer, address: $address);
        $this->shopAnswers($scenario['quote'], $scenario, deliveryModuleId: $this->moduleId('CustomDelivery'));

        return $scenario;
    }

    /**
     * The carrier serves the country of the address and accepts the payment by cheque, so that the steps of the
     * checkout that ask them settle.
     */
    protected function servedByTheCarrier(Address $address): void
    {
        $connection = $this->getPropelConnection();
        $area = (new Area())->setName('Quote checkout test area');
        $area->save($connection);
        (new CountryArea())->setAreaId($area->getId())->setCountryId($address->getCountryId())->save($connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->moduleId('CustomDelivery'))->save($connection);

        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(12.0, 2.0, 'VAT 20'));
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });
    }

    protected function moduleId(string $code): int
    {
        return (int) (ModuleQuery::create()->findOneByCode($code)?->getId() ?? self::fail(\sprintf('No "%s" module installed.', $code)));
    }

    protected function listen(string $eventName, callable $listener): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, 512);
        $this->listeners[] = [$eventName, $listener];
    }

    /**
     * The token of the session the client holds, which a page of a quote the account may answer carries. The session is the
     * client's and not the account's: the token is read on the page of a quote of a buyer of its own, and then whoever signs in
     * next sends it.
     */
    protected function tokenForTheSession(): string
    {
        $spare = $this->quotedQuote();
        $this->signInAs($spare['buyer']);

        return (string) $this->get($this->url('account_quote', ['quoteId' => $spare['quote']->getId()]))->filter('form[data-testid="quote-counter-offer"]')->attr('data-live-token-param');
    }

    /**
     * The quote as it is stored now, read again.
     */
    protected function reread(Quote $quote): Quote
    {
        \Thelia\Model\Map\QuoteTableMap::clearInstancePool();

        return QuoteQuery::create()->findPk($quote->getId()) ?? self::fail('The quote is gone.');
    }

    /**
     * The id of each line of a revision of the quote, by the reference of its product.
     *
     * @return array<string, int>
     */
    protected function lineIdsOf(Quote $quote, int $revisionNumber): array
    {
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->filterByNumber($revisionNumber)->findOne() ?? self::fail('No such revision.');
        $ids = [];

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $ids[(string) $line->getProductRef()] = (int) $line->getId();
        }

        return $ids;
    }

    /**
     * @param array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product} $scenario
     */
    protected function shopAnswers(Quote $quote, array $scenario, string $aPrice = '90', ?string $merchantNote = null, ?\DateTimeImmutable $validUntil = null, ?int $deliveryModuleId = null): void
    {
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->orderByNumber('desc')->findOne();
        $lines = [];

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $isA = $line->getProductRef() === $scenario['a']->getRef();
            $isB = $line->getProductRef() === $scenario['b']->getRef();
            $lines[] = new QuoteDraftLine(
                (int) $line->getId(),
                (int) $line->getProductSaleElementsId(),
                $isA ? 12.0 : (float) $line->getQuantity(),
                $isA ? $aPrice : ($isB ? '45' : (string) $line->getPrice()),
            );
        }

        $lines[] = new QuoteDraftLine(null, $this->saleElementId($scenario['c']), 5.0, '20');

        $this->quotes->saveDraft($quote, null, new QuoteDraftInput($lines, '30', '15', $deliveryModuleId, $merchantNote, $validUntil));
        $this->quotes->send($quote, null);
    }

    /**
     * A quote of one line, asked for by the buyer: the cheapest way to have many of them.
     */
    protected function simpleQuote(Customer $buyer, ?Product $product = null): Quote
    {
        $product ??= $this->product('10', 'Product S');

        return $this->quotes->requestFromCart($this->cartOf($buyer, [[$product, 1.0, '10']]), $buyer, new QuoteRequestInput());
    }

    /**
     * The buyer counters the price of A, and the shop answers with a new revision at that price: the
     * history then holds three revisions. Returns the number of the revision the quote stands at.
     *
     * @param array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product} $scenario a quote the shop answered once
     */
    protected function negotiate(array $scenario, string $counterPrice = '85', string $newPrice = '87'): int
    {
        $quote = $scenario['quote'];
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->orderByNumber('desc')->findOne();
        $lines = [];
        $aLineId = 0;

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $isA = $line->getProductRef() === $scenario['a']->getRef();
            $aLineId = $isA ? (int) $line->getId() : $aLineId;
            $lines[] = new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), (float) $line->getQuantity(), $isA ? $newPrice : (string) $line->getPrice());
        }

        $this->quotes->counterOffer($quote, $scenario['buyer'], (int) $revision->getNumber(), [new QuoteItemRequestInput($aLineId, null, $counterPrice)]);
        $this->quotes->saveDraft($quote, null, new QuoteDraftInput($lines, '30', '15'));
        $this->quotes->send($quote, null);

        return (int) $revision->getNumber() + 1;
    }

    /**
     * The last day of validity is behind us: the quote reads as expired, whatever its stored state.
     */
    protected function expire(Quote $quote): void
    {
        $quote->setValidUntil(new \DateTimeImmutable('-2 days'))->save($this->getPropelConnection());
    }
}
