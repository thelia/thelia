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
use Symfony\Component\Serializer\Annotation\Groups;

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
        ),
        new Put(
            uriTemplate: '/admin/customer_titles/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/customer_titles/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
class CustomerTitle extends AbstractTranslatableResource
{
    public const GROUP_READ = 'customer_title:read';
    public const GROUP_READ_SINGLE = 'customer_title:read:single';
    public const GROUP_WRITE = 'customer_title:write';

    #[Groups([self::GROUP_READ, Customer::GROUP_READ_SINGLE, Address::GROUP_READ,OrderAddress::GROUP_READ_SINGLE,State::GROUP_READ_SINGLE,Order::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $position;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public int $byDefault;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): CustomerTitle
    {
        $this->id = $id;
        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function setPosition(string $position): CustomerTitle
    {
        $this->position = $position;
        return $this;
    }

    public function getByDefault(): int
    {
        return $this->byDefault;
    }

    public function setByDefault(int $byDefault): CustomerTitle
    {
        $this->byDefault = $byDefault;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): CustomerTitle
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): CustomerTitle
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\CustomerTitle::class;
    }

    public static function getI18nResourceClass(): string
    {
        return CustomerTitleI18n::class;
    }
}
