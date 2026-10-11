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
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use Thelia\Api\State\Processor\QuickOrderProcessor;

/**
 * Ordering by reference, for an account.
 *
 * Two operations with the same body and the same answer. The first resolves the lines
 * and writes nothing: it is what a front shows as a control table before anything
 * reaches the cart. The second resolves them again, never trusting the table the front
 * holds, and adds the resolved lines to one of the account's carts in one transaction.
 *
 * Both demand an account and share one rate limit per account: each answers with
 * titles, prices and stock levels, and without a cap a signed-in caller could read the
 * catalog through them. A product the account may not see answers as an unknown
 * reference, never as a refusal that would tell it exists.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/account/quick-order/resolve',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Resolve references into a control table',
                description: 'Resolves up to 500 references (sale element references, EAN codes, or product references) with their quantities. Nothing is written. A reference several sale elements share comes back `ambiguous` with its candidates, the default one preselected when they all belong to one product: post it again with the chosen `productSaleElementsId`. Prices are unit prices in the default currency, the account discount included.',
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'One line per reference, in the order they were posted.',
                        content: new \ArrayObject(['application/json' => ['example' => self::TABLE_EXAMPLE]]),
                    ),
                    '422' => new OpenApiResponse(description: 'A line the shop cannot take: no reference, a quantity below one, more than 500 lines.'),
                    '429' => new OpenApiResponse(description: 'Too many quick order requests from this account in the last minute.'),
                ],
            ),
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            security: self::FRONT_SECURITY,
            input: QuickOrderInput::class,
            read: false,
            processor: QuickOrderProcessor::class,
        ),
        new Post(
            uriTemplate: '/front/account/quick-order/{cartId}/add',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Add the resolved references to a cart',
                description: 'Resolves the lines again and adds the `resolved` ones to the cart, in one transaction: either all of them are in the cart, or none is. A reference already in the cart adds to its quantity. The other lines come back with their status and `added: false`. Prices are in the currency of the cart.',
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'The table as resolved when adding, each line saying whether it reached the cart.',
                        content: new \ArrayObject(['application/json' => ['example' => self::TABLE_EXAMPLE]]),
                    ),
                    '404' => new OpenApiResponse(description: 'No such cart: the same answer for a cart of another account.'),
                    '422' => new OpenApiResponse(description: 'A line the shop cannot take: no reference, a quantity below one, more than 500 lines.'),
                    '429' => new OpenApiResponse(description: 'Too many quick order requests from this account in the last minute.'),
                ],
            ),
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            security: self::FRONT_SECURITY,
            input: QuickOrderInput::class,
            read: false,
            processor: QuickOrderProcessor::class,
        ),
    ],
)]
final class QuickOrder
{
    public const GROUP_FRONT_WRITE = 'front:quick_order:write';

    /**
     * Stated on every operation although `^/api/front/account` already demands it: a
     * prefix is one refactor away from no longer covering the route.
     */
    public const FRONT_SECURITY = 'is_granted("ROLE_CUSTOMER")';

    /**
     * What both operations answer with. It is written out by the processor rather than
     * serialized from a resource, so this is what documents it.
     */
    private const TABLE_EXAMPLE = [
        'lines' => [
            [
                'reference' => 'TSHIRT-01',
                'quantity' => 3,
                'status' => 'ambiguous',
                'productSaleElementsId' => null,
                'productId' => null,
                'title' => null,
                'untaxedUnitPrice' => null,
                'taxedUnitPrice' => null,
                'promo' => false,
                'availableQuantity' => null,
                'candidates' => [
                    ['productSaleElementsId' => 411, 'productId' => 57, 'ref' => 'TSHIRT-01', 'isDefault' => true, 'preselected' => true, 'attributes' => [['attribute' => 'Size', 'value' => 'S']]],
                    ['productSaleElementsId' => 412, 'productId' => 57, 'ref' => 'TSHIRT-01', 'isDefault' => false, 'preselected' => false, 'attributes' => [['attribute' => 'Size', 'value' => 'M']]],
                ],
                'added' => false,
            ],
            [
                'reference' => 'TSHIRT-01',
                'quantity' => 2,
                'status' => 'resolved',
                'productSaleElementsId' => 412,
                'productId' => 57,
                'title' => 'Cotton T-shirt',
                'untaxedUnitPrice' => 12.5,
                'taxedUnitPrice' => 15.0,
                'promo' => false,
                'availableQuantity' => null,
                'candidates' => [],
                'added' => true,
            ],
            [
                'reference' => '3760123450012',
                'quantity' => 1,
                'status' => 'quantity_refused',
                'productSaleElementsId' => 88,
                'productId' => 21,
                'title' => 'Nitrile gloves',
                'untaxedUnitPrice' => null,
                'taxedUnitPrice' => null,
                'promo' => false,
                'availableQuantity' => 0.0,
                'candidates' => [],
                'added' => false,
            ],
        ],
        'summary' => ['resolved' => 1, 'ambiguous' => 1, 'unknown' => 0, 'unavailable' => 0, 'quantity_refused' => 1],
    ];

    #[ApiProperty(identifier: true, description: 'Identifier of the cart the lines are added to.')]
    public ?int $cartId = null;
}
