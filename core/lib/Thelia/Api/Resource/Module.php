<?php

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
            uriTemplate: '/admin/module'
        ),
        new GetCollection(
            uriTemplate: '/admin/module'
        ),
        new Get(
            uriTemplate: '/admin/module/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/module/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/module/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
class Module extends AbstractTranslatableResource
{
    public const GROUP_READ = 'module:read';
    public const GROUP_READ_SINGLE = 'module:read:single';
    public const GROUP_WRITE = 'module:write';

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE, Order::GROUP_READ])]
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getActivate(): ?bool
    {
        return $this->activate;
    }

    public function setActivate(?bool $activate): void
    {
        $this->activate = $activate;
    }

    public function getFullNamespace(): ?string
    {
        return $this->fullNamespace;
    }

    public function setFullNamespace(?string $fullNamespace): void
    {
        $this->fullNamespace = $fullNamespace;
    }

    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    public function setHidden(?bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getMandatory(): ?bool
    {
        return $this->mandatory;
    }

    public function setMandatory(?bool $mandatory): void
    {
        $this->mandatory = $mandatory;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }


    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Module::class;
    }

    public static function getI18nResourceClass(): string
    {
        return ModuleI18n::class;
    }
}
