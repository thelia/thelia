<?php

namespace Thelia\Model\Base;

use \DateTime;
use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;
use Thelia\Model\Customer as ChildCustomer;
use Thelia\Model\CustomerQuery as ChildCustomerQuery;
use Thelia\Model\CustomerVersionQuery as ChildCustomerVersionQuery;
use Thelia\Model\Map\CustomerVersionTableMap;

abstract class CustomerVersion implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\CustomerVersionTableMap';


    /**
     * attribute to determine if this object has previously been saved.
     * @var boolean
     */
    protected $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     * @var boolean
     */
    protected $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     * @var array
     */
    protected $modifiedColumns = array();

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     * @var array
     */
    protected $virtualColumns = array();

    /**
     * The value for the id field.
     * @var        int
     */
    protected $id;

    /**
     * The value for the title_id field.
     * @var        int
     */
    protected $title_id;

    /**
     * The value for the lang_id field.
     * @var        int
     */
    protected $lang_id;

    /**
     * The value for the ref field.
     * @var        string
     */
    protected $ref;

    /**
     * The value for the firstname field.
     * @var        string
     */
    protected $firstname;

    /**
     * The value for the lastname field.
     * @var        string
     */
    protected $lastname;

    /**
     * The value for the email field.
     * @var        string
     */
    protected $email;

    /**
     * The value for the password field.
     * @var        string
     */
    protected $password;

    /**
     * The value for the algo field.
     * @var        string
     */
    protected $algo;

    /**
     * The value for the reseller field.
     * @var        int
     */
    protected $reseller;

    /**
     * The value for the sponsor field.
     * @var        string
     */
    protected $sponsor;

    /**
     * The value for the discount field.
     * Note: this column has a database default value of: '0.000000'
     * @var        string
     */
    protected $discount;

    /**
     * The value for the remember_me_token field.
     * @var        string
     */
    protected $remember_me_token;

    /**
     * The value for the remember_me_serial field.
     * @var        string
     */
    protected $remember_me_serial;

    /**
     * The value for the enable field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $enable;

    /**
     * The value for the confirmation_token field.
     * @var        string
     */
    protected $confirmation_token;

    /**
     * The value for the created_at field.
     * @var        string
     */
    protected $created_at;

    /**
     * The value for the updated_at field.
     * @var        string
     */
    protected $updated_at;

    /**
     * The value for the version field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $version;

    /**
     * The value for the version_created_at field.
     * @var        string
     */
    protected $version_created_at;

    /**
     * The value for the version_created_by field.
     * @var        string
     */
    protected $version_created_by;

    /**
     * The value for the order_ids field.
     * @var        array
     */
    protected $order_ids;

    /**
     * The unserialized $order_ids value - i.e. the persisted object.
     * This is necessary to avoid repeated calls to unserialize() at runtime.
     * @var object
     */
    protected $order_ids_unserialized;

    /**
     * The value for the order_versions field.
     * @var        array
     */
    protected $order_versions;

    /**
     * The unserialized $order_versions value - i.e. the persisted object.
     * This is necessary to avoid repeated calls to unserialize() at runtime.
     * @var object
     */
    protected $order_versions_unserialized;

    /**
     * @var        Customer
     */
    protected $aCustomer;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->discount = '0.000000';
        $this->enable = 0;
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\CustomerVersion object.
     * @see applyDefaults()
     */
    public function __construct()
    {
        $this->applyDefaultValues();
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !!$this->modifiedColumns;
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return $this->modifiedColumns && isset($this->modifiedColumns[$col]);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return $this->modifiedColumns ? array_keys($this->modifiedColumns) : [];
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return boolean true, if the object has never been persisted.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Setter for the isNew attribute.  This method will be called
     * by Propel-generated children and objects.
     *
     * @param boolean $b the state of the object.
     */
    public function setNew($b)
    {
        $this->new = (Boolean) $b;
    }

    /**
     * Whether this object has been deleted.
     * @return boolean The deleted state of this object.
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Specify whether this object has been deleted.
     * @param  boolean $b The deleted state of this object.
     * @return void
     */
    public function setDeleted($b)
    {
        $this->deleted = (Boolean) $b;
    }

    /**
     * Sets the modified state for the object to be false.
     * @param  string $col If supplied, only the specified column is reset.
     * @return void
     */
    public function resetModified($col = null)
    {
        if (null !== $col) {
            if (isset($this->modifiedColumns[$col])) {
                unset($this->modifiedColumns[$col]);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>CustomerVersion</code> instance.  If
     * <code>obj</code> is an instance of <code>CustomerVersion</code>, delegates to
     * <code>equals(CustomerVersion)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param  mixed   $obj The object to compare to.
     * @return boolean Whether equal to the object specified.
     */
    public function equals($obj)
    {
        $thisclazz = get_class($this);
        if (!is_object($obj) || !($obj instanceof $thisclazz)) {
            return false;
        }

        if ($this === $obj) {
            return true;
        }

        if (null === $this->getPrimaryKey()
            || null === $obj->getPrimaryKey())  {
            return false;
        }

        return $this->getPrimaryKey() === $obj->getPrimaryKey();
    }

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return int Hashcode
     */
    public function hashCode()
    {
        if (null !== $this->getPrimaryKey()) {
            return crc32(serialize($this->getPrimaryKey()));
        }

        return crc32(serialize(clone $this));
    }

    /**
     * Get the associative array of the virtual columns in this object
     *
     * @return array
     */
    public function getVirtualColumns()
    {
        return $this->virtualColumns;
    }

    /**
     * Checks the existence of a virtual column in this object
     *
     * @param  string  $name The virtual column name
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return array_key_exists($name, $this->virtualColumns);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @param  string $name The virtual column name
     * @return mixed
     *
     * @throws PropelException
     */
    public function getVirtualColumn($name)
    {
        if (!$this->hasVirtualColumn($name)) {
            throw new PropelException(sprintf('Cannot get value of inexistent virtual column %s.', $name));
        }

        return $this->virtualColumns[$name];
    }

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name  The virtual column name
     * @param mixed  $value The value to give to the virtual column
     *
     * @return CustomerVersion The current object, for fluid interface
     */
    public function setVirtualColumn($name, $value)
    {
        $this->virtualColumns[$name] = $value;

        return $this;
    }

    /**
     * Logs a message using Propel::log().
     *
     * @param  string  $msg
     * @param  int     $priority One of the Propel::LOG_* logging levels
     * @return boolean
     */
    protected function log($msg, $priority = Propel::LOG_INFO)
    {
        return Propel::log(get_class($this) . ': ' . $msg, $priority);
    }

    /**
     * Populate the current object from a string, using a given parser format
     * <code>
     * $book = new Book();
     * $book->importFrom('JSON', '{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param mixed $parser A AbstractParser instance,
     *                       or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param string $data The source data to import from
     *
     * @return CustomerVersion The current object, for fluid interface
     */
    public function importFrom($parser, $data)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        $this->fromArray($parser->toArray($data), TableMap::TYPE_PHPNAME);

        return $this;
    }

    /**
     * Export the current object properties to a string, using a given parser format
     * <code>
     * $book = BookQuery::create()->findPk(9012);
     * echo $book->exportTo('JSON');
     *  => {"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param  mixed   $parser                 A AbstractParser instance, or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param  boolean $includeLazyLoadColumns (optional) Whether to include lazy load(ed) columns. Defaults to TRUE.
     * @return string  The exported data
     */
    public function exportTo($parser, $includeLazyLoadColumns = true)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $parser->fromArray($this->toArray(TableMap::TYPE_PHPNAME, $includeLazyLoadColumns, array(), true));
    }

    /**
     * Clean up internal collections prior to serializing
     * Avoids recursive loops that turn into segmentation faults when serializing
     */
    public function __sleep()
    {
        $this->clearAllReferences();

        return array_keys(get_object_vars($this));
    }

    /**
     * Get the [id] column value.
     *
     * @return   int
     */
    public function getId()
    {

        return $this->id;
    }

    /**
     * Get the [title_id] column value.
     *
     * @return   int
     */
    public function getTitleId()
    {

        return $this->title_id;
    }

    /**
     * Get the [lang_id] column value.
     *
     * @return   int
     */
    public function getLangId()
    {

        return $this->lang_id;
    }

    /**
     * Get the [ref] column value.
     *
     * @return   string
     */
    public function getRef()
    {

        return $this->ref;
    }

    /**
     * Get the [firstname] column value.
     *
     * @return   string
     */
    public function getFirstname()
    {

        return $this->firstname;
    }

    /**
     * Get the [lastname] column value.
     *
     * @return   string
     */
    public function getLastname()
    {

        return $this->lastname;
    }

    /**
     * Get the [email] column value.
     *
     * @return   string
     */
    public function getEmail()
    {

        return $this->email;
    }

    /**
     * Get the [password] column value.
     *
     * @return   string
     */
    public function getPassword()
    {

        return $this->password;
    }

    /**
     * Get the [algo] column value.
     *
     * @return   string
     */
    public function getAlgo()
    {

        return $this->algo;
    }

    /**
     * Get the [reseller] column value.
     *
     * @return   int
     */
    public function getReseller()
    {

        return $this->reseller;
    }

    /**
     * Get the [sponsor] column value.
     *
     * @return   string
     */
    public function getSponsor()
    {

        return $this->sponsor;
    }

    /**
     * Get the [discount] column value.
     *
     * @return   string
     */
    public function getDiscount()
    {

        return $this->discount;
    }

    /**
     * Get the [remember_me_token] column value.
     *
     * @return   string
     */
    public function getRememberMeToken()
    {

        return $this->remember_me_token;
    }

    /**
     * Get the [remember_me_serial] column value.
     *
     * @return   string
     */
    public function getRememberMeSerial()
    {

        return $this->remember_me_serial;
    }

    /**
     * Get the [enable] column value.
     *
     * @return   int
     */
    public function getEnable()
    {

        return $this->enable;
    }

    /**
     * Get the [confirmation_token] column value.
     *
     * @return   string
     */
    public function getConfirmationToken()
    {

        return $this->confirmation_token;
    }

    /**
     * Get the [optionally formatted] temporal [created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->created_at;
        } else {
            return $this->created_at instanceof \DateTime ? $this->created_at->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [updated_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getUpdatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->updated_at;
        } else {
            return $this->updated_at instanceof \DateTime ? $this->updated_at->format($format) : null;
        }
    }

    /**
     * Get the [version] column value.
     *
     * @return   int
     */
    public function getVersion()
    {

        return $this->version;
    }

    /**
     * Get the [optionally formatted] temporal [version_created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getVersionCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->version_created_at;
        } else {
            return $this->version_created_at instanceof \DateTime ? $this->version_created_at->format($format) : null;
        }
    }

    /**
     * Get the [version_created_by] column value.
     *
     * @return   string
     */
    public function getVersionCreatedBy()
    {

        return $this->version_created_by;
    }

    /**
     * Get the [order_ids] column value.
     *
     * @return   array
     */
    public function getOrderIds()
    {
        if (null === $this->order_ids_unserialized) {
            $this->order_ids_unserialized = array();
        }
        if (!$this->order_ids_unserialized && null !== $this->order_ids) {
            $order_ids_unserialized = substr($this->order_ids, 2, -2);
            $this->order_ids_unserialized = $order_ids_unserialized ? explode(' | ', $order_ids_unserialized) : array();
        }

        return $this->order_ids_unserialized;
    }

    /**
     * Test the presence of a value in the [order_ids] array column value.
     * @param      mixed $value
     *
     * @return boolean
     */
    public function hasOrderId($value)
    {
        return in_array($value, $this->getOrderIds());
    } // hasOrderId()

    /**
     * Get the [order_versions] column value.
     *
     * @return   array
     */
    public function getOrderVersions()
    {
        if (null === $this->order_versions_unserialized) {
            $this->order_versions_unserialized = array();
        }
        if (!$this->order_versions_unserialized && null !== $this->order_versions) {
            $order_versions_unserialized = substr($this->order_versions, 2, -2);
            $this->order_versions_unserialized = $order_versions_unserialized ? explode(' | ', $order_versions_unserialized) : array();
        }

        return $this->order_versions_unserialized;
    }

    /**
     * Test the presence of a value in the [order_versions] array column value.
     * @param      mixed $value
     *
     * @return boolean
     */
    public function hasOrderVersion($value)
    {
        return in_array($value, $this->getOrderVersions());
    } // hasOrderVersion()

    /**
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[CustomerVersionTableMap::ID] = true;
        }

        if ($this->aCustomer !== null && $this->aCustomer->getId() !== $v) {
            $this->aCustomer = null;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [title_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setTitleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->title_id !== $v) {
            $this->title_id = $v;
            $this->modifiedColumns[CustomerVersionTableMap::TITLE_ID] = true;
        }


        return $this;
    } // setTitleId()

    /**
     * Set the value of [lang_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setLangId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->lang_id !== $v) {
            $this->lang_id = $v;
            $this->modifiedColumns[CustomerVersionTableMap::LANG_ID] = true;
        }


        return $this;
    } // setLangId()

    /**
     * Set the value of [ref] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->ref !== $v) {
            $this->ref = $v;
            $this->modifiedColumns[CustomerVersionTableMap::REF] = true;
        }


        return $this;
    } // setRef()

    /**
     * Set the value of [firstname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setFirstname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->firstname !== $v) {
            $this->firstname = $v;
            $this->modifiedColumns[CustomerVersionTableMap::FIRSTNAME] = true;
        }


        return $this;
    } // setFirstname()

    /**
     * Set the value of [lastname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setLastname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lastname !== $v) {
            $this->lastname = $v;
            $this->modifiedColumns[CustomerVersionTableMap::LASTNAME] = true;
        }


        return $this;
    } // setLastname()

    /**
     * Set the value of [email] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setEmail($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->email !== $v) {
            $this->email = $v;
            $this->modifiedColumns[CustomerVersionTableMap::EMAIL] = true;
        }


        return $this;
    } // setEmail()

    /**
     * Set the value of [password] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setPassword($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->password !== $v) {
            $this->password = $v;
            $this->modifiedColumns[CustomerVersionTableMap::PASSWORD] = true;
        }


        return $this;
    } // setPassword()

    /**
     * Set the value of [algo] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setAlgo($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->algo !== $v) {
            $this->algo = $v;
            $this->modifiedColumns[CustomerVersionTableMap::ALGO] = true;
        }


        return $this;
    } // setAlgo()

    /**
     * Set the value of [reseller] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setReseller($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->reseller !== $v) {
            $this->reseller = $v;
            $this->modifiedColumns[CustomerVersionTableMap::RESELLER] = true;
        }


        return $this;
    } // setReseller()

    /**
     * Set the value of [sponsor] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setSponsor($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->sponsor !== $v) {
            $this->sponsor = $v;
            $this->modifiedColumns[CustomerVersionTableMap::SPONSOR] = true;
        }


        return $this;
    } // setSponsor()

    /**
     * Set the value of [discount] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setDiscount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->discount !== $v) {
            $this->discount = $v;
            $this->modifiedColumns[CustomerVersionTableMap::DISCOUNT] = true;
        }


        return $this;
    } // setDiscount()

    /**
     * Set the value of [remember_me_token] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setRememberMeToken($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->remember_me_token !== $v) {
            $this->remember_me_token = $v;
            $this->modifiedColumns[CustomerVersionTableMap::REMEMBER_ME_TOKEN] = true;
        }


        return $this;
    } // setRememberMeToken()

    /**
     * Set the value of [remember_me_serial] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setRememberMeSerial($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->remember_me_serial !== $v) {
            $this->remember_me_serial = $v;
            $this->modifiedColumns[CustomerVersionTableMap::REMEMBER_ME_SERIAL] = true;
        }


        return $this;
    } // setRememberMeSerial()

    /**
     * Set the value of [enable] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setEnable($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->enable !== $v) {
            $this->enable = $v;
            $this->modifiedColumns[CustomerVersionTableMap::ENABLE] = true;
        }


        return $this;
    } // setEnable()

    /**
     * Set the value of [confirmation_token] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setConfirmationToken($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->confirmation_token !== $v) {
            $this->confirmation_token = $v;
            $this->modifiedColumns[CustomerVersionTableMap::CONFIRMATION_TOKEN] = true;
        }


        return $this;
    } // setConfirmationToken()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[CustomerVersionTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[CustomerVersionTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[CustomerVersionTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[CustomerVersionTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[CustomerVersionTableMap::VERSION_CREATED_BY] = true;
        }


        return $this;
    } // setVersionCreatedBy()

    /**
     * Set the value of [order_ids] column.
     *
     * @param      array $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setOrderIds($v)
    {
        if ($this->order_ids_unserialized !== $v) {
            $this->order_ids_unserialized = $v;
            $this->order_ids = '| ' . implode(' | ', $v) . ' |';
            $this->modifiedColumns[CustomerVersionTableMap::ORDER_IDS] = true;
        }


        return $this;
    } // setOrderIds()

    /**
     * Adds a value to the [order_ids] array column value.
     * @param      mixed $value
     *
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function addOrderId($value)
    {
        $currentArray = $this->getOrderIds();
        $currentArray []= $value;
        $this->setOrderIds($currentArray);

        return $this;
    } // addOrderId()

    /**
     * Removes a value from the [order_ids] array column value.
     * @param      mixed $value
     *
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function removeOrderId($value)
    {
        $targetArray = array();
        foreach ($this->getOrderIds() as $element) {
            if ($element != $value) {
                $targetArray []= $element;
            }
        }
        $this->setOrderIds($targetArray);

        return $this;
    } // removeOrderId()

    /**
     * Set the value of [order_versions] column.
     *
     * @param      array $v new value
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function setOrderVersions($v)
    {
        if ($this->order_versions_unserialized !== $v) {
            $this->order_versions_unserialized = $v;
            $this->order_versions = '| ' . implode(' | ', $v) . ' |';
            $this->modifiedColumns[CustomerVersionTableMap::ORDER_VERSIONS] = true;
        }


        return $this;
    } // setOrderVersions()

    /**
     * Adds a value to the [order_versions] array column value.
     * @param      mixed $value
     *
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function addOrderVersion($value)
    {
        $currentArray = $this->getOrderVersions();
        $currentArray []= $value;
        $this->setOrderVersions($currentArray);

        return $this;
    } // addOrderVersion()

    /**
     * Removes a value from the [order_versions] array column value.
     * @param      mixed $value
     *
     * @return   \Thelia\Model\CustomerVersion The current object (for fluent API support)
     */
    public function removeOrderVersion($value)
    {
        $targetArray = array();
        foreach ($this->getOrderVersions() as $element) {
            if ($element != $value) {
                $targetArray []= $element;
            }
        }
        $this->setOrderVersions($targetArray);

        return $this;
    } // removeOrderVersion()

    /**
     * Indicates whether the columns in this object are only set to default values.
     *
     * This method can be used in conjunction with isModified() to indicate whether an object is both
     * modified _and_ has some values set which are non-default.
     *
     * @return boolean Whether the columns in this object are only been set with default values.
     */
    public function hasOnlyDefaultValues()
    {
            if ($this->discount !== '0.000000') {
                return false;
            }

            if ($this->enable !== 0) {
                return false;
            }

            if ($this->version !== 0) {
                return false;
            }

        // otherwise, everything was equal, so return TRUE
        return true;
    } // hasOnlyDefaultValues()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array   $row       The row returned by DataFetcher->fetch().
     * @param int     $startcol  0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @param string  $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                  One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                            TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false, $indexType = TableMap::TYPE_NUM)
    {
        try {


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : CustomerVersionTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : CustomerVersionTableMap::translateFieldName('TitleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->title_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : CustomerVersionTableMap::translateFieldName('LangId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lang_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : CustomerVersionTableMap::translateFieldName('Ref', TableMap::TYPE_PHPNAME, $indexType)];
            $this->ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : CustomerVersionTableMap::translateFieldName('Firstname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->firstname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : CustomerVersionTableMap::translateFieldName('Lastname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lastname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : CustomerVersionTableMap::translateFieldName('Email', TableMap::TYPE_PHPNAME, $indexType)];
            $this->email = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : CustomerVersionTableMap::translateFieldName('Password', TableMap::TYPE_PHPNAME, $indexType)];
            $this->password = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : CustomerVersionTableMap::translateFieldName('Algo', TableMap::TYPE_PHPNAME, $indexType)];
            $this->algo = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : CustomerVersionTableMap::translateFieldName('Reseller', TableMap::TYPE_PHPNAME, $indexType)];
            $this->reseller = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : CustomerVersionTableMap::translateFieldName('Sponsor', TableMap::TYPE_PHPNAME, $indexType)];
            $this->sponsor = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : CustomerVersionTableMap::translateFieldName('Discount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->discount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : CustomerVersionTableMap::translateFieldName('RememberMeToken', TableMap::TYPE_PHPNAME, $indexType)];
            $this->remember_me_token = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : CustomerVersionTableMap::translateFieldName('RememberMeSerial', TableMap::TYPE_PHPNAME, $indexType)];
            $this->remember_me_serial = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : CustomerVersionTableMap::translateFieldName('Enable', TableMap::TYPE_PHPNAME, $indexType)];
            $this->enable = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : CustomerVersionTableMap::translateFieldName('ConfirmationToken', TableMap::TYPE_PHPNAME, $indexType)];
            $this->confirmation_token = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : CustomerVersionTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : CustomerVersionTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : CustomerVersionTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : CustomerVersionTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : CustomerVersionTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : CustomerVersionTableMap::translateFieldName('OrderIds', TableMap::TYPE_PHPNAME, $indexType)];
            $this->order_ids = $col;
            $this->order_ids_unserialized = null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : CustomerVersionTableMap::translateFieldName('OrderVersions', TableMap::TYPE_PHPNAME, $indexType)];
            $this->order_versions = $col;
            $this->order_versions_unserialized = null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 23; // 23 = CustomerVersionTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\CustomerVersion object", 0, $e);
        }
    }

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database.  It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @throws PropelException
     */
    public function ensureConsistency()
    {
        if ($this->aCustomer !== null && $this->id !== $this->aCustomer->getId()) {
            $this->aCustomer = null;
        }
    } // ensureConsistency

    /**
     * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
     *
     * This will only work if the object has been saved and has a valid primary key set.
     *
     * @param      boolean $deep (optional) Whether to also de-associated any related objects.
     * @param      ConnectionInterface $con (optional) The ConnectionInterface connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CustomerVersionTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildCustomerVersionQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aCustomer = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see CustomerVersion::setDeleted()
     * @see CustomerVersion::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerVersionTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildCustomerVersionQuery::create()
                ->filterByPrimaryKey($this->getPrimaryKey());
            $ret = $this->preDelete($con);
            if ($ret) {
                $deleteQuery->delete($con);
                $this->postDelete($con);
                $con->commit();
                $this->setDeleted(true);
            } else {
                $con->commit();
            }
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Persists this object to the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All modified related objects will also be persisted in the doSave()
     * method.  This method wraps all precipitate database operations in a
     * single transaction.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see doSave()
     */
    public function save(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerVersionTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
            } else {
                $ret = $ret && $this->preUpdate($con);
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                CustomerVersionTableMap::addInstanceToPool($this);
            } else {
                $affectedRows = 0;
            }
            $con->commit();

            return $affectedRows;
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Performs the work of inserting or updating the row in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see save()
     */
    protected function doSave(ConnectionInterface $con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;

            // We call the save method on the following object(s) if they
            // were passed to this object by their corresponding set
            // method.  This object relates to these object(s) by a
            // foreign key reference.

            if ($this->aCustomer !== null) {
                if ($this->aCustomer->isModified() || $this->aCustomer->isNew()) {
                    $affectedRows += $this->aCustomer->save($con);
                }
                $this->setCustomer($this->aCustomer);
            }

            if ($this->isNew() || $this->isModified()) {
                // persist changes
                if ($this->isNew()) {
                    $this->doInsert($con);
                } else {
                    $this->doUpdate($con);
                }
                $affectedRows += 1;
                $this->resetModified();
            }

            $this->alreadyInSave = false;

        }

        return $affectedRows;
    } // doSave()

    /**
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;


         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CustomerVersionTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::TITLE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`TITLE_ID`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::LANG_ID)) {
            $modifiedColumns[':p' . $index++]  = '`LANG_ID`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::REF)) {
            $modifiedColumns[':p' . $index++]  = '`REF`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`FIRSTNAME`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`LASTNAME`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::EMAIL)) {
            $modifiedColumns[':p' . $index++]  = '`EMAIL`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::PASSWORD)) {
            $modifiedColumns[':p' . $index++]  = '`PASSWORD`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::ALGO)) {
            $modifiedColumns[':p' . $index++]  = '`ALGO`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::RESELLER)) {
            $modifiedColumns[':p' . $index++]  = '`RESELLER`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::SPONSOR)) {
            $modifiedColumns[':p' . $index++]  = '`SPONSOR`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::DISCOUNT)) {
            $modifiedColumns[':p' . $index++]  = '`DISCOUNT`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::REMEMBER_ME_TOKEN)) {
            $modifiedColumns[':p' . $index++]  = '`REMEMBER_ME_TOKEN`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::REMEMBER_ME_SERIAL)) {
            $modifiedColumns[':p' . $index++]  = '`REMEMBER_ME_SERIAL`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::ENABLE)) {
            $modifiedColumns[':p' . $index++]  = '`ENABLE`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::CONFIRMATION_TOKEN)) {
            $modifiedColumns[':p' . $index++]  = '`CONFIRMATION_TOKEN`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::ORDER_IDS)) {
            $modifiedColumns[':p' . $index++]  = '`ORDER_IDS`';
        }
        if ($this->isColumnModified(CustomerVersionTableMap::ORDER_VERSIONS)) {
            $modifiedColumns[':p' . $index++]  = '`ORDER_VERSIONS`';
        }

        $sql = sprintf(
            'INSERT INTO `customer_version` (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case '`ID`':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case '`TITLE_ID`':
                        $stmt->bindValue($identifier, $this->title_id, PDO::PARAM_INT);
                        break;
                    case '`LANG_ID`':
                        $stmt->bindValue($identifier, $this->lang_id, PDO::PARAM_INT);
                        break;
                    case '`REF`':
                        $stmt->bindValue($identifier, $this->ref, PDO::PARAM_STR);
                        break;
                    case '`FIRSTNAME`':
                        $stmt->bindValue($identifier, $this->firstname, PDO::PARAM_STR);
                        break;
                    case '`LASTNAME`':
                        $stmt->bindValue($identifier, $this->lastname, PDO::PARAM_STR);
                        break;
                    case '`EMAIL`':
                        $stmt->bindValue($identifier, $this->email, PDO::PARAM_STR);
                        break;
                    case '`PASSWORD`':
                        $stmt->bindValue($identifier, $this->password, PDO::PARAM_STR);
                        break;
                    case '`ALGO`':
                        $stmt->bindValue($identifier, $this->algo, PDO::PARAM_STR);
                        break;
                    case '`RESELLER`':
                        $stmt->bindValue($identifier, $this->reseller, PDO::PARAM_INT);
                        break;
                    case '`SPONSOR`':
                        $stmt->bindValue($identifier, $this->sponsor, PDO::PARAM_STR);
                        break;
                    case '`DISCOUNT`':
                        $stmt->bindValue($identifier, $this->discount, PDO::PARAM_STR);
                        break;
                    case '`REMEMBER_ME_TOKEN`':
                        $stmt->bindValue($identifier, $this->remember_me_token, PDO::PARAM_STR);
                        break;
                    case '`REMEMBER_ME_SERIAL`':
                        $stmt->bindValue($identifier, $this->remember_me_serial, PDO::PARAM_STR);
                        break;
                    case '`ENABLE`':
                        $stmt->bindValue($identifier, $this->enable, PDO::PARAM_INT);
                        break;
                    case '`CONFIRMATION_TOKEN`':
                        $stmt->bindValue($identifier, $this->confirmation_token, PDO::PARAM_STR);
                        break;
                    case '`CREATED_AT`':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`UPDATED_AT`':
                        $stmt->bindValue($identifier, $this->updated_at ? $this->updated_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`VERSION`':
                        $stmt->bindValue($identifier, $this->version, PDO::PARAM_INT);
                        break;
                    case '`VERSION_CREATED_AT`':
                        $stmt->bindValue($identifier, $this->version_created_at ? $this->version_created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`VERSION_CREATED_BY`':
                        $stmt->bindValue($identifier, $this->version_created_by, PDO::PARAM_STR);
                        break;
                    case '`ORDER_IDS`':
                        $stmt->bindValue($identifier, $this->order_ids, PDO::PARAM_STR);
                        break;
                    case '`ORDER_VERSIONS`':
                        $stmt->bindValue($identifier, $this->order_versions, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @return Integer Number of updated rows
     * @see doSave()
     */
    protected function doUpdate(ConnectionInterface $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();

        return $selectCriteria->doUpdate($valuesCriteria, $con);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return mixed Value of field.
     */
    public function getByName($name, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = CustomerVersionTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch ($pos) {
            case 0:
                return $this->getId();
                break;
            case 1:
                return $this->getTitleId();
                break;
            case 2:
                return $this->getLangId();
                break;
            case 3:
                return $this->getRef();
                break;
            case 4:
                return $this->getFirstname();
                break;
            case 5:
                return $this->getLastname();
                break;
            case 6:
                return $this->getEmail();
                break;
            case 7:
                return $this->getPassword();
                break;
            case 8:
                return $this->getAlgo();
                break;
            case 9:
                return $this->getReseller();
                break;
            case 10:
                return $this->getSponsor();
                break;
            case 11:
                return $this->getDiscount();
                break;
            case 12:
                return $this->getRememberMeToken();
                break;
            case 13:
                return $this->getRememberMeSerial();
                break;
            case 14:
                return $this->getEnable();
                break;
            case 15:
                return $this->getConfirmationToken();
                break;
            case 16:
                return $this->getCreatedAt();
                break;
            case 17:
                return $this->getUpdatedAt();
                break;
            case 18:
                return $this->getVersion();
                break;
            case 19:
                return $this->getVersionCreatedAt();
                break;
            case 20:
                return $this->getVersionCreatedBy();
                break;
            case 21:
                return $this->getOrderIds();
                break;
            case 22:
                return $this->getOrderVersions();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param     string  $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     *                    TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                    Defaults to TableMap::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {
        if (isset($alreadyDumpedObjects['CustomerVersion'][serialize($this->getPrimaryKey())])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['CustomerVersion'][serialize($this->getPrimaryKey())] = true;
        $keys = CustomerVersionTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getTitleId(),
            $keys[2] => $this->getLangId(),
            $keys[3] => $this->getRef(),
            $keys[4] => $this->getFirstname(),
            $keys[5] => $this->getLastname(),
            $keys[6] => $this->getEmail(),
            $keys[7] => $this->getPassword(),
            $keys[8] => $this->getAlgo(),
            $keys[9] => $this->getReseller(),
            $keys[10] => $this->getSponsor(),
            $keys[11] => $this->getDiscount(),
            $keys[12] => $this->getRememberMeToken(),
            $keys[13] => $this->getRememberMeSerial(),
            $keys[14] => $this->getEnable(),
            $keys[15] => $this->getConfirmationToken(),
            $keys[16] => $this->getCreatedAt(),
            $keys[17] => $this->getUpdatedAt(),
            $keys[18] => $this->getVersion(),
            $keys[19] => $this->getVersionCreatedAt(),
            $keys[20] => $this->getVersionCreatedBy(),
            $keys[21] => $this->getOrderIds(),
            $keys[22] => $this->getOrderVersions(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aCustomer) {
                $result['Customer'] = $this->aCustomer->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
        }

        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param      string $name
     * @param      mixed  $value field value
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return void
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = CustomerVersionTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @param      mixed $value field value
     * @return void
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setTitleId($value);
                break;
            case 2:
                $this->setLangId($value);
                break;
            case 3:
                $this->setRef($value);
                break;
            case 4:
                $this->setFirstname($value);
                break;
            case 5:
                $this->setLastname($value);
                break;
            case 6:
                $this->setEmail($value);
                break;
            case 7:
                $this->setPassword($value);
                break;
            case 8:
                $this->setAlgo($value);
                break;
            case 9:
                $this->setReseller($value);
                break;
            case 10:
                $this->setSponsor($value);
                break;
            case 11:
                $this->setDiscount($value);
                break;
            case 12:
                $this->setRememberMeToken($value);
                break;
            case 13:
                $this->setRememberMeSerial($value);
                break;
            case 14:
                $this->setEnable($value);
                break;
            case 15:
                $this->setConfirmationToken($value);
                break;
            case 16:
                $this->setCreatedAt($value);
                break;
            case 17:
                $this->setUpdatedAt($value);
                break;
            case 18:
                $this->setVersion($value);
                break;
            case 19:
                $this->setVersionCreatedAt($value);
                break;
            case 20:
                $this->setVersionCreatedBy($value);
                break;
            case 21:
                if (!is_array($value)) {
                    $v = trim(substr($value, 2, -2));
                    $value = $v ? explode(' | ', $v) : array();
                }
                $this->setOrderIds($value);
                break;
            case 22:
                if (!is_array($value)) {
                    $v = trim(substr($value, 2, -2));
                    $value = $v ? explode(' | ', $v) : array();
                }
                $this->setOrderVersions($value);
                break;
        } // switch()
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $keys = CustomerVersionTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setTitleId($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setLangId($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setRef($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setFirstname($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setLastname($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setEmail($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setPassword($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setAlgo($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setReseller($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setSponsor($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setDiscount($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setRememberMeToken($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setRememberMeSerial($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setEnable($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setConfirmationToken($arr[$keys[15]]);
        if (array_key_exists($keys[16], $arr)) $this->setCreatedAt($arr[$keys[16]]);
        if (array_key_exists($keys[17], $arr)) $this->setUpdatedAt($arr[$keys[17]]);
        if (array_key_exists($keys[18], $arr)) $this->setVersion($arr[$keys[18]]);
        if (array_key_exists($keys[19], $arr)) $this->setVersionCreatedAt($arr[$keys[19]]);
        if (array_key_exists($keys[20], $arr)) $this->setVersionCreatedBy($arr[$keys[20]]);
        if (array_key_exists($keys[21], $arr)) $this->setOrderIds($arr[$keys[21]]);
        if (array_key_exists($keys[22], $arr)) $this->setOrderVersions($arr[$keys[22]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(CustomerVersionTableMap::DATABASE_NAME);

        if ($this->isColumnModified(CustomerVersionTableMap::ID)) $criteria->add(CustomerVersionTableMap::ID, $this->id);
        if ($this->isColumnModified(CustomerVersionTableMap::TITLE_ID)) $criteria->add(CustomerVersionTableMap::TITLE_ID, $this->title_id);
        if ($this->isColumnModified(CustomerVersionTableMap::LANG_ID)) $criteria->add(CustomerVersionTableMap::LANG_ID, $this->lang_id);
        if ($this->isColumnModified(CustomerVersionTableMap::REF)) $criteria->add(CustomerVersionTableMap::REF, $this->ref);
        if ($this->isColumnModified(CustomerVersionTableMap::FIRSTNAME)) $criteria->add(CustomerVersionTableMap::FIRSTNAME, $this->firstname);
        if ($this->isColumnModified(CustomerVersionTableMap::LASTNAME)) $criteria->add(CustomerVersionTableMap::LASTNAME, $this->lastname);
        if ($this->isColumnModified(CustomerVersionTableMap::EMAIL)) $criteria->add(CustomerVersionTableMap::EMAIL, $this->email);
        if ($this->isColumnModified(CustomerVersionTableMap::PASSWORD)) $criteria->add(CustomerVersionTableMap::PASSWORD, $this->password);
        if ($this->isColumnModified(CustomerVersionTableMap::ALGO)) $criteria->add(CustomerVersionTableMap::ALGO, $this->algo);
        if ($this->isColumnModified(CustomerVersionTableMap::RESELLER)) $criteria->add(CustomerVersionTableMap::RESELLER, $this->reseller);
        if ($this->isColumnModified(CustomerVersionTableMap::SPONSOR)) $criteria->add(CustomerVersionTableMap::SPONSOR, $this->sponsor);
        if ($this->isColumnModified(CustomerVersionTableMap::DISCOUNT)) $criteria->add(CustomerVersionTableMap::DISCOUNT, $this->discount);
        if ($this->isColumnModified(CustomerVersionTableMap::REMEMBER_ME_TOKEN)) $criteria->add(CustomerVersionTableMap::REMEMBER_ME_TOKEN, $this->remember_me_token);
        if ($this->isColumnModified(CustomerVersionTableMap::REMEMBER_ME_SERIAL)) $criteria->add(CustomerVersionTableMap::REMEMBER_ME_SERIAL, $this->remember_me_serial);
        if ($this->isColumnModified(CustomerVersionTableMap::ENABLE)) $criteria->add(CustomerVersionTableMap::ENABLE, $this->enable);
        if ($this->isColumnModified(CustomerVersionTableMap::CONFIRMATION_TOKEN)) $criteria->add(CustomerVersionTableMap::CONFIRMATION_TOKEN, $this->confirmation_token);
        if ($this->isColumnModified(CustomerVersionTableMap::CREATED_AT)) $criteria->add(CustomerVersionTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CustomerVersionTableMap::UPDATED_AT)) $criteria->add(CustomerVersionTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(CustomerVersionTableMap::VERSION)) $criteria->add(CustomerVersionTableMap::VERSION, $this->version);
        if ($this->isColumnModified(CustomerVersionTableMap::VERSION_CREATED_AT)) $criteria->add(CustomerVersionTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(CustomerVersionTableMap::VERSION_CREATED_BY)) $criteria->add(CustomerVersionTableMap::VERSION_CREATED_BY, $this->version_created_by);
        if ($this->isColumnModified(CustomerVersionTableMap::ORDER_IDS)) $criteria->add(CustomerVersionTableMap::ORDER_IDS, $this->order_ids);
        if ($this->isColumnModified(CustomerVersionTableMap::ORDER_VERSIONS)) $criteria->add(CustomerVersionTableMap::ORDER_VERSIONS, $this->order_versions);

        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @return Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        $criteria = new Criteria(CustomerVersionTableMap::DATABASE_NAME);
        $criteria->add(CustomerVersionTableMap::ID, $this->id);
        $criteria->add(CustomerVersionTableMap::VERSION, $this->version);

        return $criteria;
    }

    /**
     * Returns the composite primary key for this object.
     * The array elements will be in same order as specified in XML.
     * @return array
     */
    public function getPrimaryKey()
    {
        $pks = array();
        $pks[0] = $this->getId();
        $pks[1] = $this->getVersion();

        return $pks;
    }

    /**
     * Set the [composite] primary key.
     *
     * @param      array $keys The elements of the composite key (order must match the order in XML file).
     * @return void
     */
    public function setPrimaryKey($keys)
    {
        $this->setId($keys[0]);
        $this->setVersion($keys[1]);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {

        return (null === $this->getId()) && (null === $this->getVersion());
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \Thelia\Model\CustomerVersion (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setId($this->getId());
        $copyObj->setTitleId($this->getTitleId());
        $copyObj->setLangId($this->getLangId());
        $copyObj->setRef($this->getRef());
        $copyObj->setFirstname($this->getFirstname());
        $copyObj->setLastname($this->getLastname());
        $copyObj->setEmail($this->getEmail());
        $copyObj->setPassword($this->getPassword());
        $copyObj->setAlgo($this->getAlgo());
        $copyObj->setReseller($this->getReseller());
        $copyObj->setSponsor($this->getSponsor());
        $copyObj->setDiscount($this->getDiscount());
        $copyObj->setRememberMeToken($this->getRememberMeToken());
        $copyObj->setRememberMeSerial($this->getRememberMeSerial());
        $copyObj->setEnable($this->getEnable());
        $copyObj->setConfirmationToken($this->getConfirmationToken());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());
        $copyObj->setOrderIds($this->getOrderIds());
        $copyObj->setOrderVersions($this->getOrderVersions());
        if ($makeNew) {
            $copyObj->setNew(true);
        }
    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return                 \Thelia\Model\CustomerVersion Clone of current object.
     * @throws PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);

        return $copyObj;
    }

    /**
     * Declares an association between this object and a ChildCustomer object.
     *
     * @param                  ChildCustomer $v
     * @return                 \Thelia\Model\CustomerVersion The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCustomer(ChildCustomer $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getId());
        }

        $this->aCustomer = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildCustomer object, it will not be re-added.
        if ($v !== null) {
            $v->addCustomerVersion($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildCustomer object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildCustomer The associated ChildCustomer object.
     * @throws PropelException
     */
    public function getCustomer(ConnectionInterface $con = null)
    {
        if ($this->aCustomer === null && ($this->id !== null)) {
            $this->aCustomer = ChildCustomerQuery::create()->findPk($this->id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCustomer->addCustomerVersions($this);
             */
        }

        return $this->aCustomer;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->title_id = null;
        $this->lang_id = null;
        $this->ref = null;
        $this->firstname = null;
        $this->lastname = null;
        $this->email = null;
        $this->password = null;
        $this->algo = null;
        $this->reseller = null;
        $this->sponsor = null;
        $this->discount = null;
        $this->remember_me_token = null;
        $this->remember_me_serial = null;
        $this->enable = null;
        $this->confirmation_token = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->version = null;
        $this->version_created_at = null;
        $this->version_created_by = null;
        $this->order_ids = null;
        $this->order_ids_unserialized = null;
        $this->order_versions = null;
        $this->order_versions_unserialized = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
        $this->applyDefaultValues();
        $this->resetModified();
        $this->setNew(true);
        $this->setDeleted(false);
    }

    /**
     * Resets all references to other model objects or collections of model objects.
     *
     * This method is a user-space workaround for PHP's inability to garbage collect
     * objects with circular references (even in PHP 5.3). This is currently necessary
     * when using Propel in certain daemon or large-volume/high-memory operations.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
        } // if ($deep)

        $this->aCustomer = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CustomerVersionTableMap::DEFAULT_STRING_FORMAT);
    }

    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {

    }


    /**
     * Derived method to catches calls to undefined methods.
     *
     * Provides magic import/export method support (fromXML()/toXML(), fromYAML()/toYAML(), etc.).
     * Allows to define default __call() behavior if you overwrite __call()
     *
     * @param string $name
     * @param mixed  $params
     *
     * @return array|string
     */
    public function __call($name, $params)
    {
        if (0 === strpos($name, 'get')) {
            $virtualColumn = substr($name, 3);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }

            $virtualColumn = lcfirst($virtualColumn);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }
        }

        if (0 === strpos($name, 'from')) {
            $format = substr($name, 4);

            return $this->importFrom($format, reset($params));
        }

        if (0 === strpos($name, 'to')) {
            $format = substr($name, 2);
            $includeLazyLoadColumns = isset($params[0]) ? $params[0] : true;

            return $this->exportTo($format, $includeLazyLoadColumns);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method: %s.', $name));
    }

}
