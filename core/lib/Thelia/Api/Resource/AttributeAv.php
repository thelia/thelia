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
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/attributes_av'
        ),
        new GetCollection(
            uriTemplate: '/admin/attributes_av'
        ),
        new Get(
            uriTemplate: '/admin/attributes_av/{id}'
        ),
        new Put(
            uriTemplate: '/admin/attributes_av/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/attributes_av/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class AttributeAv extends AbstractTranslatableResource
{
    public const GROUP_READ = 'attribute_av:read';
    public const GROUP_READ_SINGLE = 'attribute_av:read:single';
    public const GROUP_WRITE = 'attribute_av:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Attribute::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public Attribute $attribute;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position;

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

    public function setId(?int $id): AttributeAv
    {
        $this->id = $id;
        return $this;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(Attribute $attribute): AttributeAv
    {
        $this->attribute = $attribute;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): AttributeAv
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): AttributeAv
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): AttributeAv
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\AttributeAv::class;
    }

    public static function getI18nResourceClass(): string
    {
        return AttributeAvI18n::class;
    }
}
