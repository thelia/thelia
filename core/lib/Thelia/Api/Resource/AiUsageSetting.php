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
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\AiUsageSettingWriteProcessor;
use Thelia\Model\Map\AiUsageSettingTableMap;

/**
 * What the merchant decided for one usage a module declared: switch, budget, limits, pinned model, guardrails,
 * transparency and prompt retention. One row per usage; a usage without a row runs on the defaults of its
 * declaration. Administration only, under the right `admin.configuration.ai`.
 *
 * `guardrails` maps the name of an optional guardrail to `block` or `annotate`.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/ai/usage-settings',
        ),
        new Get(
            uriTemplate: '/admin/ai/usage-settings/{id}',
        ),
        new Post(
            uriTemplate: '/admin/ai/usage-settings',
            processor: AiUsageSettingWriteProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/ai/usage-settings/{id}',
            processor: AiUsageSettingWriteProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/ai/usage-settings/{id}',
            processor: AiUsageSettingWriteProcessor::class,
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
        'usageId' => 'exact',
    ],
)]
class AiUsageSetting implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:ai-usage-setting:read';
    public const GROUP_ADMIN_WRITE = 'admin:ai-usage-setting:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $usageId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $enabled = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $monthlyBudget = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $currency = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $requestsPerMinute = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $requestsPerDay = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $maxOutputTokens = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $maxInputCharacters = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $pinnedConnectionId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $pinnedModelId = null;

    /**
     * @var array<string, string>|null
     */
    #[Column(propelFieldName: 'guardrailMap')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?array $guardrails = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $transparencyNotice = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $provenanceMark = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $storePrompt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $promptRetentionDays = null;

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

    public function getUsageId(): ?string
    {
        return $this->usageId;
    }

    public function setUsageId(?string $usageId): self
    {
        $this->usageId = $usageId;

        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(?bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getMonthlyBudget(): ?string
    {
        return $this->monthlyBudget;
    }

    public function setMonthlyBudget(?string $monthlyBudget): self
    {
        $this->monthlyBudget = $monthlyBudget;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getRequestsPerMinute(): ?int
    {
        return $this->requestsPerMinute;
    }

    public function setRequestsPerMinute(?int $requestsPerMinute): self
    {
        $this->requestsPerMinute = $requestsPerMinute;

        return $this;
    }

    public function getRequestsPerDay(): ?int
    {
        return $this->requestsPerDay;
    }

    public function setRequestsPerDay(?int $requestsPerDay): self
    {
        $this->requestsPerDay = $requestsPerDay;

        return $this;
    }

    public function getMaxOutputTokens(): ?int
    {
        return $this->maxOutputTokens;
    }

    public function setMaxOutputTokens(?int $maxOutputTokens): self
    {
        $this->maxOutputTokens = $maxOutputTokens;

        return $this;
    }

    public function getMaxInputCharacters(): ?int
    {
        return $this->maxInputCharacters;
    }

    public function setMaxInputCharacters(?int $maxInputCharacters): self
    {
        $this->maxInputCharacters = $maxInputCharacters;

        return $this;
    }

    public function getPinnedConnectionId(): ?int
    {
        return $this->pinnedConnectionId;
    }

    public function setPinnedConnectionId(?int $pinnedConnectionId): self
    {
        $this->pinnedConnectionId = $pinnedConnectionId;

        return $this;
    }

    public function getPinnedModelId(): ?int
    {
        return $this->pinnedModelId;
    }

    public function setPinnedModelId(?int $pinnedModelId): self
    {
        $this->pinnedModelId = $pinnedModelId;

        return $this;
    }

    public function getGuardrails(): ?array
    {
        return $this->guardrails;
    }

    public function setGuardrails(?array $guardrails): self
    {
        $this->guardrails = $guardrails;

        return $this;
    }

    public function getTransparencyNotice(): ?bool
    {
        return $this->transparencyNotice;
    }

    public function setTransparencyNotice(?bool $transparencyNotice): self
    {
        $this->transparencyNotice = $transparencyNotice;

        return $this;
    }

    public function getProvenanceMark(): ?bool
    {
        return $this->provenanceMark;
    }

    public function setProvenanceMark(?bool $provenanceMark): self
    {
        $this->provenanceMark = $provenanceMark;

        return $this;
    }

    public function getStorePrompt(): ?bool
    {
        return $this->storePrompt;
    }

    public function setStorePrompt(?bool $storePrompt): self
    {
        $this->storePrompt = $storePrompt;

        return $this;
    }

    public function getPromptRetentionDays(): ?int
    {
        return $this->promptRetentionDays;
    }

    public function setPromptRetentionDays(?int $promptRetentionDays): self
    {
        $this->promptRetentionDays = $promptRetentionDays;

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
        return new AiUsageSettingTableMap();
    }
}
