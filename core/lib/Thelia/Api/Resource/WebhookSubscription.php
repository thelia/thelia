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
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\WebhookSubscriptionDeleteProcessor;
use Thelia\Api\State\Processor\WebhookSubscriptionSecretProcessor;
use Thelia\Api\State\Processor\WebhookSubscriptionWriteProcessor;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Map\WebhookSubscriptionTableMap;

/**
 * An address the shop notifies of what happens in it, with the events it asks for.
 *
 * Administration only, and every operation is declared: a resource left bare would
 * expose a complete CRUD to anyone. Writes go through the same events as the
 * back-office screens, so one rule validates every field: the resource carries no
 * constraint of its own, and a refusal answers 422.
 *
 * The signing secret is in no read group. It reaches the caller twice: in the
 * answer that creates the subscription and in the answer of the operation that
 * replaces it (the group `admin:webhook-subscription:secret`). It is never an
 * input: a client cannot choose it.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/webhook-subscriptions',
        ),
        new Get(
            uriTemplate: '/admin/webhook-subscriptions/{id}',
        ),
        new Post(
            uriTemplate: '/admin/webhook-subscriptions',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_SECRET]],
            processor: WebhookSubscriptionWriteProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/webhook-subscriptions/{id}',
            processor: WebhookSubscriptionWriteProcessor::class,
        ),
        new Post(
            uriTemplate: '/admin/webhook-subscriptions/{id}/secret',
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_SECRET]],
            read: false,
            deserialize: false,
            processor: WebhookSubscriptionSecretProcessor::class,
            extraProperties: ['admin_access' => AccessManager::UPDATE],
        ),
        new Delete(
            uriTemplate: '/admin/webhook-subscriptions/{id}',
            processor: WebhookSubscriptionDeleteProcessor::class,
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
        'title' => 'partial',
        'status' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'title',
        'status',
    ],
)]
class WebhookSubscription implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:webhook-subscription:read';
    public const GROUP_ADMIN_WRITE = 'admin:webhook-subscription:write';
    public const GROUP_ADMIN_SECRET = 'admin:webhook-subscription:secret';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $title = null;

    /**
     * Where the requests are sent: https only, never a private or internal address.
     * Checked by the same rule as the back office when the subscription is saved.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $url = null;

    /**
     * The names of the published events this subscription asks for, for instance
     * `order.created`. The catalog is the one of `WebhookEventCatalog`.
     *
     * @var list<string>
     */
    #[Column(propelFieldName: 'eventNames')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public array $events = [];

    /**
     * `full` sends the object as the admin API shows it, `identifiers` only what a
     * receiver needs to fetch the rest itself.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $payloadMode = null;

    /**
     * `active` or `inactive`, written by the client. `error` is only ever set by a
     * delivery that ran out of retries, and an administrator leaves it by writing
     * `active`, which clears the failure.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $status = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $failureReason = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTimeInterface $failedAt = null;

    /**
     * Read in no operation but the two that hand it over.
     */
    #[Groups([self::GROUP_ADMIN_SECRET])]
    public ?string $secret = null;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param list<string> $events
     */
    public function setEvents(array $events): self
    {
        $this->events = $events;

        return $this;
    }

    public function getPayloadMode(): ?string
    {
        return $this->payloadMode;
    }

    public function setPayloadMode(?string $payloadMode): self
    {
        $this->payloadMode = $payloadMode;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;

        return $this;
    }

    public function getFailedAt(): ?\DateTimeInterface
    {
        return $this->failedAt;
    }

    public function setFailedAt(?\DateTimeInterface $failedAt): self
    {
        $this->failedAt = $failedAt;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;

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
        return new WebhookSubscriptionTableMap();
    }
}
