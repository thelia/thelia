<?php

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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Model\Map\CustomerTitleTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/customer_titles'
        ),
        new GetCollection(
            uriTemplate: '/admin/customer_titles'
        ),
        new Get(
            uriTemplate: '/admin/customer_titles/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/customer_titles/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/customer_titles/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
class CustomerTitle extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:customer_title:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:customer_title:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:customer_title:write';

    #[Groups([
        self::GROUP_ADMIN_READ,
        Customer::GROUP_ADMIN_READ_SINGLE,
        Address::GROUP_ADMIN_READ,
        OrderAddress::GROUP_ADMIN_READ_SINGLE,
        State::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_ADMIN_READ_SINGLE,
        OrderAddress::GROUP_ADMIN_WRITE,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public string $position;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public int $byDefault;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function setPosition(string $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getByDefault(): int
    {
        return $this->byDefault;
    }

    public function setByDefault(int $byDefault): self
    {
        $this->byDefault = $byDefault;

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
        return new CustomerTitleTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return CustomerTitleI18n::class;
    }
}
