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
use Thelia\Model\Map\ModuleTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/modules'
        ),
        new GetCollection(
            uriTemplate: '/admin/modules'
        ),
        new Get(
            uriTemplate: '/admin/modules/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/modules/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/modules/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Module extends AbstractTranslatableResource
{
    public const GROUP_READ = 'module:read';
    public const GROUP_READ_SINGLE = 'module:read:single';
    public const GROUP_WRITE = 'module:write';

    #[Groups([self::GROUP_READ, ModuleConfig::GROUP_READ, ModuleConfig::GROUP_WRITE, Order::GROUP_READ_SINGLE, Order::GROUP_READ,Order::GROUP_WRITE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public string $code;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public string $category;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public string $type;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public string $version;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?bool $activate;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?string $fullNamespace;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?bool $hidden;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?int $position;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?bool $mandatory;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getActivate(): ?bool
    {
        return $this->activate;
    }

    public function setActivate(?bool $activate): self
    {
        $this->activate = $activate;

        return $this;
    }

    public function getFullNamespace(): ?string
    {
        return $this->fullNamespace;
    }

    public function setFullNamespace(?string $fullNamespace): self
    {
        $this->fullNamespace = $fullNamespace;

        return $this;
    }

    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    public function setHidden(?bool $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getMandatory(): ?bool
    {
        return $this->mandatory;
    }

    public function setMandatory(?bool $mandatory): self
    {
        $this->mandatory = $mandatory;

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
        return new ModuleTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return ModuleI18n::class;
    }
}
