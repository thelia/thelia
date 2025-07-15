<?php

declare(strict_types=1);

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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\State\Provider\DeliveryModuleProvider;
use Thelia\Model\Map\ModuleTableMap;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/delivery_modules',
            provider: DeliveryModuleProvider::class,
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'by_code',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
            ],
        ),
        new Get(
            uriTemplate: '/front/delivery_modules/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: ['position'],
)]
class DeliveryModule extends AbstractTranslatableResource
{
    public const GROUP_FRONT_READ = 'front:delivery_module:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:delivery_module:read:single';

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public ?int $id = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public string $code;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public ?int $position = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public ?string $deliveryMode = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public array $options = [];

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    public ?bool $valid = true;

    #[Groups([self::GROUP_FRONT_READ])]
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

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

    public function getDeliveryMode(): ?string
    {
        return $this->deliveryMode;
    }

    public function setDeliveryMode(?string $deliveryMode): self
    {
        $this->deliveryMode = $deliveryMode;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): self
    {
        $this->valid = $valid;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new ModuleTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return ModuleI18n::class;
    }
}
