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

namespace Thelia\Domain\Quote\Document;

use Thelia\Core\Template\Helper\FormatService;
use Thelia\Domain\Pricing\MoneyRounding;
use Thelia\Domain\Quote\Enum\QuoteRevisionAddressKind;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteItemRequestQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionAddress;
use Thelia\Model\QuoteRevisionAddressQuery;

/**
 * What a revision of a quote says, as plain values a mail or a printed document shows as they are.
 *
 * Amounts and dates come formatted in the language asked for, texts come without markup: the
 * note of the shop is stored as plain text, and a template that prints it escapes it.
 *
 * A revision that was sent is read as it was frozen when it was sent: its amounts, its validity
 * and the copy of its addresses, never the tax rules or the address book of the day.
 */
final readonly class QuoteDocumentContext
{
    public function __construct(
        private QuoteFacade $quotes,
        private FormatService $format,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Quote $quote, QuoteRevision $revision, string $locale): array
    {
        $currencyId = (int) $quote->getCurrencyId();
        $totals = $this->quotes->totals($revision);
        $discount = (float) $totals->discount;
        /** @var Customer|null $customer Propel finds none for an orphan id. */
        $customer = $quote->getCustomer();
        $companyName = null === $quote->getCompanyId() ? null : $quote->getCompany()?->getName();
        $addresses = $this->addressesOf($revision);

        return [
            'quote_id' => (int) $quote->getId(),
            'quote_ref' => (string) $quote->getRef(),
            'revision_number' => (int) $revision->getNumber(),
            'document_locale' => $locale,
            'customer_name' => trim(\sprintf('%s %s', (string) $customer?->getFirstname(), (string) $customer?->getLastname())),
            'customer_email' => (string) $customer?->getEmail(),
            'customer_ref' => (string) $customer?->getRef(),
            'company_name' => $companyName,
            'currency_id' => $currencyId,
            'lines' => $this->lines($revision, $currencyId, $locale),
            'items_untaxed' => $this->money($totals->itemsUntaxed, $currencyId, $locale),
            'items_tax' => $this->money($totals->itemsTax, $currencyId, $locale),
            'postage_untaxed' => $this->money($totals->postageUntaxed, $currencyId, $locale),
            'postage_tax' => $this->money($totals->postageTax, $currencyId, $locale),
            'has_postage' => (float) $totals->postageUntaxed > 0.0,
            'discount' => $this->money($totals->discount, $currencyId, $locale),
            'has_discount' => $discount > 0.0,
            'total_untaxed' => $this->money($totals->totalUntaxed, $currencyId, $locale),
            'total_taxed' => $this->money($totals->totalTaxed, $currencyId, $locale),
            'merchant_note' => $this->asText($revision->getMerchantNote()),
            'buyer_comment' => $this->asText($quote->getBuyerComment()),
            'coupon_dropped' => 1 === (int) $quote->getCouponDropped(),
            'valid_until' => $this->date($revision->getValidUntil(), $locale),
            'needed_by' => $this->date($quote->getNeededBy(), $locale),
            'sent_at' => $this->date($revision->getSentAt(), $locale),
            'delivery_address' => $this->addressLines($addresses[QuoteRevisionAddressKind::Delivery->value] ?? null),
            'invoice_address' => $this->addressLines($addresses[QuoteRevisionAddressKind::Invoice->value] ?? null),
            'reverse_charge' => 1 === (int) $revision->getReverseCharge(),
            'customer_vat_number' => (string) ($addresses[QuoteRevisionAddressKind::Invoice->value] ?? null)?->getVatNumber(),
        ];
    }

    /**
     * What the buyer asked for on each line of the revision: the lines that carry a request only.
     *
     * @return list<array<string, mixed>>
     */
    public function requestedChanges(QuoteRevision $revision, int $currencyId, string $locale): array
    {
        $items = [...QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find()];

        if ([] === $items) {
            return [];
        }

        $requests = [];

        foreach (QuoteItemRequestQuery::create()->filterByQuoteItemId(array_map(static fn ($item): int => (int) $item->getId(), $items))->find() as $request) {
            $requests[(int) $request->getQuoteItemId()] = $request;
        }

        $changes = [];

        foreach ($items as $item) {
            $request = $requests[(int) $item->getId()] ?? null;

            if (null === $request) {
                continue;
            }

            $changes[] = [
                'title' => (string) $item->getTitle(),
                'ref' => (string) $item->getProductRef(),
                'quantity' => $this->quantity((float) $item->getQuantity()),
                'price' => $this->money((string) $item->getPrice(), $currencyId, $locale),
                'requested_quantity' => null === $request->getQuantity() ? null : $this->quantity((float) $request->getQuantity()),
                'requested_price' => null === $request->getPrice() ? null : $this->money((string) $request->getPrice(), $currencyId, $locale),
            ];
        }

        return $changes;
    }

    /**
     * @return list<array<string, string>>
     */
    private function lines(QuoteRevision $revision, int $currencyId, string $locale): array
    {
        $lines = [];

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $item) {
            $lines[] = [
                'title' => (string) $item->getTitle(),
                'ref' => (string) $item->getProductRef(),
                'sale_elements_ref' => (string) $item->getProductSaleElementsRef(),
                'quantity' => $this->quantity((float) $item->getQuantity()),
                'catalog_price' => $this->money((string) $item->getCatalogPrice(), $currencyId, $locale),
                'price' => $this->money((string) $item->getPrice(), $currencyId, $locale),
                'total' => $this->money($this->lineTotal((float) $item->getPrice(), (float) $item->getQuantity()), $currencyId, $locale),
            ];
        }

        return $lines;
    }

    /**
     * Totalled the way the totals of the quote total a line.
     */
    private function lineTotal(float $unitPrice, float $quantity): string
    {
        $total = ConfigQuery::isRoundingModeRoundingOfSums()
            ? MoneyRounding::toTheCent($unitPrice * $quantity)
            : MoneyRounding::toTheCent($unitPrice) * $quantity;

        return number_format(round($total, 6), 6, '.', '');
    }

    private function money(string $amount, int $currencyId, string $locale): string
    {
        return $this->format->money($amount, $currencyId, $locale);
    }

    private function quantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }

    private function date(string|\DateTimeInterface|null $date, string $locale): ?string
    {
        if (null === $date || '' === $date) {
            return null;
        }

        return $this->format->date($date, null, 'date', $locale);
    }

    private function asText(?string $text): ?string
    {
        return null === $text || '' === trim($text) ? null : trim($text);
    }

    /**
     * The addresses the revision was sent with, as they were copied onto it.
     *
     * @return array<string, QuoteRevisionAddress>
     */
    private function addressesOf(QuoteRevision $revision): array
    {
        $addresses = [];

        foreach (QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($revision->getId())->find() as $address) {
            $addresses[(string) $address->getKind()] = $address;
        }

        return $addresses;
    }

    /**
     * @return list<string>
     */
    private function addressLines(?QuoteRevisionAddress $address): array
    {
        if (null === $address) {
            return [];
        }

        $lines = [
            trim(\sprintf('%s %s', (string) $address->getFirstname(), (string) $address->getLastname())),
            (string) $address->getCompany(),
            (string) $address->getAddress1(),
            (string) $address->getAddress2(),
            (string) $address->getAddress3(),
            trim(\sprintf('%s %s', (string) $address->getZipcode(), (string) $address->getCity())),
            (string) $address->getCountryTitle(),
        ];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => '' !== $line));
    }
}
