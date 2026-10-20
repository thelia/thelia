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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\DateFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\WebhookDeliveryTableMap;

/**
 * One request sent to a subscription, with how it went.
 *
 * Read only. Neither the signed body nor the beginning of the receiver's answer is
 * exposed: the first carries the personal data the subscription asked for, the
 * second whatever the receiver chose to echo back. The back office shows both to
 * an administrator; an API client reads the outcome and replays nothing from here.
 *
 * The page is capped below the global ceiling, and every filter reads an indexed
 * column: `subscription_id` and `event` and `status`, each with `created_at`, and
 * `created_at` and `delivery_uuid` alone.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/webhook-deliveries',
        ),
        new Get(
            uriTemplate: '/admin/webhook-deliveries/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    paginationItemsPerPage: 25,
    paginationMaximumItemsPerPage: 50,
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'subscriptionId' => 'exact',
        'event' => 'exact',
        'status' => 'exact',
        'deliveryUuid' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'createdAt',
    ],
)]
#[ApiFilter(
    filterClass: DateFilter::class,
    properties: [
        'createdAt' => DateFilter::EXCLUDE_NULL,
    ],
)]
class WebhookDelivery implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:webhook-delivery:read';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $subscriptionId = null;

    /**
     * The published event name, or `webhook.test` for a test request.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $event = null;

    /**
     * The identifier sent in the headers, the same on every retry.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $deliveryUuid = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $status = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $attempts = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $responseCode = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $durationMs = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTimeInterface $deliveredAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTimeInterface $replayedAt = null;

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

    public function getSubscriptionId(): ?int
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(?int $subscriptionId): self
    {
        $this->subscriptionId = $subscriptionId;

        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(?string $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getDeliveryUuid(): ?string
    {
        return $this->deliveryUuid;
    }

    public function setDeliveryUuid(?string $deliveryUuid): self
    {
        $this->deliveryUuid = $deliveryUuid;

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

    public function getAttempts(): ?int
    {
        return $this->attempts;
    }

    public function setAttempts(?int $attempts): self
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function setResponseCode(?int $responseCode): self
    {
        $this->responseCode = $responseCode;

        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): self
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeInterface $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getReplayedAt(): ?\DateTimeInterface
    {
        return $this->replayedAt;
    }

    public function setReplayedAt(?\DateTimeInterface $replayedAt): self
    {
        $this->replayedAt = $replayedAt;

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
        return new WebhookDeliveryTableMap();
    }
}
