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
use Thelia\Api\State\Processor\AiConnectionTestProcessor;
use Thelia\Api\State\Processor\AiConnectionWriteProcessor;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Map\AiConnectionTableMap;

/**
 * A way to reach an AI provider: endpoint, key and jurisdiction. Administration only, under the right
 * `admin.configuration.ai`.
 *
 * The API key is in no read group: it is written (`apiKey`) and stored encrypted, and the connection only says
 * whether it has one (`hasKey`). An empty `apiKey` on an update keeps the stored key.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/ai/connections',
        ),
        new Get(
            uriTemplate: '/admin/ai/connections/{id}',
        ),
        new Post(
            uriTemplate: '/admin/ai/connections',
            processor: AiConnectionWriteProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/ai/connections/{id}',
            processor: AiConnectionWriteProcessor::class,
        ),
        new Post(
            uriTemplate: '/admin/ai/connections/{id}/test',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: AiConnectionTestProcessor::class,
            extraProperties: ['admin_access' => AccessManager::UPDATE],
        ),
        new Delete(
            uriTemplate: '/admin/ai/connections/{id}',
            processor: AiConnectionWriteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
    paginationItemsPerPage: 25,
    paginationMaximumItemsPerPage: 50,
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'code' => 'exact',
        'provider' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'code',
    ],
)]
class AiConnection implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:ai-connection:read';
    public const GROUP_ADMIN_WRITE = 'admin:ai-connection:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    /**
     * Lower case letters, digits, `-` and `_`: what modules and the log refer to.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $code = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $title = null;

    /**
     * mistral, anthropic, openai, openai_compatible, scaleway, ovhcloud, albert, ollama or thelia_ai.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $provider = null;

    /**
     * `eu` or `non_eu`. Derived from the provider and the address; declared by the client for openai_compatible
     * and ollama only (an input for the others is ignored).
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $jurisdiction = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $baseUrl = null;

    /**
     * Written, never read.
     */
    #[Groups([self::GROUP_ADMIN_WRITE])]
    public ?string $apiKey = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $apiKeyEnv = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $hasKey = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $allowPrivateNetwork = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $active = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTimeInterface $checkedAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $checkError = null;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

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

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getJurisdiction(): ?string
    {
        return $this->jurisdiction;
    }

    public function setJurisdiction(?string $jurisdiction): self
    {
        $this->jurisdiction = $jurisdiction;

        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getApiKeyEnv(): ?string
    {
        return $this->apiKeyEnv;
    }

    public function setApiKeyEnv(?string $apiKeyEnv): self
    {
        $this->apiKeyEnv = $apiKeyEnv;

        return $this;
    }

    public function getHasKey(): ?bool
    {
        return $this->hasKey;
    }

    public function setHasKey(?bool $hasKey): self
    {
        $this->hasKey = $hasKey;

        return $this;
    }

    public function getAllowPrivateNetwork(): ?bool
    {
        return $this->allowPrivateNetwork;
    }

    public function setAllowPrivateNetwork(?bool $allowPrivateNetwork): self
    {
        $this->allowPrivateNetwork = $allowPrivateNetwork;

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

    public function getCheckedAt(): ?\DateTimeInterface
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(?\DateTimeInterface $checkedAt): self
    {
        $this->checkedAt = $checkedAt;

        return $this;
    }

    public function getCheckError(): ?string
    {
        return $this->checkError;
    }

    public function setCheckError(?string $checkError): self
    {
        $this->checkError = $checkError;

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
        return new AiConnectionTableMap();
    }
}
