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
            uriTemplate: '/admin/country'
        ),
        new GetCollection(
            uriTemplate: '/admin/country'
        ),
        new Get(
            uriTemplate: '/admin/country/{id}'
        ),
        new Put(
            uriTemplate: '/admin/country/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/country/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
class Country extends AbstractTranslatableResource
{
    public const GROUP_READ = 'country:read';
    public const GROUP_READ_SINGLE = 'country:read:single';
    public const GROUP_WRITE = 'country:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,OrderAddress::GROUP_READ])]
    public bool $visible;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,OrderAddress::GROUP_READ])]
    public string $isocode;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,OrderAddress::GROUP_READ])]
    public ?string $isoalpha2;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,OrderAddress::GROUP_READ])]
    public ?string $isoalpha3;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $hasStates;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $needZipCode;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $zipCodeFormat;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,OrderAddress::GROUP_READ])]
    public ?bool $byDefault;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,OrderAddress::GROUP_READ])]
    public ?bool $shopCountry;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getIsocode(): string
    {
        return $this->isocode;
    }

    public function setIsocode(string $isocode): void
    {
        $this->isocode = $isocode;
    }

    public function getIsoalpha2(): ?string
    {
        return $this->isoalpha2;
    }

    public function setIsoalpha2(?string $isoalpha2): void
    {
        $this->isoalpha2 = $isoalpha2;
    }

    public function getIsoalpha3(): ?string
    {
        return $this->isoalpha3;
    }

    public function setIsoalpha3(?string $isoalpha3): void
    {
        $this->isoalpha3 = $isoalpha3;
    }

    public function getHasStates(): ?bool
    {
        return $this->hasStates;
    }

    public function setHasStates(?bool $hasStates): void
    {
        $this->hasStates = $hasStates;
    }

    public function getNeedZipCode(): ?bool
    {
        return $this->needZipCode;
    }

    public function setNeedZipCode(?bool $needZipCode): void
    {
        $this->needZipCode = $needZipCode;
    }


    public function getZipCodeFormat(): ?string
    {
        return $this->zipCodeFormat;
    }

    public function setZipCodeFormat(?string $zipCodeFormat): void
    {
        $this->zipCodeFormat = $zipCodeFormat;
    }

    public function getByDefault(): ?bool
    {
        return $this->byDefault;
    }


    public function setByDefault(?bool $byDefault): void
    {
        $this->byDefault = $byDefault;
    }


    public function getShopCountry(): ?bool
    {
        return $this->shopCountry;
    }


    public function setShopCountry(?bool $shopCountry): void
    {
        $this->shopCountry = $shopCountry;
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
        return \Thelia\Model\Country::class;
    }

    public static function getI18nResourceClass(): string
    {
        return CountryI18n::class;
    }
}
