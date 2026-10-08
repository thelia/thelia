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
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Positive;
use Thelia\Api\State\Processor\OrderPaymentCaptureProcessor;

/**
 * Taking all or part of what an order's authorization still holds.
 *
 * A resource of its own, mapped to the `admin.order.payment-capture` right rather than
 * to the order one: reading the journal goes with reading orders, taking money does
 * not. The answer is the journal line the capture wrote, settled with the provider's
 * outcome.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/orders/{orderId}/capture',
            uriVariables: ['orderId'],
            status: 201,
            openapi: new OpenApiOperation(
                summary: 'Capture all or part of the authorized payment of an order',
                description: 'Asks the payment module of the order to take the given amount, or everything the authorization still holds when no amount is given, and returns the journal line it wrote. The amount is checked against the journal before the provider is called.',
                responses: [
                    '201' => new OpenApiResponse(description: 'The journal line of the capture, succeeded, failed with the provider\'s code and message, or pending when the provider answers later.'),
                    '403' => new OpenApiResponse(description: 'The administrator does not hold the payment capture right.'),
                    '404' => new OpenApiResponse(description: 'No such order.'),
                    '409' => new OpenApiResponse(description: 'The same amount was captured on this order a moment ago: a repeated call, not a second capture.'),
                    '422' => new OpenApiResponse(description: 'The module takes the price at once, nothing is left to capture, or the amount is not positive or exceeds what the authorization holds.'),
                ],
            ),
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
            validationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
            normalizationContext: ['groups' => [OrderPaymentTransaction::GROUP_ADMIN_READ]],
            output: OrderPaymentTransaction::class,
            read: false,
            processor: OrderPaymentCaptureProcessor::class,
        ),
    ],
)]
final class OrderPaymentCapture
{
    public const GROUP_ADMIN_WRITE = 'admin:order_payment_capture:write';

    #[ApiProperty(
        description: 'What to take, in the currency of the order. Omitted, everything the authorization still holds.',
        example: 50.0,
    )]
    #[Positive(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_WRITE])]
    public ?float $amount = null;
}
