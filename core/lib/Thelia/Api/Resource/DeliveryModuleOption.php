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

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

class DeliveryModuleOption
{
    #[ApiProperty(
        description: 'Code of the delivery module option',
        required: true,
        example: 'DHL',
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private string $code;

    #[ApiProperty(
        description: 'Validity of the delivery module option',
        example: true,
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private bool $valid;

    #[ApiProperty(
        description: 'Title of the delivery module option',
        example: 'Express Delivery',
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private string $title;

    #[ApiProperty(
        description: 'Description of the delivery module option',
        example: 'Fast delivery within 24 hours',
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private string $description;

    #[ApiProperty(
        description: 'URL of the delivery logo',
        example: 'https://example.com/logo.png',
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private string $image;

    #[ApiProperty(
        description: 'Minimum delivery date',
        example: '2025-10-01T00:00:00Z',
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private ?string $minimumDeliveryDate = null;

    #[ApiProperty(
        description: 'Maximum delivery date',
        example: '2025-10-05T00:00:00Z',
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private ?string $maximumDeliveryDate = null;

    #[ApiProperty(
        description: 'Postage cost',
        example: 5.99,
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private ?float $postage = null;

    #[ApiProperty(
        description: 'Postage tax',
        example: 1.20,
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private ?float $postageTax = null;

    #[ApiProperty(
        description: 'Untaxed postage cost',
        example: 4.79,
    )]
    #[Groups([DeliveryModule::GROUP_FRONT_READ])]
    private ?float $postageUntaxed = null;

    // Getters and setters for each property
    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): self
    {
        $this->valid = $valid;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getMinimumDeliveryDate(): ?string
    {
        return $this->minimumDeliveryDate;
    }

    public function setMinimumDeliveryDate(?string $minimumDeliveryDate): self
    {
        $this->minimumDeliveryDate = $minimumDeliveryDate;

        return $this;
    }

    public function getMaximumDeliveryDate(): ?string
    {
        return $this->maximumDeliveryDate;
    }

    public function setMaximumDeliveryDate(?string $maximumDeliveryDate): self
    {
        $this->maximumDeliveryDate = $maximumDeliveryDate;

        return $this;
    }

    public function getPostage(): ?float
    {
        return $this->postage;
    }

    public function setPostage(?float $postage): self
    {
        $this->postage = $postage;

        return $this;
    }

    public function getPostageTax(): ?float
    {
        return $this->postageTax;
    }

    public function setPostageTax(?float $postageTax): self
    {
        $this->postageTax = $postageTax;

        return $this;
    }

    public function getPostageUntaxed(): ?float
    {
        return $this->postageUntaxed;
    }

    public function setPostageUntaxed(?float $postageUntaxed): self
    {
        $this->postageUntaxed = $postageUntaxed;

        return $this;
    }
}
