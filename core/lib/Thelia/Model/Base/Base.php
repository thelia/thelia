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

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 * Abstract class. All model classes inherit from this class
 *
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
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     *
     * Short name of the current class instance
     *
     * @var string
     */
    protected $className;

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
     * @param \NotORM                                                   $NotORM
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct(\NotORM $NotORM, ContainerInterface $container)
    {
        $this->db = $NotORM;
        $this->className = $this->getShortClassName();
        $this->table = $this->underscore($this->className);
        $this->container = $container;
    }

    /**
     *
     * return the date and time when the record had been updated.
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        if ($this->updated_at) {
            return new \DateTime($this->updated_at);
        } else {
            return null;
        }
    }

    /**
     *
     * return the raw date time when the record had been updated
     *
     * @return string
     */
    public function getRawUpadtedAt()
    {
        return $this->updated_at;
    }

    /**
     *
     * string date time when the record had been updated
     *
     * @param string $updated_at
     */
    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;
    }

    /**
     *
     * return the date and time when the record had been created.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        if ($this->created_at) {
            return new \DateTime($this->created_at);
        } else {
            return null;
        }
    }

    /**
     *
     * return the raw date time when the record had been created
     *
     * @return string
     */
    public function getRawCreatedAt()
    {
        return $this->created_at;
    }

    /**
     *
     * string date time when the record had been updated
     *
     * @param string $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    /**
     *
     * return the base properties like created_at, updated_at
     *
     * @return array
     */
    private function getBaseProperties()
    {
        return $this->baseProperties;
    }

    /**
     *
     * return the public properties of the current model.
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     *
     * return the id of the current record
     *
     * @return type
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * fix the id if needed
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
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

    /**
     *
     * @return Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     *
     * return the short name (without namespace) of the current instance class name.
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * Persist data in current table
     *
     * Same method for saving or updating a record
     *
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
     * @param  int    $pk
     * @return Object
     */
    public function find($pk)
    {
        $table = $this->getTable();

        $result =  $this->getConnection()->$table()->where("id", $pk);

         return $this->parseOneQuery($result);
    }

    /**
     *
     * Find record for a specific column
     *
     * @param  mixed                     $column column name
     * @param  mixed                     $search value searching
     * @return array
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
     * Find the first record for a specific column
     *
     * @param  mixed                     $column column name
     * @param  mixed                     $search value searching
     * @return Object
     * @throws \InvalidArgumentException column name cannot be empty
     */
    public function findOneBy($column, $search)
    {

        if (empty($column)) {
            throw new \InvalidArgumentException("Column name cannot be emtpy");
        }

        $table = $this->getTable();

        $result =  $this->getConnection()->$table()->where($column, $search)->limit(1);

        return $this->parseOneQuery($result);
    }

    /**
     *
     * delete the current record
     *
     * @return int
     * @throws \RuntimeException
     */
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
     * @param  \NotORM_Result $results
     * @return array
     */
    private function parseQuery(\NotORM_Result $results)
    {
        $return = array();
        $properties = array_merge($this->getBaseProperties(), $this->getProperties());

        // @TODO : change hard code assignation
        array_push($properties, "id");
        foreach ($results as $result) {
            //$class = new static($this->getConnection());
            $class = $this->getContainer()->get("model.".$this->getClassName());

            foreach ($properties as $property) {
                call_user_func(array($class, "set".ucfirst(self::camelize($property))), $result[$property]);
            }
            array_push($return, $class);
        }

        return $return;
    }

    private function parseOneQuery(\NotORM_Result $results)
    {
        $return = $this->parseQuery($results);

        return count($return) ? $return[0] : null ;
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

    /**
     *
     * check if the current record is new or not.
     *
     * @return boolean
     */
    public function isNew()
    {
        return $this->getId() ? false:true;
    }

    /**
     *
     * @return string name of the current table
     */
    protected function getShortClassName()
    {
        $info = new \ReflectionObject($this);

        return $info->getShortName();
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
