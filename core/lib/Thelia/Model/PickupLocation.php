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

    /** @var string */
    protected $id;

    /** @var float */
    protected $latitude;

    /** @var float */
    protected $longitude;

    /** @var string */
    protected $title;

    /** @var int */
    protected $moduleId;

    /** @var Serializer */
    protected $serializer;

    /** @var array */
    protected $openingHours = [
        self::MONDAY_OPENING_HOURS_KEY => null,
        self::TUESDAY_OPENING_HOURS_KEY => null,
        self::WEDNESDAY_OPENING_HOURS_KEY => null,
        self::THURSDAY_OPENING_HOURS_KEY => null,
        self::FRIDAY_OPENING_HOURS_KEY => null,
        self::SATURDAY_OPENING_HOURS_KEY => null,
        self::SUNDAY_OPENING_HOURS_KEY => null,
    ];

    /**
     * @var PickupLocationAddress
     */
    protected $address;

    public function __construct()
    {
        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return PickupLocation
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /** @return float */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /** @param float
     *  @return $this
     * */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /** @return float */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /** @param float
     * @return $this|PickupLocation
     * */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /** @return string */
    public function getTitle()
    {
        return $this->title;
    }

    /** @param string
     *  @return $this|PickupLocation
     * */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return PickupLocationAddress
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param PickupLocationAddress $address
     *
     * @return PickupLocation
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return int
     */
    public function getModuleId()
    {
        return $this->moduleId;
    }

    /**
     * @param int $moduleId
     *
     * @return PickupLocation
     */
    public function setModuleId($moduleId)
    {
        $this->moduleId = $moduleId;

        return $this;
    }

    /**
     * @return array
     */
    public function getOpeningHours()
    {
        return $this->openingHours;
    }

    /**
     * @param int    $day
     * @param string $hours
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setOpeningHours($day, $hours)
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
    public function toArray()
    {
        return json_decode($this->serializer->serialize($this, 'json'), true);
    }
}
