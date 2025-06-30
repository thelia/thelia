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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use DateTime;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Model\Map\TemplateTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/templates'
        ),
        new GetCollection(
            uriTemplate: '/admin/templates'
        ),
        new Get(
            uriTemplate: '/admin/templates/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Delete(
            uriTemplate: '/admin/templates/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
class Template extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:template:read';

    public const GROUP_ADMIN_READ_SINGLE = 'admin:template:read:single';

    public const GROUP_ADMIN_WRITE = 'admin:template:write';

    #[Groups([self::GROUP_ADMIN_READ, Category::GROUP_ADMIN_READ, Category::GROUP_ADMIN_WRITE, Product::GROUP_ADMIN_READ, Product::GROUP_ADMIN_WRITE])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?DateTime $updatedAt = null;

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

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new TemplateTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return TemplateI18n::class;
    }
}
