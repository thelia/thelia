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
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody as OpenApiRequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use Thelia\Api\State\Processor\CheckoutPlacementProcessor;
use Thelia\Api\State\Processor\CheckoutSelectionProcessor;
use Thelia\Api\State\Provider\CheckoutValidationProvider;

/**
 * The order tunnel of an account, without a browser and without a session.
 *
 * Six operations, all of them naming the cart they act on: the four choices a buyer
 * makes, the verdict on what is left to settle, and the placement itself. They are
 * deliberately not writes on the cart resource — the postage, the discount and the
 * totals are the shop's to compute, and a payload able to name them is a payload able to
 * choose them.
 *
 * Two barriers guard every one of them. The firewall answers first: `/api/front/account`
 * is `ROLE_CUSTOMER`, so an anonymous caller never reaches the code below, and each
 * operation states the same rule again rather than relying on a path prefix staying what
 * it is today. The second barrier is ownership, and it lives in the processors and the
 * provider: with `read: false` there is no object for an expression to read, and the
 * answer to a cart that is not the caller's has to be the answer to a cart that does not
 * exist — a different one would turn the endpoints into a way of counting the carts of
 * the shop.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/account/checkout/{cartId}/delivery_address',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Choose the address the cart is shipped to',
                description: 'Selects one address of the authenticated account as the delivery address of the cart, and returns the cart with its postage requoted. An address the account does not own is answered exactly as an address that does not exist.',
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'The choice was taken: the cart it shipped to, with the amounts the shop computed.',
                        content: new \ArrayObject(['application/json' => ['example' => self::CART_EXAMPLE]]),
                    ),
                    '404' => new OpenApiResponse(description: 'No such cart, or no such address — the same answer for a cart or an address of another account.'),
                    '422' => new OpenApiResponse(description: 'The identifier names nothing the checkout can take: no such activated module, or none given.'),
                ],
            ),
            normalizationContext: ['groups' => [Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            security: self::FRONT_SECURITY,
            input: CheckoutDeliveryAddressInput::class,
            output: Cart::class,
            read: false,
            processor: CheckoutSelectionProcessor::class,
        ),
        new Post(
            uriTemplate: '/front/account/checkout/{cartId}/invoice_address',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Choose the address the order is billed to',
                description: 'Selects one address of the authenticated account as the invoice address of the cart, and returns the cart.',
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'The choice was taken: the cart it is billed to, with the amounts the shop computed.',
                        content: new \ArrayObject(['application/json' => ['example' => self::CART_EXAMPLE]]),
                    ),
                    '404' => new OpenApiResponse(description: 'No such cart, or no such address — the same answer for a cart or an address of another account.'),
                    '422' => new OpenApiResponse(description: 'The identifier names nothing the checkout can take: no such activated module, or none given.'),
                ],
            ),
            normalizationContext: ['groups' => [Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            security: self::FRONT_SECURITY,
            input: CheckoutInvoiceAddressInput::class,
            output: Cart::class,
            read: false,
            processor: CheckoutSelectionProcessor::class,
        ),
        new Post(
            uriTemplate: '/front/account/checkout/{cartId}/delivery_module',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Choose who carries the order',
                description: 'Selects a delivery module on the cart and returns the cart with the postage that module quoted for the delivery address.',
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'The choice was taken: the cart and the postage the carrier quoted, with the amounts the shop computed.',
                        content: new \ArrayObject(['application/json' => ['example' => self::CART_EXAMPLE]]),
                    ),
                    '404' => new OpenApiResponse(description: 'No such cart, or no such address — the same answer for a cart or an address of another account.'),
                    '422' => new OpenApiResponse(description: 'The identifier names nothing the checkout can take: no such activated module, or none given.'),
                ],
            ),
            normalizationContext: ['groups' => [Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            security: self::FRONT_SECURITY,
            input: CheckoutDeliveryModuleInput::class,
            output: Cart::class,
            read: false,
            processor: CheckoutSelectionProcessor::class,
        ),
        new Post(
            uriTemplate: '/front/account/checkout/{cartId}/payment_module',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Choose how the order is paid',
                description: 'Selects a payment module on the cart and returns the cart.',
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'The choice was taken: the cart it is paid through, with the amounts the shop computed.',
                        content: new \ArrayObject(['application/json' => ['example' => self::CART_EXAMPLE]]),
                    ),
                    '404' => new OpenApiResponse(description: 'No such cart, or no such address — the same answer for a cart or an address of another account.'),
                    '422' => new OpenApiResponse(description: 'The identifier names nothing the checkout can take: no such activated module, or none given.'),
                ],
            ),
            normalizationContext: ['groups' => [Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            security: self::FRONT_SECURITY,
            input: CheckoutPaymentModuleInput::class,
            output: Cart::class,
            read: false,
            processor: CheckoutSelectionProcessor::class,
        ),
        new Get(
            uriTemplate: '/front/account/checkout/{cartId}/validation',
            openapi: new OpenApiOperation(
                summary: 'Ask whether the cart may be ordered',
                description: <<<'DESCRIPTION'
                    Answers `{"ready": true, "violations": []}` for a cart that may be ordered, and otherwise every refusal at once, in the order of the checkout tunnel. Each refusal carries a stable machine code next to the sentence the buyer reads. Reading this changes nothing: unlike the placement, it does not settle the carrier of a cart with nothing to ship.

                    **It takes no body, so it never sees the consents.** A shop asking for a mandatory consent — which is how a shop is installed — is therefore reported here as `consent-missing` right up to the placement that carries the answer, and that answer is what settles it. A client rendering a checkout treats `consent-missing` as the list of boxes to show, not as a refusal to correct before posting.
                    DESCRIPTION,
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'The verdict on the cart.',
                        content: new \ArrayObject(['application/json' => ['example' => self::VALIDATION_EXAMPLE]]),
                    ),
                    '404' => new OpenApiResponse(description: 'No such cart — the same answer for a cart of another account.'),
                ],
            ),
            security: self::FRONT_SECURITY,
            provider: CheckoutValidationProvider::class,
        ),
        new Post(
            uriTemplate: '/front/account/checkout/{cartId}/place',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Place the order',
                requestBody: new OpenApiRequestBody(
                    description: 'Optional. The answers the buyer gave to the consents the shop asks for, which is the one thing the operations above have nowhere to put: until the order exists there is nothing to attach an answer to, and the API keeps no session. Leave the body out entirely on a shop that asks for no consent.',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => self::CONSENTS_SCHEMA,
                            'example' => self::CONSENTS_EXAMPLE,
                        ],
                    ]),
                ),
                description: <<<'DESCRIPTION'
                    Turns the cart into an order. Everything the order is built from was settled by the operations above, except one thing: the consents.

                    **The consents are answered here, in the body, and nowhere else.** `GET .../validation` lists the ones the shop is asking for as `consent-missing` violations, each carrying the `consentCode` in its `details`; this request answers them all at once with `{"consents": [{"code": "terms_and_conditions", "accepted": true}]}`. A mandatory consent left out of the body, or answered `false`, refuses the placement with a 422 naming it — there is no way to place an order without agreeing to it. An optional one is recorded exactly as given, refusal included, and a consent the shop is not asking for is refused with `consent-unknown` rather than ignored. What is recorded is frozen onto the order: the wording the answer was given under, the moment it was given, and the address it came from.

                    The body is optional and a placement posted without one is unchanged from previous versions.

                    A cart that already has an order gets that order back with `alreadyPlaced: true`, so a retried request never charges the buyer twice. **On `alreadyPlaced: true` the payment is not raised again and `paymentAction` is always `{"type": "none"}`**, whatever the first call answered: calling a payment module a second time is how a buyer ends up with two authorisations, and the shop has no way to replay the redirection the first call produced. What the order is waiting for is in `paid` and `orderStatusCode`, both read back off the order row; the rest is at `GET /front/account/orders/{id}`. A front that loses the answer to a placement therefore reads the order rather than expecting the payment step again — and a buyer who never reached the gateway has to be sent back through it by the shop, not by this endpoint.

                    `paymentAction.html` is **raw HTML produced by the payment module** — typically the self-posting form a gateway requires — passed on exactly as the module wrote it, unescaped and unrewritten. Render it only when `paymentAction.type` is `form`, and only on the page that hands the buyer over to the gateway: it is third-party markup, and injecting it anywhere else in the front is running a third party's script on that screen.
                    DESCRIPTION,
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'The order, and what is left for the front to do. On a retry (`alreadyPlaced: true`), what is left is to read the state of the order: no payment action is produced a second time.',
                        content: new \ArrayObject(['application/json' => ['example' => self::PLACEMENT_EXAMPLE]]),
                    ),
                    '403' => new OpenApiResponse(description: 'The shop no longer allows this cart to be ordered without an account.'),
                    '404' => new OpenApiResponse(description: 'No such cart — the same answer for a cart of another account.'),
                    '409' => new OpenApiResponse(description: 'Another request is placing this very cart, or the shop no longer holds what the cart asks for — the message names the product. Both are worth retrying; nothing else that goes wrong while placing is answered with a 409.'),
                    '422' => new OpenApiResponse(
                        description: 'The cart still has something to settle, a mandatory consent included: the body is the one GET .../validation answers with. A body that does not say what a consent answer says — a missing `code`, an `accepted` that is not a boolean, the same consent answered twice — is refused with the standard error payload instead.',
                        content: new \ArrayObject(['application/json' => ['example' => self::VALIDATION_EXAMPLE]]),
                    ),
                ],
            ),
            security: self::FRONT_SECURITY,
            input: false,
            read: false,
            processor: CheckoutPlacementProcessor::class,
        ),
    ],
)]
final class Checkout
{
    public const GROUP_FRONT_WRITE = 'front:checkout:write';

    /**
     * The two payloads the checkout answers with that are not a cart. They are written
     * out by the provider and the processor rather than serialized from a resource — the
     * two keys are a published contract, and a refused placement answers with the very
     * same body a verdict does — so this is what documents them.
     */
    private const VALIDATION_EXAMPLE = [
        'ready' => false,
        'violations' => [
            [
                'stepCode' => 'delivery',
                'code' => 'delivery-invalid',
                'message' => 'Please select a delivery method.',
                'details' => [],
            ],
        ],
    ];

    /**
     * What the four selection operations hand back: the cart as the shop now sees it.
     * Cut down to the fields a buyer reads a price off — the schema next to it has the
     * rest — because the point of the example is that the amounts come back computed and
     * are not something a payload could have named.
     */
    private const CART_EXAMPLE = [
        'id' => 886,
        'postage' => 7.5,
        'postageTax' => 1.5,
        'discount' => 0,
        'totalWithoutTax' => 100.0,
        'taxes' => 20.0,
        'total' => 127.5,
        'virtual' => false,
    ];

    /**
     * The only thing a placement takes, written out here because the operation declares
     * no input: the body is optional, and a schema generated from a class would make it
     * the shape a placement has to be posted with.
     */
    private const CONSENTS_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'consents' => [
                'type' => 'array',
                'description' => 'One entry per consent the shop is asking for, as GET .../validation reports them. A consent left out is a consent refused.',
                'items' => [
                    'type' => 'object',
                    'required' => ['code', 'accepted'],
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'The code of a consent the shop is asking for, as carried by the `consentCode` of a `consent-missing` violation.'],
                        'accepted' => ['type' => 'boolean', 'description' => 'What the buyer answered. A refusal is recorded on the order as such.'],
                    ],
                ],
            ],
        ],
    ];

    private const CONSENTS_EXAMPLE = [
        'consents' => [
            ['code' => 'terms_and_conditions', 'accepted' => true],
            ['code' => 'newsletter', 'accepted' => false],
        ],
    ];

    private const PLACEMENT_EXAMPLE = [
        'orderId' => 42,
        'orderReference' => 'ORD123456',
        'orderStatusCode' => 'not_paid',
        'paid' => false,
        'alreadyPlaced' => false,
        'paymentAction' => ['type' => 'none', 'url' => null, 'html' => null],
    ];

    /**
     * Stated on every operation although `^/api/front/account` already demands it: the
     * rule a route is protected by must be readable on the route itself, and a prefix is
     * one refactor away from no longer covering it.
     */
    public const FRONT_SECURITY = 'is_granted("ROLE_CUSTOMER")';

    #[ApiProperty(identifier: true, description: 'Identifier of the cart being checked out.')]
    public ?int $cartId = null;
}
