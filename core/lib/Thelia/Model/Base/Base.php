<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Model\Base;

use Thelia\Exception\MemberAccessException;

/**
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
abstract class Base
{
    /**
     * Primary key
     * @var int
     */
    protected $id;
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
      * base properties for all models
      *
      * @var array
      */
    private $baseProperties = array(
        "created_at",
        "updated_at"
    );

        /**
     *
     *
     *
     * @param \NotORM $NotORM
     */
    public function __construct(\NotORM $NotORM)
    {
        $this->db = $NotORM;
        $this->table = $this->getTableName();
    }

    public function __call($name, $arguments) {
        if (substr($name,0,3) == "get") {
            return $this->_get($this->underscore(substr($name,3)));
        }

        if (substr($name,0,3) == "set") {
            return $this->_set($this->underscore(substr($name,3)), $arguments[0]);
        }
        $calee = next(debug_backtrace());
        throw new MemberAccessException(sprintf("Call to undefined method %s->%s in %s on line %s",  $calee['class'], $name,$calee['file'],$calee['line']));
    }

    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    private function getBaseProperties()
    {
        return $this->baseProperties;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     *
     * @return \NotORM
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     *
     * @return string Name of the current Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     *
     * @return \NotORM
     */
    public function getConnection()
    {
        return $this->db;
    }

    private function _get($property)
    {
        if (!property_exists($this, $property)) {
            throw new \InvalidArgumentException($property." property does not exists");
        }

        return $this->$property;
    }

    private function _set($property, $value)
    {
        if (!property_exists($this, $property)) {
            throw new \InvalidArgumentException($property." property does not exists");
        }

        $this->$property = $value;
    }

    /**
     * Persist data in current table
     */
    public function save()
    {
        if ($this->isNew()) {
            $this->updated_at = $this->created_at = date('Y-m-d H:i:s');
        } else {
            $this->updated_at = date("Y-m-d H:i:s");
        }

        $values = $this->prepare();
        $table = $this->getTable();
        $this->getConnection()->$table()->insert_update(
                array("id", $this->getId()),
                $values,
                $values
        );

    }

    /**
     *
     * Find record by primary key
     *
     * @param  int            $pk
     * @return \NotORM_Result
     */
    public function find($pk)
    {
        $table = $this->getTable();

        return $this->getConnection()->$table()->where("id", $pk);
    }

    /**
     *
     * Find record for a specific column
     *
     * @param  mixed                     $column column name
     * @param  mixed                     $search value searching
     * @return \NotORM_Result
     * @throws \InvalidArgumentException column name cannot be empty
     */
    public function findBy($column, $search)
    {

        if (empty($column)) {
            throw new \InvalidArgumentException("Column name cannot be emtpy");
        }

        $table = $this->getTable();

        $result =  $this->getConnection()->$table()->where($column, $search);

        return $this->parseQuery($result);
    }

    /**
     *
     * Find record for a specific column
     *
     * @param  mixed                     $column column name
     * @param  mixed                     $search value searching
     * @return \NotORM_Result
     * @throws \InvalidArgumentException column name cannot be empty
     */
    public function findOneBy($column, $search)
    {

        if (empty($column)) {
            throw new \InvalidArgumentException("Column name cannot be emtpy");
        }

        $table = $this->getTable();

        $result =  $this->getConnection()->$table()->where($column, $search)->limit(1);

        $return = $this->parseQuery($result);

        return count($return) ? $return[0] : null ;
    }

    public function delete()
    {
        if ($this->isNew()) {
            throw new \RuntimeException("Cannot delete row. id is empty");
        }

        $table = $this->getTable();

        return $this->getConnection()->$table()
                ->where("id", $this->getId())
                ->delete();
    }

    /**
     *
     * @param \NotORM_Result $results
     * @return array
     */
    private function parseQuery(\NotORM_Result $results)
    {
        $return = array();
        $properties = array_merge($this->getBaseProperties(), $this->getProperties());

        // @TODO : change hard code assignation
        array_push($properties, "id");
        foreach ($results as $result) {
            $class = new static($this->getConnection());
            foreach($properties as $property)
            {
                call_user_func(array($class, "set".ucfirst(self::camelize($property))), $result[$property]);
            }
            array_push($return, $class);
        }

        return $return;
    }

    /**
     *
     * prepare an array for persisting data
     *
     * @return Array
     */
    private function prepare()
    {
        $properties = array_merge($this->getBaseProperties(), $this->getProperties());

        $values = array();

        foreach ($properties as $property) {
            $values[$property] = $this->$property;
        }

        return $values;
    }

    public function isNew()
    {
        return $this->getId() ? false:true;
    }

    /**
     *
     * @return string name of the current table
     */
    protected function getTableName()
    {
        $info = new \ReflectionObject($this);
        return $this->underscore($info->getShortName());
    }

    /**
     *
     * extract from symfony 1.4
     *
     * change camelized wirnd into underscore word.
     *
     * ex : AttributeCategory => attribute_category
     *
     * @param  string $camel_cased_word
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

    /**
    * Returns a camelized string from a lower case and underscored string by replaceing slash with
    * double-colon and upper-casing each letter preceded by an underscore.
    *
    * @param  string $lower_case_and_underscored_word  String to camelize.
    *
    * @return string Camelized string.
    */
    public static function camelize($lower_case_and_underscored_word)
    {
        $tmp = $lower_case_and_underscored_word;
        $tmp = self::pregtr($tmp, array('#/(.?)#e'    => "'::'.strtoupper('\\1')",'/(^|_|-)+(.)/e' => "strtoupper('\\2')"));

        return $tmp;
    }

    public static function pregtr($search, $replacePairs)
    {
        return preg_replace(array_keys($replacePairs), array_values($replacePairs), $search);
    }
}
