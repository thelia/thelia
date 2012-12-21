<?php

namespace Thelia\Database;

class NotORM extends \NotORM
{
    
    public function setCache(\NotORM_Cache $cache)
    {
        $this->cache = $cache;
    }
    
    public function setDebug($debug)
    {
        if(is_callable($debug))
        {
            $this->debug = $debug;
        } else {
            $this->debug = true;
        }
    }
}