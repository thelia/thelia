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
            uriTemplate: '/admin/configs'
        ),
        new GetCollection(
            uriTemplate: '/admin/configs'
        ),
        new Get(
            uriTemplate: '/admin/configs/{id}'
        ),
        new Put(
            uriTemplate: '/admin/configs/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/configs/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Config extends AbstractTranslatableResource
{
    public const GROUP_READ = 'config:read';
    public const GROUP_READ_SINGLE = 'config:read:single';
    public const GROUP_WRITE = 'config:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $name;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $value;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $secured = false;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $hidden = false;

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

    public function setId(?int $id): Config
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Config
    {
        $this->name = $name;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): Config
    {
        $this->value = $value;
        return $this;
    }

    public function getSecured(): ?bool
    {
        return $this->secured;
    }

    public function setSecured(?bool $secured): Config
    {
        $this->secured = $secured;
        return $this;
    }

    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    public function setHidden(?bool $hidden): Config
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): Config
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): Config
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Config::class;
    }

    public static function getI18nResourceClass(): string
    {
        return ConfigI18n::class;
    }
}
