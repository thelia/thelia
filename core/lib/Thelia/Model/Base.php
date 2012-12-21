<?php

namespace Thelia\Model;

abstract class Base
{
    /**
     *
     * @var \NotORM
     */
    protected $db;
    
    /**
     *
     * @var string
     */
    protected $table;

    /**
     * 
     * 
     * 
     * @param \NotORM $NotORM
     */
    public function __construct(\NotORM $NotORM) {
        $this->db = $NotORM;
        $this->table = $this->getTableName();
    }
    
    protected function getTableName()
    {   
        return $this->underscore(__CLASS__);
    }
    
    protected function underscore($camel_cased_word)
    {
        $tmp = $camel_cased_word;
        $tmp = str_replace('::', '/', $tmp);
        $tmp = self::pregtr($tmp, array('/([A-Z]+)([A-Z][a-z])/' => '\\1_\\2',
                                               '/([a-z\d])([A-Z])/'     => '\\1_\\2'));
        return strtolower($tmp);
    }
    
    public static function pregtr($search, $replacePairs)
    {
        return preg_replace(array_keys($replacePairs), array_values($replacePairs), $search);
    }
}
