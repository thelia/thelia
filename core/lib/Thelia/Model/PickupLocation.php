<?php

namespace Thelia\Model;

use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderAddress;
use Symfony\Component\Config\Definition\Exception\Exception;
use Thelia\Model\CountryQuery;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class PickupLocation  {

    /** @var integer */
    protected $id = null;

    /** @var float */
    protected $latitude = null;

    /** @var float */
    protected $longitude = null;

    /** @var string */
    protected $title = null;

    /**
     * @var PickupLocationAddress
     */
    protected $address = null;

    function __construct() {
        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
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
     * @return array
     * */
    public function toArray() {
        return json_decode($this->serializer->serialize($this, 'json'), true);
    }
}                    
