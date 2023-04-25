<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/attributes'
        ),
        new GetCollection(
            uriTemplate: '/admin/attributes'
        ),
        new Get(
            uriTemplate: '/admin/attributes/{id}'
        ),
        new Put(
            uriTemplate: '/admin/attributes/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/attributes/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Attribute extends AbstractTranslatableResource
{
    public const GROUP_READ = 'attribute:read';
    public const GROUP_READ_SINGLE = 'attribute:read:single';
    public const GROUP_WRITE = 'attribute:write';

    #[Groups([self::GROUP_READ, AttributeAv::GROUP_READ, AttributeAv::GROUP_WRITE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position = null;

    #[Groups([self::GROUP_READ])]
    public ?DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?DateTime $updatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Attribute
    {
        $this->id = $id;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Attribute
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): Attribute
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): Attribute
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Attribute::class;
    }

    public static function getI18nResourceClass(): string
    {
        return AttributeI18n::class;
    }
}
