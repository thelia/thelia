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
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\OrderPaymentSummaryProvider;

/**
 * What the payment journal of an order adds up to, and whether its module can still
 * take money by hand: the figures a back office shows above the journal.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/orders/{orderId}/payment',
            uriVariables: ['orderId'],
            provider: OrderPaymentSummaryProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
final class OrderPaymentSummary
{
    public const GROUP_ADMIN_READ = 'admin:order_payment_summary:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $orderId = null;

    #[ApiProperty(description: 'The reference of the main payment, as carried by the order.')]
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $transactionRef = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $paymentModuleId = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $paymentModuleCode = null;

    #[ApiProperty(description: 'Sum of the succeeded authorizations.', example: '120.000000')]
    #[Groups([self::GROUP_ADMIN_READ])]
    public string $authorized = '0.000000';

    #[ApiProperty(description: 'Sum of the succeeded captures.', example: '50.000000')]
    #[Groups([self::GROUP_ADMIN_READ])]
    public string $captured = '0.000000';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $voided = '0.000000';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $refunded = '0.000000';

    #[ApiProperty(description: 'Sum of the captures waiting for the provider\'s answer: not captured yet, but out of reach of another capture.', example: '0.000000')]
    #[Groups([self::GROUP_ADMIN_READ])]
    public string $pendingCapture = '0.000000';

    #[ApiProperty(description: 'What the authorization still holds once the captures waiting for their answer are set aside; zero when the module took the price at once.', example: '70.000000')]
    #[Groups([self::GROUP_ADMIN_READ])]
    public string $remainingToCapture = '0.000000';

    #[ApiProperty(description: 'Whether the payment module separates the authorization from the capture, so that a capture can be asked for.')]
    #[Groups([self::GROUP_ADMIN_READ])]
    public bool $supportsCapture = false;
}
