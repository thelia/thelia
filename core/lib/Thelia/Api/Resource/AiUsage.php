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
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\AiUsageRegisterProvider;

/**
 * One usage a module declared, with where it stands: active or not, on which jurisdiction it runs, and its
 * AI Act duties in plain language. Read only: it is the register of the shop. The compliance messages come in
 * the language of the optional `locale` query parameter. Administration only, under the right
 * `admin.configuration.ai`.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/ai/usages',
            paginationEnabled: false,
            provider: AiUsageRegisterProvider::class,
        ),
        new Get(
            uriTemplate: '/admin/ai/usages/{id}',
            requirements: ['id' => '[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*'],
            provider: AiUsageRegisterProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
final class AiUsage
{
    public const GROUP_ADMIN_READ = 'admin:ai-usage:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public string $id = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $module = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $purpose = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $audience = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $tier = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $transparency = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public bool $active = false;

    #[Groups([self::GROUP_ADMIN_READ])]
    public bool $noticeEnabled = true;

    #[Groups([self::GROUP_ADMIN_READ])]
    public bool $provenanceMarkEnabled = true;

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $compliance = '';

    /**
     * @var list<array{obligation: string, state: string, reason: string|null, message: string}>
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public array $complianceDetails = [];

    /**
     * `eu` or `non_eu`, null when no connection serves the usage now.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $jurisdiction = null;
}
