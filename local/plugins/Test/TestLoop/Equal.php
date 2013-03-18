<?php

namespace Test\TestLoop;

use Thelia\Tpex\Element\TestLoop\BaseTestLoop;

class Equal extends BaseTestLoop
{

    public function exec($variable, $value)
    {
        return $variable == $value;
    }
}