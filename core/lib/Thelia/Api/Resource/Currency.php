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
            uriTemplate: '/admin/currency'
        ),
        new GetCollection(
            uriTemplate: '/admin/currency'
        ),
        new Get(
            uriTemplate: '/admin/currency/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/currency/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/currency/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Currency extends AbstractTranslatableResource
{
    public const GROUP_READ = 'currency:read';
    public const GROUP_READ_SINGLE = 'currency:read:single';
    public const GROUP_WRITE = 'currency:write';


    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Order::GROUP_READ_SINGLE])]
    public ?string $code;


    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Order::GROUP_READ_SINGLE])]
    public ?string $symbol;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $format;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?float $rate;

    #[Groups([self::GROUP_READ])]
    public ?int $position;

    #[Groups([self::GROUP_READ])]
    public ?bool $visible;

    #[Groups([self::GROUP_READ])]
    public ?bool $byDefault;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(?string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): void
    {
        $this->format = $format;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(?float $rate): void
    {
        $this->rate = $rate;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(?bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getByDefault(): ?bool
    {
        return $this->byDefault;
    }

    public function setByDefault(?bool $byDefault): void
    {
        $this->byDefault = $byDefault;
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
        return \Thelia\Model\Currency::class;
    }

    public static function getI18nResourceClass(): string
    {
        return CurrencyI18n::class;
    }
}
