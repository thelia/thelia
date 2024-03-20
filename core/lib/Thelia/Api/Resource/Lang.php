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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\LangTableMap;

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
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/languages/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/languages/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/languages'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
        'active',
    ]
)]
class Lang implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:lang:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:lang:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:lang:write';

    public const GROUP_FRONT_READ = 'front:lang:read';


    #[Groups([self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Customer::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        Order::GROUP_FRONT_READ_SINGLE,
        Customer::GROUP_FRONT_WRITE,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, Customer::GROUP_ADMIN_READ_SINGLE,self::GROUP_FRONT_READ,Customer::GROUP_FRONT_READ_SINGLE])]
    public ?string $title;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE,self::GROUP_FRONT_READ,Customer::GROUP_FRONT_READ_SINGLE])]
    public ?string $code;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE,self::GROUP_FRONT_READ,Customer::GROUP_FRONT_READ_SINGLE])]
    public ?string $locale;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $url;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $dateFormat;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $timeFormat;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $datetimeFormat;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $decimalSeparator;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $thousandsSeparator;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE,self::GROUP_FRONT_READ])]
    public ?bool $active;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE,self::GROUP_FRONT_READ])]
    public ?bool $visible;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $decimals;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE,self::GROUP_FRONT_READ])]
    public ?bool $byDefault;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $position;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    public function setDateFormat(?string $dateFormat): self
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    public function getTimeFormat(): ?string
    {
        return $this->timeFormat;
    }

    public function setTimeFormat(?string $timeFormat): self
    {
        $this->timeFormat = $timeFormat;

        return $this;
    }

    public function getDatetimeFormat(): ?string
    {
        return $this->datetimeFormat;
    }

    public function setDatetimeFormat(?string $datetimeFormat): self
    {
        $this->datetimeFormat = $datetimeFormat;

        return $this;
    }

    public function getDecimalSeparator(): ?string
    {
        return $this->decimalSeparator;
    }

    public function setDecimalSeparator(?string $decimalSeparator): self
    {
        $this->decimalSeparator = $decimalSeparator;

        return $this;
    }

    public function getThousandsSeparator(): ?string
    {
        return $this->thousandsSeparator;
    }

    public function setThousandsSeparator(?string $thousandsSeparator): self
    {
        $this->thousandsSeparator = $thousandsSeparator;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(?bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getDecimals(): ?string
    {
        return $this->decimals;
    }

    public function setDecimals(?string $decimals): self
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function getByDefault(): ?bool
    {
        return $this->byDefault;
    }

    public function setByDefault(?bool $byDefault): self
    {
        $this->byDefault = $byDefault;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new LangTableMap();
    }
}
