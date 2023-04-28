<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/area_delivery_module/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class AreaDeliveryModule extends AbstractPropelResource
{
    public const GROUP_READ = 'area_delivery_module:read';
    public const GROUP_READ_SINGLE = 'area_delivery_module:read:single';
    public const GROUP_WRITE = 'area_delivery_module:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Area::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Area $area;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Module $module;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): AreaDeliveryModule
    {
        $this->id = $id;
        return $this;
    }

    public function getArea(): Area
    {
        return $this->area;
    }

    public function setArea(Area $area): AreaDeliveryModule
    {
        $this->area = $area;
        return $this;
    }

    public function getModule(): Module
    {
        return $this->module;
    }

    public function setModule(Module $module): AreaDeliveryModule
    {
        $this->module = $module;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): AreaDeliveryModule
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): AreaDeliveryModule
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\AreaDeliveryModule::class;
    }
}
