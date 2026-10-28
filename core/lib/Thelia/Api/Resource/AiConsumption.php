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
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\AiConsumptionProvider;

/**
 * What a usage consumed in a calendar month, summed from the AI log: calls, failures, tokens and cost. Amounts
 * are decimal strings in US dollars, as the log keeps them, with no conversion. Read only. Query parameters:
 * `month` (YYYY-MM), `from` and `to` (YYYY-MM, both included), `usageId`, `module`. Administration only,
 * under the right `admin.configuration.ai`.
 *
 * Calls count the model calls (completions and streams); a cost declared for a third-party API is in `cost`
 * and in `externalCalls`.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/ai/consumption',
            paginationEnabled: false,
            provider: AiConsumptionProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
final class AiConsumption
{
    public const GROUP_ADMIN_READ = 'admin:ai-consumption:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public string $id = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $month = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $usageId = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $module = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public int $calls = 0;

    #[Groups([self::GROUP_ADMIN_READ])]
    public int $failures = 0;

    #[Groups([self::GROUP_ADMIN_READ])]
    public int $externalCalls = 0;

    #[Groups([self::GROUP_ADMIN_READ])]
    public int $inputTokens = 0;

    #[Groups([self::GROUP_ADMIN_READ])]
    public int $outputTokens = 0;

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $cost = '0.00000000';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $currency = 'USD';
}
