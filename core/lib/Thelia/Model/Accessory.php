<?php

namespace Thelia\Model;

use Thelia\Model\Base\Base;

class Accessory extends Base
{

    protected $product_id;
    protected $accessory;
    protected $position;

    protected $properties = array(
        "product_id",
        "accessory",
        "position"
    );

    public function getProductId()
    {
        return $this->product_id;
    }

    public function setProductId($product_id)
    {
        $this->product_id = $product_id;
    }

    public function getAccessory()
    {
        return $this->accessory;
    }

    public function setAccessory($accessory)
    {
        $this->accessory = $accessory;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setPosition($position)
    {
        $this->position = $position;
    }

}
