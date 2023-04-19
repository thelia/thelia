<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Cassandra\Custom;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/lang'
        ),
        new GetCollection(
            uriTemplate: '/admin/lang'
        ),
        new Get(
            uriTemplate: '/admin/lang/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/lang/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/lang/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Lang extends AbstractPropelResource
{
    public const GROUP_READ = 'lang:read';
    public const GROUP_READ_SINGLE = 'lang:read:single';
    public const GROUP_WRITE = 'lang:write';

    #[Groups([self::GROUP_READ,self::GROUP_WRITE,Order::GROUP_READ_SINGLE,Customer::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE,Customer::GROUP_READ_SINGLE])]
    public ?string $title;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE])]
    public ?string $code;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE])]
    public ?string $locale;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE])]
    public ?string $url;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE])]
    public ?string $dateFormat;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $timeFormat;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $datetimeFormat;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $decimalSeparator;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $thousandsSeparator;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $active;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $visible;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $decimals;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $byDefault;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    public function setDateFormat(?string $dateFormat): void
    {
        $this->dateFormat = $dateFormat;
    }

    public function getTimeFormat(): ?string
    {
        return $this->timeFormat;
    }

    public function setTimeFormat(?string $timeFormat): void
    {
        $this->timeFormat = $timeFormat;
    }

    public function getDatetimeFormat(): ?string
    {
        return $this->datetimeFormat;
    }

    public function setDatetimeFormat(?string $datetimeFormat): void
    {
        $this->datetimeFormat = $datetimeFormat;
    }

    public function getDecimalSeparator(): ?string
    {
        return $this->decimalSeparator;
    }

    public function setDecimalSeparator(?string $decimalSeparator): void
    {
        $this->decimalSeparator = $decimalSeparator;
    }

    public function getThousandsSeparator(): ?string
    {
        return $this->thousandsSeparator;
    }

    public function setThousandsSeparator(?string $thousandsSeparator): void
    {
        $this->thousandsSeparator = $thousandsSeparator;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(?bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getDecimals(): ?string
    {
        return $this->decimals;
    }

    public function setDecimals(?string $decimals): void
    {
        $this->decimals = $decimals;
    }

    public function getByDefault(): ?bool
    {
        return $this->byDefault;
    }

    public function setByDefault(?bool $byDefault): void
    {
        $this->byDefault = $byDefault;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
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
        return \Thelia\Model\Lang::class;
    }
}
