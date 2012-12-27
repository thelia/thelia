<?php

namespace Thelia\Model;

use Thelia\Model\Base\Base;

class Accessory extends Base
{
    protected $id;
    protected $product_id;
    protected $accessory;
    protected $position;
    
    protected $properties = array(
        "id",
        "product_id",
        "accessory",
        "position"
    );  
    
    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getProductId() {
        return $this->product_id;
    }

    public function setProductId($product_id) {
        $this->product_id = $product_id;
    }

    public function getAccessory() {
        return $this->accessory;
    }

    public function setAccessory($accessory) {
        $this->accessory = $accessory;
    }

    public function getPosition() {
        return $this->position;
    }

    public function setPosition($position) {
        $this->position = $position;
    }


    
}
