<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;


#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/states'
        ),
        new GetCollection(
            uriTemplate: '/admin/states'
        ),
        new Get(
            uriTemplate: '/admin/states/{id}'
        ),
        new Put(
            uriTemplate: '/admin/states/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/states/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
class State extends AbstractTranslatableResource
{
    public const GROUP_READ = 'state:read';
    public const GROUP_READ_SINGLE = 'state:read:single';
    public const GROUP_WRITE = 'state:write';

    #[Groups([self::GROUP_READ,Customer::GROUP_READ_SINGLE,Address::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,OrderAddress::GROUP_READ])]
    public bool $visible;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE,OrderAddress::GROUP_READ])]
    public ?string $isocode;

    #[Relation(targetResource: Country::class)]
    #[Groups(groups:[self::GROUP_READ,self::GROUP_WRITE,Order::GROUP_READ_SINGLE])]
    public Country $country;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): State
    {
        $this->id = $id;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): State
    {
        $this->visible = $visible;
        return $this;
    }

    public function getIsocode(): ?string
    {
        return $this->isocode;
    }

    public function setIsocode(?string $isocode): State
    {
        $this->isocode = $isocode;
        return $this;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function setCountry(Country $country): State
    {
        $this->country = $country;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): State
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): State
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\State::class;
    }

    public static function getI18nResourceClass(): string
    {
        return StateI18n::class;
    }
}
