<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Thelia\Api\State\Provider\PaymentModuleProvider;
use Thelia\Model\Map\ModuleTableMap;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/payment/modules',
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'moduleId',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            provider: PaymentModuleProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]]
)]
class PaymentModule extends AbstractTranslatableResource
{
    public const GROUP_FRONT_READ = 'front:payment_module:read';

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public ?int $id = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public ?bool $valid;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public string $code;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public ?float $minimumAmount;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public ?float $maximumAmount;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public array $optionGroups = [];

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public array $images = [];

    #[Groups([self::GROUP_FRONT_READ])]
    public I18nCollection $i18ns;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): PaymentModule
    {
        $this->code = $code;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): PaymentModule
    {
        $this->id = $id;
        return $this;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function setImages(array $images): PaymentModule
    {
        $this->images = $images;
        return $this;
    }

    public function getMaximumAmount(): ?float
    {
        return $this->maximumAmount;
    }

    public function setMaximumAmount(?float $maximumAmount): PaymentModule
    {
        $this->maximumAmount = $maximumAmount;
        return $this;
    }

    public function getMinimumAmount(): ?float
    {
        return $this->minimumAmount;
    }

    public function setMinimumAmount(?float $minimumAmount): PaymentModule
    {
        $this->minimumAmount = $minimumAmount;
        return $this;
    }

    public function getOptionGroups(): array
    {
        return $this->optionGroups;
    }

    public function setOptionGroups(array $optionGroups): PaymentModule
    {
        $this->optionGroups = $optionGroups;
        return $this;
    }

    public function getValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(?bool $valid): PaymentModule
    {
        $this->valid = $valid;
        return $this;
    }



    #[Ignore] public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new ModuleTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return ModuleI18n::class;
    }
}
