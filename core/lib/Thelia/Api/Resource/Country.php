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
            uriTemplate: '/admin/countries'
        ),
        new GetCollection(
            uriTemplate: '/admin/countries'
        ),
        new Get(
            uriTemplate: '/admin/countries/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/countries/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/countries/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Country extends AbstractTranslatableResource
{
    public const GROUP_READ = 'country:read';
    public const GROUP_READ_SINGLE = 'country:read:single';
    public const GROUP_WRITE = 'country:write';

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE, Customer::GROUP_READ_SINGLE, Address::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $isocode;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $isoalpha2;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $isoalpha3;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $hasStates;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $needZipCode;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $zipCodeFormat;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $byDefault;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $shopCountry;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
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

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getIsocode(): string
    {
        return $this->isocode;
    }

    public function setIsocode(string $isocode): self
    {
        $this->isocode = $isocode;

        return $this;
    }

    public function getIsoalpha2(): ?string
    {
        return $this->isoalpha2;
    }

    public function setIsoalpha2(?string $isoalpha2): self
    {
        $this->isoalpha2 = $isoalpha2;

        return $this;
    }

    public function getIsoalpha3(): ?string
    {
        return $this->isoalpha3;
    }

    public function setIsoalpha3(?string $isoalpha3): self
    {
        $this->isoalpha3 = $isoalpha3;

        return $this;
    }

    public function getHasStates(): ?bool
    {
        return $this->hasStates;
    }

    public function setHasStates(?bool $hasStates): self
    {
        $this->hasStates = $hasStates;

        return $this;
    }

    public function getNeedZipCode(): ?bool
    {
        return $this->needZipCode;
    }

    public function setNeedZipCode(?bool $needZipCode): self
    {
        $this->needZipCode = $needZipCode;

        return $this;
    }

    public function getZipCodeFormat(): ?string
    {
        return $this->zipCodeFormat;
    }

    public function setZipCodeFormat(?string $zipCodeFormat): self
    {
        $this->zipCodeFormat = $zipCodeFormat;

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

    public function getShopCountry(): ?bool
    {
        return $this->shopCountry;
    }

    public function setShopCountry(?bool $shopCountry): self
    {
        $this->shopCountry = $shopCountry;

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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Country::class;
    }

    public static function getI18nResourceClass(): string
    {
        return CountryI18n::class;
    }
}
