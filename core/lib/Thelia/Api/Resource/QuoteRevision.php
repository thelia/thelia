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

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\QuoteFrontRevisionProvider;
use Thelia\Api\State\Provider\QuoteRevisionProvider;
use Thelia\Domain\Quote\Enum\QuoteRevisionAddressKind;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Model\Quote as QuoteModel;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteItemRequestQuery;
use Thelia\Model\QuoteRevision as QuoteRevisionModel;
use Thelia\Model\QuoteRevisionAddress as QuoteRevisionAddressModel;
use Thelia\Model\QuoteRevisionAddressQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * One step of the negotiation: the request of the buyer, then each sending of the shop.
 *
 * A revision that was sent is final. The draft the shop is still writing is read by the
 * administration only: the buyer never sees it, and never knows there is one.
 *
 * The administration reads the revisions of a quote under the quote, the draft included, and
 * reads nothing else of them: a revision is written with the draft of its quote, as a whole. The
 * storefront reads the revisions that were sent, under the quote it may reach and by the same rule
 * as the quote itself: a quote with a long history stays light to read, the list of its revisions
 * is a summary and one revision carries its lines. The list leaves the lines out in both.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/quotes/{id}/revisions',
            uriVariables: ['id' => new Link(fromClass: self::class, identifiers: ['quoteId'])],
            requirements: ['id' => '\d+'],
            paginationEnabled: false,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_LIST]],
            openapi: new OpenApiOperation(
                summary: 'The revisions of a quote, without their lines',
                description: 'Every revision in the order it was written, the draft of the shop included.',
                responses: ['404' => new OpenApiResponse(description: 'No such quote.')],
            ),
            provider: QuoteRevisionProvider::class,
        ),
        new Get(
            uriTemplate: '/admin/quotes/{id}/revisions/{number}',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['quoteId']),
                'number' => new Link(fromClass: self::class, identifiers: ['number']),
            ],
            requirements: ['id' => '\d+', 'number' => '\d+'],
            normalizationContext: ['groups' => [Quote::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'A revision of a quote with its lines',
                responses: ['404' => new OpenApiResponse(description: 'No such quote, or no such revision on it.')],
            ),
            provider: QuoteRevisionProvider::class,
        ),
    ],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/quotes/{id}/revisions',
            uriVariables: ['id' => new Link(fromClass: self::class, identifiers: ['quoteId'])],
            requirements: ['id' => '\d+'],
            paginationEnabled: false,
            itemUriTemplate: '/front/account/quotes/{id}/revisions/{number}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_LIST]],
            security: Quote::FRONT_SECURITY,
            openapi: new OpenApiOperation(
                summary: 'The revisions of a quote that were sent, without their lines',
                description: 'In the order they were written: who wrote it, when it was sent, until when it is valid and what it comes to. The draft of the shop is never read from the storefront.',
                responses: ['404' => new OpenApiResponse(description: 'No such quote: the same answer for a quote the account may not see.')],
            ),
            provider: QuoteFrontRevisionProvider::class,
        ),
        new Get(
            uriTemplate: '/front/account/quotes/{id}/revisions/{number}',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['quoteId']),
                'number' => new Link(fromClass: self::class, identifiers: ['number']),
            ],
            requirements: ['id' => '\d+', 'number' => '\d+'],
            normalizationContext: ['groups' => [Quote::GROUP_FRONT_READ_SINGLE]],
            security: Quote::FRONT_SECURITY,
            openapi: new OpenApiOperation(
                summary: 'A revision of a quote that was sent, with its lines',
                responses: ['404' => new OpenApiResponse(description: 'No such quote (the same answer for a quote the account may not see), or no revision that was sent with this number.')],
            ),
            provider: QuoteFrontRevisionProvider::class,
        ),
    ],
)]
final class QuoteRevision
{
    public const GROUP_ADMIN_LIST = 'admin:quote_revision:list';
    public const GROUP_FRONT_LIST = 'front:quote_revision:list';

