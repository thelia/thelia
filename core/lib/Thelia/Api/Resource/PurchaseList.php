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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Api\State\Processor\PurchaseListProcessor;
use Thelia\Api\State\Provider\PurchaseListProvider;

/**
 * The purchase lists of an account: lines of references and quantities a buyer
 * keeps to order again.
 *
 * The resource is served by the purchase list facade and by nothing else: no Propel
 * provider reads the table, so the generic filter that bounds an account collection
 * to its owner never applies, and who sees or changes a list is decided in one place
 * (PurchaseListAccessPolicy), the one company sharing will change. A list the
 * account may not see answers 404, whatever the operation, like an id that was never
 * issued; a list it sees but may not change answers 403.
 *
 * Loading a list into a control table answers the way ordering by reference does, and
 * counts against the same rate limit: it reads prices and stock for up to five hundred
 * lines too.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/purchase-lists',
            paginationEnabled: false,
            openapi: new OpenApiOperation(
                summary: 'The purchase lists the account can see',
                description: 'Every list the account can see, most recently changed first, without their lines. An account keeps at most 100 lists.',
            ),
        ),
        new Get(
            uriTemplate: '/front/account/purchase-lists/{id}',
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            openapi: new OpenApiOperation(
                summary: 'A purchase list and its lines',
                responses: [
                    '404' => new OpenApiResponse(description: 'No such list: the same answer for a list of another account.'),
                ],
            ),
        ),
        new Get(
            name: self::OPERATION_TABLE,
            uriTemplate: '/front/account/purchase-lists/{id}/table',
            requirements: ['id' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Load a purchase list into a control table',
                description: 'Resolves the lines of the list the way `POST /front/account/quick-order/resolve` does, and answers in the same format: nothing is written, and the cart is left alone. A line whose sale element was deleted since it was saved is resolved again on its reference, and comes back `unknown` when nothing carries it any more. Prices are unit prices in the default currency. Counts against the quick order rate limit.',
                responses: [
                    '200' => new OpenApiResponse(description: 'One line per line of the list, in the order of the list. The format of `POST /front/account/quick-order/resolve`.'),
                    '404' => new OpenApiResponse(description: 'No such list: the same answer for a list of another account.'),
                    '429' => new OpenApiResponse(description: 'Too many quick order requests from this account in the last minute.'),
                ],
            ),
        ),
        new Post(
            name: self::OPERATION_CREATE,
            uriTemplate: '/front/account/purchase-lists',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            validationContext: ['groups' => [self::VALIDATION_CREATE]],
            input: PurchaseListInput::class,
            read: false,
            openapi: new OpenApiOperation(
                summary: 'Create a purchase list',
                description: 'A list with a title and, optionally, its first lines, at most 500.',
                responses: [
                    '422' => new OpenApiResponse(description: 'No title, a line the shop cannot take, or the account already keeps 100 lists.'),
                ],
            ),
        ),
        new Post(
            name: self::OPERATION_FROM_CART,
            uriTemplate: '/front/account/purchase-lists/from-cart/{cartId}',
            uriVariables: ['cartId' => new Link(fromClass: Cart::class, identifiers: ['id'])],
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            validationContext: ['groups' => [self::VALIDATION_FROM_SOURCE]],
            input: PurchaseListInput::class,
            read: false,
            openapi: new OpenApiOperation(
                summary: 'Save a cart as a purchase list',
                description: 'A new list holding the lines of one of the account\'s carts, each on the reference of its sale element. The cart is left alone.',
                responses: [
                    '404' => new OpenApiResponse(description: 'No such cart: the same answer for a cart of another account.'),
                    '422' => new OpenApiResponse(description: 'No title, or the account already keeps 100 lists.'),
                ],
            ),
        ),
        new Post(
            name: self::OPERATION_FROM_ORDER,
            uriTemplate: '/front/account/purchase-lists/from-order/{orderId}',
            uriVariables: ['orderId' => new Link(fromClass: Order::class, identifiers: ['id'])],
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            validationContext: ['groups' => [self::VALIDATION_FROM_SOURCE]],
            input: PurchaseListInput::class,
            read: false,
            openapi: new OpenApiOperation(
                summary: 'Save a past order as a purchase list',
                description: 'A new list holding the lines of one of the account\'s orders, on the references the order kept. A line whose sale element was deleted since keeps its reference.',
                responses: [
                    '404' => new OpenApiResponse(description: 'No such order: the same answer for an order of another account.'),
                    '422' => new OpenApiResponse(description: 'No title, or the account already keeps 100 lists.'),
                ],
            ),
        ),
        new Patch(
            name: self::OPERATION_RENAME,
            uriTemplate: '/front/account/purchase-lists/{id}',
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            validationContext: ['groups' => [self::VALIDATION_RENAME]],
            input: PurchaseListInput::class,
            read: false,
            openapi: new OpenApiOperation(
                summary: 'Rename a purchase list',
                responses: [
                    '403' => new OpenApiResponse(description: 'The account sees the list but may not change it.'),
                    '404' => new OpenApiResponse(description: 'No such list: the same answer for a list of another account.'),
                    '422' => new OpenApiResponse(description: 'No title, or a title longer than 255 characters.'),
                ],
            ),
        ),
        new Delete(
            name: self::OPERATION_DELETE,
            uriTemplate: '/front/account/purchase-lists/{id}',
            requirements: ['id' => '\d+'],
            read: false,
            openapi: new OpenApiOperation(
                summary: 'Delete a purchase list',
                responses: [
                    '403' => new OpenApiResponse(description: 'The account sees the list but may not change it.'),
                    '404' => new OpenApiResponse(description: 'No such list: the same answer for a list of another account.'),
                ],
            ),
        ),
        new Post(
            name: self::OPERATION_DUPLICATE,
            uriTemplate: '/front/account/purchase-lists/{id}/duplicate',
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            validationContext: ['groups' => [self::VALIDATION_DUPLICATE]],
            input: PurchaseListInput::class,
            read: false,
            openapi: new OpenApiOperation(
                summary: 'Copy a purchase list',
                description: 'A copy owned by the account, of any list it can see. The body is required: send `{}` for a copy that keeps the title of the list.',
                responses: [
                    '404' => new OpenApiResponse(description: 'No such list: the same answer for a list of another account.'),
                    '422' => new OpenApiResponse(description: 'The account already keeps 100 lists.'),
                ],
            ),
        ),
        new Post(
            name: self::OPERATION_APPEND_ITEMS,
            uriTemplate: '/front/account/purchase-lists/{id}/items',
            requirements: ['id' => '\d+'],
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_ITEMS_WRITE]],
            input: QuickOrderInput::class,
            read: false,
            openapi: new OpenApiOperation(
                summary: 'Add lines to a purchase list',
                description: 'The body of `POST /front/account/quick-order/resolve`. The lines go after the current ones; a reference already on the list, for the same sale element, adds its quantity to that line. A list holds at most 500 lines.',
                responses: [
                    '403' => new OpenApiResponse(description: 'The account sees the list but may not change it.'),
                    '404' => new OpenApiResponse(description: 'No such list: the same answer for a list of another account.'),
                    '422' => new OpenApiResponse(description: 'A line the shop cannot take, or more than 500 lines once added.'),
                ],
            ),
        ),
        new Put(
            name: self::OPERATION_REPLACE_ITEMS,
            uriTemplate: '/front/account/purchase-lists/{id}/items',
            requirements: ['id' => '\d+'],
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_ITEMS_WRITE]],
            input: QuickOrderInput::class,
            read: false,
            openapi: new OpenApiOperation(
                summary: 'Replace the lines of a purchase list',
                description: 'The body of `POST /front/account/quick-order/resolve`: the lines of the list become these, in this order.',
                responses: [
                    '403' => new OpenApiResponse(description: 'The account sees the list but may not change it.'),
                    '404' => new OpenApiResponse(description: 'No such list: the same answer for a list of another account.'),
                    '422' => new OpenApiResponse(description: 'A line the shop cannot take, or more than 500 lines.'),
                ],
            ),
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    security: self::FRONT_SECURITY,
    provider: PurchaseListProvider::class,
    processor: PurchaseListProcessor::class,
)]
final class PurchaseList
{
    public const GROUP_FRONT_READ = 'front:purchase_list:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:purchase_list:read:single';
    public const GROUP_FRONT_WRITE = 'front:purchase_list:write';
    public const GROUP_FRONT_ITEMS_WRITE = 'front:purchase_list:items:write';

    public const OPERATION_TABLE = 'front_purchase_list_table';
    public const OPERATION_CREATE = 'front_purchase_list_create';
    public const OPERATION_FROM_CART = 'front_purchase_list_from_cart';
    public const OPERATION_FROM_ORDER = 'front_purchase_list_from_order';
    public const OPERATION_RENAME = 'front_purchase_list_rename';
    public const OPERATION_DELETE = 'front_purchase_list_delete';
    public const OPERATION_DUPLICATE = 'front_purchase_list_duplicate';
    public const OPERATION_APPEND_ITEMS = 'front_purchase_list_append_items';
    public const OPERATION_REPLACE_ITEMS = 'front_purchase_list_replace_items';

    public const VALIDATION_CREATE = 'purchase_list:create';
    public const VALIDATION_FROM_SOURCE = 'purchase_list:from_source';
    public const VALIDATION_RENAME = 'purchase_list:rename';
    public const VALIDATION_DUPLICATE = 'purchase_list:duplicate';

    /**
     * Stated on every operation although `^/api/front/account` already demands it: a
     * prefix is one refactor away from no longer covering the route.
     */
    public const FRONT_SECURITY = 'is_granted("ROLE_CUSTOMER")';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_FRONT_READ])]
    public string $title = '';

    #[ApiProperty(description: 'Whether the list is shared with the company of its owner. Always false until companies exist.')]
    #[Groups([self::GROUP_FRONT_READ])]
    public bool $shared = false;

    #[ApiProperty(description: 'Whether the account may rename the list, change its lines and delete it.')]
    #[Groups([self::GROUP_FRONT_READ])]
    public bool $canWrite = false;

    #[Groups([self::GROUP_FRONT_READ])]
    public int $itemCount = 0;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?\DateTimeInterface $createdAt = null;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?\DateTimeInterface $updatedAt = null;

    /**
     * Written out as arrays rather than objects: the serialization groups of this
     * resource would not reach a nested class, which would come out empty.
     *
     * @var list<array{reference: string, quantity: int, productSaleElementsId: int|null}>
     */
    #[ApiProperty(
        description: 'The lines of the list, in order. `productSaleElementsId` is the sale element chosen for a reference several of them share, or null.',
        example: [['reference' => 'TSHIRT-01', 'quantity' => 3, 'productSaleElementsId' => 412]],
    )]
    #[Groups([self::GROUP_FRONT_READ_SINGLE])]
    public array $items = [];
}
