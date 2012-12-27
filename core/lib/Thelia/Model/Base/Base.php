<?php

namespace Thelia\Model\Base;

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
     * @var string date when the record had been updated
     */
    protected $updated_at;
    

        /**
     *
     * @var string date when the record had been saved
     */
    protected $created_at;
    
    
    

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
    
    public function getUpdatedAt() {
        return $this->updated_at;
    }

    public function setUpdatedAt($updated_at) {
        $this->updated_at = $updated_at;
    }

    public function getCreatedAt() {
        return $this->created_at;
    }

    public function setCreatedAt($created_at) {
        $this->created_at = $created_at;
    }
    
    /**
     * 
     * @return \NotORM
     */
    public function getDb() {
        return $this->db;
    }

    public function getTable() {
        return $this->table;
    }

        
    public function save()
    {
        $this->updated_at = $this->created_at = date('Y-m-d H:i:s');
    }
    
    /**
     * 
     * @return string name of the current table
     */
    protected function getTableName()
    {   
        return $this->underscore(__CLASS__);
    }
    
    /**
     * 
     * extract from symfony 1.4
     * 
     * change camelized wirnd into underscore word. 
     * 
     * ex : AttributeCategory => attribute_category
     * 
     * @param string $camel_cased_word
     * @return string
     */
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
