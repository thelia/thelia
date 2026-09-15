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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\State\Provider\OrderHistoryCollectionProvider;
use Thelia\Model\Map\OrderHistoryTableMap;

/**
 * The journal of one order, read from the administration side.
 *
 * It is the whole of it — every kind of entry, internal notes included — and it
 * is deliberately reachable only under the order it belongs to: a history line
 * says nothing on its own, and a flat collection of every line of every order is
 * not a question anybody asks. The uri variable is therefore the order, and the
 * provider is what turns it into the scope of the query.
 *
 * What travels is what the journal holds and nothing around it: no request, no
 * mail body, no session. The payload is handed over decoded, as the object it
 * was written from, so a reader never parses a string a second time.
 *
 * There is no front side to this resource, and there never is one: the customer
 * reads the notes a human addressed to them, through OrderCustomerNotesAddon on
 * the order itself, and nothing else of the journal leaves the back-office.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/orders/{orderId}/history',
            uriVariables: ['orderId'],
            // The whole journal of a busy order is long, and a back-office card
            // shows a handful of lines at a time. Twenty is what a first screen
            // needs; a caller walking the journal asks for more with
            // itemsPerPage, up to the ceiling every collection shares.
            paginationItemsPerPage: 20,
            provider: OrderHistoryCollectionProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
class OrderHistory implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_history:read';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $eventType = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $actorType = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $actorLabel = null;

    /**
     * The administrator who acted, null once that account is gone. The label
     * above survives them, which is the point of keeping both.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $adminId = null;

    /**
     * The event details, as the object they were recorded from. The column holds
     * JSON text; getDecodedPayload() is what reads it, and an unreadable payload
     * reads as an empty one rather than breaking the whole page.
     *
     * @var array<string, mixed>
     */
    #[Column(propelFieldName: 'decodedPayload')]
    #[Groups([self::GROUP_ADMIN_READ])]
    public array $payload = [];

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $comment = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public bool $visibleToCustomer = false;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(?string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getActorType(): ?string
    {
        return $this->actorType;
    }

    public function setActorType(?string $actorType): self
    {
        $this->actorType = $actorType;

        return $this;
    }

    public function getActorLabel(): ?string
    {
        return $this->actorLabel;
    }

    public function setActorLabel(?string $actorLabel): self
    {
        $this->actorLabel = $actorLabel;

        return $this;
    }

    public function getAdminId(): ?int
    {
        return $this->adminId;
    }

    public function setAdminId(?int $adminId): self
    {
        $this->adminId = $adminId;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function isVisibleToCustomer(): bool
    {
        return $this->visibleToCustomer;
    }

    public function setVisibleToCustomer(bool $visibleToCustomer): self
    {
        $this->visibleToCustomer = $visibleToCustomer;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new OrderHistoryTableMap();
    }
}
