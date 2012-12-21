<?php

namespace Thelia\Model;

use Thelia\Model\Base;

class Config extends Base
{
    
    public function read($search, $default)
    {
       return $this->db->config()->where("name",$search)->fetch()?:$default;
    }
}
