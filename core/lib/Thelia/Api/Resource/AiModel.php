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
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\AiModelWriteProcessor;
use Thelia\Model\Map\AiModelTableMap;

/**
 * A model a connection can call, with its prices (decimal strings, per million tokens, in `currency`). A row with
 * no `connectionId` completes the catalogue of its provider; one with a `connectionId` belongs to that connection
 * (self-hosted or compatible endpoint). A row written here is `manual`. Administration only, under the right
 * `admin.configuration.ai`.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/ai/models',
        ),
        new Get(
            uriTemplate: '/admin/ai/models/{id}',
        ),
        new Post(
            uriTemplate: '/admin/ai/models',
            processor: AiModelWriteProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/ai/models/{id}',
            processor: AiModelWriteProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/ai/models/{id}',
            processor: AiModelWriteProcessor::class,
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
        'provider' => 'exact',
        'name' => 'partial',
        'connectionId' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'provider',
        'name',
    ],
)]
class AiModel implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:ai-model:read';
    public const GROUP_ADMIN_WRITE = 'admin:ai-model:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $provider = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $name = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $connectionId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $title = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $inputPrice = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $outputPrice = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $cacheReadPrice = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $cacheWritePrice = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $currency = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $contextWindow = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $maxOutputTokens = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $supportsStructuredOutput = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $supportsTools = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $supportsVision = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $supportsStreaming = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $source = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $active = null;

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

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getInputPrice(): ?string
    {
        return $this->inputPrice;
    }

    public function setInputPrice(?string $inputPrice): self
    {
        $this->inputPrice = $inputPrice;

        return $this;
    }

    public function getOutputPrice(): ?string
    {
        return $this->outputPrice;
    }

    public function setOutputPrice(?string $outputPrice): self
    {
        $this->outputPrice = $outputPrice;

        return $this;
    }

    public function getCacheReadPrice(): ?string
    {
        return $this->cacheReadPrice;
    }

    public function setCacheReadPrice(?string $cacheReadPrice): self
    {
        $this->cacheReadPrice = $cacheReadPrice;

        return $this;
    }

    public function getCacheWritePrice(): ?string
    {
        return $this->cacheWritePrice;
    }

    public function setCacheWritePrice(?string $cacheWritePrice): self
    {
        $this->cacheWritePrice = $cacheWritePrice;

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

    public function getContextWindow(): ?int
    {
        return $this->contextWindow;
    }

    public function setContextWindow(?int $contextWindow): self
    {
        $this->contextWindow = $contextWindow;

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

    public function getSupportsStructuredOutput(): ?bool
    {
        return $this->supportsStructuredOutput;
    }

    public function setSupportsStructuredOutput(?bool $supportsStructuredOutput): self
    {
        $this->supportsStructuredOutput = $supportsStructuredOutput;

        return $this;
    }

    public function getSupportsTools(): ?bool
    {
        return $this->supportsTools;
    }

    public function setSupportsTools(?bool $supportsTools): self
    {
        $this->supportsTools = $supportsTools;

        return $this;
    }

    public function getSupportsVision(): ?bool
    {
        return $this->supportsVision;
    }

    public function setSupportsVision(?bool $supportsVision): self
    {
        $this->supportsVision = $supportsVision;

        return $this;
    }

    public function getSupportsStreaming(): ?bool
    {
        return $this->supportsStreaming;
    }

    public function setSupportsStreaming(?bool $supportsStreaming): self
    {
        $this->supportsStreaming = $supportsStreaming;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;

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
        return new AiModelTableMap();
    }
}
