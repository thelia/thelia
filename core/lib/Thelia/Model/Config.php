<?php

namespace Thelia\Model;

use Thelia\Model\Base\Base;

class Config extends Base
{
    protected $name;
    protected $value;
    protected $secure;
    protected $hidden;


    protected $properties = array(
        "name",
        "value",
        "secure",
        "hidden"
    );

    public function read($search, $default)
    {
       $result = $this->findOneBy("name",$search);

       return $result ? $result->name : $default;
    }
}
