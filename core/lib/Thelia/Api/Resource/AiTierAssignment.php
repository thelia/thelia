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
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\AiTierAssignmentWriteProcessor;
use Thelia\Model\Map\AiTierAssignmentTableMap;

/**
 * The connection and the model that serve a tier (fast, standard, advanced). One row per tier. Administration only,
 * under the right `admin.configuration.ai`.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/ai/tiers',
        ),
        new Get(
            uriTemplate: '/admin/ai/tiers/{id}',
        ),
        new Post(
            uriTemplate: '/admin/ai/tiers',
            processor: AiTierAssignmentWriteProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/ai/tiers/{id}',
            processor: AiTierAssignmentWriteProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/ai/tiers/{id}',
            processor: AiTierAssignmentWriteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
    paginationItemsPerPage: 50,
    paginationMaximumItemsPerPage: 200,
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'tier' => 'exact',
    ],
)]
class AiTierAssignment implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:ai-tier-assignment:read';
    public const GROUP_ADMIN_WRITE = 'admin:ai-tier-assignment:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $tier = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $connectionId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $modelId = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTier(): ?string
    {
        return $this->tier;
    }

    public function setTier(?string $tier): self
    {
        $this->tier = $tier;

        return $this;
    }

    public function getConnectionId(): ?int
    {
        return $this->connectionId;
    }

    public function setConnectionId(?int $connectionId): self
    {
        $this->connectionId = $connectionId;

        return $this;
    }

    public function getModelId(): ?int
    {
        return $this->modelId;
    }

    public function setModelId(?int $modelId): self
    {
        $this->modelId = $modelId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new AiTierAssignmentTableMap();
    }
}
