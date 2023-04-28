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
            uriTemplate: '/admin/areas'
        ),
        new GetCollection(
            uriTemplate: '/admin/areas'
        ),
        new Get(
            uriTemplate: '/admin/areas/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/areas/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/areas/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Area extends AbstractPropelResource
{
    public const GROUP_READ = 'area:read';
    public const GROUP_READ_SINGLE = 'area:read:single';
    public const GROUP_WRITE = 'area:write';

    #[Groups([self::GROUP_READ, CountryArea::GROUP_READ_SINGLE, AreaDeliveryModule::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, CountryArea::GROUP_READ_SINGLE, AreaDeliveryModule::GROUP_READ_SINGLE])]
    public string $name;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, CountryArea::GROUP_READ_SINGLE, AreaDeliveryModule::GROUP_READ_SINGLE])]
    public ?float $postage;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPostage(): ?float
    {
        return $this->postage;
    }

    public function setPostage(?float $postage): self
    {
        $this->postage = $postage;

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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Area::class;
    }
}
