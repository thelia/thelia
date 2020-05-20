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
    
    
    /** @var floatval */
    protected $latitude = null;
    
    /** @var floatval */
    protected $longitude = null;
    
    /** @var string */
    protected $title = null;
    
    /** @var string */
    protected $company = null;

    /** @var string */
    protected $address1 = null;
    
    /** @var string */
    protected $address2 = null;    
    
    /** @var string */
    protected $address3 = null;
    
    /** @var int */
    protected $zipcode = null;
    
    /** @var string */
    protected $city = null;

    /** @var string ISO 3166-1 alpha-2 code */
    protected $countryCode = null;
    
    /** @var array  */
    protected $additionalData = [];

    function __construct() {
        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    /** @return floatval */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /** @param floatval 
     *  @return $this 
     * */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
        return $this;
    }

    /** @return floatval */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /** @param floatval 
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

    /** @return string */
    public function getCompany(){
		return $this->company;
	}

    /** @param string 
     * @return $this 
     * */
	public function setCompany($company){
        $this->company = $company;
        return $this;
	}
    
    /** @return string */
    public function getAddress1()
    {
        return $this->address1;
    }

    /** @param string 
     *  @return $this 
     * */
    public function setAddress1($address)
    {
        $this->address1 = $address;
        return $this;
    }

    /** @return string */
    public function getAddress2()
    {
        return $this->address2;
    }
    
    /** @param string 
     *  @return $this 
     * */
    public function setAddress2($address)
    {
        $this->address2 = $address;
        return $this;
    }

    /** @return string */
    public function getAddress3()
    {
        return $this->address3;
    }

    /** 
     * @param string 
     * @return $this 
     * */
    public function setAddress3($address)
    {
        $this->address3 = $address;
        return $this;
    }

	public function getZipcode(){
		return $this->zipcode;
	}

    /** @param int 
     * @return $this 
     * */
	public function setZipcode($zipcode){
        $this->zipcode = $zipcode;
        return $this;
	}

    /** @param string */
	public function getCity(){
		return $this->city;
	}

    /** @param string 
     *  @return $this 
     * */
	public function setCity($city){
        $this->city = $city;
        return $this;
	}

    /** @return string an ISO 3166-1 alpha-2 code */
	public function getCountryCode(){
		return $this->countryCode;
	}

    /** @param string an ISO 3166-1 alpha-2 code 
     *  @return $this 
     * */    
	public function setCountryCode($countryCode){
		$this->countryCode = $countryCode;
	}

    /**
     * @param string|null 
     * @return mixed 
     * */
	public function getAdditionalData($key){
		return $key ? $this->additionalData[$key] : $this->additionalData;
	}

    /** @param string
     *  @param mixed
     *  @return $this 
     * */    
	public function setAdditionalData($key, $data){
        $this->additionalData[$key] = $data;
        return $this;
	}

    /**
     * @return array
     * */
    public function toArray() {
        return json_decode($this->serializer->serialize($this, 'json'), true);
    }

    /** @param int 
     *  @return \Thelia\Model\OrderAddress
     * */
    public function toOrderAddress($customerId)
    {

        if (!$customerId) {
            throw new \InvalidArgumentException("customerId is mandatory");
        }

        if (!is_int($customerId)) {
            throw new \InvalidArgumentException("customerId must be an int");
        }

        $customer = CustomerQuery::create()->findOneById($customerId);

        if ($customer === null) {
            throw new Exception("customer with id " . $customerId . " doesn't exist");
        }


        $orderAddress = new OrderAddress();
        $country = CountryQuery::create()->findOneByIsoalpha2($this->countryCode);
        $countryId = $country !== null ? $country->getId() : null;

        $orderAddress
        ->setCustomerTitleId($customer->getTitleId())
        ->setFirstname($customer->getFirstname())
        ->setLastname($customer->getLastname())
        ->setCompany($this->company)
        ->setAddress1($this->address1)
        ->setAddress2($this->address2)
        ->setAddress3($this->address3)
        ->setZipcode($this->zipcode)
        ->setCity($this->city)
        ->setCountryId($countryId);

        return $orderAddress;
    }
}                    