    /**
     * The quote the revision belongs to; what the address of the revision is made of, never written.
     */
    public int $quoteId = 0;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST, self::GROUP_FRONT_LIST])]
    public int $number = 0;

    /**
     * Who wrote it: customer for the request, admin for the answers of the shop.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST, self::GROUP_FRONT_LIST])]
    public string $author = '';

    /**
     * Draft while the shop writes it (administration only), sent once it is final.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public string $state = '';

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public ?int $adminId = null;

    /**
     * What the shop wrote to the buyer with the revision.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public ?string $merchantNote = null;

    /**
     * The discount on the whole revision, taxes included.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public string $discount = '0';

    /**
     * The postage of the revision, taxes excluded.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public string $postage = '0';

    /**
     * The totals frozen when the revision was sent; null for a draft.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST, self::GROUP_FRONT_LIST])]
    public ?string $totalUntaxed = null;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST, self::GROUP_FRONT_LIST])]
    public ?string $totalTaxed = null;

    /**
     * The amounts the revision was sent with, frozen on it: the lines without taxes, the taxes on
     * the lines and the taxes on the postage. Null for a draft, which is not worked out here.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public ?string $itemsUntaxed = null;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public ?string $itemsTax = null;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public ?string $postageTax = null;

    /**
     * Whether the buyer owes the VAT himself (reverse charge) for this revision, frozen when it
     * was sent: the amounts above still carry the VAT, the order will not. Null for a draft.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST, self::GROUP_FRONT_LIST])]
    public ?bool $reverseCharge = null;

    /**
     * The last day the revision can be accepted; on a draft, the day the shop chose for it.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST, self::GROUP_FRONT_LIST])]
    public ?\DateTimeInterface $validUntil = null;

    /**
     * The carrier the revision proposes with its postage.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public ?int $deliveryModuleId = null;

    /**
     * The invoice and delivery addresses the revision was sent with, copied onto it: the address
     * book of the buyer may change, the revision does not. Null for a draft.
     *
     * @var array<string, string|null>|null
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public ?array $invoiceAddress = null;

    /**
     * @var array<string, string|null>|null
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST])]
    public ?array $deliveryAddress = null;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE, self::GROUP_ADMIN_LIST, self::GROUP_FRONT_LIST])]
    public ?\DateTimeInterface $sentAt = null;

    /**
     * @var list<QuoteItem>
     */
    #[ApiProperty(genId: false)]
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public array $items = [];

    /**
     * The revisions of a quote with their lines and the counter-proposals on them, in three
     * statements however many lines there are (two without the lines). The draft is left out
     * unless the administration asks for it. One revision, when its number is given.
     *
     * @return list<self>
     */
    public static function readAllOf(QuoteModel $quote, bool $withDraft, ?int $number = null, bool $withItems = true): array
    {
        $query = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->orderByNumber(Criteria::ASC);

        if (!$withDraft) {
            $query->filterByState(QuoteRevisionState::Sent->value);
        }

        if (null !== $number) {
            $query->filterByNumber($number);
        }

        $revisions = [...$query->find()];

        if ([] === $revisions) {
            return [];
        }

        $linesByRevision = [];
        $lineIds = [];

        foreach ($withItems ? QuoteItemQuery::create()
            ->filterByQuoteRevisionId(array_map(static fn (QuoteRevisionModel $revision): int => (int) $revision->getId(), $revisions), Criteria::IN)
            ->orderByPosition(Criteria::ASC)
            ->find() : [] as $line) {
            $linesByRevision[(int) $line->getQuoteRevisionId()][] = $line;
            $lineIds[] = (int) $line->getId();
        }

        $counterOffers = [];

        if ([] !== $lineIds) {
            foreach (QuoteItemRequestQuery::create()->filterByQuoteItemId($lineIds, Criteria::IN)->find() as $counterOffer) {
                $counterOffers[(int) $counterOffer->getQuoteItemId()] = $counterOffer;
            }
        }

        $addresses = [];

        foreach (QuoteRevisionAddressQuery::create()
            ->filterByQuoteRevisionId(array_map(static fn (QuoteRevisionModel $revision): int => (int) $revision->getId(), $revisions), Criteria::IN)
            ->find() as $address) {
            $addresses[(int) $address->getQuoteRevisionId()][(string) $address->getKind()] = $address;
        }

        return array_map(
            static function (QuoteRevisionModel $revision) use ($linesByRevision, $counterOffers, $addresses): self {
                $resource = new self();
                $resource->quoteId = (int) $revision->getQuoteId();
                $resource->number = (int) $revision->getNumber();
                $resource->author = (string) $revision->getAuthor();
                $resource->state = (string) $revision->getState();
                $resource->adminId = $revision->getAdminId();
                $resource->merchantNote = $revision->getMerchantNote();
                $resource->discount = (string) $revision->getDiscount();
                $resource->postage = (string) $revision->getPostage();
                $resource->totalUntaxed = null === $revision->getTotalUntaxed() ? null : (string) $revision->getTotalUntaxed();
                $resource->totalTaxed = null === $revision->getTotalTaxed() ? null : (string) $revision->getTotalTaxed();
                $resource->itemsUntaxed = null === $revision->getItemsUntaxed() ? null : (string) $revision->getItemsUntaxed();
                $resource->itemsTax = null === $revision->getItemsTax() ? null : (string) $revision->getItemsTax();
                $resource->postageTax = null === $revision->getPostageTax() ? null : (string) $revision->getPostageTax();
                $resource->reverseCharge = null === $revision->getReverseCharge() ? null : 1 === (int) $revision->getReverseCharge();
                $resource->validUntil = $revision->getValidUntil();
                $resource->deliveryModuleId = $revision->getDeliveryModuleId();
                $resource->invoiceAddress = self::addressOf($addresses[(int) $revision->getId()][QuoteRevisionAddressKind::Invoice->value] ?? null);
                $resource->deliveryAddress = self::addressOf($addresses[(int) $revision->getId()][QuoteRevisionAddressKind::Delivery->value] ?? null);
                $resource->sentAt = $revision->getSentAt();
                $resource->items = array_map(
                    static fn ($line): QuoteItem => QuoteItem::fromModel($line, $counterOffers[(int) $line->getId()] ?? null, (int) $revision->getQuoteId(), (int) $revision->getNumber()),
                    $linesByRevision[(int) $revision->getId()] ?? [],
                );

                return $resource;
            },
            $revisions,
        );
    }

    /**
     * @return array<string, string|null>|null
     */
    private static function addressOf(?QuoteRevisionAddressModel $address): ?array
    {
        if (null === $address) {
            return null;
        }

        return [
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'company' => $address->getCompany(),
            'address1' => $address->getAddress1(),
            'address2' => $address->getAddress2(),
            'address3' => $address->getAddress3(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'country' => $address->getCountryTitle(),
            'vatNumber' => $address->getVatNumber(),
        ];
    }
}
