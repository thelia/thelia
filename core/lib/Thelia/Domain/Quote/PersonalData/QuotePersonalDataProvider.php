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

namespace Thelia\Domain\Quote\PersonalData;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Model\Customer;
use Thelia\Model\Map\QuoteRevisionAddressTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevisionAddressQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * What the shop holds about a customer as the buyer of quotes: each quote with its dates, what the
 * buyer wrote and the lines of each revision that was sent. A quote the shop has not sent yet and
 * the draft of the next revision are the shop's own work: the buyer does not know they exist, and
 * they are not exported.
 *
 * The quotes stay when the customer is anonymized: they are part of the commercial record, and a
 * quote that became an order is part of the accounting one. What identifies the person goes: the
 * comment they wrote, and the addresses of the quote and the copies of them kept on each revision,
 * which are entries of their address book.
 */
final readonly class QuotePersonalDataProvider implements CustomerPersonalDataProviderInterface
{
    public const string SECTION_NAME = 'quotes';

    public function getPersonalDataSectionName(): string
    {
        return self::SECTION_NAME;
    }

    public function exportPersonalData(Customer $customer): array
    {
        // What the shop has not sent is the shop's own work: the buyer does not know it exists.
        $quoteRows = [...QuoteQuery::create()
            ->filterByCustomerId($customer->getId())
            ->filterByState(QuoteState::Draft->value, Criteria::NOT_EQUAL)
            ->orderById()
            ->find()];

        if ([] === $quoteRows) {
            return [];
        }

        // The revisions, their lines and their addresses are read once for all the quotes.
        $revisionsOf = [];

        foreach (QuoteRevisionQuery::create()
            ->filterByQuoteId(array_map(static fn ($quote): int => (int) $quote->getId(), $quoteRows), Criteria::IN)
            ->filterByState(QuoteRevisionState::Draft->value, Criteria::NOT_EQUAL)
            ->orderByNumber()
            ->find() as $revision) {
            $revisionsOf[(int) $revision->getQuoteId()][] = $revision;
        }

        $revisionIds = array_merge(...array_map(
            static fn (array $revisions): array => array_map(static fn ($revision): int => (int) $revision->getId(), $revisions),
            array_values($revisionsOf),
        ));

        $linesOf = [];
        $addressesOf = [];

        if ([] !== $revisionIds) {
            foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revisionIds, Criteria::IN)->orderByPosition()->find() as $item) {
                $linesOf[(int) $item->getQuoteRevisionId()][] = [
                    'title' => $item->getTitle(),
                    'ref' => $item->getProductRef(),
                    'quantity' => $item->getQuantity(),
                    'catalog_price' => $item->getCatalogPrice(),
                    'price' => $item->getPrice(),
                ];
            }

            foreach (QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($revisionIds, Criteria::IN)->orderByKind()->find() as $address) {
                $addressesOf[(int) $address->getQuoteRevisionId()][] = [
                    'kind' => $address->getKind(),
                    'name' => trim(\sprintf('%s %s', (string) $address->getFirstname(), (string) $address->getLastname())),
                    'company' => $address->getCompany(),
                    'address1' => $address->getAddress1(),
                    'address2' => $address->getAddress2(),
                    'address3' => $address->getAddress3(),
                    'zipcode' => $address->getZipcode(),
                    'city' => $address->getCity(),
                    'country' => $address->getCountryTitle(),
                    'vat_number' => $address->getVatNumber(),
                ];
            }
        }

        $quotes = [];

        foreach ($quoteRows as $quote) {
            $revisions = [];

            foreach ($revisionsOf[(int) $quote->getId()] ?? [] as $revision) {
                $revisions[] = [
                    'number' => $revision->getNumber(),
                    'author' => $revision->getAuthor(),
                    'state' => $revision->getState(),
                    'sent_at' => $this->format($revision->getSentAt()),
                    'merchant_note' => $revision->getMerchantNote(),
                    'discount' => $revision->getDiscount(),
                    'postage' => $revision->getPostage(),
                    'addresses' => $addressesOf[(int) $revision->getId()] ?? [],
                    'lines' => $linesOf[(int) $revision->getId()] ?? [],
                ];
            }

            $quotes[] = [
                'ref' => $quote->getRef(),
                'state' => $quote->getState(),
                'created_at' => $this->format($quote->getCreatedAt()),
                'sent_at' => $this->format($quote->getSentAt()),
                'valid_until' => $this->format($quote->getValidUntil()),
                'needed_by' => $this->format($quote->getNeededBy()),
                'buyer_comment' => $quote->getBuyerComment(),
                'revisions' => $revisions,
            ];
        }

        return $quotes;
    }

    public function anonymizePersonalData(Customer $customer): void
    {
        QuoteQuery::create()
            ->filterByCustomerId($customer->getId())
            ->update([
                'BuyerComment' => null,
                'InvoiceAddressId' => null,
                'DeliveryAddressId' => null,
            ]);

        QuoteTableMap::clearInstancePool();

        // The copies of the addresses kept on the revisions are entries of the address book too.
        $revisionIds = QuoteRevisionQuery::create()
            ->useQuoteQuery()->filterByCustomerId($customer->getId())->endUse()
            ->select('Id')
            ->find()
            ->toArray();

        if ([] !== $revisionIds) {
            QuoteRevisionAddressQuery::create()
                ->filterByQuoteRevisionId($revisionIds, Criteria::IN)
                ->update([
                    'Firstname' => null,
                    'Lastname' => null,
                    'Company' => null,
                    'Address1' => null,
                    'Address2' => null,
                    'Address3' => null,
                    'Zipcode' => null,
                    'City' => null,
                    'VatNumber' => null,
                ]);

            QuoteRevisionAddressTableMap::clearInstancePool();
        }
    }

    private function format(string|\DateTimeInterface|null $date): ?string
    {
        if (null === $date) {
            return null;
        }

        return $date instanceof \DateTimeInterface ? $date->format(\DATE_ATOM) : $date;
    }
}
