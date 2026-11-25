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

namespace Thelia\Domain\Quote\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Quote\DTO\QuoteTotals;
use Thelia\Domain\Quote\Enum\QuoteRevisionAddressKind;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\Address;
use Thelia\Model\CountryI18nQuery;
use Thelia\Model\Lang;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItem;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionAddress;
use Thelia\Model\QuoteRevisionAddressQuery;

/**
 * What a revision becomes when it is sent: everything it proposes is written on it, so that it is
 * read back as it was proposed, whatever the tax rules, the address book or the shop settings
 * say afterwards.
 *
 * The amounts of the lines and of the postage with the taxes on each, the discount and the total,
 * the answer to whether the buyer owes the VAT himself, and a copy of the invoice and delivery
 * addresses of the quote. The postage and the discount are written as they were worked out, to the
 * cent, which is what the buyer pays.
 */
final readonly class QuoteRevisionFreezer
{
    public function __construct(
        private QuoteTotalsCalculator $totalsCalculator,
        private VatExemptionResolver $vatExemption,
    ) {
    }

    /**
     * @param iterable<QuoteItem> $lines  the lines of the revision
     * @param QuoteTotals|null    $totals what the revision comes to, when the caller has just worked it out: the lines
     *                                    and the revision are the same, and it is not worked out a second time
     */
    public function freeze(Quote $quote, QuoteRevision $revision, iterable $lines, ConnectionInterface $connection, ?QuoteTotals $totals = null): QuoteTotals
    {
        $totals ??= $this->totalsCalculator->calculate($quote, $revision, $lines);
        $invoiceAddress = null === $quote->getInvoiceAddressId() ? null : $quote->getInvoiceAddress();

        $revision
            ->setDiscount($totals->discount)
            ->setPostage($totals->postageUntaxed)
            ->setItemsUntaxed($totals->itemsUntaxed)
            ->setItemsTax($totals->itemsTax)
            ->setPostageTax($totals->postageTax)
            ->setTotalUntaxed($totals->totalUntaxed)
            ->setTotalTaxed($totals->totalTaxed)
            ->setReverseCharge($this->vatExemption->isExemptedForInvoiceAddress($invoiceAddress) ? 1 : 0)
            ->save($connection);

        $this->copyAddresses($quote, $revision, $connection);

        return $totals;
    }

    private function copyAddresses(Quote $quote, QuoteRevision $revision, ConnectionInterface $connection): void
    {
        QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($revision->getId())->delete($connection);

        $locale = (string) ($quote->getLocale() ?: Lang::getDefaultLanguage()->getLocale());
        $addresses = [
            QuoteRevisionAddressKind::Invoice->value => null === $quote->getInvoiceAddressId() ? null : $quote->getInvoiceAddress(),
            QuoteRevisionAddressKind::Delivery->value => null === $quote->getDeliveryAddressId() ? null : $quote->getDeliveryAddress(),
        ];

        foreach ($addresses as $kind => $address) {
            if (!$address instanceof Address) {
                continue;
            }

            (new QuoteRevisionAddress())
                ->setQuoteRevisionId($revision->getId())
                ->setKind($kind)
                ->setFirstname($address->getFirstname())
                ->setLastname($address->getLastname())
                ->setCompany($address->getCompany())
                ->setAddress1($address->getAddress1())
                ->setAddress2($address->getAddress2())
                ->setAddress3($address->getAddress3())
                ->setZipcode($address->getZipcode())
                ->setCity($address->getCity())
                ->setCountryId($address->getCountryId())
                ->setCountryTitle($this->countryTitle($address->getCountryId(), $locale, $connection))
                ->setVatNumber($address->getVatNumber())
                ->save($connection);
        }
    }

    /**
     * The name of the country in the language of the quote, read without touching the country object,
     * which the rest of the process shares.
     */
    private function countryTitle(?int $countryId, string $locale, ConnectionInterface $connection): ?string
    {
        if (null === $countryId) {
            return null;
        }

        return CountryI18nQuery::create()->filterById($countryId)->filterByLocale($locale)->findOne($connection)?->getTitle();
    }
}
