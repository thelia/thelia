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
use Thelia\Model\Coupon as ChildCoupon;
use Thelia\Model\CouponCustomerCount as ChildCouponCustomerCount;
use Thelia\Model\CouponCustomerCountQuery as ChildCouponCustomerCountQuery;
use Thelia\Model\CouponQuery as ChildCouponQuery;
use Thelia\Model\Customer as ChildCustomer;
use Thelia\Model\CustomerQuery as ChildCustomerQuery;
use Thelia\Model\CustomerTitle as ChildCustomerTitle;
use Thelia\Model\CustomerTitleQuery as ChildCustomerTitleQuery;
use Thelia\Model\CustomerVersion as ChildCustomerVersion;
use Thelia\Model\CustomerVersionQuery as ChildCustomerVersionQuery;
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\OrderVersionQuery as ChildOrderVersionQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\CustomerVersionTableMap;
use Thelia\Model\Map\OrderVersionTableMap;

abstract class Customer implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\CustomerTableMap';


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
     * The value for the ref field.
     * @var        string
     */
    protected $ref;

    /**
     * The value for the title_id field.
     * @var        int
     */
    protected $title_id;

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
     * The value for the lang field.
     * @var        string
     */
    protected $lang;

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
     * @var        CustomerTitle
     */
    protected $aCustomerTitle;

    /**
     * @var        ObjectCollection|ChildAddress[] Collection to store aggregation of ChildAddress objects.
     */
    protected $collAddresses;
    protected $collAddressesPartial;

    /**
     * @var        ObjectCollection|ChildOrder[] Collection to store aggregation of ChildOrder objects.
     */
    protected $collOrders;
    protected $collOrdersPartial;

    /**
     * @var        ObjectCollection|ChildCart[] Collection to store aggregation of ChildCart objects.
     */
    protected $collCarts;
    protected $collCartsPartial;

    /**
     * @var        ObjectCollection|ChildCouponCustomerCount[] Collection to store aggregation of ChildCouponCustomerCount objects.
     */
    protected $collCouponCustomerCounts;
    protected $collCouponCustomerCountsPartial;

    /**
     * @var        ObjectCollection|ChildCustomerVersion[] Collection to store aggregation of ChildCustomerVersion objects.
     */
    protected $collCustomerVersions;
    protected $collCustomerVersionsPartial;

    /**
     * @var        ChildCoupon[] Collection to store aggregation of ChildCoupon objects.
     */
    protected $collCoupons;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    // versionable behavior


    /**
     * @var bool
     */
    protected $enforceVersion = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $addressesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $ordersScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $cartsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponCustomerCountsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $customerVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->discount = '0.000000';
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Customer object.
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
     * Compares this with another <code>Customer</code> instance.  If
     * <code>obj</code> is an instance of <code>Customer</code>, delegates to
     * <code>equals(Customer)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Customer The current object, for fluid interface
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
     * @return Customer The current object, for fluid interface
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
     * Get the [ref] column value.
     *
     * @return   string
     */
    public function getRef()
    {

        return $this->ref;
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
     * Get the [lang] column value.
     *
     * @return   string
     */
    public function getLang()
    {

        return $this->lang;
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
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[CustomerTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [ref] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->ref !== $v) {
            $this->ref = $v;
            $this->modifiedColumns[CustomerTableMap::REF] = true;
        }


        return $this;
    } // setRef()

    /**
     * Set the value of [title_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setTitleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->title_id !== $v) {
            $this->title_id = $v;
            $this->modifiedColumns[CustomerTableMap::TITLE_ID] = true;
        }

        if ($this->aCustomerTitle !== null && $this->aCustomerTitle->getId() !== $v) {
            $this->aCustomerTitle = null;
        }


        return $this;
    } // setTitleId()

    /**
     * Set the value of [firstname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setFirstname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->firstname !== $v) {
            $this->firstname = $v;
            $this->modifiedColumns[CustomerTableMap::FIRSTNAME] = true;
        }


        return $this;
    } // setFirstname()

    /**
     * Set the value of [lastname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setLastname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lastname !== $v) {
            $this->lastname = $v;
            $this->modifiedColumns[CustomerTableMap::LASTNAME] = true;
        }


        return $this;
    } // setLastname()

    /**
     * Set the value of [email] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setEmail($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->email !== $v) {
            $this->email = $v;
            $this->modifiedColumns[CustomerTableMap::EMAIL] = true;
        }


        return $this;
    } // setEmail()

    /**
     * Set the value of [password] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setPassword($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->password !== $v) {
            $this->password = $v;
            $this->modifiedColumns[CustomerTableMap::PASSWORD] = true;
        }


        return $this;
    } // setPassword()

    /**
     * Set the value of [algo] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setAlgo($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->algo !== $v) {
            $this->algo = $v;
            $this->modifiedColumns[CustomerTableMap::ALGO] = true;
        }


        return $this;
    } // setAlgo()

    /**
     * Set the value of [reseller] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setReseller($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->reseller !== $v) {
            $this->reseller = $v;
            $this->modifiedColumns[CustomerTableMap::RESELLER] = true;
        }


        return $this;
    } // setReseller()

    /**
     * Set the value of [lang] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setLang($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lang !== $v) {
            $this->lang = $v;
            $this->modifiedColumns[CustomerTableMap::LANG] = true;
        }


        return $this;
    } // setLang()

    /**
     * Set the value of [sponsor] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setSponsor($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->sponsor !== $v) {
            $this->sponsor = $v;
            $this->modifiedColumns[CustomerTableMap::SPONSOR] = true;
        }


        return $this;
    } // setSponsor()

    /**
     * Set the value of [discount] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setDiscount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->discount !== $v) {
            $this->discount = $v;
            $this->modifiedColumns[CustomerTableMap::DISCOUNT] = true;
        }


        return $this;
    } // setDiscount()

    /**
     * Set the value of [remember_me_token] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setRememberMeToken($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->remember_me_token !== $v) {
            $this->remember_me_token = $v;
            $this->modifiedColumns[CustomerTableMap::REMEMBER_ME_TOKEN] = true;
        }


        return $this;
    } // setRememberMeToken()

    /**
     * Set the value of [remember_me_serial] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setRememberMeSerial($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->remember_me_serial !== $v) {
            $this->remember_me_serial = $v;
            $this->modifiedColumns[CustomerTableMap::REMEMBER_ME_SERIAL] = true;
        }


        return $this;
    } // setRememberMeSerial()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[CustomerTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[CustomerTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[CustomerTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[CustomerTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[CustomerTableMap::VERSION_CREATED_BY] = true;
        }


        return $this;
    } // setVersionCreatedBy()

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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : CustomerTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : CustomerTableMap::translateFieldName('Ref', TableMap::TYPE_PHPNAME, $indexType)];
            $this->ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : CustomerTableMap::translateFieldName('TitleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->title_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : CustomerTableMap::translateFieldName('Firstname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->firstname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : CustomerTableMap::translateFieldName('Lastname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lastname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : CustomerTableMap::translateFieldName('Email', TableMap::TYPE_PHPNAME, $indexType)];
            $this->email = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : CustomerTableMap::translateFieldName('Password', TableMap::TYPE_PHPNAME, $indexType)];
            $this->password = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : CustomerTableMap::translateFieldName('Algo', TableMap::TYPE_PHPNAME, $indexType)];
            $this->algo = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : CustomerTableMap::translateFieldName('Reseller', TableMap::TYPE_PHPNAME, $indexType)];
            $this->reseller = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : CustomerTableMap::translateFieldName('Lang', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lang = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : CustomerTableMap::translateFieldName('Sponsor', TableMap::TYPE_PHPNAME, $indexType)];
            $this->sponsor = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : CustomerTableMap::translateFieldName('Discount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->discount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : CustomerTableMap::translateFieldName('RememberMeToken', TableMap::TYPE_PHPNAME, $indexType)];
            $this->remember_me_token = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : CustomerTableMap::translateFieldName('RememberMeSerial', TableMap::TYPE_PHPNAME, $indexType)];
            $this->remember_me_serial = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : CustomerTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : CustomerTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : CustomerTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : CustomerTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : CustomerTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 19; // 19 = CustomerTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Customer object", 0, $e);
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
        if ($this->aCustomerTitle !== null && $this->title_id !== $this->aCustomerTitle->getId()) {
            $this->aCustomerTitle = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(CustomerTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildCustomerQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aCustomerTitle = null;
            $this->collAddresses = null;

            $this->collOrders = null;

            $this->collCarts = null;

            $this->collCouponCustomerCounts = null;

            $this->collCustomerVersions = null;

            $this->collCoupons = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Customer::setDeleted()
     * @see Customer::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildCustomerQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(CustomerTableMap::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(CustomerTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(CustomerTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(CustomerTableMap::UPDATED_AT)) {
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
                // versionable behavior
                if (isset($createVersion)) {
                    $this->addVersion($con);
                }
                CustomerTableMap::addInstanceToPool($this);
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

            if ($this->couponsScheduledForDeletion !== null) {
                if (!$this->couponsScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->couponsScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    CouponCustomerCountQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->couponsScheduledForDeletion = null;
                }

                foreach ($this->getCoupons() as $coupon) {
                    if ($coupon->isModified()) {
                        $coupon->save($con);
                    }
                }
            } elseif ($this->collCoupons) {
                foreach ($this->collCoupons as $coupon) {
                    if ($coupon->isModified()) {
                        $coupon->save($con);
                    }
                }
            }

            if ($this->addressesScheduledForDeletion !== null) {
                if (!$this->addressesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AddressQuery::create()
                        ->filterByPrimaryKeys($this->addressesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->addressesScheduledForDeletion = null;
                }
            }

                if ($this->collAddresses !== null) {
            foreach ($this->collAddresses as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->ordersScheduledForDeletion !== null) {
                if (!$this->ordersScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderQuery::create()
                        ->filterByPrimaryKeys($this->ordersScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->ordersScheduledForDeletion = null;
                }
            }

                if ($this->collOrders !== null) {
            foreach ($this->collOrders as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->cartsScheduledForDeletion !== null) {
                if (!$this->cartsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CartQuery::create()
                        ->filterByPrimaryKeys($this->cartsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->cartsScheduledForDeletion = null;
                }
            }

                if ($this->collCarts !== null) {
            foreach ($this->collCarts as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->couponCustomerCountsScheduledForDeletion !== null) {
                if (!$this->couponCustomerCountsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CouponCustomerCountQuery::create()
                        ->filterByPrimaryKeys($this->couponCustomerCountsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->couponCustomerCountsScheduledForDeletion = null;
                }
            }

                if ($this->collCouponCustomerCounts !== null) {
            foreach ($this->collCouponCustomerCounts as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->customerVersionsScheduledForDeletion !== null) {
                if (!$this->customerVersionsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CustomerVersionQuery::create()
                        ->filterByPrimaryKeys($this->customerVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->customerVersionsScheduledForDeletion = null;
                }
            }

                if ($this->collCustomerVersions !== null) {
            foreach ($this->collCustomerVersions as $referrerFK) {
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

        $this->modifiedColumns[CustomerTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . CustomerTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CustomerTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CustomerTableMap::REF)) {
            $modifiedColumns[':p' . $index++]  = '`REF`';
        }
        if ($this->isColumnModified(CustomerTableMap::TITLE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`TITLE_ID`';
        }
        if ($this->isColumnModified(CustomerTableMap::FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`FIRSTNAME`';
        }
        if ($this->isColumnModified(CustomerTableMap::LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`LASTNAME`';
        }
        if ($this->isColumnModified(CustomerTableMap::EMAIL)) {
            $modifiedColumns[':p' . $index++]  = '`EMAIL`';
        }
        if ($this->isColumnModified(CustomerTableMap::PASSWORD)) {
            $modifiedColumns[':p' . $index++]  = '`PASSWORD`';
        }
        if ($this->isColumnModified(CustomerTableMap::ALGO)) {
            $modifiedColumns[':p' . $index++]  = '`ALGO`';
        }
        if ($this->isColumnModified(CustomerTableMap::RESELLER)) {
            $modifiedColumns[':p' . $index++]  = '`RESELLER`';
        }
        if ($this->isColumnModified(CustomerTableMap::LANG)) {
            $modifiedColumns[':p' . $index++]  = '`LANG`';
        }
        if ($this->isColumnModified(CustomerTableMap::SPONSOR)) {
            $modifiedColumns[':p' . $index++]  = '`SPONSOR`';
        }
        if ($this->isColumnModified(CustomerTableMap::DISCOUNT)) {
            $modifiedColumns[':p' . $index++]  = '`DISCOUNT`';
        }
        if ($this->isColumnModified(CustomerTableMap::REMEMBER_ME_TOKEN)) {
            $modifiedColumns[':p' . $index++]  = '`REMEMBER_ME_TOKEN`';
        }
        if ($this->isColumnModified(CustomerTableMap::REMEMBER_ME_SERIAL)) {
            $modifiedColumns[':p' . $index++]  = '`REMEMBER_ME_SERIAL`';
        }
        if ($this->isColumnModified(CustomerTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CustomerTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(CustomerTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(CustomerTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(CustomerTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
        }

        $sql = sprintf(
            'INSERT INTO `customer` (%s) VALUES (%s)',
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
                    case '`REF`':
                        $stmt->bindValue($identifier, $this->ref, PDO::PARAM_STR);
                        break;
                    case '`TITLE_ID`':
                        $stmt->bindValue($identifier, $this->title_id, PDO::PARAM_INT);
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
                    case '`LANG`':
                        $stmt->bindValue($identifier, $this->lang, PDO::PARAM_STR);
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
        $pos = CustomerTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getRef();
                break;
            case 2:
                return $this->getTitleId();
                break;
            case 3:
                return $this->getFirstname();
                break;
            case 4:
                return $this->getLastname();
                break;
            case 5:
                return $this->getEmail();
                break;
            case 6:
                return $this->getPassword();
                break;
            case 7:
                return $this->getAlgo();
                break;
            case 8:
                return $this->getReseller();
                break;
            case 9:
                return $this->getLang();
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
                return $this->getCreatedAt();
                break;
            case 15:
                return $this->getUpdatedAt();
                break;
            case 16:
                return $this->getVersion();
                break;
            case 17:
                return $this->getVersionCreatedAt();
                break;
            case 18:
                return $this->getVersionCreatedBy();
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
        if (isset($alreadyDumpedObjects['Customer'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Customer'][$this->getPrimaryKey()] = true;
        $keys = CustomerTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getRef(),
            $keys[2] => $this->getTitleId(),
            $keys[3] => $this->getFirstname(),
            $keys[4] => $this->getLastname(),
            $keys[5] => $this->getEmail(),
            $keys[6] => $this->getPassword(),
            $keys[7] => $this->getAlgo(),
            $keys[8] => $this->getReseller(),
            $keys[9] => $this->getLang(),
            $keys[10] => $this->getSponsor(),
            $keys[11] => $this->getDiscount(),
            $keys[12] => $this->getRememberMeToken(),
            $keys[13] => $this->getRememberMeSerial(),
            $keys[14] => $this->getCreatedAt(),
            $keys[15] => $this->getUpdatedAt(),
            $keys[16] => $this->getVersion(),
            $keys[17] => $this->getVersionCreatedAt(),
            $keys[18] => $this->getVersionCreatedBy(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aCustomerTitle) {
                $result['CustomerTitle'] = $this->aCustomerTitle->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collAddresses) {
                $result['Addresses'] = $this->collAddresses->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrders) {
                $result['Orders'] = $this->collOrders->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCarts) {
                $result['Carts'] = $this->collCarts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCouponCustomerCounts) {
                $result['CouponCustomerCounts'] = $this->collCouponCustomerCounts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCustomerVersions) {
                $result['CustomerVersions'] = $this->collCustomerVersions->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = CustomerTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setRef($value);
                break;
            case 2:
                $this->setTitleId($value);
                break;
            case 3:
                $this->setFirstname($value);
                break;
            case 4:
                $this->setLastname($value);
                break;
            case 5:
                $this->setEmail($value);
                break;
            case 6:
                $this->setPassword($value);
                break;
            case 7:
                $this->setAlgo($value);
                break;
            case 8:
                $this->setReseller($value);
                break;
            case 9:
                $this->setLang($value);
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
                $this->setCreatedAt($value);
                break;
            case 15:
                $this->setUpdatedAt($value);
                break;
            case 16:
                $this->setVersion($value);
                break;
            case 17:
                $this->setVersionCreatedAt($value);
                break;
            case 18:
                $this->setVersionCreatedBy($value);
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
        $keys = CustomerTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setRef($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setTitleId($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setFirstname($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setLastname($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setEmail($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setPassword($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setAlgo($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setReseller($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setLang($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setSponsor($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setDiscount($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setRememberMeToken($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setRememberMeSerial($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setCreatedAt($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setUpdatedAt($arr[$keys[15]]);
        if (array_key_exists($keys[16], $arr)) $this->setVersion($arr[$keys[16]]);
        if (array_key_exists($keys[17], $arr)) $this->setVersionCreatedAt($arr[$keys[17]]);
        if (array_key_exists($keys[18], $arr)) $this->setVersionCreatedBy($arr[$keys[18]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(CustomerTableMap::DATABASE_NAME);

        if ($this->isColumnModified(CustomerTableMap::ID)) $criteria->add(CustomerTableMap::ID, $this->id);
        if ($this->isColumnModified(CustomerTableMap::REF)) $criteria->add(CustomerTableMap::REF, $this->ref);
        if ($this->isColumnModified(CustomerTableMap::TITLE_ID)) $criteria->add(CustomerTableMap::TITLE_ID, $this->title_id);
        if ($this->isColumnModified(CustomerTableMap::FIRSTNAME)) $criteria->add(CustomerTableMap::FIRSTNAME, $this->firstname);
        if ($this->isColumnModified(CustomerTableMap::LASTNAME)) $criteria->add(CustomerTableMap::LASTNAME, $this->lastname);
        if ($this->isColumnModified(CustomerTableMap::EMAIL)) $criteria->add(CustomerTableMap::EMAIL, $this->email);
        if ($this->isColumnModified(CustomerTableMap::PASSWORD)) $criteria->add(CustomerTableMap::PASSWORD, $this->password);
        if ($this->isColumnModified(CustomerTableMap::ALGO)) $criteria->add(CustomerTableMap::ALGO, $this->algo);
        if ($this->isColumnModified(CustomerTableMap::RESELLER)) $criteria->add(CustomerTableMap::RESELLER, $this->reseller);
        if ($this->isColumnModified(CustomerTableMap::LANG)) $criteria->add(CustomerTableMap::LANG, $this->lang);
        if ($this->isColumnModified(CustomerTableMap::SPONSOR)) $criteria->add(CustomerTableMap::SPONSOR, $this->sponsor);
        if ($this->isColumnModified(CustomerTableMap::DISCOUNT)) $criteria->add(CustomerTableMap::DISCOUNT, $this->discount);
        if ($this->isColumnModified(CustomerTableMap::REMEMBER_ME_TOKEN)) $criteria->add(CustomerTableMap::REMEMBER_ME_TOKEN, $this->remember_me_token);
        if ($this->isColumnModified(CustomerTableMap::REMEMBER_ME_SERIAL)) $criteria->add(CustomerTableMap::REMEMBER_ME_SERIAL, $this->remember_me_serial);
        if ($this->isColumnModified(CustomerTableMap::CREATED_AT)) $criteria->add(CustomerTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CustomerTableMap::UPDATED_AT)) $criteria->add(CustomerTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(CustomerTableMap::VERSION)) $criteria->add(CustomerTableMap::VERSION, $this->version);
        if ($this->isColumnModified(CustomerTableMap::VERSION_CREATED_AT)) $criteria->add(CustomerTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(CustomerTableMap::VERSION_CREATED_BY)) $criteria->add(CustomerTableMap::VERSION_CREATED_BY, $this->version_created_by);

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
        $criteria = new Criteria(CustomerTableMap::DATABASE_NAME);
        $criteria->add(CustomerTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Customer (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setRef($this->getRef());
        $copyObj->setTitleId($this->getTitleId());
        $copyObj->setFirstname($this->getFirstname());
        $copyObj->setLastname($this->getLastname());
        $copyObj->setEmail($this->getEmail());
        $copyObj->setPassword($this->getPassword());
        $copyObj->setAlgo($this->getAlgo());
        $copyObj->setReseller($this->getReseller());
        $copyObj->setLang($this->getLang());
        $copyObj->setSponsor($this->getSponsor());
        $copyObj->setDiscount($this->getDiscount());
        $copyObj->setRememberMeToken($this->getRememberMeToken());
        $copyObj->setRememberMeSerial($this->getRememberMeSerial());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getAddresses() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAddress($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrders() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrder($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCarts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCart($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCouponCustomerCounts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCouponCustomerCount($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCustomerVersions() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCustomerVersion($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Customer Clone of current object.
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
     * @return                 \Thelia\Model\Customer The current object (for fluent API support)
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
            $v->addCustomer($this);
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
                $this->aCustomerTitle->addCustomers($this);
             */
        }

        return $this->aCustomerTitle;
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
        if ('Address' == $relationName) {
            return $this->initAddresses();
        }
        if ('Order' == $relationName) {
            return $this->initOrders();
        }
        if ('Cart' == $relationName) {
            return $this->initCarts();
        }
        if ('CouponCustomerCount' == $relationName) {
            return $this->initCouponCustomerCounts();
        }
        if ('CustomerVersion' == $relationName) {
            return $this->initCustomerVersions();
        }
    }

    /**
     * Clears out the collAddresses collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAddresses()
     */
    public function clearAddresses()
    {
        $this->collAddresses = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAddresses collection loaded partially.
     */
    public function resetPartialAddresses($v = true)
    {
        $this->collAddressesPartial = $v;
    }

    /**
     * Initializes the collAddresses collection.
     *
     * By default this just sets the collAddresses collection to an empty array (like clearcollAddresses());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAddresses($overrideExisting = true)
    {
        if (null !== $this->collAddresses && !$overrideExisting) {
            return;
        }
        $this->collAddresses = new ObjectCollection();
        $this->collAddresses->setModel('\Thelia\Model\Address');
    }

    /**
     * Gets an array of ChildAddress objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomer is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAddress[] List of ChildAddress objects
     * @throws PropelException
     */
    public function getAddresses($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAddressesPartial && !$this->isNew();
        if (null === $this->collAddresses || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAddresses) {
                // return empty collection
                $this->initAddresses();
            } else {
                $collAddresses = ChildAddressQuery::create(null, $criteria)
                    ->filterByCustomer($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAddressesPartial && count($collAddresses)) {
                        $this->initAddresses(false);

                        foreach ($collAddresses as $obj) {
                            if (false == $this->collAddresses->contains($obj)) {
                                $this->collAddresses->append($obj);
                            }
                        }

                        $this->collAddressesPartial = true;
                    }

                    reset($collAddresses);

                    return $collAddresses;
                }

                if ($partial && $this->collAddresses) {
                    foreach ($this->collAddresses as $obj) {
                        if ($obj->isNew()) {
                            $collAddresses[] = $obj;
                        }
                    }
                }

                $this->collAddresses = $collAddresses;
                $this->collAddressesPartial = false;
            }
        }

        return $this->collAddresses;
    }

    /**
     * Sets a collection of Address objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $addresses A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCustomer The current object (for fluent API support)
     */
    public function setAddresses(Collection $addresses, ConnectionInterface $con = null)
    {
        $addressesToDelete = $this->getAddresses(new Criteria(), $con)->diff($addresses);


        $this->addressesScheduledForDeletion = $addressesToDelete;

        foreach ($addressesToDelete as $addressRemoved) {
            $addressRemoved->setCustomer(null);
        }

        $this->collAddresses = null;
        foreach ($addresses as $address) {
            $this->addAddress($address);
        }

        $this->collAddresses = $addresses;
        $this->collAddressesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Address objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Address objects.
     * @throws PropelException
     */
    public function countAddresses(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAddressesPartial && !$this->isNew();
        if (null === $this->collAddresses || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAddresses) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAddresses());
            }

            $query = ChildAddressQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCustomer($this)
                ->count($con);
        }

        return count($this->collAddresses);
    }

    /**
     * Method called to associate a ChildAddress object to this object
     * through the ChildAddress foreign key attribute.
     *
     * @param    ChildAddress $l ChildAddress
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function addAddress(ChildAddress $l)
    {
        if ($this->collAddresses === null) {
            $this->initAddresses();
            $this->collAddressesPartial = true;
        }

        if (!in_array($l, $this->collAddresses->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAddress($l);
        }

        return $this;
    }

    /**
     * @param Address $address The address object to add.
     */
    protected function doAddAddress($address)
    {
        $this->collAddresses[]= $address;
        $address->setCustomer($this);
    }

    /**
     * @param  Address $address The address object to remove.
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function removeAddress($address)
    {
        if ($this->getAddresses()->contains($address)) {
            $this->collAddresses->remove($this->collAddresses->search($address));
            if (null === $this->addressesScheduledForDeletion) {
                $this->addressesScheduledForDeletion = clone $this->collAddresses;
                $this->addressesScheduledForDeletion->clear();
            }
            $this->addressesScheduledForDeletion[]= clone $address;
            $address->setCustomer(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAddress[] List of ChildAddress objects
     */
    public function getAddressesJoinCustomerTitle($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAddressQuery::create(null, $criteria);
        $query->joinWith('CustomerTitle', $joinBehavior);

        return $this->getAddresses($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAddress[] List of ChildAddress objects
     */
    public function getAddressesJoinCountry($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAddressQuery::create(null, $criteria);
        $query->joinWith('Country', $joinBehavior);

        return $this->getAddresses($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAddress[] List of ChildAddress objects
     */
    public function getAddressesJoinState($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAddressQuery::create(null, $criteria);
        $query->joinWith('State', $joinBehavior);

        return $this->getAddresses($query, $con);
    }

    /**
     * Clears out the collOrders collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrders()
     */
    public function clearOrders()
    {
        $this->collOrders = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrders collection loaded partially.
     */
    public function resetPartialOrders($v = true)
    {
        $this->collOrdersPartial = $v;
    }

    /**
     * Initializes the collOrders collection.
     *
     * By default this just sets the collOrders collection to an empty array (like clearcollOrders());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrders($overrideExisting = true)
    {
        if (null !== $this->collOrders && !$overrideExisting) {
            return;
        }
        $this->collOrders = new ObjectCollection();
        $this->collOrders->setModel('\Thelia\Model\Order');
    }

    /**
     * Gets an array of ChildOrder objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomer is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrder[] List of ChildOrder objects
     * @throws PropelException
     */
    public function getOrders($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersPartial && !$this->isNew();
        if (null === $this->collOrders || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrders) {
                // return empty collection
                $this->initOrders();
            } else {
                $collOrders = ChildOrderQuery::create(null, $criteria)
                    ->filterByCustomer($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrdersPartial && count($collOrders)) {
                        $this->initOrders(false);

                        foreach ($collOrders as $obj) {
                            if (false == $this->collOrders->contains($obj)) {
                                $this->collOrders->append($obj);
                            }
                        }

                        $this->collOrdersPartial = true;
                    }

                    reset($collOrders);

                    return $collOrders;
                }

                if ($partial && $this->collOrders) {
                    foreach ($this->collOrders as $obj) {
                        if ($obj->isNew()) {
                            $collOrders[] = $obj;
                        }
                    }
                }

                $this->collOrders = $collOrders;
                $this->collOrdersPartial = false;
            }
        }

        return $this->collOrders;
    }

    /**
     * Sets a collection of Order objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orders A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCustomer The current object (for fluent API support)
     */
    public function setOrders(Collection $orders, ConnectionInterface $con = null)
    {
        $ordersToDelete = $this->getOrders(new Criteria(), $con)->diff($orders);


        $this->ordersScheduledForDeletion = $ordersToDelete;

        foreach ($ordersToDelete as $orderRemoved) {
            $orderRemoved->setCustomer(null);
        }

        $this->collOrders = null;
        foreach ($orders as $order) {
            $this->addOrder($order);
        }

        $this->collOrders = $orders;
        $this->collOrdersPartial = false;

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
    public function countOrders(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersPartial && !$this->isNew();
        if (null === $this->collOrders || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrders) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrders());
            }

            $query = ChildOrderQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCustomer($this)
                ->count($con);
        }

        return count($this->collOrders);
    }

    /**
     * Method called to associate a ChildOrder object to this object
     * through the ChildOrder foreign key attribute.
     *
     * @param    ChildOrder $l ChildOrder
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function addOrder(ChildOrder $l)
    {
        if ($this->collOrders === null) {
            $this->initOrders();
            $this->collOrdersPartial = true;
        }

        if (!in_array($l, $this->collOrders->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrder($l);
        }

        return $this;
    }

    /**
     * @param Order $order The order object to add.
     */
    protected function doAddOrder($order)
    {
        $this->collOrders[]= $order;
        $order->setCustomer($this);
    }

    /**
     * @param  Order $order The order object to remove.
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function removeOrder($order)
    {
        if ($this->getOrders()->contains($order)) {
            $this->collOrders->remove($this->collOrders->search($order));
            if (null === $this->ordersScheduledForDeletion) {
                $this->ordersScheduledForDeletion = clone $this->collOrders;
                $this->ordersScheduledForDeletion->clear();
            }
            $this->ordersScheduledForDeletion[]= clone $order;
            $order->setCustomer(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersJoinOrderAddressRelatedByInvoiceOrderAddressId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderAddressRelatedByInvoiceOrderAddressId', $joinBehavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersJoinOrderAddressRelatedByDeliveryOrderAddressId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderAddressRelatedByDeliveryOrderAddressId', $joinBehavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersJoinOrderStatus($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderStatus', $joinBehavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersJoinModuleRelatedByPaymentModuleId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('ModuleRelatedByPaymentModuleId', $joinBehavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersJoinModuleRelatedByDeliveryModuleId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('ModuleRelatedByDeliveryModuleId', $joinBehavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersJoinLang($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Lang', $joinBehavior);

        return $this->getOrders($query, $con);
    }

    /**
     * Clears out the collCarts collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCarts()
     */
    public function clearCarts()
    {
        $this->collCarts = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCarts collection loaded partially.
     */
    public function resetPartialCarts($v = true)
    {
        $this->collCartsPartial = $v;
    }

    /**
     * Initializes the collCarts collection.
     *
     * By default this just sets the collCarts collection to an empty array (like clearcollCarts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCarts($overrideExisting = true)
    {
        if (null !== $this->collCarts && !$overrideExisting) {
            return;
        }
        $this->collCarts = new ObjectCollection();
        $this->collCarts->setModel('\Thelia\Model\Cart');
    }

    /**
     * Gets an array of ChildCart objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomer is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCart[] List of ChildCart objects
     * @throws PropelException
     */
    public function getCarts($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCartsPartial && !$this->isNew();
        if (null === $this->collCarts || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCarts) {
                // return empty collection
                $this->initCarts();
            } else {
                $collCarts = ChildCartQuery::create(null, $criteria)
                    ->filterByCustomer($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCartsPartial && count($collCarts)) {
                        $this->initCarts(false);

                        foreach ($collCarts as $obj) {
                            if (false == $this->collCarts->contains($obj)) {
                                $this->collCarts->append($obj);
                            }
                        }

                        $this->collCartsPartial = true;
                    }

                    reset($collCarts);

                    return $collCarts;
                }

                if ($partial && $this->collCarts) {
                    foreach ($this->collCarts as $obj) {
                        if ($obj->isNew()) {
                            $collCarts[] = $obj;
                        }
                    }
                }

                $this->collCarts = $collCarts;
                $this->collCartsPartial = false;
            }
        }

        return $this->collCarts;
    }

    /**
     * Sets a collection of Cart objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $carts A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCustomer The current object (for fluent API support)
     */
    public function setCarts(Collection $carts, ConnectionInterface $con = null)
    {
        $cartsToDelete = $this->getCarts(new Criteria(), $con)->diff($carts);


        $this->cartsScheduledForDeletion = $cartsToDelete;

        foreach ($cartsToDelete as $cartRemoved) {
            $cartRemoved->setCustomer(null);
        }

        $this->collCarts = null;
        foreach ($carts as $cart) {
            $this->addCart($cart);
        }

        $this->collCarts = $carts;
        $this->collCartsPartial = false;

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
    public function countCarts(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCartsPartial && !$this->isNew();
        if (null === $this->collCarts || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCarts) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCarts());
            }

            $query = ChildCartQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCustomer($this)
                ->count($con);
        }

        return count($this->collCarts);
    }

    /**
     * Method called to associate a ChildCart object to this object
     * through the ChildCart foreign key attribute.
     *
     * @param    ChildCart $l ChildCart
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function addCart(ChildCart $l)
    {
        if ($this->collCarts === null) {
            $this->initCarts();
            $this->collCartsPartial = true;
        }

        if (!in_array($l, $this->collCarts->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCart($l);
        }

        return $this;
    }

    /**
     * @param Cart $cart The cart object to add.
     */
    protected function doAddCart($cart)
    {
        $this->collCarts[]= $cart;
        $cart->setCustomer($this);
    }

    /**
     * @param  Cart $cart The cart object to remove.
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function removeCart($cart)
    {
        if ($this->getCarts()->contains($cart)) {
            $this->collCarts->remove($this->collCarts->search($cart));
            if (null === $this->cartsScheduledForDeletion) {
                $this->cartsScheduledForDeletion = clone $this->collCarts;
                $this->cartsScheduledForDeletion->clear();
            }
            $this->cartsScheduledForDeletion[]= $cart;
            $cart->setCustomer(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Carts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCart[] List of ChildCart objects
     */
    public function getCartsJoinAddressRelatedByAddressDeliveryId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartQuery::create(null, $criteria);
        $query->joinWith('AddressRelatedByAddressDeliveryId', $joinBehavior);

        return $this->getCarts($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Carts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCart[] List of ChildCart objects
     */
    public function getCartsJoinAddressRelatedByAddressInvoiceId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartQuery::create(null, $criteria);
        $query->joinWith('AddressRelatedByAddressInvoiceId', $joinBehavior);

        return $this->getCarts($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Carts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCart[] List of ChildCart objects
     */
    public function getCartsJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getCarts($query, $con);
    }

    /**
     * Clears out the collCouponCustomerCounts collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCouponCustomerCounts()
     */
    public function clearCouponCustomerCounts()
    {
        $this->collCouponCustomerCounts = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCouponCustomerCounts collection loaded partially.
     */
    public function resetPartialCouponCustomerCounts($v = true)
    {
        $this->collCouponCustomerCountsPartial = $v;
    }

    /**
     * Initializes the collCouponCustomerCounts collection.
     *
     * By default this just sets the collCouponCustomerCounts collection to an empty array (like clearcollCouponCustomerCounts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCouponCustomerCounts($overrideExisting = true)
    {
        if (null !== $this->collCouponCustomerCounts && !$overrideExisting) {
            return;
        }
        $this->collCouponCustomerCounts = new ObjectCollection();
        $this->collCouponCustomerCounts->setModel('\Thelia\Model\CouponCustomerCount');
    }

    /**
     * Gets an array of ChildCouponCustomerCount objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomer is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCouponCustomerCount[] List of ChildCouponCustomerCount objects
     * @throws PropelException
     */
    public function getCouponCustomerCounts($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponCustomerCountsPartial && !$this->isNew();
        if (null === $this->collCouponCustomerCounts || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCouponCustomerCounts) {
                // return empty collection
                $this->initCouponCustomerCounts();
            } else {
                $collCouponCustomerCounts = ChildCouponCustomerCountQuery::create(null, $criteria)
                    ->filterByCustomer($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCouponCustomerCountsPartial && count($collCouponCustomerCounts)) {
                        $this->initCouponCustomerCounts(false);

                        foreach ($collCouponCustomerCounts as $obj) {
                            if (false == $this->collCouponCustomerCounts->contains($obj)) {
                                $this->collCouponCustomerCounts->append($obj);
                            }
                        }

                        $this->collCouponCustomerCountsPartial = true;
                    }

                    reset($collCouponCustomerCounts);

                    return $collCouponCustomerCounts;
                }

                if ($partial && $this->collCouponCustomerCounts) {
                    foreach ($this->collCouponCustomerCounts as $obj) {
                        if ($obj->isNew()) {
                            $collCouponCustomerCounts[] = $obj;
                        }
                    }
                }

                $this->collCouponCustomerCounts = $collCouponCustomerCounts;
                $this->collCouponCustomerCountsPartial = false;
            }
        }

        return $this->collCouponCustomerCounts;
    }

    /**
     * Sets a collection of CouponCustomerCount objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $couponCustomerCounts A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCustomer The current object (for fluent API support)
     */
    public function setCouponCustomerCounts(Collection $couponCustomerCounts, ConnectionInterface $con = null)
    {
        $couponCustomerCountsToDelete = $this->getCouponCustomerCounts(new Criteria(), $con)->diff($couponCustomerCounts);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->couponCustomerCountsScheduledForDeletion = clone $couponCustomerCountsToDelete;

        foreach ($couponCustomerCountsToDelete as $couponCustomerCountRemoved) {
            $couponCustomerCountRemoved->setCustomer(null);
        }

        $this->collCouponCustomerCounts = null;
        foreach ($couponCustomerCounts as $couponCustomerCount) {
            $this->addCouponCustomerCount($couponCustomerCount);
        }

        $this->collCouponCustomerCounts = $couponCustomerCounts;
        $this->collCouponCustomerCountsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CouponCustomerCount objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CouponCustomerCount objects.
     * @throws PropelException
     */
    public function countCouponCustomerCounts(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponCustomerCountsPartial && !$this->isNew();
        if (null === $this->collCouponCustomerCounts || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCouponCustomerCounts) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCouponCustomerCounts());
            }

            $query = ChildCouponCustomerCountQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCustomer($this)
                ->count($con);
        }

        return count($this->collCouponCustomerCounts);
    }

    /**
     * Method called to associate a ChildCouponCustomerCount object to this object
     * through the ChildCouponCustomerCount foreign key attribute.
     *
     * @param    ChildCouponCustomerCount $l ChildCouponCustomerCount
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function addCouponCustomerCount(ChildCouponCustomerCount $l)
    {
        if ($this->collCouponCustomerCounts === null) {
            $this->initCouponCustomerCounts();
            $this->collCouponCustomerCountsPartial = true;
        }

        if (!in_array($l, $this->collCouponCustomerCounts->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCouponCustomerCount($l);
        }

        return $this;
    }

    /**
     * @param CouponCustomerCount $couponCustomerCount The couponCustomerCount object to add.
     */
    protected function doAddCouponCustomerCount($couponCustomerCount)
    {
        $this->collCouponCustomerCounts[]= $couponCustomerCount;
        $couponCustomerCount->setCustomer($this);
    }

    /**
     * @param  CouponCustomerCount $couponCustomerCount The couponCustomerCount object to remove.
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function removeCouponCustomerCount($couponCustomerCount)
    {
        if ($this->getCouponCustomerCounts()->contains($couponCustomerCount)) {
            $this->collCouponCustomerCounts->remove($this->collCouponCustomerCounts->search($couponCustomerCount));
            if (null === $this->couponCustomerCountsScheduledForDeletion) {
                $this->couponCustomerCountsScheduledForDeletion = clone $this->collCouponCustomerCounts;
                $this->couponCustomerCountsScheduledForDeletion->clear();
            }
            $this->couponCustomerCountsScheduledForDeletion[]= clone $couponCustomerCount;
            $couponCustomerCount->setCustomer(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related CouponCustomerCounts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCouponCustomerCount[] List of ChildCouponCustomerCount objects
     */
    public function getCouponCustomerCountsJoinCoupon($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCouponCustomerCountQuery::create(null, $criteria);
        $query->joinWith('Coupon', $joinBehavior);

        return $this->getCouponCustomerCounts($query, $con);
    }

    /**
     * Clears out the collCustomerVersions collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCustomerVersions()
     */
    public function clearCustomerVersions()
    {
        $this->collCustomerVersions = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCustomerVersions collection loaded partially.
     */
    public function resetPartialCustomerVersions($v = true)
    {
        $this->collCustomerVersionsPartial = $v;
    }

    /**
     * Initializes the collCustomerVersions collection.
     *
     * By default this just sets the collCustomerVersions collection to an empty array (like clearcollCustomerVersions());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCustomerVersions($overrideExisting = true)
    {
        if (null !== $this->collCustomerVersions && !$overrideExisting) {
            return;
        }
        $this->collCustomerVersions = new ObjectCollection();
        $this->collCustomerVersions->setModel('\Thelia\Model\CustomerVersion');
    }

    /**
     * Gets an array of ChildCustomerVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomer is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCustomerVersion[] List of ChildCustomerVersion objects
     * @throws PropelException
     */
    public function getCustomerVersions($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCustomerVersionsPartial && !$this->isNew();
        if (null === $this->collCustomerVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCustomerVersions) {
                // return empty collection
                $this->initCustomerVersions();
            } else {
                $collCustomerVersions = ChildCustomerVersionQuery::create(null, $criteria)
                    ->filterByCustomer($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCustomerVersionsPartial && count($collCustomerVersions)) {
                        $this->initCustomerVersions(false);

                        foreach ($collCustomerVersions as $obj) {
                            if (false == $this->collCustomerVersions->contains($obj)) {
                                $this->collCustomerVersions->append($obj);
                            }
                        }

                        $this->collCustomerVersionsPartial = true;
                    }

                    reset($collCustomerVersions);

                    return $collCustomerVersions;
                }

                if ($partial && $this->collCustomerVersions) {
                    foreach ($this->collCustomerVersions as $obj) {
                        if ($obj->isNew()) {
                            $collCustomerVersions[] = $obj;
                        }
                    }
                }

                $this->collCustomerVersions = $collCustomerVersions;
                $this->collCustomerVersionsPartial = false;
            }
        }

        return $this->collCustomerVersions;
    }

    /**
     * Sets a collection of CustomerVersion objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $customerVersions A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCustomer The current object (for fluent API support)
     */
    public function setCustomerVersions(Collection $customerVersions, ConnectionInterface $con = null)
    {
        $customerVersionsToDelete = $this->getCustomerVersions(new Criteria(), $con)->diff($customerVersions);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->customerVersionsScheduledForDeletion = clone $customerVersionsToDelete;

        foreach ($customerVersionsToDelete as $customerVersionRemoved) {
            $customerVersionRemoved->setCustomer(null);
        }

        $this->collCustomerVersions = null;
        foreach ($customerVersions as $customerVersion) {
            $this->addCustomerVersion($customerVersion);
        }

        $this->collCustomerVersions = $customerVersions;
        $this->collCustomerVersionsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CustomerVersion objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CustomerVersion objects.
     * @throws PropelException
     */
    public function countCustomerVersions(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCustomerVersionsPartial && !$this->isNew();
        if (null === $this->collCustomerVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCustomerVersions) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCustomerVersions());
            }

            $query = ChildCustomerVersionQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCustomer($this)
                ->count($con);
        }

        return count($this->collCustomerVersions);
    }

    /**
     * Method called to associate a ChildCustomerVersion object to this object
     * through the ChildCustomerVersion foreign key attribute.
     *
     * @param    ChildCustomerVersion $l ChildCustomerVersion
     * @return   \Thelia\Model\Customer The current object (for fluent API support)
     */
    public function addCustomerVersion(ChildCustomerVersion $l)
    {
        if ($this->collCustomerVersions === null) {
            $this->initCustomerVersions();
            $this->collCustomerVersionsPartial = true;
        }

        if (!in_array($l, $this->collCustomerVersions->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCustomerVersion($l);
        }

        return $this;
    }

    /**
     * @param CustomerVersion $customerVersion The customerVersion object to add.
     */
    protected function doAddCustomerVersion($customerVersion)
    {
        $this->collCustomerVersions[]= $customerVersion;
        $customerVersion->setCustomer($this);
    }

    /**
     * @param  CustomerVersion $customerVersion The customerVersion object to remove.
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function removeCustomerVersion($customerVersion)
    {
        if ($this->getCustomerVersions()->contains($customerVersion)) {
            $this->collCustomerVersions->remove($this->collCustomerVersions->search($customerVersion));
            if (null === $this->customerVersionsScheduledForDeletion) {
                $this->customerVersionsScheduledForDeletion = clone $this->collCustomerVersions;
                $this->customerVersionsScheduledForDeletion->clear();
            }
            $this->customerVersionsScheduledForDeletion[]= clone $customerVersion;
            $customerVersion->setCustomer(null);
        }

        return $this;
    }

    /**
     * Clears out the collCoupons collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCoupons()
     */
    public function clearCoupons()
    {
        $this->collCoupons = null; // important to set this to NULL since that means it is uninitialized
        $this->collCouponsPartial = null;
    }

    /**
     * Initializes the collCoupons collection.
     *
     * By default this just sets the collCoupons collection to an empty collection (like clearCoupons());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initCoupons()
    {
        $this->collCoupons = new ObjectCollection();
        $this->collCoupons->setModel('\Thelia\Model\Coupon');
    }

    /**
     * Gets a collection of ChildCoupon objects related by a many-to-many relationship
     * to the current object by way of the coupon_customer_count cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomer is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildCoupon[] List of ChildCoupon objects
     */
    public function getCoupons($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collCoupons || null !== $criteria) {
            if ($this->isNew() && null === $this->collCoupons) {
                // return empty collection
                $this->initCoupons();
            } else {
                $collCoupons = ChildCouponQuery::create(null, $criteria)
                    ->filterByCustomer($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collCoupons;
                }
                $this->collCoupons = $collCoupons;
            }
        }

        return $this->collCoupons;
    }

    /**
     * Sets a collection of Coupon objects related by a many-to-many relationship
     * to the current object by way of the coupon_customer_count cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $coupons A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function setCoupons(Collection $coupons, ConnectionInterface $con = null)
    {
        $this->clearCoupons();
        $currentCoupons = $this->getCoupons();

        $this->couponsScheduledForDeletion = $currentCoupons->diff($coupons);

        foreach ($coupons as $coupon) {
            if (!$currentCoupons->contains($coupon)) {
                $this->doAddCoupon($coupon);
            }
        }

        $this->collCoupons = $coupons;

        return $this;
    }

    /**
     * Gets the number of ChildCoupon objects related by a many-to-many relationship
     * to the current object by way of the coupon_customer_count cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildCoupon objects
     */
    public function countCoupons($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collCoupons || null !== $criteria) {
            if ($this->isNew() && null === $this->collCoupons) {
                return 0;
            } else {
                $query = ChildCouponQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCustomer($this)
                    ->count($con);
            }
        } else {
            return count($this->collCoupons);
        }
    }

    /**
     * Associate a ChildCoupon object to this object
     * through the coupon_customer_count cross reference table.
     *
     * @param  ChildCoupon $coupon The ChildCouponCustomerCount object to relate
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function addCoupon(ChildCoupon $coupon)
    {
        if ($this->collCoupons === null) {
            $this->initCoupons();
        }

        if (!$this->collCoupons->contains($coupon)) { // only add it if the **same** object is not already associated
            $this->doAddCoupon($coupon);
            $this->collCoupons[] = $coupon;
        }

        return $this;
    }

    /**
     * @param    Coupon $coupon The coupon object to add.
     */
    protected function doAddCoupon($coupon)
    {
        $couponCustomerCount = new ChildCouponCustomerCount();
        $couponCustomerCount->setCoupon($coupon);
        $this->addCouponCustomerCount($couponCustomerCount);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$coupon->getCustomers()->contains($this)) {
            $foreignCollection   = $coupon->getCustomers();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildCoupon object to this object
     * through the coupon_customer_count cross reference table.
     *
     * @param ChildCoupon $coupon The ChildCouponCustomerCount object to relate
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function removeCoupon(ChildCoupon $coupon)
    {
        if ($this->getCoupons()->contains($coupon)) {
            $this->collCoupons->remove($this->collCoupons->search($coupon));

            if (null === $this->couponsScheduledForDeletion) {
                $this->couponsScheduledForDeletion = clone $this->collCoupons;
                $this->couponsScheduledForDeletion->clear();
            }

            $this->couponsScheduledForDeletion[] = $coupon;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->ref = null;
        $this->title_id = null;
        $this->firstname = null;
        $this->lastname = null;
        $this->email = null;
        $this->password = null;
        $this->algo = null;
        $this->reseller = null;
        $this->lang = null;
        $this->sponsor = null;
        $this->discount = null;
        $this->remember_me_token = null;
        $this->remember_me_serial = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->version = null;
        $this->version_created_at = null;
        $this->version_created_by = null;
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
            if ($this->collAddresses) {
                foreach ($this->collAddresses as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrders) {
                foreach ($this->collOrders as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCarts) {
                foreach ($this->collCarts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCouponCustomerCounts) {
                foreach ($this->collCouponCustomerCounts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCustomerVersions) {
                foreach ($this->collCustomerVersions as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCoupons) {
                foreach ($this->collCoupons as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collAddresses = null;
        $this->collOrders = null;
        $this->collCarts = null;
        $this->collCouponCustomerCounts = null;
        $this->collCustomerVersions = null;
        $this->collCoupons = null;
        $this->aCustomerTitle = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CustomerTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildCustomer The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[CustomerTableMap::UPDATED_AT] = true;

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return \Thelia\Model\Customer
     */
    public function enforceVersioning()
    {
        $this->enforceVersion = true;

        return $this;
    }

    /**
     * Checks whether the current state must be recorded as a version
     *
     * @return  boolean
     */
    public function isVersioningNecessary($con = null)
    {
        if ($this->alreadyInSave) {
            return false;
        }

        if ($this->enforceVersion) {
            return true;
        }

        if (ChildCustomerQuery::isVersioningEnabled() && ($this->isNew() || $this->isModified()) || $this->isDeleted()) {
            return true;
        }
        // to avoid infinite loops, emulate in save
        $this->alreadyInSave = true;
        foreach ($this->getOrders(null, $con) as $relatedObject) {
            if ($relatedObject->isVersioningNecessary($con)) {
                $this->alreadyInSave = false;

                return true;
            }
        }
        $this->alreadyInSave = false;


        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildCustomerVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new ChildCustomerVersion();
        $version->setId($this->getId());
        $version->setRef($this->getRef());
        $version->setTitleId($this->getTitleId());
        $version->setFirstname($this->getFirstname());
        $version->setLastname($this->getLastname());
        $version->setEmail($this->getEmail());
        $version->setPassword($this->getPassword());
        $version->setAlgo($this->getAlgo());
        $version->setReseller($this->getReseller());
        $version->setLang($this->getLang());
        $version->setSponsor($this->getSponsor());
        $version->setDiscount($this->getDiscount());
        $version->setRememberMeToken($this->getRememberMeToken());
        $version->setRememberMeSerial($this->getRememberMeSerial());
        $version->setCreatedAt($this->getCreatedAt());
        $version->setUpdatedAt($this->getUpdatedAt());
        $version->setVersion($this->getVersion());
        $version->setVersionCreatedAt($this->getVersionCreatedAt());
        $version->setVersionCreatedBy($this->getVersionCreatedBy());
        $version->setCustomer($this);
        if ($relateds = $this->getOrders($con)->toKeyValue('Id', 'Version')) {
            $version->setOrderIds(array_keys($relateds));
            $version->setOrderVersions(array_values($relateds));
        }
        $version->save($con);

        return $version;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con The connection to use
     *
     * @return  ChildCustomer The current object (for fluent API support)
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No ChildCustomer object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param ChildCustomerVersion $version The version object to use
     * @param ConnectionInterface   $con the connection to use
     * @param array                 $loadedObjects objects that been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return ChildCustomer The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {
        $loadedObjects['ChildCustomer'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
        $this->setRef($version->getRef());
        $this->setTitleId($version->getTitleId());
        $this->setFirstname($version->getFirstname());
        $this->setLastname($version->getLastname());
        $this->setEmail($version->getEmail());
        $this->setPassword($version->getPassword());
        $this->setAlgo($version->getAlgo());
        $this->setReseller($version->getReseller());
        $this->setLang($version->getLang());
        $this->setSponsor($version->getSponsor());
        $this->setDiscount($version->getDiscount());
        $this->setRememberMeToken($version->getRememberMeToken());
        $this->setRememberMeSerial($version->getRememberMeSerial());
        $this->setCreatedAt($version->getCreatedAt());
        $this->setUpdatedAt($version->getUpdatedAt());
        $this->setVersion($version->getVersion());
        $this->setVersionCreatedAt($version->getVersionCreatedAt());
        $this->setVersionCreatedBy($version->getVersionCreatedBy());
        if ($fkValues = $version->getOrderIds()) {
            $this->clearOrders();
            $fkVersions = $version->getOrderVersions();
            $query = ChildOrderVersionQuery::create();
            foreach ($fkValues as $key => $value) {
                $c1 = $query->getNewCriterion(OrderVersionTableMap::ID, $value);
                $c2 = $query->getNewCriterion(OrderVersionTableMap::VERSION, $fkVersions[$key]);
                $c1->addAnd($c2);
                $query->addOr($c1);
            }
            foreach ($query->find($con) as $relatedVersion) {
                if (isset($loadedObjects['ChildOrder']) && isset($loadedObjects['ChildOrder'][$relatedVersion->getId()]) && isset($loadedObjects['ChildOrder'][$relatedVersion->getId()][$relatedVersion->getVersion()])) {
                    $related = $loadedObjects['ChildOrder'][$relatedVersion->getId()][$relatedVersion->getVersion()];
                } else {
                    $related = new ChildOrder();
                    $related->populateFromVersion($relatedVersion, $con, $loadedObjects);
                    $related->setNew(false);
                }
                $this->addOrder($related);
                $this->collOrdersPartial = false;
            }
        }

        return $this;
    }

    /**
     * Gets the latest persisted version number for the current object
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  integer
     */
    public function getLastVersionNumber($con = null)
    {
        $v = ChildCustomerVersionQuery::create()
            ->filterByCustomer($this)
            ->orderByVersion('desc')
            ->findOne($con);
        if (!$v) {
            return 0;
        }

        return $v->getVersion();
    }

    /**
     * Checks whether the current object is the latest one
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  Boolean
     */
    public function isLastVersion($con = null)
    {
        return $this->getLastVersionNumber($con) == $this->getVersion();
    }

    /**
     * Retrieves a version object for this entity and a version number
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildCustomerVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return ChildCustomerVersionQuery::create()
            ->filterByCustomer($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ObjectCollection A list of ChildCustomerVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(CustomerVersionTableMap::VERSION);

        return $this->getCustomerVersions($criteria, $con);
    }

    /**
     * Compares the current object with another of its version.
     * <code>
     * print_r($book->compareVersion(1));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   integer             $versionNumber
     * @param   string              $keys Main key used for the result diff (versions|columns)
     * @param   ConnectionInterface $con the connection to use
     * @param   array               $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    public function compareVersion($versionNumber, $keys = 'columns', $con = null, $ignoredColumns = array())
    {
        $fromVersion = $this->toArray();
        $toVersion = $this->getOneVersion($versionNumber, $con)->toArray();

        return $this->computeDiff($fromVersion, $toVersion, $keys, $ignoredColumns);
    }

    /**
     * Compares two versions of the current object.
     * <code>
     * print_r($book->compareVersions(1, 2));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   integer             $fromVersionNumber
     * @param   integer             $toVersionNumber
     * @param   string              $keys Main key used for the result diff (versions|columns)
     * @param   ConnectionInterface $con the connection to use
     * @param   array               $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    public function compareVersions($fromVersionNumber, $toVersionNumber, $keys = 'columns', $con = null, $ignoredColumns = array())
    {
        $fromVersion = $this->getOneVersion($fromVersionNumber, $con)->toArray();
        $toVersion = $this->getOneVersion($toVersionNumber, $con)->toArray();

        return $this->computeDiff($fromVersion, $toVersion, $keys, $ignoredColumns);
    }

    /**
     * Computes the diff between two versions.
     * <code>
     * print_r($book->computeDiff(1, 2));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   array     $fromVersion     An array representing the original version.
     * @param   array     $toVersion       An array representing the destination version.
     * @param   string    $keys            Main key used for the result diff (versions|columns).
     * @param   array     $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    protected function computeDiff($fromVersion, $toVersion, $keys = 'columns', $ignoredColumns = array())
    {
        $fromVersionNumber = $fromVersion['Version'];
        $toVersionNumber = $toVersion['Version'];
        $ignoredColumns = array_merge(array(
            'Version',
            'VersionCreatedAt',
            'VersionCreatedBy',
        ), $ignoredColumns);
        $diff = array();
        foreach ($fromVersion as $key => $value) {
            if (in_array($key, $ignoredColumns)) {
                continue;
            }
            if ($toVersion[$key] != $value) {
                switch ($keys) {
                    case 'versions':
                        $diff[$fromVersionNumber][$key] = $value;
                        $diff[$toVersionNumber][$key] = $toVersion[$key];
                        break;
                    default:
                        $diff[$key] = array(
                            $fromVersionNumber => $value,
                            $toVersionNumber => $toVersion[$key],
                        );
                        break;
                }
            }
        }

        return $diff;
    }
    /**
     * retrieve the last $number versions.
     *
     * @param Integer $number the number of record to return.
     * @return PropelCollection|array \Thelia\Model\CustomerVersion[] List of \Thelia\Model\CustomerVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, $con = null)
    {
        $criteria = ChildCustomerVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(CustomerVersionTableMap::VERSION);
        $criteria->limit($number);

        return $this->getCustomerVersions($criteria, $con);
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
