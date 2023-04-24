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

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Module
    {
        $this->id = $id;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): Module
    {
        $this->code = $code;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): Module
    {
        $this->category = $category;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): Module
    {
        $this->type = $type;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): Module
    {
        $this->version = $version;
        return $this;
    }

    public function getActivate(): ?bool
    {
        return $this->activate;
    }

    public function setActivate(?bool $activate): Module
    {
        $this->activate = $activate;
        return $this;
    }

    public function getFullNamespace(): ?string
    {
        return $this->fullNamespace;
    }

    public function setFullNamespace(?string $fullNamespace): Module
    {
        $this->fullNamespace = $fullNamespace;
        return $this;
    }

    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    public function setHidden(?bool $hidden): Module
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Module
    {
        $this->position = $position;
        return $this;
    }

    public function getMandatory(): ?bool
    {
        return $this->mandatory;
    }

    public function setMandatory(?bool $mandatory): Module
    {
        $this->mandatory = $mandatory;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): Module
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): Module
    {
        $this->updatedAt = $updatedAt;
        return $this;
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
