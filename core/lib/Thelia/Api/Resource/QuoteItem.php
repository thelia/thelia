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
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\QuoteItemProvider;
use Thelia\Model\QuoteItem as QuoteItemModel;
use Thelia\Model\QuoteItemRequest;

/**
 * A line of a revision, as the buyer and the shop read it: what is sold, how many, the catalogue
 * price of the day it was written and the price the revision proposes, with what the buyer asked
 * for instead when they countered it.
 *
 * It is read through its quote, never on its own, and nothing of it is written from the API: the
 * prices are the shop's, the buyer's wishes go in a counter-proposal. The administration lists and reads the
 * lines of one revision, the draft included; the storefront reads them with the quote.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/quotes/{id}/revisions/{number}/items',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['quoteId']),
                'number' => new Link(fromClass: self::class, identifiers: ['revisionNumber']),
            ],
            requirements: ['id' => '\d+', 'number' => '\d+'],
            paginationEnabled: false,
            normalizationContext: ['groups' => [Quote::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'The lines of a revision',
                description: 'In the order of the revision, each with the counter-proposal of the buyer when there is one.',
                responses: ['404' => new OpenApiResponse(description: 'No such quote, or no such revision on it.')],
            ),
            provider: QuoteItemProvider::class,
        ),
        new Get(
            uriTemplate: '/admin/quotes/{id}/revisions/{number}/items/{itemId}',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['quoteId']),
                'number' => new Link(fromClass: self::class, identifiers: ['revisionNumber']),
                'itemId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['id' => '\d+', 'number' => '\d+', 'itemId' => '\d+'],
            normalizationContext: ['groups' => [Quote::GROUP_ADMIN_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'A line of a revision',
                responses: ['404' => new OpenApiResponse(description: 'No such quote, no such revision on it, or no such line on that revision.')],
            ),
            provider: QuoteItemProvider::class,
        ),
    ],
)]
final class QuoteItem
{
    /**
     * The quote and the revision the line belongs to: what the address of the line is made of, never written.
     */
    public int $quoteId = 0;

    public int $revisionNumber = 0;

    #[ApiProperty(identifier: true)]
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public int $id = 0;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public int $position = 0;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public ?int $productId = null;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public ?int $productSaleElementsId = null;

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public string $productRef = '';

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public string $productSaleElementsRef = '';

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public string $title = '';

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public float $quantity = 0.0;

    /**
     * The price of one unit without taxes in the catalogue when the line was written.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public string $catalogPrice = '0';

    /**
     * The price of one unit without taxes the revision proposes.
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public string $price = '0';

    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public ?string $taxRuleTitle = null;

    /**
     * What the buyer asked for instead of the quantity and the price of the line: a value left
     * out is one they left as it is. Null when the line was not countered.
     *
     * @var array{quantity: float|null, price: string|null}|null
     */
    #[Groups([Quote::GROUP_ADMIN_READ_SINGLE, Quote::GROUP_FRONT_READ_SINGLE])]
    public ?array $counterOffer = null;

    public static function fromModel(QuoteItemModel $model, ?QuoteItemRequest $counterOffer, int $quoteId, int $revisionNumber): self
    {
        $line = new self();
        $line->quoteId = $quoteId;
        $line->revisionNumber = $revisionNumber;
        $line->id = (int) $model->getId();
        $line->position = (int) $model->getPosition();
        $line->productId = $model->getProductId();
        $line->productSaleElementsId = $model->getProductSaleElementsId();
        $line->productRef = (string) $model->getProductRef();
        $line->productSaleElementsRef = (string) $model->getProductSaleElementsRef();
        $line->title = (string) $model->getTitle();
        $line->quantity = (float) $model->getQuantity();
        $line->catalogPrice = (string) $model->getCatalogPrice();
        $line->price = (string) $model->getPrice();
        $line->taxRuleTitle = $model->getTaxRuleTitle();
        $line->counterOffer = null === $counterOffer ? null : [
            'quantity' => null === $counterOffer->getQuantity() ? null : (float) $counterOffer->getQuantity(),
            'price' => null === $counterOffer->getPrice() ? null : (string) $counterOffer->getPrice(),
        ];

        return $line;
    }
}
