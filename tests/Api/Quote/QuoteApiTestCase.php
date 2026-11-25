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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Model\Admin;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevisionQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Tests\Api\Company\CompanyApiTestCase;

/**
 * What the API tests of the quotes share: a shop that takes quotes, products at known prices under a
 * tax of 20 %, and the quotes the facade writes, which the tests then read and answer over HTTP.
 */
abstract class QuoteApiTestCase extends CompanyApiTestCase
{
    protected QuoteFacade $quotes;

    private ?TaxRule $vatRule = null;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('quote_mode', 'alongside');
        ConfigQuery::write('quote_validity_days', '30');
        ConfigQuery::write('taxrule_id_delivery_module', (string) $this->vatRule()->getId());
        ConfigQuery::resetCache();

        $this->quotes = $this->getService(QuoteFacade::class);
    }

    protected function vatRule(): TaxRule
    {
        if (null === $this->vatRule) {
            $this->vatRule = $this->fixtures->taxRule(['isDefault' => false]);
            $tax = $this->fixtures->tax(['requirements' => ['percent' => '20.000000'], 'title' => 'VAT 20']);
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
        return $this->fixtures->product(
            $this->fixtures->category(),
            $this->vatRule(),
            $this->fixtures->currency(),
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
        $cart = $this->fixtures->cart($customer);

        foreach ($lines as [$product, $quantity, $price]) {
            $this->fixtures->cartItem($cart, $product, null, ['quantity' => $quantity, 'price' => $price, 'promoPrice' => $price]);
        }

        return $cart;
    }

    /**
     * A quote the buyer asked for, 10 of A at 100 and 4 of B at 50, written by the facade.
     *
     * @return array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product}
     */
    protected function requestedQuote(?Customer $buyer = null): array
    {
        $buyer ??= $this->customer();
        $a = $this->product('100', 'Product A');
        $b = $this->product('50', 'Product B');
        $c = $this->product('20', 'Product C');
        $quote = $this->quotes->requestFromCart($this->cartOf($buyer, [[$a, 10.0, '100'], [$b, 4.0, '50']]), $buyer, new QuoteRequestInput());

        return ['buyer' => $buyer, 'quote' => $quote, 'a' => $a, 'b' => $b, 'c' => $c];
    }

    /**
     * The same quote after the shop answered: 12 of A at 90, 4 of B at 45 and 5 of C at 20, a discount of 30
     * and a postage of 15 (1 620.00 with taxes). The facade writes and sends it.
     *
     * @return array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product}
     */
    protected function quotedQuote(?Customer $buyer = null, ?Admin $admin = null): array
    {
        $scenario = $this->requestedQuote($buyer);
        $this->shopAnswers($scenario['quote'], $scenario, admin: $admin);

        return $scenario;
    }

    /**
     * @param array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product} $scenario
     */
    protected function shopAnswers(Quote $quote, array $scenario, string $aPrice = '90', ?Admin $admin = null): void
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

        $this->quotes->saveDraft($quote, $admin, new QuoteDraftInput($lines, '30', '15'));
        $this->quotes->send($quote, $admin);
    }

    /**
     * @return array<string, mixed>
     */
    protected function bodyOf(Response $response): array
    {
        return $this->body($response);
    }

    protected function reason(Response $response): ?string
    {
        return $this->body($response)['reason'] ?? null;
    }

    protected function adminToken(): string
    {
        return $this->authenticateAsAdmin();
    }

    /**
     * A revision of a quote with its lines, as the account whose token it is reads it.
     *
     * @return array<string, mixed>
     */
    protected function frontRevision(int|Quote $quote, int $revisionNumber, string $token): array
    {
        $id = $quote instanceof Quote ? $quote->getId() : $quote;
        $response = $this->jsonRequest('GET', '/api/front/account/quotes/'.$id.'/revisions/'.$revisionNumber, token: $token);
        self::assertJsonResponseSuccessful($response);

        return $this->body($response);
    }

    /**
     * @return list<int> the ids of the lines of a revision of a quote body
     */
    protected function lineIdsOf(array $quote, int $revisionNumber): array
    {
        foreach ($quote['revisions'] as $revision) {
            if ($revision['number'] === $revisionNumber) {
                return array_map(static fn (array $line): int => $line['id'], $revision['items']);
            }
        }

        return [];
    }
}
