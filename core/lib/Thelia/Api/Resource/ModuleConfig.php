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
            uriTemplate: '/admin/module_configs'
        ),
        new GetCollection(
            uriTemplate: '/admin/module_configs'
        ),
        new Get(
            uriTemplate: '/admin/module_configs/{id}'
        ),
        new Put(
            uriTemplate: '/admin/module_configs/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/module_configs/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class ModuleConfig extends AbstractTranslatableResource
{
    public const GROUP_READ = 'module_config:read';
    public const GROUP_READ_SINGLE = 'module_config:read:single';
    public const GROUP_WRITE = 'module_config:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_READ])]
    public Module $module;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $name;

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

    public function setId(?int $id): ModuleConfig
    {
        $this->id = $id;
        return $this;
    }

    public function getModule(): Module
    {
        return $this->module;
    }

    public function setModule(Module $module): ModuleConfig
    {
        $this->module = $module;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): ModuleConfig
    {
        $this->name = $name;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): ModuleConfig
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): ModuleConfig
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\ModuleConfig::class;
    }

    public static function getI18nResourceClass(): string
    {
        return ModuleConfigI18n::class;
    }
}
