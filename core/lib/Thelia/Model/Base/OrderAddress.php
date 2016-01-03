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
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;
use Thelia\Model\Country as ChildCountry;
use Thelia\Model\CountryQuery as ChildCountryQuery;
use Thelia\Model\CustomerTitle as ChildCustomerTitle;
use Thelia\Model\CustomerTitleQuery as ChildCustomerTitleQuery;
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderAddress as ChildOrderAddress;
use Thelia\Model\OrderAddressQuery as ChildOrderAddressQuery;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\State as ChildState;
use Thelia\Model\StateQuery as ChildStateQuery;
use Thelia\Model\Map\OrderAddressTableMap;

abstract class OrderAddress implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\OrderAddressTableMap';


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
     * The value for the customer_title_id field.
     * @var        int
     */
    protected $customer_title_id;

    /**
     * The value for the company field.
     * @var        string
     */
    protected $company;

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
     * The value for the address1 field.
     * @var        string
     */
    protected $address1;

    /**
     * The value for the address2 field.
     * @var        string
     */
    protected $address2;

    /**
     * The value for the address3 field.
     * @var        string
     */
    protected $address3;

    /**
     * The value for the zipcode field.
     * @var        string
     */
    protected $zipcode;

    /**
     * The value for the city field.
     * @var        string
     */
    protected $city;

    /**
     * The value for the phone field.
     * @var        string
     */
    protected $phone;

    /**
     * The value for the cellphone field.
     * @var        string
     */
    protected $cellphone;

    /**
     * The value for the country_id field.
     * @var        int
     */
    protected $country_id;

    /**
     * The value for the state_id field.
     * @var        int
     */
    protected $state_id;

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
     * @var        CustomerTitle
     */
    protected $aCustomerTitle;

    /**
     * @var        Country
     */
    protected $aCountry;

    /**
     * @var        State
     */
    protected $aState;

    /**
     * @var        ObjectCollection|ChildOrder[] Collection to store aggregation of ChildOrder objects.
     */
    protected $collOrdersRelatedByInvoiceOrderAddressId;
    protected $collOrdersRelatedByInvoiceOrderAddressIdPartial;

    /**
     * @var        ObjectCollection|ChildOrder[] Collection to store aggregation of ChildOrder objects.
     */
    protected $collOrdersRelatedByDeliveryOrderAddressId;
    protected $collOrdersRelatedByDeliveryOrderAddressIdPartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion = null;

    /**
     * Initializes internal state of Thelia\Model\Base\OrderAddress object.
     */
    public function __construct()
    {
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
     * Compares this with another <code>OrderAddress</code> instance.  If
     * <code>obj</code> is an instance of <code>OrderAddress</code>, delegates to
     * <code>equals(OrderAddress)</code>.  Otherwise, returns <code>false</code>.
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
     * @return OrderAddress The current object, for fluid interface
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
     * @return OrderAddress The current object, for fluid interface
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
     * Get the [customer_title_id] column value.
     *
     * @return   int
     */
    public function getCustomerTitleId()
    {

        return $this->customer_title_id;
    }

    /**
     * Get the [company] column value.
     *
     * @return   string
     */
    public function getCompany()
    {

        return $this->company;
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
     * Get the [address1] column value.
     *
     * @return   string
     */
    public function getAddress1()
    {

        return $this->address1;
    }

    /**
     * Get the [address2] column value.
     *
     * @return   string
     */
    public function getAddress2()
    {

        return $this->address2;
    }

    /**
     * Get the [address3] column value.
     *
     * @return   string
     */
    public function getAddress3()
    {

        return $this->address3;
    }

    /**
     * Get the [zipcode] column value.
     *
     * @return   string
     */
    public function getZipcode()
    {

        return $this->zipcode;
    }

    /**
     * Get the [city] column value.
     *
     * @return   string
     */
    public function getCity()
    {

        return $this->city;
    }

    /**
     * Get the [phone] column value.
     *
     * @return   string
     */
    public function getPhone()
    {

        return $this->phone;
    }

    /**
     * Get the [cellphone] column value.
     *
     * @return   string
     */
    public function getCellphone()
    {

        return $this->cellphone;
    }

    /**
     * Get the [country_id] column value.
     *
     * @return   int
     */
    public function getCountryId()
    {

        return $this->country_id;
    }

    /**
     * Get the [state_id] column value.
     *
     * @return   int
     */
    public function getStateId()
    {

        return $this->state_id;
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
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[OrderAddressTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [customer_title_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setCustomerTitleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->customer_title_id !== $v) {
            $this->customer_title_id = $v;
            $this->modifiedColumns[OrderAddressTableMap::CUSTOMER_TITLE_ID] = true;
        }

        if ($this->aCustomerTitle !== null && $this->aCustomerTitle->getId() !== $v) {
            $this->aCustomerTitle = null;
        }


        return $this;
    } // setCustomerTitleId()

    /**
     * Set the value of [company] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setCompany($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->company !== $v) {
            $this->company = $v;
            $this->modifiedColumns[OrderAddressTableMap::COMPANY] = true;
        }


        return $this;
    } // setCompany()

    /**
     * Set the value of [firstname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setFirstname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->firstname !== $v) {
            $this->firstname = $v;
            $this->modifiedColumns[OrderAddressTableMap::FIRSTNAME] = true;
        }


        return $this;
    } // setFirstname()

    /**
     * Set the value of [lastname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setLastname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lastname !== $v) {
            $this->lastname = $v;
            $this->modifiedColumns[OrderAddressTableMap::LASTNAME] = true;
        }


        return $this;
    } // setLastname()

    /**
     * Set the value of [address1] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setAddress1($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address1 !== $v) {
            $this->address1 = $v;
            $this->modifiedColumns[OrderAddressTableMap::ADDRESS1] = true;
        }


        return $this;
    } // setAddress1()

    /**
     * Set the value of [address2] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setAddress2($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address2 !== $v) {
            $this->address2 = $v;
            $this->modifiedColumns[OrderAddressTableMap::ADDRESS2] = true;
        }


        return $this;
    } // setAddress2()

    /**
     * Set the value of [address3] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setAddress3($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address3 !== $v) {
            $this->address3 = $v;
            $this->modifiedColumns[OrderAddressTableMap::ADDRESS3] = true;
        }


        return $this;
    } // setAddress3()

    /**
     * Set the value of [zipcode] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setZipcode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->zipcode !== $v) {
            $this->zipcode = $v;
            $this->modifiedColumns[OrderAddressTableMap::ZIPCODE] = true;
        }


        return $this;
    } // setZipcode()

    /**
     * Set the value of [city] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setCity($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->city !== $v) {
            $this->city = $v;
            $this->modifiedColumns[OrderAddressTableMap::CITY] = true;
        }


        return $this;
    } // setCity()

    /**
     * Set the value of [phone] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setPhone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->phone !== $v) {
            $this->phone = $v;
            $this->modifiedColumns[OrderAddressTableMap::PHONE] = true;
        }


        return $this;
    } // setPhone()

    /**
     * Set the value of [cellphone] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setCellphone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cellphone !== $v) {
            $this->cellphone = $v;
            $this->modifiedColumns[OrderAddressTableMap::CELLPHONE] = true;
        }


        return $this;
    } // setCellphone()

    /**
     * Set the value of [country_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setCountryId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->country_id !== $v) {
            $this->country_id = $v;
            $this->modifiedColumns[OrderAddressTableMap::COUNTRY_ID] = true;
        }

        if ($this->aCountry !== null && $this->aCountry->getId() !== $v) {
            $this->aCountry = null;
        }


        return $this;
    } // setCountryId()

    /**
     * Set the value of [state_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setStateId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->state_id !== $v) {
            $this->state_id = $v;
            $this->modifiedColumns[OrderAddressTableMap::STATE_ID] = true;
        }

        if ($this->aState !== null && $this->aState->getId() !== $v) {
            $this->aState = null;
        }


        return $this;
    } // setStateId()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[OrderAddressTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[OrderAddressTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : OrderAddressTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : OrderAddressTableMap::translateFieldName('CustomerTitleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->customer_title_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : OrderAddressTableMap::translateFieldName('Company', TableMap::TYPE_PHPNAME, $indexType)];
            $this->company = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : OrderAddressTableMap::translateFieldName('Firstname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->firstname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : OrderAddressTableMap::translateFieldName('Lastname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lastname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : OrderAddressTableMap::translateFieldName('Address1', TableMap::TYPE_PHPNAME, $indexType)];
            $this->address1 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : OrderAddressTableMap::translateFieldName('Address2', TableMap::TYPE_PHPNAME, $indexType)];
            $this->address2 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : OrderAddressTableMap::translateFieldName('Address3', TableMap::TYPE_PHPNAME, $indexType)];
            $this->address3 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : OrderAddressTableMap::translateFieldName('Zipcode', TableMap::TYPE_PHPNAME, $indexType)];
            $this->zipcode = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : OrderAddressTableMap::translateFieldName('City', TableMap::TYPE_PHPNAME, $indexType)];
            $this->city = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : OrderAddressTableMap::translateFieldName('Phone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->phone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : OrderAddressTableMap::translateFieldName('Cellphone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cellphone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : OrderAddressTableMap::translateFieldName('CountryId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->country_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : OrderAddressTableMap::translateFieldName('StateId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->state_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : OrderAddressTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : OrderAddressTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 16; // 16 = OrderAddressTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\OrderAddress object", 0, $e);
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
        if ($this->aCustomerTitle !== null && $this->customer_title_id !== $this->aCustomerTitle->getId()) {
            $this->aCustomerTitle = null;
        }
        if ($this->aCountry !== null && $this->country_id !== $this->aCountry->getId()) {
            $this->aCountry = null;
        }
        if ($this->aState !== null && $this->state_id !== $this->aState->getId()) {
            $this->aState = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(OrderAddressTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildOrderAddressQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aCustomerTitle = null;
            $this->aCountry = null;
            $this->aState = null;
            $this->collOrdersRelatedByInvoiceOrderAddressId = null;

            $this->collOrdersRelatedByDeliveryOrderAddressId = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see OrderAddress::setDeleted()
     * @see OrderAddress::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderAddressTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildOrderAddressQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderAddressTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(OrderAddressTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(OrderAddressTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(OrderAddressTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                OrderAddressTableMap::addInstanceToPool($this);
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

            if ($this->aCustomerTitle !== null) {
                if ($this->aCustomerTitle->isModified() || $this->aCustomerTitle->isNew()) {
                    $affectedRows += $this->aCustomerTitle->save($con);
                }
                $this->setCustomerTitle($this->aCustomerTitle);
            }

            if ($this->aCountry !== null) {
                if ($this->aCountry->isModified() || $this->aCountry->isNew()) {
                    $affectedRows += $this->aCountry->save($con);
                }
                $this->setCountry($this->aCountry);
            }

            if ($this->aState !== null) {
                if ($this->aState->isModified() || $this->aState->isNew()) {
                    $affectedRows += $this->aState->save($con);
                }
                $this->setState($this->aState);
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

            if ($this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion !== null) {
                if (!$this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderQuery::create()
                        ->filterByPrimaryKeys($this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion = null;
                }
            }

                if ($this->collOrdersRelatedByInvoiceOrderAddressId !== null) {
            foreach ($this->collOrdersRelatedByInvoiceOrderAddressId as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion !== null) {
                if (!$this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderQuery::create()
                        ->filterByPrimaryKeys($this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion = null;
                }
            }

                if ($this->collOrdersRelatedByDeliveryOrderAddressId !== null) {
            foreach ($this->collOrdersRelatedByDeliveryOrderAddressId as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
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

        $this->modifiedColumns[OrderAddressTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . OrderAddressTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(OrderAddressTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::CUSTOMER_TITLE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CUSTOMER_TITLE_ID`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::COMPANY)) {
            $modifiedColumns[':p' . $index++]  = '`COMPANY`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`FIRSTNAME`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`LASTNAME`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::ADDRESS1)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS1`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::ADDRESS2)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS2`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::ADDRESS3)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS3`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::ZIPCODE)) {
            $modifiedColumns[':p' . $index++]  = '`ZIPCODE`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::CITY)) {
            $modifiedColumns[':p' . $index++]  = '`CITY`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::PHONE)) {
            $modifiedColumns[':p' . $index++]  = '`PHONE`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::CELLPHONE)) {
            $modifiedColumns[':p' . $index++]  = '`CELLPHONE`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::COUNTRY_ID)) {
            $modifiedColumns[':p' . $index++]  = '`COUNTRY_ID`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::STATE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`STATE_ID`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(OrderAddressTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `order_address` (%s) VALUES (%s)',
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
                    case '`CUSTOMER_TITLE_ID`':
                        $stmt->bindValue($identifier, $this->customer_title_id, PDO::PARAM_INT);
                        break;
                    case '`COMPANY`':
                        $stmt->bindValue($identifier, $this->company, PDO::PARAM_STR);
                        break;
                    case '`FIRSTNAME`':
                        $stmt->bindValue($identifier, $this->firstname, PDO::PARAM_STR);
                        break;
                    case '`LASTNAME`':
                        $stmt->bindValue($identifier, $this->lastname, PDO::PARAM_STR);
                        break;
                    case '`ADDRESS1`':
                        $stmt->bindValue($identifier, $this->address1, PDO::PARAM_STR);
                        break;
                    case '`ADDRESS2`':
                        $stmt->bindValue($identifier, $this->address2, PDO::PARAM_STR);
                        break;
                    case '`ADDRESS3`':
                        $stmt->bindValue($identifier, $this->address3, PDO::PARAM_STR);
                        break;
                    case '`ZIPCODE`':
                        $stmt->bindValue($identifier, $this->zipcode, PDO::PARAM_STR);
                        break;
                    case '`CITY`':
                        $stmt->bindValue($identifier, $this->city, PDO::PARAM_STR);
                        break;
                    case '`PHONE`':
                        $stmt->bindValue($identifier, $this->phone, PDO::PARAM_STR);
                        break;
                    case '`CELLPHONE`':
                        $stmt->bindValue($identifier, $this->cellphone, PDO::PARAM_STR);
                        break;
                    case '`COUNTRY_ID`':
                        $stmt->bindValue($identifier, $this->country_id, PDO::PARAM_INT);
                        break;
                    case '`STATE_ID`':
                        $stmt->bindValue($identifier, $this->state_id, PDO::PARAM_INT);
                        break;
                    case '`CREATED_AT`':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`UPDATED_AT`':
                        $stmt->bindValue($identifier, $this->updated_at ? $this->updated_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
        $this->setId($pk);

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
        $pos = OrderAddressTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getCustomerTitleId();
                break;
            case 2:
                return $this->getCompany();
                break;
            case 3:
                return $this->getFirstname();
                break;
            case 4:
                return $this->getLastname();
                break;
            case 5:
                return $this->getAddress1();
                break;
            case 6:
                return $this->getAddress2();
                break;
            case 7:
                return $this->getAddress3();
                break;
            case 8:
                return $this->getZipcode();
                break;
            case 9:
                return $this->getCity();
                break;
            case 10:
                return $this->getPhone();
                break;
            case 11:
                return $this->getCellphone();
                break;
            case 12:
                return $this->getCountryId();
                break;
            case 13:
                return $this->getStateId();
                break;
            case 14:
                return $this->getCreatedAt();
                break;
            case 15:
                return $this->getUpdatedAt();
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
        if (isset($alreadyDumpedObjects['OrderAddress'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['OrderAddress'][$this->getPrimaryKey()] = true;
        $keys = OrderAddressTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCustomerTitleId(),
            $keys[2] => $this->getCompany(),
            $keys[3] => $this->getFirstname(),
            $keys[4] => $this->getLastname(),
            $keys[5] => $this->getAddress1(),
            $keys[6] => $this->getAddress2(),
            $keys[7] => $this->getAddress3(),
            $keys[8] => $this->getZipcode(),
            $keys[9] => $this->getCity(),
            $keys[10] => $this->getPhone(),
            $keys[11] => $this->getCellphone(),
            $keys[12] => $this->getCountryId(),
            $keys[13] => $this->getStateId(),
            $keys[14] => $this->getCreatedAt(),
            $keys[15] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aCustomerTitle) {
                $result['CustomerTitle'] = $this->aCustomerTitle->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aCountry) {
                $result['Country'] = $this->aCountry->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aState) {
                $result['State'] = $this->aState->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collOrdersRelatedByInvoiceOrderAddressId) {
                $result['OrdersRelatedByInvoiceOrderAddressId'] = $this->collOrdersRelatedByInvoiceOrderAddressId->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrdersRelatedByDeliveryOrderAddressId) {
                $result['OrdersRelatedByDeliveryOrderAddressId'] = $this->collOrdersRelatedByDeliveryOrderAddressId->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = OrderAddressTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setCustomerTitleId($value);
                break;
            case 2:
                $this->setCompany($value);
                break;
            case 3:
                $this->setFirstname($value);
                break;
            case 4:
                $this->setLastname($value);
                break;
            case 5:
                $this->setAddress1($value);
                break;
            case 6:
                $this->setAddress2($value);
                break;
            case 7:
                $this->setAddress3($value);
                break;
            case 8:
                $this->setZipcode($value);
                break;
            case 9:
                $this->setCity($value);
                break;
            case 10:
                $this->setPhone($value);
                break;
            case 11:
                $this->setCellphone($value);
                break;
            case 12:
                $this->setCountryId($value);
                break;
            case 13:
                $this->setStateId($value);
                break;
            case 14:
                $this->setCreatedAt($value);
                break;
            case 15:
                $this->setUpdatedAt($value);
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
        $keys = OrderAddressTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCustomerTitleId($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCompany($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setFirstname($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setLastname($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setAddress1($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setAddress2($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setAddress3($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setZipcode($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setCity($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setPhone($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setCellphone($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setCountryId($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setStateId($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setCreatedAt($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setUpdatedAt($arr[$keys[15]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(OrderAddressTableMap::DATABASE_NAME);

        if ($this->isColumnModified(OrderAddressTableMap::ID)) $criteria->add(OrderAddressTableMap::ID, $this->id);
        if ($this->isColumnModified(OrderAddressTableMap::CUSTOMER_TITLE_ID)) $criteria->add(OrderAddressTableMap::CUSTOMER_TITLE_ID, $this->customer_title_id);
        if ($this->isColumnModified(OrderAddressTableMap::COMPANY)) $criteria->add(OrderAddressTableMap::COMPANY, $this->company);
        if ($this->isColumnModified(OrderAddressTableMap::FIRSTNAME)) $criteria->add(OrderAddressTableMap::FIRSTNAME, $this->firstname);
        if ($this->isColumnModified(OrderAddressTableMap::LASTNAME)) $criteria->add(OrderAddressTableMap::LASTNAME, $this->lastname);
        if ($this->isColumnModified(OrderAddressTableMap::ADDRESS1)) $criteria->add(OrderAddressTableMap::ADDRESS1, $this->address1);
        if ($this->isColumnModified(OrderAddressTableMap::ADDRESS2)) $criteria->add(OrderAddressTableMap::ADDRESS2, $this->address2);
        if ($this->isColumnModified(OrderAddressTableMap::ADDRESS3)) $criteria->add(OrderAddressTableMap::ADDRESS3, $this->address3);
        if ($this->isColumnModified(OrderAddressTableMap::ZIPCODE)) $criteria->add(OrderAddressTableMap::ZIPCODE, $this->zipcode);
        if ($this->isColumnModified(OrderAddressTableMap::CITY)) $criteria->add(OrderAddressTableMap::CITY, $this->city);
        if ($this->isColumnModified(OrderAddressTableMap::PHONE)) $criteria->add(OrderAddressTableMap::PHONE, $this->phone);
        if ($this->isColumnModified(OrderAddressTableMap::CELLPHONE)) $criteria->add(OrderAddressTableMap::CELLPHONE, $this->cellphone);
        if ($this->isColumnModified(OrderAddressTableMap::COUNTRY_ID)) $criteria->add(OrderAddressTableMap::COUNTRY_ID, $this->country_id);
        if ($this->isColumnModified(OrderAddressTableMap::STATE_ID)) $criteria->add(OrderAddressTableMap::STATE_ID, $this->state_id);
        if ($this->isColumnModified(OrderAddressTableMap::CREATED_AT)) $criteria->add(OrderAddressTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(OrderAddressTableMap::UPDATED_AT)) $criteria->add(OrderAddressTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(OrderAddressTableMap::DATABASE_NAME);
        $criteria->add(OrderAddressTableMap::ID, $this->id);

        return $criteria;
    }

    /**
     * Returns the primary key for this object (row).
     * @return   int
     */
    public function getPrimaryKey()
    {
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {

        return null === $this->getId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \Thelia\Model\OrderAddress (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCustomerTitleId($this->getCustomerTitleId());
        $copyObj->setCompany($this->getCompany());
        $copyObj->setFirstname($this->getFirstname());
        $copyObj->setLastname($this->getLastname());
        $copyObj->setAddress1($this->getAddress1());
        $copyObj->setAddress2($this->getAddress2());
        $copyObj->setAddress3($this->getAddress3());
        $copyObj->setZipcode($this->getZipcode());
        $copyObj->setCity($this->getCity());
        $copyObj->setPhone($this->getPhone());
        $copyObj->setCellphone($this->getCellphone());
        $copyObj->setCountryId($this->getCountryId());
        $copyObj->setStateId($this->getStateId());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getOrdersRelatedByInvoiceOrderAddressId() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderRelatedByInvoiceOrderAddressId($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrdersRelatedByDeliveryOrderAddressId() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderRelatedByDeliveryOrderAddressId($relObj->copy($deepCopy));
                }
            }

        } // if ($deepCopy)

        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setId(NULL); // this is a auto-increment column, so set to default value
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
     * @return                 \Thelia\Model\OrderAddress Clone of current object.
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
     * Declares an association between this object and a ChildCustomerTitle object.
     *
     * @param                  ChildCustomerTitle $v
     * @return                 \Thelia\Model\OrderAddress The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCustomerTitle(ChildCustomerTitle $v = null)
    {
        if ($v === null) {
            $this->setCustomerTitleId(NULL);
        } else {
            $this->setCustomerTitleId($v->getId());
        }

        $this->aCustomerTitle = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildCustomerTitle object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderAddress($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildCustomerTitle object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildCustomerTitle The associated ChildCustomerTitle object.
     * @throws PropelException
     */
    public function getCustomerTitle(ConnectionInterface $con = null)
    {
        if ($this->aCustomerTitle === null && ($this->customer_title_id !== null)) {
            $this->aCustomerTitle = ChildCustomerTitleQuery::create()->findPk($this->customer_title_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCustomerTitle->addOrderAddresses($this);
             */
        }

        return $this->aCustomerTitle;
    }

    /**
     * Declares an association between this object and a ChildCountry object.
     *
     * @param                  ChildCountry $v
     * @return                 \Thelia\Model\OrderAddress The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCountry(ChildCountry $v = null)
    {
        if ($v === null) {
            $this->setCountryId(NULL);
        } else {
            $this->setCountryId($v->getId());
        }

        $this->aCountry = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildCountry object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderAddress($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildCountry object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildCountry The associated ChildCountry object.
     * @throws PropelException
     */
    public function getCountry(ConnectionInterface $con = null)
    {
        if ($this->aCountry === null && ($this->country_id !== null)) {
            $this->aCountry = ChildCountryQuery::create()->findPk($this->country_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCountry->addOrderAddresses($this);
             */
        }

        return $this->aCountry;
    }

    /**
     * Declares an association between this object and a ChildState object.
     *
     * @param                  ChildState $v
     * @return                 \Thelia\Model\OrderAddress The current object (for fluent API support)
     * @throws PropelException
     */
    public function setState(ChildState $v = null)
    {
        if ($v === null) {
            $this->setStateId(NULL);
        } else {
            $this->setStateId($v->getId());
        }

        $this->aState = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildState object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderAddress($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildState object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildState The associated ChildState object.
     * @throws PropelException
     */
    public function getState(ConnectionInterface $con = null)
    {
        if ($this->aState === null && ($this->state_id !== null)) {
            $this->aState = ChildStateQuery::create()->findPk($this->state_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aState->addOrderAddresses($this);
             */
        }

        return $this->aState;
    }


    /**
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param      string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('OrderRelatedByInvoiceOrderAddressId' == $relationName) {
            return $this->initOrdersRelatedByInvoiceOrderAddressId();
        }
        if ('OrderRelatedByDeliveryOrderAddressId' == $relationName) {
            return $this->initOrdersRelatedByDeliveryOrderAddressId();
        }
    }

    /**
     * Clears out the collOrdersRelatedByInvoiceOrderAddressId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrdersRelatedByInvoiceOrderAddressId()
     */
    public function clearOrdersRelatedByInvoiceOrderAddressId()
    {
        $this->collOrdersRelatedByInvoiceOrderAddressId = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrdersRelatedByInvoiceOrderAddressId collection loaded partially.
     */
    public function resetPartialOrdersRelatedByInvoiceOrderAddressId($v = true)
    {
        $this->collOrdersRelatedByInvoiceOrderAddressIdPartial = $v;
    }

    /**
     * Initializes the collOrdersRelatedByInvoiceOrderAddressId collection.
     *
     * By default this just sets the collOrdersRelatedByInvoiceOrderAddressId collection to an empty array (like clearcollOrdersRelatedByInvoiceOrderAddressId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrdersRelatedByInvoiceOrderAddressId($overrideExisting = true)
    {
        if (null !== $this->collOrdersRelatedByInvoiceOrderAddressId && !$overrideExisting) {
            return;
        }
        $this->collOrdersRelatedByInvoiceOrderAddressId = new ObjectCollection();
        $this->collOrdersRelatedByInvoiceOrderAddressId->setModel('\Thelia\Model\Order');
    }

    /**
     * Gets an array of ChildOrder objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrderAddress is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrder[] List of ChildOrder objects
     * @throws PropelException
     */
    public function getOrdersRelatedByInvoiceOrderAddressId($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersRelatedByInvoiceOrderAddressIdPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByInvoiceOrderAddressId || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByInvoiceOrderAddressId) {
                // return empty collection
                $this->initOrdersRelatedByInvoiceOrderAddressId();
            } else {
                $collOrdersRelatedByInvoiceOrderAddressId = ChildOrderQuery::create(null, $criteria)
                    ->filterByOrderAddressRelatedByInvoiceOrderAddressId($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrdersRelatedByInvoiceOrderAddressIdPartial && count($collOrdersRelatedByInvoiceOrderAddressId)) {
                        $this->initOrdersRelatedByInvoiceOrderAddressId(false);

                        foreach ($collOrdersRelatedByInvoiceOrderAddressId as $obj) {
                            if (false == $this->collOrdersRelatedByInvoiceOrderAddressId->contains($obj)) {
                                $this->collOrdersRelatedByInvoiceOrderAddressId->append($obj);
                            }
                        }

                        $this->collOrdersRelatedByInvoiceOrderAddressIdPartial = true;
                    }

                    reset($collOrdersRelatedByInvoiceOrderAddressId);

                    return $collOrdersRelatedByInvoiceOrderAddressId;
                }

                if ($partial && $this->collOrdersRelatedByInvoiceOrderAddressId) {
                    foreach ($this->collOrdersRelatedByInvoiceOrderAddressId as $obj) {
                        if ($obj->isNew()) {
                            $collOrdersRelatedByInvoiceOrderAddressId[] = $obj;
                        }
                    }
                }

                $this->collOrdersRelatedByInvoiceOrderAddressId = $collOrdersRelatedByInvoiceOrderAddressId;
                $this->collOrdersRelatedByInvoiceOrderAddressIdPartial = false;
            }
        }

        return $this->collOrdersRelatedByInvoiceOrderAddressId;
    }

    /**
     * Sets a collection of OrderRelatedByInvoiceOrderAddressId objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $ordersRelatedByInvoiceOrderAddressId A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildOrderAddress The current object (for fluent API support)
     */
    public function setOrdersRelatedByInvoiceOrderAddressId(Collection $ordersRelatedByInvoiceOrderAddressId, ConnectionInterface $con = null)
    {
        $ordersRelatedByInvoiceOrderAddressIdToDelete = $this->getOrdersRelatedByInvoiceOrderAddressId(new Criteria(), $con)->diff($ordersRelatedByInvoiceOrderAddressId);


        $this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion = $ordersRelatedByInvoiceOrderAddressIdToDelete;

        foreach ($ordersRelatedByInvoiceOrderAddressIdToDelete as $orderRelatedByInvoiceOrderAddressIdRemoved) {
            $orderRelatedByInvoiceOrderAddressIdRemoved->setOrderAddressRelatedByInvoiceOrderAddressId(null);
        }

        $this->collOrdersRelatedByInvoiceOrderAddressId = null;
        foreach ($ordersRelatedByInvoiceOrderAddressId as $orderRelatedByInvoiceOrderAddressId) {
            $this->addOrderRelatedByInvoiceOrderAddressId($orderRelatedByInvoiceOrderAddressId);
        }

        $this->collOrdersRelatedByInvoiceOrderAddressId = $ordersRelatedByInvoiceOrderAddressId;
        $this->collOrdersRelatedByInvoiceOrderAddressIdPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Order objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Order objects.
     * @throws PropelException
     */
    public function countOrdersRelatedByInvoiceOrderAddressId(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersRelatedByInvoiceOrderAddressIdPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByInvoiceOrderAddressId || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByInvoiceOrderAddressId) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrdersRelatedByInvoiceOrderAddressId());
            }

            $query = ChildOrderQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByOrderAddressRelatedByInvoiceOrderAddressId($this)
                ->count($con);
        }

        return count($this->collOrdersRelatedByInvoiceOrderAddressId);
    }

    /**
     * Method called to associate a ChildOrder object to this object
     * through the ChildOrder foreign key attribute.
     *
     * @param    ChildOrder $l ChildOrder
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function addOrderRelatedByInvoiceOrderAddressId(ChildOrder $l)
    {
        if ($this->collOrdersRelatedByInvoiceOrderAddressId === null) {
            $this->initOrdersRelatedByInvoiceOrderAddressId();
            $this->collOrdersRelatedByInvoiceOrderAddressIdPartial = true;
        }

        if (!in_array($l, $this->collOrdersRelatedByInvoiceOrderAddressId->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderRelatedByInvoiceOrderAddressId($l);
        }

        return $this;
    }

    /**
     * @param OrderRelatedByInvoiceOrderAddressId $orderRelatedByInvoiceOrderAddressId The orderRelatedByInvoiceOrderAddressId object to add.
     */
    protected function doAddOrderRelatedByInvoiceOrderAddressId($orderRelatedByInvoiceOrderAddressId)
    {
        $this->collOrdersRelatedByInvoiceOrderAddressId[]= $orderRelatedByInvoiceOrderAddressId;
        $orderRelatedByInvoiceOrderAddressId->setOrderAddressRelatedByInvoiceOrderAddressId($this);
    }

    /**
     * @param  OrderRelatedByInvoiceOrderAddressId $orderRelatedByInvoiceOrderAddressId The orderRelatedByInvoiceOrderAddressId object to remove.
     * @return ChildOrderAddress The current object (for fluent API support)
     */
    public function removeOrderRelatedByInvoiceOrderAddressId($orderRelatedByInvoiceOrderAddressId)
    {
        if ($this->getOrdersRelatedByInvoiceOrderAddressId()->contains($orderRelatedByInvoiceOrderAddressId)) {
            $this->collOrdersRelatedByInvoiceOrderAddressId->remove($this->collOrdersRelatedByInvoiceOrderAddressId->search($orderRelatedByInvoiceOrderAddressId));
            if (null === $this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion) {
                $this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion = clone $this->collOrdersRelatedByInvoiceOrderAddressId;
                $this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion->clear();
            }
            $this->ordersRelatedByInvoiceOrderAddressIdScheduledForDeletion[]= clone $orderRelatedByInvoiceOrderAddressId;
            $orderRelatedByInvoiceOrderAddressId->setOrderAddressRelatedByInvoiceOrderAddressId(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByInvoiceOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByInvoiceOrderAddressIdJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getOrdersRelatedByInvoiceOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByInvoiceOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByInvoiceOrderAddressIdJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getOrdersRelatedByInvoiceOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByInvoiceOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByInvoiceOrderAddressIdJoinOrderStatus($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderStatus', $joinBehavior);

        return $this->getOrdersRelatedByInvoiceOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByInvoiceOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByInvoiceOrderAddressIdJoinModuleRelatedByPaymentModuleId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('ModuleRelatedByPaymentModuleId', $joinBehavior);

        return $this->getOrdersRelatedByInvoiceOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByInvoiceOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByInvoiceOrderAddressIdJoinModuleRelatedByDeliveryModuleId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('ModuleRelatedByDeliveryModuleId', $joinBehavior);

        return $this->getOrdersRelatedByInvoiceOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByInvoiceOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByInvoiceOrderAddressIdJoinLang($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Lang', $joinBehavior);

        return $this->getOrdersRelatedByInvoiceOrderAddressId($query, $con);
    }

    /**
     * Clears out the collOrdersRelatedByDeliveryOrderAddressId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrdersRelatedByDeliveryOrderAddressId()
     */
    public function clearOrdersRelatedByDeliveryOrderAddressId()
    {
        $this->collOrdersRelatedByDeliveryOrderAddressId = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrdersRelatedByDeliveryOrderAddressId collection loaded partially.
     */
    public function resetPartialOrdersRelatedByDeliveryOrderAddressId($v = true)
    {
        $this->collOrdersRelatedByDeliveryOrderAddressIdPartial = $v;
    }

    /**
     * Initializes the collOrdersRelatedByDeliveryOrderAddressId collection.
     *
     * By default this just sets the collOrdersRelatedByDeliveryOrderAddressId collection to an empty array (like clearcollOrdersRelatedByDeliveryOrderAddressId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrdersRelatedByDeliveryOrderAddressId($overrideExisting = true)
    {
        if (null !== $this->collOrdersRelatedByDeliveryOrderAddressId && !$overrideExisting) {
            return;
        }
        $this->collOrdersRelatedByDeliveryOrderAddressId = new ObjectCollection();
        $this->collOrdersRelatedByDeliveryOrderAddressId->setModel('\Thelia\Model\Order');
    }

    /**
     * Gets an array of ChildOrder objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrderAddress is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrder[] List of ChildOrder objects
     * @throws PropelException
     */
    public function getOrdersRelatedByDeliveryOrderAddressId($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersRelatedByDeliveryOrderAddressIdPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByDeliveryOrderAddressId || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByDeliveryOrderAddressId) {
                // return empty collection
                $this->initOrdersRelatedByDeliveryOrderAddressId();
            } else {
                $collOrdersRelatedByDeliveryOrderAddressId = ChildOrderQuery::create(null, $criteria)
                    ->filterByOrderAddressRelatedByDeliveryOrderAddressId($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrdersRelatedByDeliveryOrderAddressIdPartial && count($collOrdersRelatedByDeliveryOrderAddressId)) {
                        $this->initOrdersRelatedByDeliveryOrderAddressId(false);

                        foreach ($collOrdersRelatedByDeliveryOrderAddressId as $obj) {
                            if (false == $this->collOrdersRelatedByDeliveryOrderAddressId->contains($obj)) {
                                $this->collOrdersRelatedByDeliveryOrderAddressId->append($obj);
                            }
                        }

                        $this->collOrdersRelatedByDeliveryOrderAddressIdPartial = true;
                    }

                    reset($collOrdersRelatedByDeliveryOrderAddressId);

                    return $collOrdersRelatedByDeliveryOrderAddressId;
                }

                if ($partial && $this->collOrdersRelatedByDeliveryOrderAddressId) {
                    foreach ($this->collOrdersRelatedByDeliveryOrderAddressId as $obj) {
                        if ($obj->isNew()) {
                            $collOrdersRelatedByDeliveryOrderAddressId[] = $obj;
                        }
                    }
                }

                $this->collOrdersRelatedByDeliveryOrderAddressId = $collOrdersRelatedByDeliveryOrderAddressId;
                $this->collOrdersRelatedByDeliveryOrderAddressIdPartial = false;
            }
        }

        return $this->collOrdersRelatedByDeliveryOrderAddressId;
    }

    /**
     * Sets a collection of OrderRelatedByDeliveryOrderAddressId objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $ordersRelatedByDeliveryOrderAddressId A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildOrderAddress The current object (for fluent API support)
     */
    public function setOrdersRelatedByDeliveryOrderAddressId(Collection $ordersRelatedByDeliveryOrderAddressId, ConnectionInterface $con = null)
    {
        $ordersRelatedByDeliveryOrderAddressIdToDelete = $this->getOrdersRelatedByDeliveryOrderAddressId(new Criteria(), $con)->diff($ordersRelatedByDeliveryOrderAddressId);


        $this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion = $ordersRelatedByDeliveryOrderAddressIdToDelete;

        foreach ($ordersRelatedByDeliveryOrderAddressIdToDelete as $orderRelatedByDeliveryOrderAddressIdRemoved) {
            $orderRelatedByDeliveryOrderAddressIdRemoved->setOrderAddressRelatedByDeliveryOrderAddressId(null);
        }

        $this->collOrdersRelatedByDeliveryOrderAddressId = null;
        foreach ($ordersRelatedByDeliveryOrderAddressId as $orderRelatedByDeliveryOrderAddressId) {
            $this->addOrderRelatedByDeliveryOrderAddressId($orderRelatedByDeliveryOrderAddressId);
        }

        $this->collOrdersRelatedByDeliveryOrderAddressId = $ordersRelatedByDeliveryOrderAddressId;
        $this->collOrdersRelatedByDeliveryOrderAddressIdPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Order objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Order objects.
     * @throws PropelException
     */
    public function countOrdersRelatedByDeliveryOrderAddressId(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersRelatedByDeliveryOrderAddressIdPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByDeliveryOrderAddressId || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByDeliveryOrderAddressId) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrdersRelatedByDeliveryOrderAddressId());
            }

            $query = ChildOrderQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByOrderAddressRelatedByDeliveryOrderAddressId($this)
                ->count($con);
        }

        return count($this->collOrdersRelatedByDeliveryOrderAddressId);
    }

    /**
     * Method called to associate a ChildOrder object to this object
     * through the ChildOrder foreign key attribute.
     *
     * @param    ChildOrder $l ChildOrder
     * @return   \Thelia\Model\OrderAddress The current object (for fluent API support)
     */
    public function addOrderRelatedByDeliveryOrderAddressId(ChildOrder $l)
    {
        if ($this->collOrdersRelatedByDeliveryOrderAddressId === null) {
            $this->initOrdersRelatedByDeliveryOrderAddressId();
            $this->collOrdersRelatedByDeliveryOrderAddressIdPartial = true;
        }

        if (!in_array($l, $this->collOrdersRelatedByDeliveryOrderAddressId->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderRelatedByDeliveryOrderAddressId($l);
        }

        return $this;
    }

    /**
     * @param OrderRelatedByDeliveryOrderAddressId $orderRelatedByDeliveryOrderAddressId The orderRelatedByDeliveryOrderAddressId object to add.
     */
    protected function doAddOrderRelatedByDeliveryOrderAddressId($orderRelatedByDeliveryOrderAddressId)
    {
        $this->collOrdersRelatedByDeliveryOrderAddressId[]= $orderRelatedByDeliveryOrderAddressId;
        $orderRelatedByDeliveryOrderAddressId->setOrderAddressRelatedByDeliveryOrderAddressId($this);
    }

    /**
     * @param  OrderRelatedByDeliveryOrderAddressId $orderRelatedByDeliveryOrderAddressId The orderRelatedByDeliveryOrderAddressId object to remove.
     * @return ChildOrderAddress The current object (for fluent API support)
     */
    public function removeOrderRelatedByDeliveryOrderAddressId($orderRelatedByDeliveryOrderAddressId)
    {
        if ($this->getOrdersRelatedByDeliveryOrderAddressId()->contains($orderRelatedByDeliveryOrderAddressId)) {
            $this->collOrdersRelatedByDeliveryOrderAddressId->remove($this->collOrdersRelatedByDeliveryOrderAddressId->search($orderRelatedByDeliveryOrderAddressId));
            if (null === $this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion) {
                $this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion = clone $this->collOrdersRelatedByDeliveryOrderAddressId;
                $this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion->clear();
            }
            $this->ordersRelatedByDeliveryOrderAddressIdScheduledForDeletion[]= clone $orderRelatedByDeliveryOrderAddressId;
            $orderRelatedByDeliveryOrderAddressId->setOrderAddressRelatedByDeliveryOrderAddressId(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryOrderAddressIdJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryOrderAddressIdJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryOrderAddressIdJoinOrderStatus($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderStatus', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryOrderAddressIdJoinModuleRelatedByPaymentModuleId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('ModuleRelatedByPaymentModuleId', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryOrderAddressIdJoinModuleRelatedByDeliveryModuleId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('ModuleRelatedByDeliveryModuleId', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryOrderAddressId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryOrderAddressId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryOrderAddressIdJoinLang($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Lang', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryOrderAddressId($query, $con);
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->customer_title_id = null;
        $this->company = null;
        $this->firstname = null;
        $this->lastname = null;
        $this->address1 = null;
        $this->address2 = null;
        $this->address3 = null;
        $this->zipcode = null;
        $this->city = null;
        $this->phone = null;
        $this->cellphone = null;
        $this->country_id = null;
        $this->state_id = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
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
            if ($this->collOrdersRelatedByInvoiceOrderAddressId) {
                foreach ($this->collOrdersRelatedByInvoiceOrderAddressId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrdersRelatedByDeliveryOrderAddressId) {
                foreach ($this->collOrdersRelatedByDeliveryOrderAddressId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collOrdersRelatedByInvoiceOrderAddressId = null;
        $this->collOrdersRelatedByDeliveryOrderAddressId = null;
        $this->aCustomerTitle = null;
        $this->aCountry = null;
        $this->aState = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(OrderAddressTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildOrderAddress The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[OrderAddressTableMap::UPDATED_AT] = true;

        return $this;
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
