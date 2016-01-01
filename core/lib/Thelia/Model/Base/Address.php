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
use Thelia\Model\Address as ChildAddress;
use Thelia\Model\AddressQuery as ChildAddressQuery;
use Thelia\Model\Cart as ChildCart;
use Thelia\Model\CartQuery as ChildCartQuery;
use Thelia\Model\Country as ChildCountry;
use Thelia\Model\CountryQuery as ChildCountryQuery;
use Thelia\Model\Customer as ChildCustomer;
use Thelia\Model\CustomerQuery as ChildCustomerQuery;
use Thelia\Model\CustomerTitle as ChildCustomerTitle;
use Thelia\Model\CustomerTitleQuery as ChildCustomerTitleQuery;
use Thelia\Model\State as ChildState;
use Thelia\Model\StateQuery as ChildStateQuery;
use Thelia\Model\Map\AddressTableMap;

abstract class Address implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\AddressTableMap';


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
     * The value for the label field.
     * @var        string
     */
    protected $label;

    /**
     * The value for the customer_id field.
     * @var        int
     */
    protected $customer_id;

    /**
     * The value for the title_id field.
     * @var        int
     */
    protected $title_id;

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
     * The value for the is_default field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $is_default;

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
     * @var        Customer
     */
    protected $aCustomer;

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
     * @var        ObjectCollection|ChildCart[] Collection to store aggregation of ChildCart objects.
     */
    protected $collCartsRelatedByAddressDeliveryId;
    protected $collCartsRelatedByAddressDeliveryIdPartial;

    /**
     * @var        ObjectCollection|ChildCart[] Collection to store aggregation of ChildCart objects.
     */
    protected $collCartsRelatedByAddressInvoiceId;
    protected $collCartsRelatedByAddressInvoiceIdPartial;

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
    protected $cartsRelatedByAddressDeliveryIdScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $cartsRelatedByAddressInvoiceIdScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->is_default = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Address object.
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
     * Compares this with another <code>Address</code> instance.  If
     * <code>obj</code> is an instance of <code>Address</code>, delegates to
     * <code>equals(Address)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Address The current object, for fluid interface
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
     * @return Address The current object, for fluid interface
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
     * Get the [label] column value.
     *
     * @return   string
     */
    public function getLabel()
    {

        return $this->label;
    }

    /**
     * Get the [customer_id] column value.
     *
     * @return   int
     */
    public function getCustomerId()
    {

        return $this->customer_id;
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
     * Get the [is_default] column value.
     *
     * @return   int
     */
    public function getIsDefault()
    {

        return $this->is_default;
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
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[AddressTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [label] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setLabel($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->label !== $v) {
            $this->label = $v;
            $this->modifiedColumns[AddressTableMap::LABEL] = true;
        }


        return $this;
    } // setLabel()

    /**
     * Set the value of [customer_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setCustomerId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->customer_id !== $v) {
            $this->customer_id = $v;
            $this->modifiedColumns[AddressTableMap::CUSTOMER_ID] = true;
        }

        if ($this->aCustomer !== null && $this->aCustomer->getId() !== $v) {
            $this->aCustomer = null;
        }


        return $this;
    } // setCustomerId()

    /**
     * Set the value of [title_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setTitleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->title_id !== $v) {
            $this->title_id = $v;
            $this->modifiedColumns[AddressTableMap::TITLE_ID] = true;
        }

        if ($this->aCustomerTitle !== null && $this->aCustomerTitle->getId() !== $v) {
            $this->aCustomerTitle = null;
        }


        return $this;
    } // setTitleId()

    /**
     * Set the value of [company] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setCompany($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->company !== $v) {
            $this->company = $v;
            $this->modifiedColumns[AddressTableMap::COMPANY] = true;
        }


        return $this;
    } // setCompany()

    /**
     * Set the value of [firstname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setFirstname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->firstname !== $v) {
            $this->firstname = $v;
            $this->modifiedColumns[AddressTableMap::FIRSTNAME] = true;
        }


        return $this;
    } // setFirstname()

    /**
     * Set the value of [lastname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setLastname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lastname !== $v) {
            $this->lastname = $v;
            $this->modifiedColumns[AddressTableMap::LASTNAME] = true;
        }


        return $this;
    } // setLastname()

    /**
     * Set the value of [address1] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setAddress1($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address1 !== $v) {
            $this->address1 = $v;
            $this->modifiedColumns[AddressTableMap::ADDRESS1] = true;
        }


        return $this;
    } // setAddress1()

    /**
     * Set the value of [address2] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setAddress2($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address2 !== $v) {
            $this->address2 = $v;
            $this->modifiedColumns[AddressTableMap::ADDRESS2] = true;
        }


        return $this;
    } // setAddress2()

    /**
     * Set the value of [address3] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setAddress3($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address3 !== $v) {
            $this->address3 = $v;
            $this->modifiedColumns[AddressTableMap::ADDRESS3] = true;
        }


        return $this;
    } // setAddress3()

    /**
     * Set the value of [zipcode] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setZipcode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->zipcode !== $v) {
            $this->zipcode = $v;
            $this->modifiedColumns[AddressTableMap::ZIPCODE] = true;
        }


        return $this;
    } // setZipcode()

    /**
     * Set the value of [city] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setCity($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->city !== $v) {
            $this->city = $v;
            $this->modifiedColumns[AddressTableMap::CITY] = true;
        }


        return $this;
    } // setCity()

    /**
     * Set the value of [country_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setCountryId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->country_id !== $v) {
            $this->country_id = $v;
            $this->modifiedColumns[AddressTableMap::COUNTRY_ID] = true;
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
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setStateId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->state_id !== $v) {
            $this->state_id = $v;
            $this->modifiedColumns[AddressTableMap::STATE_ID] = true;
        }

        if ($this->aState !== null && $this->aState->getId() !== $v) {
            $this->aState = null;
        }


        return $this;
    } // setStateId()

    /**
     * Set the value of [phone] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setPhone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->phone !== $v) {
            $this->phone = $v;
            $this->modifiedColumns[AddressTableMap::PHONE] = true;
        }


        return $this;
    } // setPhone()

    /**
     * Set the value of [cellphone] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setCellphone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cellphone !== $v) {
            $this->cellphone = $v;
            $this->modifiedColumns[AddressTableMap::CELLPHONE] = true;
        }


        return $this;
    } // setCellphone()

    /**
     * Set the value of [is_default] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setIsDefault($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->is_default !== $v) {
            $this->is_default = $v;
            $this->modifiedColumns[AddressTableMap::IS_DEFAULT] = true;
        }


        return $this;
    } // setIsDefault()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[AddressTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[AddressTableMap::UPDATED_AT] = true;
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
            if ($this->is_default !== 0) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : AddressTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : AddressTableMap::translateFieldName('Label', TableMap::TYPE_PHPNAME, $indexType)];
            $this->label = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : AddressTableMap::translateFieldName('CustomerId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->customer_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : AddressTableMap::translateFieldName('TitleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->title_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : AddressTableMap::translateFieldName('Company', TableMap::TYPE_PHPNAME, $indexType)];
            $this->company = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : AddressTableMap::translateFieldName('Firstname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->firstname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : AddressTableMap::translateFieldName('Lastname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lastname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : AddressTableMap::translateFieldName('Address1', TableMap::TYPE_PHPNAME, $indexType)];
            $this->address1 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : AddressTableMap::translateFieldName('Address2', TableMap::TYPE_PHPNAME, $indexType)];
            $this->address2 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : AddressTableMap::translateFieldName('Address3', TableMap::TYPE_PHPNAME, $indexType)];
            $this->address3 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : AddressTableMap::translateFieldName('Zipcode', TableMap::TYPE_PHPNAME, $indexType)];
            $this->zipcode = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : AddressTableMap::translateFieldName('City', TableMap::TYPE_PHPNAME, $indexType)];
            $this->city = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : AddressTableMap::translateFieldName('CountryId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->country_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : AddressTableMap::translateFieldName('StateId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->state_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : AddressTableMap::translateFieldName('Phone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->phone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : AddressTableMap::translateFieldName('Cellphone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cellphone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : AddressTableMap::translateFieldName('IsDefault', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_default = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : AddressTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : AddressTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 19; // 19 = AddressTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Address object", 0, $e);
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
        if ($this->aCustomer !== null && $this->customer_id !== $this->aCustomer->getId()) {
            $this->aCustomer = null;
        }
        if ($this->aCustomerTitle !== null && $this->title_id !== $this->aCustomerTitle->getId()) {
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
            $con = Propel::getServiceContainer()->getReadConnection(AddressTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildAddressQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aCustomer = null;
            $this->aCustomerTitle = null;
            $this->aCountry = null;
            $this->aState = null;
            $this->collCartsRelatedByAddressDeliveryId = null;

            $this->collCartsRelatedByAddressInvoiceId = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Address::setDeleted()
     * @see Address::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(AddressTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildAddressQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(AddressTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(AddressTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(AddressTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(AddressTableMap::UPDATED_AT)) {
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
                AddressTableMap::addInstanceToPool($this);
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

            if ($this->cartsRelatedByAddressDeliveryIdScheduledForDeletion !== null) {
                if (!$this->cartsRelatedByAddressDeliveryIdScheduledForDeletion->isEmpty()) {
                    foreach ($this->cartsRelatedByAddressDeliveryIdScheduledForDeletion as $cartRelatedByAddressDeliveryId) {
                        // need to save related object because we set the relation to null
                        $cartRelatedByAddressDeliveryId->save($con);
                    }
                    $this->cartsRelatedByAddressDeliveryIdScheduledForDeletion = null;
                }
            }

                if ($this->collCartsRelatedByAddressDeliveryId !== null) {
            foreach ($this->collCartsRelatedByAddressDeliveryId as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->cartsRelatedByAddressInvoiceIdScheduledForDeletion !== null) {
                if (!$this->cartsRelatedByAddressInvoiceIdScheduledForDeletion->isEmpty()) {
                    foreach ($this->cartsRelatedByAddressInvoiceIdScheduledForDeletion as $cartRelatedByAddressInvoiceId) {
                        // need to save related object because we set the relation to null
                        $cartRelatedByAddressInvoiceId->save($con);
                    }
                    $this->cartsRelatedByAddressInvoiceIdScheduledForDeletion = null;
                }
            }

                if ($this->collCartsRelatedByAddressInvoiceId !== null) {
            foreach ($this->collCartsRelatedByAddressInvoiceId as $referrerFK) {
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

        $this->modifiedColumns[AddressTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . AddressTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(AddressTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(AddressTableMap::LABEL)) {
            $modifiedColumns[':p' . $index++]  = '`LABEL`';
        }
        if ($this->isColumnModified(AddressTableMap::CUSTOMER_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CUSTOMER_ID`';
        }
        if ($this->isColumnModified(AddressTableMap::TITLE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`TITLE_ID`';
        }
        if ($this->isColumnModified(AddressTableMap::COMPANY)) {
            $modifiedColumns[':p' . $index++]  = '`COMPANY`';
        }
        if ($this->isColumnModified(AddressTableMap::FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`FIRSTNAME`';
        }
        if ($this->isColumnModified(AddressTableMap::LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`LASTNAME`';
        }
        if ($this->isColumnModified(AddressTableMap::ADDRESS1)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS1`';
        }
        if ($this->isColumnModified(AddressTableMap::ADDRESS2)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS2`';
        }
        if ($this->isColumnModified(AddressTableMap::ADDRESS3)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS3`';
        }
        if ($this->isColumnModified(AddressTableMap::ZIPCODE)) {
            $modifiedColumns[':p' . $index++]  = '`ZIPCODE`';
        }
        if ($this->isColumnModified(AddressTableMap::CITY)) {
            $modifiedColumns[':p' . $index++]  = '`CITY`';
        }
        if ($this->isColumnModified(AddressTableMap::COUNTRY_ID)) {
            $modifiedColumns[':p' . $index++]  = '`COUNTRY_ID`';
        }
        if ($this->isColumnModified(AddressTableMap::STATE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`STATE_ID`';
        }
        if ($this->isColumnModified(AddressTableMap::PHONE)) {
            $modifiedColumns[':p' . $index++]  = '`PHONE`';
        }
        if ($this->isColumnModified(AddressTableMap::CELLPHONE)) {
            $modifiedColumns[':p' . $index++]  = '`CELLPHONE`';
        }
        if ($this->isColumnModified(AddressTableMap::IS_DEFAULT)) {
            $modifiedColumns[':p' . $index++]  = '`IS_DEFAULT`';
        }
        if ($this->isColumnModified(AddressTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(AddressTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `address` (%s) VALUES (%s)',
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
                    case '`LABEL`':
                        $stmt->bindValue($identifier, $this->label, PDO::PARAM_STR);
                        break;
                    case '`CUSTOMER_ID`':
                        $stmt->bindValue($identifier, $this->customer_id, PDO::PARAM_INT);
                        break;
                    case '`TITLE_ID`':
                        $stmt->bindValue($identifier, $this->title_id, PDO::PARAM_INT);
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
                    case '`COUNTRY_ID`':
                        $stmt->bindValue($identifier, $this->country_id, PDO::PARAM_INT);
                        break;
                    case '`STATE_ID`':
                        $stmt->bindValue($identifier, $this->state_id, PDO::PARAM_INT);
                        break;
                    case '`PHONE`':
                        $stmt->bindValue($identifier, $this->phone, PDO::PARAM_STR);
                        break;
                    case '`CELLPHONE`':
                        $stmt->bindValue($identifier, $this->cellphone, PDO::PARAM_STR);
                        break;
                    case '`IS_DEFAULT`':
                        $stmt->bindValue($identifier, $this->is_default, PDO::PARAM_INT);
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
        $pos = AddressTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getLabel();
                break;
            case 2:
                return $this->getCustomerId();
                break;
            case 3:
                return $this->getTitleId();
                break;
            case 4:
                return $this->getCompany();
                break;
            case 5:
                return $this->getFirstname();
                break;
            case 6:
                return $this->getLastname();
                break;
            case 7:
                return $this->getAddress1();
                break;
            case 8:
                return $this->getAddress2();
                break;
            case 9:
                return $this->getAddress3();
                break;
            case 10:
                return $this->getZipcode();
                break;
            case 11:
                return $this->getCity();
                break;
            case 12:
                return $this->getCountryId();
                break;
            case 13:
                return $this->getStateId();
                break;
            case 14:
                return $this->getPhone();
                break;
            case 15:
                return $this->getCellphone();
                break;
            case 16:
                return $this->getIsDefault();
                break;
            case 17:
                return $this->getCreatedAt();
                break;
            case 18:
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
        if (isset($alreadyDumpedObjects['Address'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Address'][$this->getPrimaryKey()] = true;
        $keys = AddressTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getLabel(),
            $keys[2] => $this->getCustomerId(),
            $keys[3] => $this->getTitleId(),
            $keys[4] => $this->getCompany(),
            $keys[5] => $this->getFirstname(),
            $keys[6] => $this->getLastname(),
            $keys[7] => $this->getAddress1(),
            $keys[8] => $this->getAddress2(),
            $keys[9] => $this->getAddress3(),
            $keys[10] => $this->getZipcode(),
            $keys[11] => $this->getCity(),
            $keys[12] => $this->getCountryId(),
            $keys[13] => $this->getStateId(),
            $keys[14] => $this->getPhone(),
            $keys[15] => $this->getCellphone(),
            $keys[16] => $this->getIsDefault(),
            $keys[17] => $this->getCreatedAt(),
            $keys[18] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aCustomer) {
                $result['Customer'] = $this->aCustomer->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aCustomerTitle) {
                $result['CustomerTitle'] = $this->aCustomerTitle->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aCountry) {
                $result['Country'] = $this->aCountry->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aState) {
                $result['State'] = $this->aState->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collCartsRelatedByAddressDeliveryId) {
                $result['CartsRelatedByAddressDeliveryId'] = $this->collCartsRelatedByAddressDeliveryId->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCartsRelatedByAddressInvoiceId) {
                $result['CartsRelatedByAddressInvoiceId'] = $this->collCartsRelatedByAddressInvoiceId->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = AddressTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setLabel($value);
                break;
            case 2:
                $this->setCustomerId($value);
                break;
            case 3:
                $this->setTitleId($value);
                break;
            case 4:
                $this->setCompany($value);
                break;
            case 5:
                $this->setFirstname($value);
                break;
            case 6:
                $this->setLastname($value);
                break;
            case 7:
                $this->setAddress1($value);
                break;
            case 8:
                $this->setAddress2($value);
                break;
            case 9:
                $this->setAddress3($value);
                break;
            case 10:
                $this->setZipcode($value);
                break;
            case 11:
                $this->setCity($value);
                break;
            case 12:
                $this->setCountryId($value);
                break;
            case 13:
                $this->setStateId($value);
                break;
            case 14:
                $this->setPhone($value);
                break;
            case 15:
                $this->setCellphone($value);
                break;
            case 16:
                $this->setIsDefault($value);
                break;
            case 17:
                $this->setCreatedAt($value);
                break;
            case 18:
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
        $keys = AddressTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setLabel($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCustomerId($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setTitleId($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setCompany($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setFirstname($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setLastname($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setAddress1($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setAddress2($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setAddress3($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setZipcode($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setCity($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setCountryId($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setStateId($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setPhone($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setCellphone($arr[$keys[15]]);
        if (array_key_exists($keys[16], $arr)) $this->setIsDefault($arr[$keys[16]]);
        if (array_key_exists($keys[17], $arr)) $this->setCreatedAt($arr[$keys[17]]);
        if (array_key_exists($keys[18], $arr)) $this->setUpdatedAt($arr[$keys[18]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(AddressTableMap::DATABASE_NAME);

        if ($this->isColumnModified(AddressTableMap::ID)) $criteria->add(AddressTableMap::ID, $this->id);
        if ($this->isColumnModified(AddressTableMap::LABEL)) $criteria->add(AddressTableMap::LABEL, $this->label);
        if ($this->isColumnModified(AddressTableMap::CUSTOMER_ID)) $criteria->add(AddressTableMap::CUSTOMER_ID, $this->customer_id);
        if ($this->isColumnModified(AddressTableMap::TITLE_ID)) $criteria->add(AddressTableMap::TITLE_ID, $this->title_id);
        if ($this->isColumnModified(AddressTableMap::COMPANY)) $criteria->add(AddressTableMap::COMPANY, $this->company);
        if ($this->isColumnModified(AddressTableMap::FIRSTNAME)) $criteria->add(AddressTableMap::FIRSTNAME, $this->firstname);
        if ($this->isColumnModified(AddressTableMap::LASTNAME)) $criteria->add(AddressTableMap::LASTNAME, $this->lastname);
        if ($this->isColumnModified(AddressTableMap::ADDRESS1)) $criteria->add(AddressTableMap::ADDRESS1, $this->address1);
        if ($this->isColumnModified(AddressTableMap::ADDRESS2)) $criteria->add(AddressTableMap::ADDRESS2, $this->address2);
        if ($this->isColumnModified(AddressTableMap::ADDRESS3)) $criteria->add(AddressTableMap::ADDRESS3, $this->address3);
        if ($this->isColumnModified(AddressTableMap::ZIPCODE)) $criteria->add(AddressTableMap::ZIPCODE, $this->zipcode);
        if ($this->isColumnModified(AddressTableMap::CITY)) $criteria->add(AddressTableMap::CITY, $this->city);
        if ($this->isColumnModified(AddressTableMap::COUNTRY_ID)) $criteria->add(AddressTableMap::COUNTRY_ID, $this->country_id);
        if ($this->isColumnModified(AddressTableMap::STATE_ID)) $criteria->add(AddressTableMap::STATE_ID, $this->state_id);
        if ($this->isColumnModified(AddressTableMap::PHONE)) $criteria->add(AddressTableMap::PHONE, $this->phone);
        if ($this->isColumnModified(AddressTableMap::CELLPHONE)) $criteria->add(AddressTableMap::CELLPHONE, $this->cellphone);
        if ($this->isColumnModified(AddressTableMap::IS_DEFAULT)) $criteria->add(AddressTableMap::IS_DEFAULT, $this->is_default);
        if ($this->isColumnModified(AddressTableMap::CREATED_AT)) $criteria->add(AddressTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(AddressTableMap::UPDATED_AT)) $criteria->add(AddressTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(AddressTableMap::DATABASE_NAME);
        $criteria->add(AddressTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Address (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setLabel($this->getLabel());
        $copyObj->setCustomerId($this->getCustomerId());
        $copyObj->setTitleId($this->getTitleId());
        $copyObj->setCompany($this->getCompany());
        $copyObj->setFirstname($this->getFirstname());
        $copyObj->setLastname($this->getLastname());
        $copyObj->setAddress1($this->getAddress1());
        $copyObj->setAddress2($this->getAddress2());
        $copyObj->setAddress3($this->getAddress3());
        $copyObj->setZipcode($this->getZipcode());
        $copyObj->setCity($this->getCity());
        $copyObj->setCountryId($this->getCountryId());
        $copyObj->setStateId($this->getStateId());
        $copyObj->setPhone($this->getPhone());
        $copyObj->setCellphone($this->getCellphone());
        $copyObj->setIsDefault($this->getIsDefault());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getCartsRelatedByAddressDeliveryId() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCartRelatedByAddressDeliveryId($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCartsRelatedByAddressInvoiceId() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCartRelatedByAddressInvoiceId($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Address Clone of current object.
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
     * @return                 \Thelia\Model\Address The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCustomer(ChildCustomer $v = null)
    {
        if ($v === null) {
            $this->setCustomerId(NULL);
        } else {
            $this->setCustomerId($v->getId());
        }

        $this->aCustomer = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildCustomer object, it will not be re-added.
        if ($v !== null) {
            $v->addAddress($this);
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
        if ($this->aCustomer === null && ($this->customer_id !== null)) {
            $this->aCustomer = ChildCustomerQuery::create()->findPk($this->customer_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCustomer->addAddresses($this);
             */
        }

        return $this->aCustomer;
    }

    /**
     * Declares an association between this object and a ChildCustomerTitle object.
     *
     * @param                  ChildCustomerTitle $v
     * @return                 \Thelia\Model\Address The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCustomerTitle(ChildCustomerTitle $v = null)
    {
        if ($v === null) {
            $this->setTitleId(NULL);
        } else {
            $this->setTitleId($v->getId());
        }

        $this->aCustomerTitle = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildCustomerTitle object, it will not be re-added.
        if ($v !== null) {
            $v->addAddress($this);
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
        if ($this->aCustomerTitle === null && ($this->title_id !== null)) {
            $this->aCustomerTitle = ChildCustomerTitleQuery::create()->findPk($this->title_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCustomerTitle->addAddresses($this);
             */
        }

        return $this->aCustomerTitle;
    }

    /**
     * Declares an association between this object and a ChildCountry object.
     *
     * @param                  ChildCountry $v
     * @return                 \Thelia\Model\Address The current object (for fluent API support)
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
            $v->addAddress($this);
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
                $this->aCountry->addAddresses($this);
             */
        }

        return $this->aCountry;
    }

    /**
     * Declares an association between this object and a ChildState object.
     *
     * @param                  ChildState $v
     * @return                 \Thelia\Model\Address The current object (for fluent API support)
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
            $v->addAddress($this);
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
                $this->aState->addAddresses($this);
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
        if ('CartRelatedByAddressDeliveryId' == $relationName) {
            return $this->initCartsRelatedByAddressDeliveryId();
        }
        if ('CartRelatedByAddressInvoiceId' == $relationName) {
            return $this->initCartsRelatedByAddressInvoiceId();
        }
    }

    /**
     * Clears out the collCartsRelatedByAddressDeliveryId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCartsRelatedByAddressDeliveryId()
     */
    public function clearCartsRelatedByAddressDeliveryId()
    {
        $this->collCartsRelatedByAddressDeliveryId = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCartsRelatedByAddressDeliveryId collection loaded partially.
     */
    public function resetPartialCartsRelatedByAddressDeliveryId($v = true)
    {
        $this->collCartsRelatedByAddressDeliveryIdPartial = $v;
    }

    /**
     * Initializes the collCartsRelatedByAddressDeliveryId collection.
     *
     * By default this just sets the collCartsRelatedByAddressDeliveryId collection to an empty array (like clearcollCartsRelatedByAddressDeliveryId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCartsRelatedByAddressDeliveryId($overrideExisting = true)
    {
        if (null !== $this->collCartsRelatedByAddressDeliveryId && !$overrideExisting) {
            return;
        }
        $this->collCartsRelatedByAddressDeliveryId = new ObjectCollection();
        $this->collCartsRelatedByAddressDeliveryId->setModel('\Thelia\Model\Cart');
    }

    /**
     * Gets an array of ChildCart objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAddress is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCart[] List of ChildCart objects
     * @throws PropelException
     */
    public function getCartsRelatedByAddressDeliveryId($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCartsRelatedByAddressDeliveryIdPartial && !$this->isNew();
        if (null === $this->collCartsRelatedByAddressDeliveryId || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCartsRelatedByAddressDeliveryId) {
                // return empty collection
                $this->initCartsRelatedByAddressDeliveryId();
            } else {
                $collCartsRelatedByAddressDeliveryId = ChildCartQuery::create(null, $criteria)
                    ->filterByAddressRelatedByAddressDeliveryId($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCartsRelatedByAddressDeliveryIdPartial && count($collCartsRelatedByAddressDeliveryId)) {
                        $this->initCartsRelatedByAddressDeliveryId(false);

                        foreach ($collCartsRelatedByAddressDeliveryId as $obj) {
                            if (false == $this->collCartsRelatedByAddressDeliveryId->contains($obj)) {
                                $this->collCartsRelatedByAddressDeliveryId->append($obj);
                            }
                        }

                        $this->collCartsRelatedByAddressDeliveryIdPartial = true;
                    }

                    reset($collCartsRelatedByAddressDeliveryId);

                    return $collCartsRelatedByAddressDeliveryId;
                }

                if ($partial && $this->collCartsRelatedByAddressDeliveryId) {
                    foreach ($this->collCartsRelatedByAddressDeliveryId as $obj) {
                        if ($obj->isNew()) {
                            $collCartsRelatedByAddressDeliveryId[] = $obj;
                        }
                    }
                }

                $this->collCartsRelatedByAddressDeliveryId = $collCartsRelatedByAddressDeliveryId;
                $this->collCartsRelatedByAddressDeliveryIdPartial = false;
            }
        }

        return $this->collCartsRelatedByAddressDeliveryId;
    }

    /**
     * Sets a collection of CartRelatedByAddressDeliveryId objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $cartsRelatedByAddressDeliveryId A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildAddress The current object (for fluent API support)
     */
    public function setCartsRelatedByAddressDeliveryId(Collection $cartsRelatedByAddressDeliveryId, ConnectionInterface $con = null)
    {
        $cartsRelatedByAddressDeliveryIdToDelete = $this->getCartsRelatedByAddressDeliveryId(new Criteria(), $con)->diff($cartsRelatedByAddressDeliveryId);


        $this->cartsRelatedByAddressDeliveryIdScheduledForDeletion = $cartsRelatedByAddressDeliveryIdToDelete;

        foreach ($cartsRelatedByAddressDeliveryIdToDelete as $cartRelatedByAddressDeliveryIdRemoved) {
            $cartRelatedByAddressDeliveryIdRemoved->setAddressRelatedByAddressDeliveryId(null);
        }

        $this->collCartsRelatedByAddressDeliveryId = null;
        foreach ($cartsRelatedByAddressDeliveryId as $cartRelatedByAddressDeliveryId) {
            $this->addCartRelatedByAddressDeliveryId($cartRelatedByAddressDeliveryId);
        }

        $this->collCartsRelatedByAddressDeliveryId = $cartsRelatedByAddressDeliveryId;
        $this->collCartsRelatedByAddressDeliveryIdPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Cart objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Cart objects.
     * @throws PropelException
     */
    public function countCartsRelatedByAddressDeliveryId(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCartsRelatedByAddressDeliveryIdPartial && !$this->isNew();
        if (null === $this->collCartsRelatedByAddressDeliveryId || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCartsRelatedByAddressDeliveryId) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCartsRelatedByAddressDeliveryId());
            }

            $query = ChildCartQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByAddressRelatedByAddressDeliveryId($this)
                ->count($con);
        }

        return count($this->collCartsRelatedByAddressDeliveryId);
    }

    /**
     * Method called to associate a ChildCart object to this object
     * through the ChildCart foreign key attribute.
     *
     * @param    ChildCart $l ChildCart
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function addCartRelatedByAddressDeliveryId(ChildCart $l)
    {
        if ($this->collCartsRelatedByAddressDeliveryId === null) {
            $this->initCartsRelatedByAddressDeliveryId();
            $this->collCartsRelatedByAddressDeliveryIdPartial = true;
        }

        if (!in_array($l, $this->collCartsRelatedByAddressDeliveryId->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCartRelatedByAddressDeliveryId($l);
        }

        return $this;
    }

    /**
     * @param CartRelatedByAddressDeliveryId $cartRelatedByAddressDeliveryId The cartRelatedByAddressDeliveryId object to add.
     */
    protected function doAddCartRelatedByAddressDeliveryId($cartRelatedByAddressDeliveryId)
    {
        $this->collCartsRelatedByAddressDeliveryId[]= $cartRelatedByAddressDeliveryId;
        $cartRelatedByAddressDeliveryId->setAddressRelatedByAddressDeliveryId($this);
    }

    /**
     * @param  CartRelatedByAddressDeliveryId $cartRelatedByAddressDeliveryId The cartRelatedByAddressDeliveryId object to remove.
     * @return ChildAddress The current object (for fluent API support)
     */
    public function removeCartRelatedByAddressDeliveryId($cartRelatedByAddressDeliveryId)
    {
        if ($this->getCartsRelatedByAddressDeliveryId()->contains($cartRelatedByAddressDeliveryId)) {
            $this->collCartsRelatedByAddressDeliveryId->remove($this->collCartsRelatedByAddressDeliveryId->search($cartRelatedByAddressDeliveryId));
            if (null === $this->cartsRelatedByAddressDeliveryIdScheduledForDeletion) {
                $this->cartsRelatedByAddressDeliveryIdScheduledForDeletion = clone $this->collCartsRelatedByAddressDeliveryId;
                $this->cartsRelatedByAddressDeliveryIdScheduledForDeletion->clear();
            }
            $this->cartsRelatedByAddressDeliveryIdScheduledForDeletion[]= $cartRelatedByAddressDeliveryId;
            $cartRelatedByAddressDeliveryId->setAddressRelatedByAddressDeliveryId(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Address is new, it will return
     * an empty collection; or if this Address has previously
     * been saved, it will retrieve related CartsRelatedByAddressDeliveryId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Address.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCart[] List of ChildCart objects
     */
    public function getCartsRelatedByAddressDeliveryIdJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getCartsRelatedByAddressDeliveryId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Address is new, it will return
     * an empty collection; or if this Address has previously
     * been saved, it will retrieve related CartsRelatedByAddressDeliveryId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Address.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCart[] List of ChildCart objects
     */
    public function getCartsRelatedByAddressDeliveryIdJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getCartsRelatedByAddressDeliveryId($query, $con);
    }

    /**
     * Clears out the collCartsRelatedByAddressInvoiceId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCartsRelatedByAddressInvoiceId()
     */
    public function clearCartsRelatedByAddressInvoiceId()
    {
        $this->collCartsRelatedByAddressInvoiceId = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCartsRelatedByAddressInvoiceId collection loaded partially.
     */
    public function resetPartialCartsRelatedByAddressInvoiceId($v = true)
    {
        $this->collCartsRelatedByAddressInvoiceIdPartial = $v;
    }

    /**
     * Initializes the collCartsRelatedByAddressInvoiceId collection.
     *
     * By default this just sets the collCartsRelatedByAddressInvoiceId collection to an empty array (like clearcollCartsRelatedByAddressInvoiceId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCartsRelatedByAddressInvoiceId($overrideExisting = true)
    {
        if (null !== $this->collCartsRelatedByAddressInvoiceId && !$overrideExisting) {
            return;
        }
        $this->collCartsRelatedByAddressInvoiceId = new ObjectCollection();
        $this->collCartsRelatedByAddressInvoiceId->setModel('\Thelia\Model\Cart');
    }

    /**
     * Gets an array of ChildCart objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAddress is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCart[] List of ChildCart objects
     * @throws PropelException
     */
    public function getCartsRelatedByAddressInvoiceId($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCartsRelatedByAddressInvoiceIdPartial && !$this->isNew();
        if (null === $this->collCartsRelatedByAddressInvoiceId || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCartsRelatedByAddressInvoiceId) {
                // return empty collection
                $this->initCartsRelatedByAddressInvoiceId();
            } else {
                $collCartsRelatedByAddressInvoiceId = ChildCartQuery::create(null, $criteria)
                    ->filterByAddressRelatedByAddressInvoiceId($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCartsRelatedByAddressInvoiceIdPartial && count($collCartsRelatedByAddressInvoiceId)) {
                        $this->initCartsRelatedByAddressInvoiceId(false);

                        foreach ($collCartsRelatedByAddressInvoiceId as $obj) {
                            if (false == $this->collCartsRelatedByAddressInvoiceId->contains($obj)) {
                                $this->collCartsRelatedByAddressInvoiceId->append($obj);
                            }
                        }

                        $this->collCartsRelatedByAddressInvoiceIdPartial = true;
                    }

                    reset($collCartsRelatedByAddressInvoiceId);

                    return $collCartsRelatedByAddressInvoiceId;
                }

                if ($partial && $this->collCartsRelatedByAddressInvoiceId) {
                    foreach ($this->collCartsRelatedByAddressInvoiceId as $obj) {
                        if ($obj->isNew()) {
                            $collCartsRelatedByAddressInvoiceId[] = $obj;
                        }
                    }
                }

                $this->collCartsRelatedByAddressInvoiceId = $collCartsRelatedByAddressInvoiceId;
                $this->collCartsRelatedByAddressInvoiceIdPartial = false;
            }
        }

        return $this->collCartsRelatedByAddressInvoiceId;
    }

    /**
     * Sets a collection of CartRelatedByAddressInvoiceId objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $cartsRelatedByAddressInvoiceId A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildAddress The current object (for fluent API support)
     */
    public function setCartsRelatedByAddressInvoiceId(Collection $cartsRelatedByAddressInvoiceId, ConnectionInterface $con = null)
    {
        $cartsRelatedByAddressInvoiceIdToDelete = $this->getCartsRelatedByAddressInvoiceId(new Criteria(), $con)->diff($cartsRelatedByAddressInvoiceId);


        $this->cartsRelatedByAddressInvoiceIdScheduledForDeletion = $cartsRelatedByAddressInvoiceIdToDelete;

        foreach ($cartsRelatedByAddressInvoiceIdToDelete as $cartRelatedByAddressInvoiceIdRemoved) {
            $cartRelatedByAddressInvoiceIdRemoved->setAddressRelatedByAddressInvoiceId(null);
        }

        $this->collCartsRelatedByAddressInvoiceId = null;
        foreach ($cartsRelatedByAddressInvoiceId as $cartRelatedByAddressInvoiceId) {
            $this->addCartRelatedByAddressInvoiceId($cartRelatedByAddressInvoiceId);
        }

        $this->collCartsRelatedByAddressInvoiceId = $cartsRelatedByAddressInvoiceId;
        $this->collCartsRelatedByAddressInvoiceIdPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Cart objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Cart objects.
     * @throws PropelException
     */
    public function countCartsRelatedByAddressInvoiceId(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCartsRelatedByAddressInvoiceIdPartial && !$this->isNew();
        if (null === $this->collCartsRelatedByAddressInvoiceId || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCartsRelatedByAddressInvoiceId) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCartsRelatedByAddressInvoiceId());
            }

            $query = ChildCartQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByAddressRelatedByAddressInvoiceId($this)
                ->count($con);
        }

        return count($this->collCartsRelatedByAddressInvoiceId);
    }

    /**
     * Method called to associate a ChildCart object to this object
     * through the ChildCart foreign key attribute.
     *
     * @param    ChildCart $l ChildCart
     * @return   \Thelia\Model\Address The current object (for fluent API support)
     */
    public function addCartRelatedByAddressInvoiceId(ChildCart $l)
    {
        if ($this->collCartsRelatedByAddressInvoiceId === null) {
            $this->initCartsRelatedByAddressInvoiceId();
            $this->collCartsRelatedByAddressInvoiceIdPartial = true;
        }

        if (!in_array($l, $this->collCartsRelatedByAddressInvoiceId->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCartRelatedByAddressInvoiceId($l);
        }

        return $this;
    }

    /**
     * @param CartRelatedByAddressInvoiceId $cartRelatedByAddressInvoiceId The cartRelatedByAddressInvoiceId object to add.
     */
    protected function doAddCartRelatedByAddressInvoiceId($cartRelatedByAddressInvoiceId)
    {
        $this->collCartsRelatedByAddressInvoiceId[]= $cartRelatedByAddressInvoiceId;
        $cartRelatedByAddressInvoiceId->setAddressRelatedByAddressInvoiceId($this);
    }

    /**
     * @param  CartRelatedByAddressInvoiceId $cartRelatedByAddressInvoiceId The cartRelatedByAddressInvoiceId object to remove.
     * @return ChildAddress The current object (for fluent API support)
     */
    public function removeCartRelatedByAddressInvoiceId($cartRelatedByAddressInvoiceId)
    {
        if ($this->getCartsRelatedByAddressInvoiceId()->contains($cartRelatedByAddressInvoiceId)) {
            $this->collCartsRelatedByAddressInvoiceId->remove($this->collCartsRelatedByAddressInvoiceId->search($cartRelatedByAddressInvoiceId));
            if (null === $this->cartsRelatedByAddressInvoiceIdScheduledForDeletion) {
                $this->cartsRelatedByAddressInvoiceIdScheduledForDeletion = clone $this->collCartsRelatedByAddressInvoiceId;
                $this->cartsRelatedByAddressInvoiceIdScheduledForDeletion->clear();
            }
            $this->cartsRelatedByAddressInvoiceIdScheduledForDeletion[]= $cartRelatedByAddressInvoiceId;
            $cartRelatedByAddressInvoiceId->setAddressRelatedByAddressInvoiceId(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Address is new, it will return
     * an empty collection; or if this Address has previously
     * been saved, it will retrieve related CartsRelatedByAddressInvoiceId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Address.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCart[] List of ChildCart objects
     */
    public function getCartsRelatedByAddressInvoiceIdJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getCartsRelatedByAddressInvoiceId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Address is new, it will return
     * an empty collection; or if this Address has previously
     * been saved, it will retrieve related CartsRelatedByAddressInvoiceId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Address.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCart[] List of ChildCart objects
     */
    public function getCartsRelatedByAddressInvoiceIdJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getCartsRelatedByAddressInvoiceId($query, $con);
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->label = null;
        $this->customer_id = null;
        $this->title_id = null;
        $this->company = null;
        $this->firstname = null;
        $this->lastname = null;
        $this->address1 = null;
        $this->address2 = null;
        $this->address3 = null;
        $this->zipcode = null;
        $this->city = null;
        $this->country_id = null;
        $this->state_id = null;
        $this->phone = null;
        $this->cellphone = null;
        $this->is_default = null;
        $this->created_at = null;
        $this->updated_at = null;
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
            if ($this->collCartsRelatedByAddressDeliveryId) {
                foreach ($this->collCartsRelatedByAddressDeliveryId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCartsRelatedByAddressInvoiceId) {
                foreach ($this->collCartsRelatedByAddressInvoiceId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collCartsRelatedByAddressDeliveryId = null;
        $this->collCartsRelatedByAddressInvoiceId = null;
        $this->aCustomer = null;
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
        return (string) $this->exportTo(AddressTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildAddress The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[AddressTableMap::UPDATED_AT] = true;

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
