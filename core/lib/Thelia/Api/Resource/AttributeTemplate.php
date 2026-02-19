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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\AttributeTemplateTableMap;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/admin/attribute-templates'),
        new Get(uriTemplate: '/admin/attribute-templates/{id}'),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]]
)]
class AttributeTemplate implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:attribute-template:read';

    #[Groups([
        self::GROUP_ADMIN_READ,
        Template::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?int $id = null;

    #[Relation(targetResource: Attribute::class)]
    #[Groups([
        self::GROUP_ADMIN_READ,
        Template::GROUP_ADMIN_READ_SINGLE,
    ])]
    public ?Attribute $attribute = null;

    #[Relation(targetResource: Template::class)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?Template $template = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $position = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getAttribute(): ?Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(?Attribute $attribute): void
    {
        $this->attribute = $attribute;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new AttributeTemplateTableMap();
    }
}
