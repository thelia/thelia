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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Api\State\Provider\DeliveryPickupLocationProvider;
use Thelia\Core\Translation\Translator;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/delivery_pickup_locations/{city}/{zipcode}',
            uriVariables: ['city', 'zipcode'],
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'stateId',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer',
                        ],
                    ],
                    [
                        'name' => 'countryId',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer',
                        ],
                    ],
                    [
                        'name' => 'radius',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer',
                        ],
                    ],
                    [
                        'name' => 'maxRelays',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer',
                        ],
                    ],
                    [
                        'name' => 'address',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'city',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'zipCode',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'orderWeight',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer',
                        ],
                    ],
                    [
                        'name' => 'moduleIds',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ],
            paginationEnabled: false,
            provider: DeliveryPickupLocationProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]]
)]
class DeliveryPickupLocation
{
    public const GROUP_FRONT_READ = 'front:delivery_pickup_location:read';

    public const GROUP_FRONT_READ_SINGLE = 'front:delivery_pickup_location:read:single';

    /** OPENING HOURS ARRAY KEYS */
    public const MONDAY_OPENING_HOURS_KEY = '0';

    public const TUESDAY_OPENING_HOURS_KEY = '1';

    public const WEDNESDAY_OPENING_HOURS_KEY = '2';

    public const THURSDAY_OPENING_HOURS_KEY = '3';

    public const FRIDAY_OPENING_HOURS_KEY = '4';

    public const SATURDAY_OPENING_HOURS_KEY = '5';

    public const SUNDAY_OPENING_HOURS_KEY = '6';

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    protected string $id;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    protected ?float $latitude = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    protected ?float $longitude = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    protected ?string $title = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    protected ?int $moduleId = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    protected ?string $moduleOptionCode = null;

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    protected array $openingHours = [
        self::MONDAY_OPENING_HOURS_KEY => null,
        self::TUESDAY_OPENING_HOURS_KEY => null,
        self::WEDNESDAY_OPENING_HOURS_KEY => null,
        self::THURSDAY_OPENING_HOURS_KEY => null,
        self::FRIDAY_OPENING_HOURS_KEY => null,
        self::SATURDAY_OPENING_HOURS_KEY => null,
        self::SUNDAY_OPENING_HOURS_KEY => null,
    ];

    #[Groups([
        self::GROUP_FRONT_READ,
    ])]
    protected PickupLocationAddress $address;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

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

    public function getModuleId(): ?int
    {
        return $this->moduleId;
    }

    public function setModuleId(?int $moduleId): self
    {
        $this->moduleId = $moduleId;

        return $this;
    }

    public function getModuleOptionCode(): ?string
    {
        return $this->moduleOptionCode;
    }

    public function setModuleOptionCode(?string $moduleOptionCode): self
    {
        $this->moduleOptionCode = $moduleOptionCode;

        return $this;
    }

    public function getAddress(): PickupLocationAddress
    {
        return $this->address;
    }

    public function setAddress(PickupLocationAddress $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function setOpeningHours(int $day, string $hours): static
    {
        if (!\array_key_exists($day, $this->openingHours)) {
            throw new \Exception(Translator::getInstance()->trans('Tried to set the opening hours for a non existant day in the array. Please use the constants defined in the PickupLocation class.'));
        }

        $this->openingHours[$day] = $hours;

        return $this;
    }

    public function getOpeningHours(): array
    {
        return $this->openingHours;
    }
}
