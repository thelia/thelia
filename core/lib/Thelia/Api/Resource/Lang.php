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
            uriTemplate: '/admin/languages'
        ),
        new GetCollection(
            uriTemplate: '/admin/languages'
        ),
        new Get(
            uriTemplate: '/admin/languages/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/languages/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/languages/{id}'
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
    public ?DateTime $createdAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Lang
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): Lang
    {
        $this->title = $title;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): Lang
    {
        $this->code = $code;
        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): Lang
    {
        $this->locale = $locale;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): Lang
    {
        $this->url = $url;
        return $this;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    public function setDateFormat(?string $dateFormat): Lang
    {
        $this->dateFormat = $dateFormat;
        return $this;
    }

    public function getTimeFormat(): ?string
    {
        return $this->timeFormat;
    }

    public function setTimeFormat(?string $timeFormat): Lang
    {
        $this->timeFormat = $timeFormat;
        return $this;
    }

    public function getDatetimeFormat(): ?string
    {
        return $this->datetimeFormat;
    }

    public function setDatetimeFormat(?string $datetimeFormat): Lang
    {
        $this->datetimeFormat = $datetimeFormat;
        return $this;
    }

    public function getDecimalSeparator(): ?string
    {
        return $this->decimalSeparator;
    }

    public function setDecimalSeparator(?string $decimalSeparator): Lang
    {
        $this->decimalSeparator = $decimalSeparator;
        return $this;
    }

    public function getThousandsSeparator(): ?string
    {
        return $this->thousandsSeparator;
    }

    public function setThousandsSeparator(?string $thousandsSeparator): Lang
    {
        $this->thousandsSeparator = $thousandsSeparator;
        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): Lang
    {
        $this->active = $active;
        return $this;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(?bool $visible): Lang
    {
        $this->visible = $visible;
        return $this;
    }

    public function getDecimals(): ?string
    {
        return $this->decimals;
    }

    public function setDecimals(?string $decimals): Lang
    {
        $this->decimals = $decimals;
        return $this;
    }

    public function getByDefault(): ?bool
    {
        return $this->byDefault;
    }

    public function setByDefault(?bool $byDefault): Lang
    {
        $this->byDefault = $byDefault;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Lang
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): Lang
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): Lang
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Lang::class;
    }
}
