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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody as OpenApiRequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Extension\QuoteLatestTotalExtension;
use Thelia\Api\Bridge\Propel\Extension\QuoteOrderExtension;
use Thelia\Api\Bridge\Propel\Filter\DateFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\QuoteStateFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\QuoteAdminProcessor;
use Thelia\Api\State\Processor\QuoteFrontProcessor;
use Thelia\Core\Security\AccessManager;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\QuoteTransitions;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\Quote as QuoteModel;
use Thelia\Model\QuoteRevisionQuery;

/**
 * A quote: the request of a buyer, the answers of the shop, and the negotiation between them, up to
 * the order it becomes.
 *
 * The administration lists and reads every quote, creates one for a customer, writes the draft of
 * the next revision and sends, extends and reminds; it deletes a quote the shop never sent, and
 * reads the revisions and the lines of a quote under it (see QuoteRevision and QuoteItem). The storefront lists the quotes the account may
 * see (its own and, when its role says so, its company's), asks for a quote from a cart, and
 * answers the revision the quote stands at: counter-propose, accept or turn it down.
 *
 * A state is never written: it moves by an operation of its own, which hands the work to the quote
 * facade and so cannot leave a state the transitions do not allow. The prices are the shop's: the
 * buyer's wishes go in a counter-proposal, which sits next to the line it discusses. The shop's draft
 * and its own notes are never read from the storefront, and the list does not carry the lines.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/quotes',
            openapi: new OpenApiOperation(
                summary: 'List the quotes',
                description: 'The quotes of every account, without their revisions. The state filter answers the state a quote reads in: a quote waiting for the buyer past its last valid day is expired.',
            ),
        ),
        new Get(
            uriTemplate: '/admin/quotes/{id}',
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'A quote with its revisions and their lines',
                description: 'Every revision, the draft of the shop included.',
            ),
        ),
        new Delete(
            name: self::OPERATION_ADMIN_DELETE,
            uriTemplate: '/admin/quotes/{id}',
            requirements: ['id' => '\d+'],
            status: 204,
            openapi: new OpenApiOperation(
                summary: 'Delete a quote the shop never sent',
                description: 'Only a quote none of whose revisions was sent by the shop and that no order came from: it goes with its revisions, its lines and what the buyers countered. A quote that was sent is the record of what the shop offered, and stays.',
                responses: [
                    '404' => new OpenApiResponse(description: 'No such quote.'),
                    '409' => new OpenApiResponse(description: 'The shop sent a revision of this quote (deletion_refused_sent), or an order came from it (deletion_refused_ordered).'),
                ],
            ),
            read: false,
            deserialize: false,
            processor: QuoteAdminProcessor::class,
        ),
        new Post(
            name: self::OPERATION_ADMIN_CREATE,
            uriTemplate: '/admin/quotes',
            status: 201,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'Start a quote for a customer',
                description: 'A draft with an empty first revision of the shop, in the default currency, addressed to the default address of the customer.',
                requestBody: new OpenApiRequestBody(content: new \ArrayObject(['application/json' => ['schema' => self::CREATE_SCHEMA]])),
                responses: ['422' => new OpenApiResponse(description: 'The customer does not exist, or is a guest.')],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteAdminProcessor::class,
        ),
        new Put(
            name: self::OPERATION_ADMIN_DRAFT,
            uriTemplate: '/admin/quotes/{id}/draft',
            requirements: ['id' => '\d+'],
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'Write the draft of the next revision',
                description: 'Replaces all of the draft: its lines, discount, postage, carrier, note and last day of validity. When the revision the quote stands at was sent, the draft is the next one, created now. A revision that was sent is never written again.',
                requestBody: new OpenApiRequestBody(content: new \ArrayObject(['application/json' => ['schema' => self::DRAFT_SCHEMA]])),
                responses: [
                    '404' => new OpenApiResponse(description: 'No such quote.'),
                    '409' => new OpenApiResponse(description: 'The quote is in a state that takes no revision.'),
                    '422' => new OpenApiResponse(description: 'A line, an amount or a date the shop refuses; the reason says which.'),
                ],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteAdminProcessor::class,
        ),
        new Post(
            name: self::OPERATION_ADMIN_SEND,
            uriTemplate: '/admin/quotes/{id}/send',
            requirements: ['id' => '\d+'],
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'Send the draft to the buyer',
                description: 'The draft becomes final, its totals are frozen, and the quote becomes quoted. It is valid until the day the draft names, or for the validity of the shop.',
                responses: [
                    '404' => new OpenApiResponse(description: 'No such quote.'),
                    '409' => new OpenApiResponse(description: 'The quote is in a state that cannot be sent.'),
                    '422' => new OpenApiResponse(description: 'Nothing to send, or a line the shop no longer sells.'),
                ],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteAdminProcessor::class,
            extraProperties: ['admin_access' => AccessManager::UPDATE],
        ),
        new Post(
            name: self::OPERATION_ADMIN_EXTEND,
            uriTemplate: '/admin/quotes/{id}/extend',
            requirements: ['id' => '\d+'],
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'Push the validity of a quote back',
                description: 'An expired or waiting quote is valid until the day given, which is not in the past; a quote that is still valid is pushed back, so the day is later than its current last day.',
                requestBody: new OpenApiRequestBody(content: new \ArrayObject(['application/json' => ['schema' => self::EXTEND_SCHEMA]])),
                responses: [
                    '404' => new OpenApiResponse(description: 'No such quote.'),
                    '409' => new OpenApiResponse(description: 'The quote is in a state that cannot be extended.'),
                    '422' => new OpenApiResponse(description: 'A day in the past, or, for a quote still valid, no later than its current last day.'),
                ],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteAdminProcessor::class,
            extraProperties: ['admin_access' => AccessManager::UPDATE],
        ),
        new Post(
            name: self::OPERATION_ADMIN_REMIND,
            uriTemplate: '/admin/quotes/{id}/remind',
            requirements: ['id' => '\d+'],
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'Remind the buyer of a quote',
                description: 'Mails the revision the quote stands at again, and writes the time of the reminder on the quote.',
                responses: [
                    '404' => new OpenApiResponse(description: 'No such quote.'),
                    '409' => new OpenApiResponse(description: 'The quote is not waiting for the buyer.'),
                ],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteAdminProcessor::class,
            extraProperties: ['admin_access' => AccessManager::UPDATE],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/quotes',
            security: self::FRONT_SECURITY,
            openapi: new OpenApiOperation(
                summary: 'The quotes the account can see',
                description: 'Its own and, when its role in a company reads the orders of the company, the quotes of the company. Without their lines.',
            ),
        ),
        new Get(
            uriTemplate: '/front/account/quotes/{id}',
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: self::FRONT_SECURITY,
            openapi: new OpenApiOperation(
                summary: 'A quote',
                description: 'The quote and what it comes to, without its revisions: the revisions are read apart, so that a quote with a long history stays light to read (see QuoteRevision).',
                responses: ['404' => new OpenApiResponse(description: 'No such quote: the same answer for a quote the account may not see.')],
            ),
        ),
        new Post(
            name: self::OPERATION_FRONT_REQUEST,
            uriTemplate: '/front/account/quotes',
            status: 201,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: self::FRONT_SECURITY,
            openapi: new OpenApiOperation(
                summary: 'Ask for a quote from a cart',
                description: 'The lines of the cart become the first revision of a new quote, at the prices the buyer was shown. The cart is left as it is; its promo code is not carried over. Answers 429 after too many requests.',
                requestBody: new OpenApiRequestBody(content: new \ArrayObject(['application/json' => ['schema' => self::REQUEST_SCHEMA]])),
                responses: [
                    '403' => new OpenApiResponse(description: 'The role of the account in its company does not allow it to ask for quotes.'),
                    '422' => new OpenApiResponse(description: 'The shop takes no quote, the company of the account is suspended, the cart is not the account\'s, is empty, or is the cart of a quote (its prices are the negotiated ones), a line cannot be quoted: the reason says which.'),
                    '429' => new OpenApiResponse(description: 'Too many requests from this account.'),
                ],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteFrontProcessor::class,
        ),
        new Post(
            name: self::OPERATION_FRONT_COUNTER_OFFER,
            uriTemplate: '/front/account/quotes/{id}/counter-offer',
            requirements: ['id' => '\d+'],
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: self::FRONT_SECURITY,
            openapi: new OpenApiOperation(
                summary: 'Counter-propose a quantity or a price on some lines',
                description: 'Only the quantity and the price asked for, on lines of the revision the quote stands at: a counter-proposal adds and removes no line. The quote is then waiting for the shop. Answers 429 after too many counter-proposals.',
                requestBody: new OpenApiRequestBody(content: new \ArrayObject(['application/json' => ['schema' => self::COUNTER_OFFER_SCHEMA]])),
                responses: [
                    '403' => new OpenApiResponse(description: 'The account sees the quote but its role does not allow it to answer.'),
                    '404' => new OpenApiResponse(description: 'No such quote: the same answer for a quote the account may not see.'),
                    '409' => new OpenApiResponse(description: 'The quote is not waiting for the buyer.'),
                    '422' => new OpenApiResponse(description: 'A revision that is no longer the current one, a line that is not on it, a quantity or a price that is not one.'),
                    '429' => new OpenApiResponse(description: 'Too many counter-proposals from this account.'),
                ],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteFrontProcessor::class,
        ),
        new Post(
            name: self::OPERATION_FRONT_ACCEPT,
            uriTemplate: '/front/account/quotes/{id}/accept',
            requirements: ['id' => '\d+'],
            status: 200,
            output: Cart::class,
            normalizationContext: ['groups' => [Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE]],
            security: self::FRONT_SECURITY,
            openapi: new OpenApiOperation(
                summary: 'Accept the revision the quote stands at',
                description: 'Names a revision, never an amount: the prices come from the quote. Answers the cart to pay, at the agreed prices; the order is placed through the checkout. When the caller holds a storefront session, the cart becomes its cart; without one nothing else happens.',
                requestBody: new OpenApiRequestBody(content: new \ArrayObject(['application/json' => ['schema' => self::ANSWER_SCHEMA]])),
                responses: [
                    '403' => new OpenApiResponse(description: 'The account sees the quote but its role does not allow it to accept.'),
                    '404' => new OpenApiResponse(description: 'No such quote: the same answer for a quote the account may not see.'),
                    '409' => new OpenApiResponse(description: 'The quote is not waiting for the buyer, expired, or already ordered.'),
                    '422' => new OpenApiResponse(description: 'A revision that is no longer the current one, a line the shop no longer sells, a currency the shop no longer takes.'),
                ],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteFrontProcessor::class,
        ),
        new Post(
            name: self::OPERATION_FRONT_REJECT,
            uriTemplate: '/front/account/quotes/{id}/reject',
            requirements: ['id' => '\d+'],
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: self::FRONT_SECURITY,
            openapi: new OpenApiOperation(
                summary: 'Turn the quote down',
                requestBody: new OpenApiRequestBody(content: new \ArrayObject(['application/json' => ['schema' => self::ANSWER_SCHEMA]])),
                responses: [
                    '403' => new OpenApiResponse(description: 'The account sees the quote but its role does not allow it to answer.'),
                    '404' => new OpenApiResponse(description: 'No such quote: the same answer for a quote the account may not see.'),
                    '409' => new OpenApiResponse(description: 'The quote is not waiting for the buyer.'),
                    '422' => new OpenApiResponse(description: 'A revision that is no longer the current one.'),
                ],
            ),
            input: false,
            read: false,
            deserialize: false,
            processor: QuoteFrontProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'ref' => 'partial',
        'customerId' => 'exact',
        'companyId' => 'exact',
    ],
)]
#[ApiFilter(filterClass: QuoteStateFilter::class, properties: ['state' => 'exact'])]
#[ApiFilter(
    filterClass: DateFilter::class,
    properties: [
        'validUntil' => DateFilter::EXCLUDE_NULL,
        'createdAt' => DateFilter::EXCLUDE_NULL,
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'createdAt',
        'validUntil',
        'sentAt',
    ],
)]
class Quote implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:quote:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:quote:read:single';
    public const GROUP_FRONT_READ = 'front:quote:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:quote:read:single';

    public const OPERATION_ADMIN_DELETE = 'admin_quote_delete';
    public const OPERATION_ADMIN_CREATE = 'admin_quote_create';
    public const OPERATION_ADMIN_DRAFT = 'admin_quote_draft';
    public const OPERATION_ADMIN_SEND = 'admin_quote_send';
    public const OPERATION_ADMIN_EXTEND = 'admin_quote_extend';
    public const OPERATION_ADMIN_REMIND = 'admin_quote_remind';
    public const OPERATION_FRONT_REQUEST = 'front_quote_request';
    public const OPERATION_FRONT_COUNTER_OFFER = 'front_quote_counter_offer';
    public const OPERATION_FRONT_ACCEPT = 'front_quote_accept';
    public const OPERATION_FRONT_REJECT = 'front_quote_reject';

    /**
     * Stated on every storefront operation although the account prefix of the firewall already
     * demands it: a prefix is one refactor away from no longer covering the route. Who may reach
     * which quote is decided by the ownership extension and by the quote access rule.
     */
    public const FRONT_SECURITY = 'is_granted("ROLE_CUSTOMER")';

    private const CREATE_SCHEMA = [
        'type' => 'object',
        'required' => ['customerId'],
        'properties' => [
            'customerId' => ['type' => 'integer'],
        ],
    ];

    private const REQUEST_SCHEMA = [
        'type' => 'object',
        'required' => ['cartId'],
        'properties' => [
            'cartId' => ['type' => 'integer', 'description' => 'A cart of the account.'],
            'comment' => ['type' => 'string', 'nullable' => true, 'description' => 'At most 5000 characters; tags are removed.'],
            'neededBy' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
            'invoiceAddressId' => ['type' => 'integer', 'nullable' => true],
            'deliveryAddressId' => ['type' => 'integer', 'nullable' => true],
        ],
    ];

    private const COUNTER_OFFER_SCHEMA = [
        'type' => 'object',
        'required' => ['revision', 'lines'],
        'properties' => [
            'revision' => ['type' => 'integer', 'description' => 'The number of the revision the quote stands at.'],
            'lines' => [
                'type' => 'array',
                'minItems' => 1,
                'items' => [
                    'type' => 'object',
                    'required' => ['quoteItemId'],
                    'properties' => [
                        'quoteItemId' => ['type' => 'integer'],
                        'quantity' => ['type' => 'number', 'nullable' => true, 'description' => 'The quantity asked for; left out when it stays as it is.'],
                        'price' => ['oneOf' => [['type' => 'string'], ['type' => 'number']], 'nullable' => true, 'description' => 'The price of one unit without taxes asked for; left out when it stays as it is.'],
                    ],
                ],
            ],
        ],
    ];

    private const ANSWER_SCHEMA = [
        'type' => 'object',
        'required' => ['revision'],
        'properties' => [
            'revision' => ['type' => 'integer', 'description' => 'The number of the revision the quote stands at.'],
        ],
    ];

    private const DRAFT_SCHEMA = [
        'type' => 'object',
        'required' => ['lines'],
        'properties' => [
            'lines' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['productSaleElementsId', 'quantity', 'price'],
                    'properties' => [
                        'quoteItemId' => ['type' => 'integer', 'nullable' => true, 'description' => 'The line of the revision before, when it is one the draft keeps.'],
                        'productSaleElementsId' => ['type' => 'integer'],
                        'quantity' => ['type' => 'number'],
                        'price' => ['oneOf' => [['type' => 'string'], ['type' => 'number']], 'description' => 'The price of one unit without taxes.'],
                    ],
                ],
            ],
            'discount' => ['oneOf' => [['type' => 'string'], ['type' => 'number']], 'description' => 'On the whole revision, taxes included. Zero when left out.'],
            'postage' => ['oneOf' => [['type' => 'string'], ['type' => 'number']], 'description' => 'Taxes excluded. Zero when left out.'],
            'deliveryModuleId' => ['type' => 'integer', 'nullable' => true],
            'merchantNote' => ['type' => 'string', 'nullable' => true, 'description' => 'Written to the buyer; tags are removed.'],
            'validUntil' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
        ],
    ];

    private const EXTEND_SCHEMA = [
        'type' => 'object',
        'required' => ['validUntil'],
        'properties' => [
            'validUntil' => ['type' => 'string', 'format' => 'date'],
        ],
    ];

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $ref = null;

    /**
     * The state the quote reads in today: a quote waiting for the buyer past its last valid day is
     * expired, whether or not the console has written it yet.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $state = null;

    /**
     * The number of the last revision that was sent.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $currentRevision = null;

    /**
     * The account the quote was requested by, or created for.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $customerId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $companyId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $currencyId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $currencyCode = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $locale = null;

    /**
     * What the buyer wrote when they asked for the quote.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $buyerComment = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTimeInterface $neededBy = null;

    /**
     * The last day the quote can be accepted.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTimeInterface $validUntil = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTimeInterface $sentAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTimeInterface $remindedAt = null;

    /**
     * Whether the promo code of the cart the quote was requested from was left behind.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?bool $couponDropped = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $invoiceAddressId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $deliveryAddressId = null;

    /**
     * The carrier the shop picked with the postage it negotiated.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $deliveryModuleId = null;

    /**
     * The total with taxes of the latest revision sent, as the decimal frozen on it. Left out until a
     * revision with a total was sent: a draft is never counted.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $totalTaxed = null;

    /**
     * The order the quote became, left out while there is none: an order that was cancelled lets the
     * quote go, so that the one placed next is the order of the quote.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $orderId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $orderRef = null;

    /**
     * Whether the buyer owes the VAT himself (reverse charge) for the revision the quote stands at:
     * the amounts of the quote still carry the VAT, the order will not. Read on one quote only, from
     * the revision as it was frozen when it was sent.
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?bool $reverseCharge = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTimeInterface $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTimeInterface $updatedAt = null;

    /**
     * The revisions in the order they were written, with their lines, the draft too. Read by the
     * administration on one quote, never on a list; the storefront reads them apart, through
     * `/front/account/quotes/{id}/revisions`, and never the draft.
     *
     * @var list<QuoteRevision>
     */
    #[ApiProperty(genId: false)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public array $revisions = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getCurrentRevision(): ?int
    {
        return $this->currentRevision;
    }

    public function setCurrentRevision(?int $currentRevision): self
    {
        $this->currentRevision = $currentRevision;

        return $this;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function setCustomerId(?int $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;

        return $this;
    }

    public function getCurrencyId(): ?int
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?int $currencyId): self
    {
        $this->currencyId = $currencyId;

        return $this;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(?string $currencyCode): self
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getBuyerComment(): ?string
    {
        return $this->buyerComment;
    }

    public function setBuyerComment(?string $buyerComment): self
    {
        $this->buyerComment = $buyerComment;

        return $this;
    }

    public function getNeededBy(): ?\DateTimeInterface
    {
        return $this->neededBy;
    }

    public function setNeededBy(?\DateTimeInterface $neededBy): self
    {
        $this->neededBy = $neededBy;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): self
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getRemindedAt(): ?\DateTimeInterface
    {
        return $this->remindedAt;
    }

    public function setRemindedAt(?\DateTimeInterface $remindedAt): self
    {
        $this->remindedAt = $remindedAt;

        return $this;
    }

    public function getCouponDropped(): ?bool
    {
        return $this->couponDropped;
    }

    public function setCouponDropped(?bool $couponDropped): self
    {
        $this->couponDropped = $couponDropped;

        return $this;
    }

    public function getInvoiceAddressId(): ?int
    {
        return $this->invoiceAddressId;
    }

    public function setInvoiceAddressId(?int $invoiceAddressId): self
    {
        $this->invoiceAddressId = $invoiceAddressId;

        return $this;
    }

    public function getDeliveryAddressId(): ?int
    {
        return $this->deliveryAddressId;
    }

    public function setDeliveryAddressId(?int $deliveryAddressId): self
    {
        $this->deliveryAddressId = $deliveryAddressId;

        return $this;
    }

    public function getDeliveryModuleId(): ?int
    {
        return $this->deliveryModuleId;
    }

    public function setDeliveryModuleId(?int $deliveryModuleId): self
    {
        $this->deliveryModuleId = $deliveryModuleId;

        return $this;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function setOrderId(?int $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getOrderRef(): ?string
    {
        return $this->orderRef;
    }

    public function setOrderRef(?string $orderRef): self
    {
        $this->orderRef = $orderRef;

        return $this;
    }

    public function getTotalTaxed(): ?string
    {
        return $this->totalTaxed;
    }

    public function setTotalTaxed(?string $totalTaxed): self
    {
        $this->totalTaxed = $totalTaxed;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return list<QuoteRevision>
     */
    public function getRevisions(): array
    {
        return $this->revisions;
    }

    /**
     * @param list<QuoteRevision> $revisions
     */
    public function setRevisions(array $revisions): self
    {
        $this->revisions = $revisions;

        return $this;
    }

    /**
     * The state, the currency code and, on one quote, the revisions: what no column holds.
     */
    public function afterModelToResource(array $context): void
    {
        $model = $this->getPropelModel();

        if (!$model instanceof QuoteModel) {
            return;
        }

        $this->state = (new QuoteTransitions())->effectiveState(
            QuoteState::from((string) $model->getState()),
            $model->getValidUntil(),
            Clock::get()->now(),
        )->value;
        /** @var \Thelia\Model\Currency|null $currency Propel finds no currency for an unsaved or orphan id. */
        $currency = $model->getCurrency();
        $this->currencyCode = $currency?->getCode();
        $this->totalTaxed = $this->latestSentTotalOf($model);
        [$this->orderId, $this->orderRef] = $this->liveOrderOf($model);

        $groups = $context['groups'] ?? null;

        if (null !== $groups && [] === array_intersect((array) $groups, [self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])) {
            return;
        }

        // The draft is the shop's: the administration alone reads the revisions with the quote.
        if (null !== $groups && \in_array(self::GROUP_ADMIN_READ_SINGLE, (array) $groups, true)) {
            $this->revisions = QuoteRevision::readAllOf($model, true);

            foreach ($this->revisions as $revision) {
                if ($revision->number === (int) $model->getCurrentRevision() && QuoteRevisionState::Sent->value === $revision->state) {
                    $this->reverseCharge = $revision->reverseCharge;
                }
            }

            return;
        }

        $current = QuoteRevisionQuery::create()
            ->filterByQuoteId($model->getId())
            ->filterByNumber($model->getCurrentRevision())
            ->filterByState(QuoteRevisionState::Sent->value)
            ->findOne();
        $this->reverseCharge = null === $current || null === $current->getReverseCharge() ? null : 1 === (int) $current->getReverseCharge();
    }

    /**
     * Brought with the row by the quote query; read from the revisions when the quote was loaded
     * another way.
     */
    private function latestSentTotalOf(QuoteModel $model): ?string
    {
        if ($model->hasVirtualColumn(QuoteLatestTotalExtension::VIRTUAL_COLUMN)) {
            $total = $model->getVirtualColumn(QuoteLatestTotalExtension::VIRTUAL_COLUMN);

            return null === $total ? null : (string) $total;
        }

        $revision = QuoteRevisionQuery::create()
            ->filterByQuoteId($model->getId())
            ->filterByState(QuoteRevisionState::Sent->value)
            ->orderByNumber(Criteria::DESC)
            ->findOne();

        return $revision?->getTotalTaxed();
    }

    /**
     * Brought with the row by the quote query; read from the order table when the quote was loaded
     * another way.
     *
     * @return array{int|null, string|null}
     */
    private function liveOrderOf(QuoteModel $model): array
    {
        if ($model->hasVirtualColumn(QuoteOrderExtension::ID_COLUMN)) {
            $id = $model->getVirtualColumn(QuoteOrderExtension::ID_COLUMN);
            $ref = $model->getVirtualColumn(QuoteOrderExtension::REF_COLUMN);

            return [null === $id ? null : (int) $id, null === $ref ? null : (string) $ref];
        }

        foreach (OrderQuery::create()->filterByQuoteId($model->getId())->find() as $order) {
            if (!$order->isCancelled()) {
                return [(int) $order->getId(), (string) $order->getRef()];
            }
        }

        return [null, null];
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new QuoteTableMap();
    }
}
