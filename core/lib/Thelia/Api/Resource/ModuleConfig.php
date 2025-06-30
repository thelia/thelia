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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use DateTime;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\ModuleConfigTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/module_configs'
        ),
        new GetCollection(
            uriTemplate: '/admin/module_configs'
        ),
        new Get(
            uriTemplate: '/admin/module_configs/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/module_configs/{id}'
        ),
        new Patch(
            uriTemplate: '/admin/module_configs/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/module_configs/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'name',
        'module.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'module_config.module_id',
        ],
    ]
)]
class ModuleConfig extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:module_config:read';

    public const GROUP_ADMIN_READ_SINGLE = 'admin:module_config:read:single';

    public const GROUP_ADMIN_WRITE = 'admin:module_config:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public Module $module;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public string $name;

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

    public function getModule(): Module
    {
        return $this->module;
    }

    public function setModule(Module $module): self
    {
        $this->module = $module;

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
        return new ModuleConfigTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return ModuleConfigI18n::class;
    }
}
