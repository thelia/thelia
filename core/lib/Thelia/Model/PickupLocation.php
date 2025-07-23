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

namespace Thelia\Model;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Thelia\Core\Translation\Translator;

class PickupLocation
{
    /** OPENING HOURS ARRAY KEYS */
    public const MONDAY_OPENING_HOURS_KEY = '0';

    public const TUESDAY_OPENING_HOURS_KEY = '1';
    public const WEDNESDAY_OPENING_HOURS_KEY = '2';
    public const THURSDAY_OPENING_HOURS_KEY = '3';
    public const FRIDAY_OPENING_HOURS_KEY = '4';
    public const SATURDAY_OPENING_HOURS_KEY = '5';
    public const SUNDAY_OPENING_HOURS_KEY = '6';

    protected string $id;

    protected float $latitude;

    protected float $longitude;

    protected string $title;

    protected int $moduleId;

    protected string $moduleOptionCode;

    protected Serializer $serializer;

    protected array $openingHours = [
        self::MONDAY_OPENING_HOURS_KEY => null,
        self::TUESDAY_OPENING_HOURS_KEY => null,
        self::WEDNESDAY_OPENING_HOURS_KEY => null,
        self::THURSDAY_OPENING_HOURS_KEY => null,
        self::FRIDAY_OPENING_HOURS_KEY => null,
        self::SATURDAY_OPENING_HOURS_KEY => null,
        self::SUNDAY_OPENING_HOURS_KEY => null,
    ];

    protected PickupLocationAddress $address;

    public function __construct()
    {
        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    /** @param float
     * @return $this
     * */
    public function setLatitude($latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /** @param float
     * */
    public function setLongitude($longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @param string
     * */
    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getAddress(): PickupLocationAddress
    {
        return $this->address;
    }

    public function setAddress(PickupLocationAddress $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getModuleId(): int
    {
        return $this->moduleId;
    }

    public function setModuleOptionCode($moduleOptionCode): static
    {
        $this->moduleOptionCode = $moduleOptionCode;

        return $this;
    }

    public function getModuleOptionCode(): string
    {
        return $this->moduleOptionCode;
    }

    public function setModuleId(int $moduleId): static
    {
        $this->moduleId = $moduleId;

        return $this;
    }

    public function getOpeningHours(): array
    {
        return $this->openingHours;
    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function setOpeningHours(int $day, string $hours): static
    {
        if (!\array_key_exists($day, $this->openingHours)) {
            throw new \Exception(Translator::getInstance()->trans('Tried to set the opening hours for a non existant day in the array. Please use the constants defined in the PickupLocation class.'));
        }

        $this->openingHours[$day] = $hours;

        return $this;
    }

    /**
     * @return array
     * */
    public function toArray(): mixed
    {
        return json_decode($this->serializer->serialize($this, 'json'), true);
    }
}
