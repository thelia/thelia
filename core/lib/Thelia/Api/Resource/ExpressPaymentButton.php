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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\ExpressPaymentButtonProvider;

/**
 * The wallet buttons a shop offers for the cart in hand, in one place of the shop.
 *
 * Anonymous, like the list of payment methods next door, and for the same reason: the
 * cart is the one the request carries, never one named in a parameter, so a visitor only
 * ever reads their own. Everything here is meant to be rendered into a page —
 * `attributes` included, which is why a module never puts a secret in it.
 *
 * `amountUrl` is where the module asks what the checkout comes to, `confirmationUrl` where the
 * wallet's answer is posted, and `confirmationToken` the proof that goes with both. The token is bound to the cart this request carries, so
 * handing it to the page that shows the button gives nothing to anyone else.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/payment/express-buttons',
            openapi: new Operation(
                parameters: [
                    new Parameter(name: 'zone', in: 'query', required: false, schema: ['type' => 'string', 'enum' => ['checkout']]),
                ],
            ),
            provider: ExpressPaymentButtonProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
class ExpressPaymentButton
{
    public const GROUP_FRONT_READ = 'front:express_payment_button:read';

    #[Groups([self::GROUP_FRONT_READ])]
    public int $paymentModuleId;

    #[Groups([self::GROUP_FRONT_READ])]
    public string $paymentModuleCode;

    #[Groups([self::GROUP_FRONT_READ])]
    public string $code;

    #[Groups([self::GROUP_FRONT_READ])]
    public string $label;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $logo = null;

    /** @var array<string, scalar> */
    #[Groups([self::GROUP_FRONT_READ])]
    public array $attributes = [];

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $confirmationUrl = null;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $confirmationToken = null;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $amountUrl = null;
}
