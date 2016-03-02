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
use Thelia\Model\Coupon as ChildCoupon;
use Thelia\Model\CouponCountry as ChildCouponCountry;
use Thelia\Model\CouponCountryQuery as ChildCouponCountryQuery;
use Thelia\Model\CouponCustomerCount as ChildCouponCustomerCount;
use Thelia\Model\CouponCustomerCountQuery as ChildCouponCustomerCountQuery;
use Thelia\Model\CouponI18n as ChildCouponI18n;
use Thelia\Model\CouponI18nQuery as ChildCouponI18nQuery;
use Thelia\Model\CouponModule as ChildCouponModule;
use Thelia\Model\CouponModuleQuery as ChildCouponModuleQuery;
use Thelia\Model\CouponQuery as ChildCouponQuery;
use Thelia\Model\CouponVersion as ChildCouponVersion;
use Thelia\Model\CouponVersionQuery as ChildCouponVersionQuery;
use Thelia\Model\Customer as ChildCustomer;
use Thelia\Model\CustomerQuery as ChildCustomerQuery;
use Thelia\Model\Module as ChildModule;
use Thelia\Model\ModuleQuery as ChildModuleQuery;
use Thelia\Model\Map\CouponTableMap;
use Thelia\Model\Map\CouponVersionTableMap;

abstract class Coupon implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\CouponTableMap';


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
     * The value for the code field.
     * @var        string
     */
    protected $code;

    /**
     * The value for the type field.
     * @var        string
     */
    protected $type;

    /**
     * The value for the serialized_effects field.
     * @var        string
     */
    protected $serialized_effects;

    /**
     * The value for the is_enabled field.
     * @var        boolean
     */
    protected $is_enabled;

    /**
     * The value for the start_date field.
     * @var        string
     */
    protected $start_date;

    /**
     * The value for the expiration_date field.
     * @var        string
     */
    protected $expiration_date;

    /**
     * The value for the max_usage field.
     * @var        int
     */
    protected $max_usage;

    /**
     * The value for the is_cumulative field.
     * @var        boolean
     */
    protected $is_cumulative;

    /**
     * The value for the is_removing_postage field.
     * @var        boolean
     */
    protected $is_removing_postage;

    /**
     * The value for the is_available_on_special_offers field.
     * @var        boolean
     */
    protected $is_available_on_special_offers;

    /**
     * The value for the is_used field.
     * @var        boolean
     */
    protected $is_used;

    /**
     * The value for the serialized_conditions field.
     * @var        string
     */
    protected $serialized_conditions;

    /**
     * The value for the per_customer_usage_count field.
     * @var        boolean
     */
    protected $per_customer_usage_count;

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
     * @var        ObjectCollection|ChildCouponCountry[] Collection to store aggregation of ChildCouponCountry objects.
     */
    protected $collCouponCountries;
    protected $collCouponCountriesPartial;

    /**
     * @var        ObjectCollection|ChildCouponModule[] Collection to store aggregation of ChildCouponModule objects.
     */
    protected $collCouponModules;
    protected $collCouponModulesPartial;

    /**
     * @var        ObjectCollection|ChildCouponCustomerCount[] Collection to store aggregation of ChildCouponCustomerCount objects.
     */
    protected $collCouponCustomerCounts;
    protected $collCouponCustomerCountsPartial;

    /**
     * @var        ObjectCollection|ChildCouponI18n[] Collection to store aggregation of ChildCouponI18n objects.
     */
    protected $collCouponI18ns;
    protected $collCouponI18nsPartial;

    /**
     * @var        ObjectCollection|ChildCouponVersion[] Collection to store aggregation of ChildCouponVersion objects.
     */
    protected $collCouponVersions;
    protected $collCouponVersionsPartial;

    /**
     * @var        ChildCountry[] Collection to store aggregation of ChildCountry objects.
     */
    protected $collCountries;

    /**
     * @var        ChildModule[] Collection to store aggregation of ChildModule objects.
     */
    protected $collModules;

    /**
     * @var        ChildCustomer[] Collection to store aggregation of ChildCustomer objects.
     */
    protected $collCustomers;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    // i18n behavior

    /**
     * Current locale
     * @var        string
     */
    protected $currentLocale = 'en_US';

    /**
     * Current translation objects
     * @var        array[ChildCouponI18n]
     */
    protected $currentTranslations;

    // versionable behavior


    /**
     * @var bool
     */
    protected $enforceVersion = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $countriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $modulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $customersScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponCountriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponModulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponCustomerCountsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponI18nsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Coupon object.
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
     * Compares this with another <code>Coupon</code> instance.  If
     * <code>obj</code> is an instance of <code>Coupon</code>, delegates to
     * <code>equals(Coupon)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Coupon The current object, for fluid interface
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
     * @return Coupon The current object, for fluid interface
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
     * Get the [code] column value.
     *
     * @return   string
     */
    public function getCode()
    {

        return $this->code;
    }

    /**
     * Get the [type] column value.
     *
     * @return   string
     */
    public function getType()
    {

        return $this->type;
    }

    /**
     * Get the [serialized_effects] column value.
     *
     * @return   string
     */
    public function getSerializedEffects()
    {

        return $this->serialized_effects;
    }

    /**
     * Get the [is_enabled] column value.
     *
     * @return   boolean
     */
    public function getIsEnabled()
    {

        return $this->is_enabled;
    }

    /**
     * Get the [optionally formatted] temporal [start_date] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getStartDate($format = NULL)
    {
        if ($format === null) {
            return $this->start_date;
        } else {
            return $this->start_date instanceof \DateTime ? $this->start_date->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [expiration_date] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getExpirationDate($format = NULL)
    {
        if ($format === null) {
            return $this->expiration_date;
        } else {
            return $this->expiration_date instanceof \DateTime ? $this->expiration_date->format($format) : null;
        }
    }

    /**
     * Get the [max_usage] column value.
     *
     * @return   int
     */
    public function getMaxUsage()
    {

        return $this->max_usage;
    }

    /**
     * Get the [is_cumulative] column value.
     *
     * @return   boolean
     */
    public function getIsCumulative()
    {

        return $this->is_cumulative;
    }

    /**
     * Get the [is_removing_postage] column value.
     *
     * @return   boolean
     */
    public function getIsRemovingPostage()
    {

        return $this->is_removing_postage;
    }

    /**
     * Get the [is_available_on_special_offers] column value.
     *
     * @return   boolean
     */
    public function getIsAvailableOnSpecialOffers()
    {

        return $this->is_available_on_special_offers;
    }

    /**
     * Get the [is_used] column value.
     *
     * @return   boolean
     */
    public function getIsUsed()
    {

        return $this->is_used;
    }

    /**
     * Get the [serialized_conditions] column value.
     *
     * @return   string
     */
    public function getSerializedConditions()
    {

        return $this->serialized_conditions;
    }

    /**
     * Get the [per_customer_usage_count] column value.
     *
     * @return   boolean
     */
    public function getPerCustomerUsageCount()
    {

        return $this->per_customer_usage_count;
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
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[CouponTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [code] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[CouponTableMap::CODE] = true;
        }


        return $this;
    } // setCode()

    /**
     * Set the value of [type] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->type !== $v) {
            $this->type = $v;
            $this->modifiedColumns[CouponTableMap::TYPE] = true;
        }


        return $this;
    } // setType()

    /**
     * Set the value of [serialized_effects] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setSerializedEffects($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->serialized_effects !== $v) {
            $this->serialized_effects = $v;
            $this->modifiedColumns[CouponTableMap::SERIALIZED_EFFECTS] = true;
        }


        return $this;
    } // setSerializedEffects()

    /**
     * Sets the value of the [is_enabled] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setIsEnabled($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->is_enabled !== $v) {
            $this->is_enabled = $v;
            $this->modifiedColumns[CouponTableMap::IS_ENABLED] = true;
        }


        return $this;
    } // setIsEnabled()

    /**
     * Sets the value of [start_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setStartDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->start_date !== null || $dt !== null) {
            if ($dt !== $this->start_date) {
                $this->start_date = $dt;
                $this->modifiedColumns[CouponTableMap::START_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setStartDate()

    /**
     * Sets the value of [expiration_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setExpirationDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->expiration_date !== null || $dt !== null) {
            if ($dt !== $this->expiration_date) {
                $this->expiration_date = $dt;
                $this->modifiedColumns[CouponTableMap::EXPIRATION_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setExpirationDate()

    /**
     * Set the value of [max_usage] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setMaxUsage($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->max_usage !== $v) {
            $this->max_usage = $v;
            $this->modifiedColumns[CouponTableMap::MAX_USAGE] = true;
        }


        return $this;
    } // setMaxUsage()

    /**
     * Sets the value of the [is_cumulative] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setIsCumulative($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->is_cumulative !== $v) {
            $this->is_cumulative = $v;
            $this->modifiedColumns[CouponTableMap::IS_CUMULATIVE] = true;
        }


        return $this;
    } // setIsCumulative()

    /**
     * Sets the value of the [is_removing_postage] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setIsRemovingPostage($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->is_removing_postage !== $v) {
            $this->is_removing_postage = $v;
            $this->modifiedColumns[CouponTableMap::IS_REMOVING_POSTAGE] = true;
        }


        return $this;
    } // setIsRemovingPostage()

    /**
     * Sets the value of the [is_available_on_special_offers] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setIsAvailableOnSpecialOffers($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->is_available_on_special_offers !== $v) {
            $this->is_available_on_special_offers = $v;
            $this->modifiedColumns[CouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS] = true;
        }


        return $this;
    } // setIsAvailableOnSpecialOffers()

    /**
     * Sets the value of the [is_used] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setIsUsed($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->is_used !== $v) {
            $this->is_used = $v;
            $this->modifiedColumns[CouponTableMap::IS_USED] = true;
        }


        return $this;
    } // setIsUsed()

    /**
     * Set the value of [serialized_conditions] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setSerializedConditions($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->serialized_conditions !== $v) {
            $this->serialized_conditions = $v;
            $this->modifiedColumns[CouponTableMap::SERIALIZED_CONDITIONS] = true;
        }


        return $this;
    } // setSerializedConditions()

    /**
     * Sets the value of the [per_customer_usage_count] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setPerCustomerUsageCount($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->per_customer_usage_count !== $v) {
            $this->per_customer_usage_count = $v;
            $this->modifiedColumns[CouponTableMap::PER_CUSTOMER_USAGE_COUNT] = true;
        }


        return $this;
    } // setPerCustomerUsageCount()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[CouponTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[CouponTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[CouponTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[CouponTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[CouponTableMap::VERSION_CREATED_BY] = true;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : CouponTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : CouponTableMap::translateFieldName('Code', TableMap::TYPE_PHPNAME, $indexType)];
            $this->code = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : CouponTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->type = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : CouponTableMap::translateFieldName('SerializedEffects', TableMap::TYPE_PHPNAME, $indexType)];
            $this->serialized_effects = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : CouponTableMap::translateFieldName('IsEnabled', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_enabled = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : CouponTableMap::translateFieldName('StartDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->start_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : CouponTableMap::translateFieldName('ExpirationDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->expiration_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : CouponTableMap::translateFieldName('MaxUsage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->max_usage = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : CouponTableMap::translateFieldName('IsCumulative', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_cumulative = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : CouponTableMap::translateFieldName('IsRemovingPostage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_removing_postage = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : CouponTableMap::translateFieldName('IsAvailableOnSpecialOffers', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_available_on_special_offers = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : CouponTableMap::translateFieldName('IsUsed', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_used = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : CouponTableMap::translateFieldName('SerializedConditions', TableMap::TYPE_PHPNAME, $indexType)];
            $this->serialized_conditions = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : CouponTableMap::translateFieldName('PerCustomerUsageCount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_customer_usage_count = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : CouponTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : CouponTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : CouponTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : CouponTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : CouponTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 19; // 19 = CouponTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Coupon object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(CouponTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildCouponQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collCouponCountries = null;

            $this->collCouponModules = null;

            $this->collCouponCustomerCounts = null;

            $this->collCouponI18ns = null;

            $this->collCouponVersions = null;

            $this->collCountries = null;
            $this->collModules = null;
            $this->collCustomers = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Coupon::setDeleted()
     * @see Coupon::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CouponTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildCouponQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(CouponTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(CouponTableMap::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(CouponTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(CouponTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(CouponTableMap::UPDATED_AT)) {
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
                CouponTableMap::addInstanceToPool($this);
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

            if ($this->countriesScheduledForDeletion !== null) {
                if (!$this->countriesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->countriesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    CouponCountryQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->countriesScheduledForDeletion = null;
                }

                foreach ($this->getCountries() as $country) {
                    if ($country->isModified()) {
                        $country->save($con);
                    }
                }
            } elseif ($this->collCountries) {
                foreach ($this->collCountries as $country) {
                    if ($country->isModified()) {
                        $country->save($con);
                    }
                }
            }

            if ($this->modulesScheduledForDeletion !== null) {
                if (!$this->modulesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->modulesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    CouponModuleQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->modulesScheduledForDeletion = null;
                }

                foreach ($this->getModules() as $module) {
                    if ($module->isModified()) {
                        $module->save($con);
                    }
                }
            } elseif ($this->collModules) {
                foreach ($this->collModules as $module) {
                    if ($module->isModified()) {
                        $module->save($con);
                    }
                }
            }

            if ($this->customersScheduledForDeletion !== null) {
                if (!$this->customersScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->customersScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    CouponCustomerCountQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->customersScheduledForDeletion = null;
                }

                foreach ($this->getCustomers() as $customer) {
                    if ($customer->isModified()) {
                        $customer->save($con);
                    }
                }
            } elseif ($this->collCustomers) {
                foreach ($this->collCustomers as $customer) {
                    if ($customer->isModified()) {
                        $customer->save($con);
                    }
                }
            }

            if ($this->couponCountriesScheduledForDeletion !== null) {
                if (!$this->couponCountriesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CouponCountryQuery::create()
                        ->filterByPrimaryKeys($this->couponCountriesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->couponCountriesScheduledForDeletion = null;
                }
            }

                if ($this->collCouponCountries !== null) {
            foreach ($this->collCouponCountries as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->couponModulesScheduledForDeletion !== null) {
                if (!$this->couponModulesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CouponModuleQuery::create()
                        ->filterByPrimaryKeys($this->couponModulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->couponModulesScheduledForDeletion = null;
                }
            }

                if ($this->collCouponModules !== null) {
            foreach ($this->collCouponModules as $referrerFK) {
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

            if ($this->couponI18nsScheduledForDeletion !== null) {
                if (!$this->couponI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CouponI18nQuery::create()
                        ->filterByPrimaryKeys($this->couponI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->couponI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collCouponI18ns !== null) {
            foreach ($this->collCouponI18ns as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->couponVersionsScheduledForDeletion !== null) {
                if (!$this->couponVersionsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CouponVersionQuery::create()
                        ->filterByPrimaryKeys($this->couponVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->couponVersionsScheduledForDeletion = null;
                }
            }

                if ($this->collCouponVersions !== null) {
            foreach ($this->collCouponVersions as $referrerFK) {
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

        $this->modifiedColumns[CouponTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . CouponTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CouponTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CouponTableMap::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`CODE`';
        }
        if ($this->isColumnModified(CouponTableMap::TYPE)) {
            $modifiedColumns[':p' . $index++]  = '`TYPE`';
        }
        if ($this->isColumnModified(CouponTableMap::SERIALIZED_EFFECTS)) {
            $modifiedColumns[':p' . $index++]  = '`SERIALIZED_EFFECTS`';
        }
        if ($this->isColumnModified(CouponTableMap::IS_ENABLED)) {
            $modifiedColumns[':p' . $index++]  = '`IS_ENABLED`';
        }
        if ($this->isColumnModified(CouponTableMap::START_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`START_DATE`';
        }
        if ($this->isColumnModified(CouponTableMap::EXPIRATION_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`EXPIRATION_DATE`';
        }
        if ($this->isColumnModified(CouponTableMap::MAX_USAGE)) {
            $modifiedColumns[':p' . $index++]  = '`MAX_USAGE`';
        }
        if ($this->isColumnModified(CouponTableMap::IS_CUMULATIVE)) {
            $modifiedColumns[':p' . $index++]  = '`IS_CUMULATIVE`';
        }
        if ($this->isColumnModified(CouponTableMap::IS_REMOVING_POSTAGE)) {
            $modifiedColumns[':p' . $index++]  = '`IS_REMOVING_POSTAGE`';
        }
        if ($this->isColumnModified(CouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS)) {
            $modifiedColumns[':p' . $index++]  = '`IS_AVAILABLE_ON_SPECIAL_OFFERS`';
        }
        if ($this->isColumnModified(CouponTableMap::IS_USED)) {
            $modifiedColumns[':p' . $index++]  = '`IS_USED`';
        }
        if ($this->isColumnModified(CouponTableMap::SERIALIZED_CONDITIONS)) {
            $modifiedColumns[':p' . $index++]  = '`SERIALIZED_CONDITIONS`';
        }
        if ($this->isColumnModified(CouponTableMap::PER_CUSTOMER_USAGE_COUNT)) {
            $modifiedColumns[':p' . $index++]  = '`PER_CUSTOMER_USAGE_COUNT`';
        }
        if ($this->isColumnModified(CouponTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CouponTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(CouponTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(CouponTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(CouponTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
        }

        $sql = sprintf(
            'INSERT INTO `coupon` (%s) VALUES (%s)',
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
                    case '`CODE`':
                        $stmt->bindValue($identifier, $this->code, PDO::PARAM_STR);
                        break;
                    case '`TYPE`':
                        $stmt->bindValue($identifier, $this->type, PDO::PARAM_STR);
                        break;
                    case '`SERIALIZED_EFFECTS`':
                        $stmt->bindValue($identifier, $this->serialized_effects, PDO::PARAM_STR);
                        break;
                    case '`IS_ENABLED`':
                        $stmt->bindValue($identifier, (int) $this->is_enabled, PDO::PARAM_INT);
                        break;
                    case '`START_DATE`':
                        $stmt->bindValue($identifier, $this->start_date ? $this->start_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`EXPIRATION_DATE`':
                        $stmt->bindValue($identifier, $this->expiration_date ? $this->expiration_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`MAX_USAGE`':
                        $stmt->bindValue($identifier, $this->max_usage, PDO::PARAM_INT);
                        break;
                    case '`IS_CUMULATIVE`':
                        $stmt->bindValue($identifier, (int) $this->is_cumulative, PDO::PARAM_INT);
                        break;
                    case '`IS_REMOVING_POSTAGE`':
                        $stmt->bindValue($identifier, (int) $this->is_removing_postage, PDO::PARAM_INT);
                        break;
                    case '`IS_AVAILABLE_ON_SPECIAL_OFFERS`':
                        $stmt->bindValue($identifier, (int) $this->is_available_on_special_offers, PDO::PARAM_INT);
                        break;
                    case '`IS_USED`':
                        $stmt->bindValue($identifier, (int) $this->is_used, PDO::PARAM_INT);
                        break;
                    case '`SERIALIZED_CONDITIONS`':
                        $stmt->bindValue($identifier, $this->serialized_conditions, PDO::PARAM_STR);
                        break;
                    case '`PER_CUSTOMER_USAGE_COUNT`':
                        $stmt->bindValue($identifier, (int) $this->per_customer_usage_count, PDO::PARAM_INT);
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
        $pos = CouponTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getCode();
                break;
            case 2:
                return $this->getType();
                break;
            case 3:
                return $this->getSerializedEffects();
                break;
            case 4:
                return $this->getIsEnabled();
                break;
            case 5:
                return $this->getStartDate();
                break;
            case 6:
                return $this->getExpirationDate();
                break;
            case 7:
                return $this->getMaxUsage();
                break;
            case 8:
                return $this->getIsCumulative();
                break;
            case 9:
                return $this->getIsRemovingPostage();
                break;
            case 10:
                return $this->getIsAvailableOnSpecialOffers();
                break;
            case 11:
                return $this->getIsUsed();
                break;
            case 12:
                return $this->getSerializedConditions();
                break;
            case 13:
                return $this->getPerCustomerUsageCount();
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
        if (isset($alreadyDumpedObjects['Coupon'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Coupon'][$this->getPrimaryKey()] = true;
        $keys = CouponTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCode(),
            $keys[2] => $this->getType(),
            $keys[3] => $this->getSerializedEffects(),
            $keys[4] => $this->getIsEnabled(),
            $keys[5] => $this->getStartDate(),
            $keys[6] => $this->getExpirationDate(),
            $keys[7] => $this->getMaxUsage(),
            $keys[8] => $this->getIsCumulative(),
            $keys[9] => $this->getIsRemovingPostage(),
            $keys[10] => $this->getIsAvailableOnSpecialOffers(),
            $keys[11] => $this->getIsUsed(),
            $keys[12] => $this->getSerializedConditions(),
            $keys[13] => $this->getPerCustomerUsageCount(),
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
            if (null !== $this->collCouponCountries) {
                $result['CouponCountries'] = $this->collCouponCountries->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCouponModules) {
                $result['CouponModules'] = $this->collCouponModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCouponCustomerCounts) {
                $result['CouponCustomerCounts'] = $this->collCouponCustomerCounts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCouponI18ns) {
                $result['CouponI18ns'] = $this->collCouponI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCouponVersions) {
                $result['CouponVersions'] = $this->collCouponVersions->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = CouponTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setCode($value);
                break;
            case 2:
                $this->setType($value);
                break;
            case 3:
                $this->setSerializedEffects($value);
                break;
            case 4:
                $this->setIsEnabled($value);
                break;
            case 5:
                $this->setStartDate($value);
                break;
            case 6:
                $this->setExpirationDate($value);
                break;
            case 7:
                $this->setMaxUsage($value);
                break;
            case 8:
                $this->setIsCumulative($value);
                break;
            case 9:
                $this->setIsRemovingPostage($value);
                break;
            case 10:
                $this->setIsAvailableOnSpecialOffers($value);
                break;
            case 11:
                $this->setIsUsed($value);
                break;
            case 12:
                $this->setSerializedConditions($value);
                break;
            case 13:
                $this->setPerCustomerUsageCount($value);
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
        $keys = CouponTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCode($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setType($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setSerializedEffects($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setIsEnabled($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setStartDate($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setExpirationDate($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setMaxUsage($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setIsCumulative($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setIsRemovingPostage($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setIsAvailableOnSpecialOffers($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setIsUsed($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setSerializedConditions($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setPerCustomerUsageCount($arr[$keys[13]]);
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
        $criteria = new Criteria(CouponTableMap::DATABASE_NAME);

        if ($this->isColumnModified(CouponTableMap::ID)) $criteria->add(CouponTableMap::ID, $this->id);
        if ($this->isColumnModified(CouponTableMap::CODE)) $criteria->add(CouponTableMap::CODE, $this->code);
        if ($this->isColumnModified(CouponTableMap::TYPE)) $criteria->add(CouponTableMap::TYPE, $this->type);
        if ($this->isColumnModified(CouponTableMap::SERIALIZED_EFFECTS)) $criteria->add(CouponTableMap::SERIALIZED_EFFECTS, $this->serialized_effects);
        if ($this->isColumnModified(CouponTableMap::IS_ENABLED)) $criteria->add(CouponTableMap::IS_ENABLED, $this->is_enabled);
        if ($this->isColumnModified(CouponTableMap::START_DATE)) $criteria->add(CouponTableMap::START_DATE, $this->start_date);
        if ($this->isColumnModified(CouponTableMap::EXPIRATION_DATE)) $criteria->add(CouponTableMap::EXPIRATION_DATE, $this->expiration_date);
        if ($this->isColumnModified(CouponTableMap::MAX_USAGE)) $criteria->add(CouponTableMap::MAX_USAGE, $this->max_usage);
        if ($this->isColumnModified(CouponTableMap::IS_CUMULATIVE)) $criteria->add(CouponTableMap::IS_CUMULATIVE, $this->is_cumulative);
        if ($this->isColumnModified(CouponTableMap::IS_REMOVING_POSTAGE)) $criteria->add(CouponTableMap::IS_REMOVING_POSTAGE, $this->is_removing_postage);
        if ($this->isColumnModified(CouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS)) $criteria->add(CouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, $this->is_available_on_special_offers);
        if ($this->isColumnModified(CouponTableMap::IS_USED)) $criteria->add(CouponTableMap::IS_USED, $this->is_used);
        if ($this->isColumnModified(CouponTableMap::SERIALIZED_CONDITIONS)) $criteria->add(CouponTableMap::SERIALIZED_CONDITIONS, $this->serialized_conditions);
        if ($this->isColumnModified(CouponTableMap::PER_CUSTOMER_USAGE_COUNT)) $criteria->add(CouponTableMap::PER_CUSTOMER_USAGE_COUNT, $this->per_customer_usage_count);
        if ($this->isColumnModified(CouponTableMap::CREATED_AT)) $criteria->add(CouponTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CouponTableMap::UPDATED_AT)) $criteria->add(CouponTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(CouponTableMap::VERSION)) $criteria->add(CouponTableMap::VERSION, $this->version);
        if ($this->isColumnModified(CouponTableMap::VERSION_CREATED_AT)) $criteria->add(CouponTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(CouponTableMap::VERSION_CREATED_BY)) $criteria->add(CouponTableMap::VERSION_CREATED_BY, $this->version_created_by);

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
        $criteria = new Criteria(CouponTableMap::DATABASE_NAME);
        $criteria->add(CouponTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Coupon (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCode($this->getCode());
        $copyObj->setType($this->getType());
        $copyObj->setSerializedEffects($this->getSerializedEffects());
        $copyObj->setIsEnabled($this->getIsEnabled());
        $copyObj->setStartDate($this->getStartDate());
        $copyObj->setExpirationDate($this->getExpirationDate());
        $copyObj->setMaxUsage($this->getMaxUsage());
        $copyObj->setIsCumulative($this->getIsCumulative());
        $copyObj->setIsRemovingPostage($this->getIsRemovingPostage());
        $copyObj->setIsAvailableOnSpecialOffers($this->getIsAvailableOnSpecialOffers());
        $copyObj->setIsUsed($this->getIsUsed());
        $copyObj->setSerializedConditions($this->getSerializedConditions());
        $copyObj->setPerCustomerUsageCount($this->getPerCustomerUsageCount());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getCouponCountries() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCouponCountry($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCouponModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCouponModule($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCouponCustomerCounts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCouponCustomerCount($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCouponI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCouponI18n($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCouponVersions() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCouponVersion($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Coupon Clone of current object.
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
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param      string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('CouponCountry' == $relationName) {
            return $this->initCouponCountries();
        }
        if ('CouponModule' == $relationName) {
            return $this->initCouponModules();
        }
        if ('CouponCustomerCount' == $relationName) {
            return $this->initCouponCustomerCounts();
        }
        if ('CouponI18n' == $relationName) {
            return $this->initCouponI18ns();
        }
        if ('CouponVersion' == $relationName) {
            return $this->initCouponVersions();
        }
    }

    /**
     * Clears out the collCouponCountries collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCouponCountries()
     */
    public function clearCouponCountries()
    {
        $this->collCouponCountries = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCouponCountries collection loaded partially.
     */
    public function resetPartialCouponCountries($v = true)
    {
        $this->collCouponCountriesPartial = $v;
    }

    /**
     * Initializes the collCouponCountries collection.
     *
     * By default this just sets the collCouponCountries collection to an empty array (like clearcollCouponCountries());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCouponCountries($overrideExisting = true)
    {
        if (null !== $this->collCouponCountries && !$overrideExisting) {
            return;
        }
        $this->collCouponCountries = new ObjectCollection();
        $this->collCouponCountries->setModel('\Thelia\Model\CouponCountry');
    }

    /**
     * Gets an array of ChildCouponCountry objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCouponCountry[] List of ChildCouponCountry objects
     * @throws PropelException
     */
    public function getCouponCountries($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponCountriesPartial && !$this->isNew();
        if (null === $this->collCouponCountries || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCouponCountries) {
                // return empty collection
                $this->initCouponCountries();
            } else {
                $collCouponCountries = ChildCouponCountryQuery::create(null, $criteria)
                    ->filterByCoupon($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCouponCountriesPartial && count($collCouponCountries)) {
                        $this->initCouponCountries(false);

                        foreach ($collCouponCountries as $obj) {
                            if (false == $this->collCouponCountries->contains($obj)) {
                                $this->collCouponCountries->append($obj);
                            }
                        }

                        $this->collCouponCountriesPartial = true;
                    }

                    reset($collCouponCountries);

                    return $collCouponCountries;
                }

                if ($partial && $this->collCouponCountries) {
                    foreach ($this->collCouponCountries as $obj) {
                        if ($obj->isNew()) {
                            $collCouponCountries[] = $obj;
                        }
                    }
                }

                $this->collCouponCountries = $collCouponCountries;
                $this->collCouponCountriesPartial = false;
            }
        }

        return $this->collCouponCountries;
    }

    /**
     * Sets a collection of CouponCountry objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $couponCountries A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCoupon The current object (for fluent API support)
     */
    public function setCouponCountries(Collection $couponCountries, ConnectionInterface $con = null)
    {
        $couponCountriesToDelete = $this->getCouponCountries(new Criteria(), $con)->diff($couponCountries);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->couponCountriesScheduledForDeletion = clone $couponCountriesToDelete;

        foreach ($couponCountriesToDelete as $couponCountryRemoved) {
            $couponCountryRemoved->setCoupon(null);
        }

        $this->collCouponCountries = null;
        foreach ($couponCountries as $couponCountry) {
            $this->addCouponCountry($couponCountry);
        }

        $this->collCouponCountries = $couponCountries;
        $this->collCouponCountriesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CouponCountry objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CouponCountry objects.
     * @throws PropelException
     */
    public function countCouponCountries(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponCountriesPartial && !$this->isNew();
        if (null === $this->collCouponCountries || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCouponCountries) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCouponCountries());
            }

            $query = ChildCouponCountryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCoupon($this)
                ->count($con);
        }

        return count($this->collCouponCountries);
    }

    /**
     * Method called to associate a ChildCouponCountry object to this object
     * through the ChildCouponCountry foreign key attribute.
     *
     * @param    ChildCouponCountry $l ChildCouponCountry
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function addCouponCountry(ChildCouponCountry $l)
    {
        if ($this->collCouponCountries === null) {
            $this->initCouponCountries();
            $this->collCouponCountriesPartial = true;
        }

        if (!in_array($l, $this->collCouponCountries->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCouponCountry($l);
        }

        return $this;
    }

    /**
     * @param CouponCountry $couponCountry The couponCountry object to add.
     */
    protected function doAddCouponCountry($couponCountry)
    {
        $this->collCouponCountries[]= $couponCountry;
        $couponCountry->setCoupon($this);
    }

    /**
     * @param  CouponCountry $couponCountry The couponCountry object to remove.
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function removeCouponCountry($couponCountry)
    {
        if ($this->getCouponCountries()->contains($couponCountry)) {
            $this->collCouponCountries->remove($this->collCouponCountries->search($couponCountry));
            if (null === $this->couponCountriesScheduledForDeletion) {
                $this->couponCountriesScheduledForDeletion = clone $this->collCouponCountries;
                $this->couponCountriesScheduledForDeletion->clear();
            }
            $this->couponCountriesScheduledForDeletion[]= clone $couponCountry;
            $couponCountry->setCoupon(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Coupon is new, it will return
     * an empty collection; or if this Coupon has previously
     * been saved, it will retrieve related CouponCountries from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Coupon.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCouponCountry[] List of ChildCouponCountry objects
     */
    public function getCouponCountriesJoinCountry($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCouponCountryQuery::create(null, $criteria);
        $query->joinWith('Country', $joinBehavior);

        return $this->getCouponCountries($query, $con);
    }

    /**
     * Clears out the collCouponModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCouponModules()
     */
    public function clearCouponModules()
    {
        $this->collCouponModules = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCouponModules collection loaded partially.
     */
    public function resetPartialCouponModules($v = true)
    {
        $this->collCouponModulesPartial = $v;
    }

    /**
     * Initializes the collCouponModules collection.
     *
     * By default this just sets the collCouponModules collection to an empty array (like clearcollCouponModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCouponModules($overrideExisting = true)
    {
        if (null !== $this->collCouponModules && !$overrideExisting) {
            return;
        }
        $this->collCouponModules = new ObjectCollection();
        $this->collCouponModules->setModel('\Thelia\Model\CouponModule');
    }

    /**
     * Gets an array of ChildCouponModule objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCouponModule[] List of ChildCouponModule objects
     * @throws PropelException
     */
    public function getCouponModules($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponModulesPartial && !$this->isNew();
        if (null === $this->collCouponModules || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCouponModules) {
                // return empty collection
                $this->initCouponModules();
            } else {
                $collCouponModules = ChildCouponModuleQuery::create(null, $criteria)
                    ->filterByCoupon($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCouponModulesPartial && count($collCouponModules)) {
                        $this->initCouponModules(false);

                        foreach ($collCouponModules as $obj) {
                            if (false == $this->collCouponModules->contains($obj)) {
                                $this->collCouponModules->append($obj);
                            }
                        }

                        $this->collCouponModulesPartial = true;
                    }

                    reset($collCouponModules);

                    return $collCouponModules;
                }

                if ($partial && $this->collCouponModules) {
                    foreach ($this->collCouponModules as $obj) {
                        if ($obj->isNew()) {
                            $collCouponModules[] = $obj;
                        }
                    }
                }

                $this->collCouponModules = $collCouponModules;
                $this->collCouponModulesPartial = false;
            }
        }

        return $this->collCouponModules;
    }

    /**
     * Sets a collection of CouponModule objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $couponModules A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCoupon The current object (for fluent API support)
     */
    public function setCouponModules(Collection $couponModules, ConnectionInterface $con = null)
    {
        $couponModulesToDelete = $this->getCouponModules(new Criteria(), $con)->diff($couponModules);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->couponModulesScheduledForDeletion = clone $couponModulesToDelete;

        foreach ($couponModulesToDelete as $couponModuleRemoved) {
            $couponModuleRemoved->setCoupon(null);
        }

        $this->collCouponModules = null;
        foreach ($couponModules as $couponModule) {
            $this->addCouponModule($couponModule);
        }

        $this->collCouponModules = $couponModules;
        $this->collCouponModulesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CouponModule objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CouponModule objects.
     * @throws PropelException
     */
    public function countCouponModules(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponModulesPartial && !$this->isNew();
        if (null === $this->collCouponModules || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCouponModules) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCouponModules());
            }

            $query = ChildCouponModuleQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCoupon($this)
                ->count($con);
        }

        return count($this->collCouponModules);
    }

    /**
     * Method called to associate a ChildCouponModule object to this object
     * through the ChildCouponModule foreign key attribute.
     *
     * @param    ChildCouponModule $l ChildCouponModule
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function addCouponModule(ChildCouponModule $l)
    {
        if ($this->collCouponModules === null) {
            $this->initCouponModules();
            $this->collCouponModulesPartial = true;
        }

        if (!in_array($l, $this->collCouponModules->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCouponModule($l);
        }

        return $this;
    }

    /**
     * @param CouponModule $couponModule The couponModule object to add.
     */
    protected function doAddCouponModule($couponModule)
    {
        $this->collCouponModules[]= $couponModule;
        $couponModule->setCoupon($this);
    }

    /**
     * @param  CouponModule $couponModule The couponModule object to remove.
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function removeCouponModule($couponModule)
    {
        if ($this->getCouponModules()->contains($couponModule)) {
            $this->collCouponModules->remove($this->collCouponModules->search($couponModule));
            if (null === $this->couponModulesScheduledForDeletion) {
                $this->couponModulesScheduledForDeletion = clone $this->collCouponModules;
                $this->couponModulesScheduledForDeletion->clear();
            }
            $this->couponModulesScheduledForDeletion[]= clone $couponModule;
            $couponModule->setCoupon(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Coupon is new, it will return
     * an empty collection; or if this Coupon has previously
     * been saved, it will retrieve related CouponModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Coupon.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCouponModule[] List of ChildCouponModule objects
     */
    public function getCouponModulesJoinModule($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCouponModuleQuery::create(null, $criteria);
        $query->joinWith('Module', $joinBehavior);

        return $this->getCouponModules($query, $con);
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
     * If this ChildCoupon is new, it will return
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
                    ->filterByCoupon($this)
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
     * @return   ChildCoupon The current object (for fluent API support)
     */
    public function setCouponCustomerCounts(Collection $couponCustomerCounts, ConnectionInterface $con = null)
    {
        $couponCustomerCountsToDelete = $this->getCouponCustomerCounts(new Criteria(), $con)->diff($couponCustomerCounts);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->couponCustomerCountsScheduledForDeletion = clone $couponCustomerCountsToDelete;

        foreach ($couponCustomerCountsToDelete as $couponCustomerCountRemoved) {
            $couponCustomerCountRemoved->setCoupon(null);
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
                ->filterByCoupon($this)
                ->count($con);
        }

        return count($this->collCouponCustomerCounts);
    }

    /**
     * Method called to associate a ChildCouponCustomerCount object to this object
     * through the ChildCouponCustomerCount foreign key attribute.
     *
     * @param    ChildCouponCustomerCount $l ChildCouponCustomerCount
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
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
        $couponCustomerCount->setCoupon($this);
    }

    /**
     * @param  CouponCustomerCount $couponCustomerCount The couponCustomerCount object to remove.
     * @return ChildCoupon The current object (for fluent API support)
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
            $couponCustomerCount->setCoupon(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Coupon is new, it will return
     * an empty collection; or if this Coupon has previously
     * been saved, it will retrieve related CouponCustomerCounts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Coupon.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCouponCustomerCount[] List of ChildCouponCustomerCount objects
     */
    public function getCouponCustomerCountsJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCouponCustomerCountQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getCouponCustomerCounts($query, $con);
    }

    /**
     * Clears out the collCouponI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCouponI18ns()
     */
    public function clearCouponI18ns()
    {
        $this->collCouponI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCouponI18ns collection loaded partially.
     */
    public function resetPartialCouponI18ns($v = true)
    {
        $this->collCouponI18nsPartial = $v;
    }

    /**
     * Initializes the collCouponI18ns collection.
     *
     * By default this just sets the collCouponI18ns collection to an empty array (like clearcollCouponI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCouponI18ns($overrideExisting = true)
    {
        if (null !== $this->collCouponI18ns && !$overrideExisting) {
            return;
        }
        $this->collCouponI18ns = new ObjectCollection();
        $this->collCouponI18ns->setModel('\Thelia\Model\CouponI18n');
    }

    /**
     * Gets an array of ChildCouponI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCouponI18n[] List of ChildCouponI18n objects
     * @throws PropelException
     */
    public function getCouponI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponI18nsPartial && !$this->isNew();
        if (null === $this->collCouponI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCouponI18ns) {
                // return empty collection
                $this->initCouponI18ns();
            } else {
                $collCouponI18ns = ChildCouponI18nQuery::create(null, $criteria)
                    ->filterByCoupon($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCouponI18nsPartial && count($collCouponI18ns)) {
                        $this->initCouponI18ns(false);

                        foreach ($collCouponI18ns as $obj) {
                            if (false == $this->collCouponI18ns->contains($obj)) {
                                $this->collCouponI18ns->append($obj);
                            }
                        }

                        $this->collCouponI18nsPartial = true;
                    }

                    reset($collCouponI18ns);

                    return $collCouponI18ns;
                }

                if ($partial && $this->collCouponI18ns) {
                    foreach ($this->collCouponI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collCouponI18ns[] = $obj;
                        }
                    }
                }

                $this->collCouponI18ns = $collCouponI18ns;
                $this->collCouponI18nsPartial = false;
            }
        }

        return $this->collCouponI18ns;
    }

    /**
     * Sets a collection of CouponI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $couponI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCoupon The current object (for fluent API support)
     */
    public function setCouponI18ns(Collection $couponI18ns, ConnectionInterface $con = null)
    {
        $couponI18nsToDelete = $this->getCouponI18ns(new Criteria(), $con)->diff($couponI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->couponI18nsScheduledForDeletion = clone $couponI18nsToDelete;

        foreach ($couponI18nsToDelete as $couponI18nRemoved) {
            $couponI18nRemoved->setCoupon(null);
        }

        $this->collCouponI18ns = null;
        foreach ($couponI18ns as $couponI18n) {
            $this->addCouponI18n($couponI18n);
        }

        $this->collCouponI18ns = $couponI18ns;
        $this->collCouponI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CouponI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CouponI18n objects.
     * @throws PropelException
     */
    public function countCouponI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponI18nsPartial && !$this->isNew();
        if (null === $this->collCouponI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCouponI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCouponI18ns());
            }

            $query = ChildCouponI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCoupon($this)
                ->count($con);
        }

        return count($this->collCouponI18ns);
    }

    /**
     * Method called to associate a ChildCouponI18n object to this object
     * through the ChildCouponI18n foreign key attribute.
     *
     * @param    ChildCouponI18n $l ChildCouponI18n
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function addCouponI18n(ChildCouponI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collCouponI18ns === null) {
            $this->initCouponI18ns();
            $this->collCouponI18nsPartial = true;
        }

        if (!in_array($l, $this->collCouponI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCouponI18n($l);
        }

        return $this;
    }

    /**
     * @param CouponI18n $couponI18n The couponI18n object to add.
     */
    protected function doAddCouponI18n($couponI18n)
    {
        $this->collCouponI18ns[]= $couponI18n;
        $couponI18n->setCoupon($this);
    }

    /**
     * @param  CouponI18n $couponI18n The couponI18n object to remove.
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function removeCouponI18n($couponI18n)
    {
        if ($this->getCouponI18ns()->contains($couponI18n)) {
            $this->collCouponI18ns->remove($this->collCouponI18ns->search($couponI18n));
            if (null === $this->couponI18nsScheduledForDeletion) {
                $this->couponI18nsScheduledForDeletion = clone $this->collCouponI18ns;
                $this->couponI18nsScheduledForDeletion->clear();
            }
            $this->couponI18nsScheduledForDeletion[]= clone $couponI18n;
            $couponI18n->setCoupon(null);
        }

        return $this;
    }

    /**
     * Clears out the collCouponVersions collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCouponVersions()
     */
    public function clearCouponVersions()
    {
        $this->collCouponVersions = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCouponVersions collection loaded partially.
     */
    public function resetPartialCouponVersions($v = true)
    {
        $this->collCouponVersionsPartial = $v;
    }

    /**
     * Initializes the collCouponVersions collection.
     *
     * By default this just sets the collCouponVersions collection to an empty array (like clearcollCouponVersions());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCouponVersions($overrideExisting = true)
    {
        if (null !== $this->collCouponVersions && !$overrideExisting) {
            return;
        }
        $this->collCouponVersions = new ObjectCollection();
        $this->collCouponVersions->setModel('\Thelia\Model\CouponVersion');
    }

    /**
     * Gets an array of ChildCouponVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCouponVersion[] List of ChildCouponVersion objects
     * @throws PropelException
     */
    public function getCouponVersions($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponVersionsPartial && !$this->isNew();
        if (null === $this->collCouponVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCouponVersions) {
                // return empty collection
                $this->initCouponVersions();
            } else {
                $collCouponVersions = ChildCouponVersionQuery::create(null, $criteria)
                    ->filterByCoupon($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCouponVersionsPartial && count($collCouponVersions)) {
                        $this->initCouponVersions(false);

                        foreach ($collCouponVersions as $obj) {
                            if (false == $this->collCouponVersions->contains($obj)) {
                                $this->collCouponVersions->append($obj);
                            }
                        }

                        $this->collCouponVersionsPartial = true;
                    }

                    reset($collCouponVersions);

                    return $collCouponVersions;
                }

                if ($partial && $this->collCouponVersions) {
                    foreach ($this->collCouponVersions as $obj) {
                        if ($obj->isNew()) {
                            $collCouponVersions[] = $obj;
                        }
                    }
                }

                $this->collCouponVersions = $collCouponVersions;
                $this->collCouponVersionsPartial = false;
            }
        }

        return $this->collCouponVersions;
    }

    /**
     * Sets a collection of CouponVersion objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $couponVersions A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCoupon The current object (for fluent API support)
     */
    public function setCouponVersions(Collection $couponVersions, ConnectionInterface $con = null)
    {
        $couponVersionsToDelete = $this->getCouponVersions(new Criteria(), $con)->diff($couponVersions);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->couponVersionsScheduledForDeletion = clone $couponVersionsToDelete;

        foreach ($couponVersionsToDelete as $couponVersionRemoved) {
            $couponVersionRemoved->setCoupon(null);
        }

        $this->collCouponVersions = null;
        foreach ($couponVersions as $couponVersion) {
            $this->addCouponVersion($couponVersion);
        }

        $this->collCouponVersions = $couponVersions;
        $this->collCouponVersionsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CouponVersion objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CouponVersion objects.
     * @throws PropelException
     */
    public function countCouponVersions(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponVersionsPartial && !$this->isNew();
        if (null === $this->collCouponVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCouponVersions) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCouponVersions());
            }

            $query = ChildCouponVersionQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCoupon($this)
                ->count($con);
        }

        return count($this->collCouponVersions);
    }

    /**
     * Method called to associate a ChildCouponVersion object to this object
     * through the ChildCouponVersion foreign key attribute.
     *
     * @param    ChildCouponVersion $l ChildCouponVersion
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function addCouponVersion(ChildCouponVersion $l)
    {
        if ($this->collCouponVersions === null) {
            $this->initCouponVersions();
            $this->collCouponVersionsPartial = true;
        }

        if (!in_array($l, $this->collCouponVersions->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCouponVersion($l);
        }

        return $this;
    }

    /**
     * @param CouponVersion $couponVersion The couponVersion object to add.
     */
    protected function doAddCouponVersion($couponVersion)
    {
        $this->collCouponVersions[]= $couponVersion;
        $couponVersion->setCoupon($this);
    }

    /**
     * @param  CouponVersion $couponVersion The couponVersion object to remove.
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function removeCouponVersion($couponVersion)
    {
        if ($this->getCouponVersions()->contains($couponVersion)) {
            $this->collCouponVersions->remove($this->collCouponVersions->search($couponVersion));
            if (null === $this->couponVersionsScheduledForDeletion) {
                $this->couponVersionsScheduledForDeletion = clone $this->collCouponVersions;
                $this->couponVersionsScheduledForDeletion->clear();
            }
            $this->couponVersionsScheduledForDeletion[]= clone $couponVersion;
            $couponVersion->setCoupon(null);
        }

        return $this;
    }

    /**
     * Clears out the collCountries collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCountries()
     */
    public function clearCountries()
    {
        $this->collCountries = null; // important to set this to NULL since that means it is uninitialized
        $this->collCountriesPartial = null;
    }

    /**
     * Initializes the collCountries collection.
     *
     * By default this just sets the collCountries collection to an empty collection (like clearCountries());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initCountries()
    {
        $this->collCountries = new ObjectCollection();
        $this->collCountries->setModel('\Thelia\Model\Country');
    }

    /**
     * Gets a collection of ChildCountry objects related by a many-to-many relationship
     * to the current object by way of the coupon_country cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildCountry[] List of ChildCountry objects
     */
    public function getCountries($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collCountries || null !== $criteria) {
            if ($this->isNew() && null === $this->collCountries) {
                // return empty collection
                $this->initCountries();
            } else {
                $collCountries = ChildCountryQuery::create(null, $criteria)
                    ->filterByCoupon($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collCountries;
                }
                $this->collCountries = $collCountries;
            }
        }

        return $this->collCountries;
    }

    /**
     * Sets a collection of Country objects related by a many-to-many relationship
     * to the current object by way of the coupon_country cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $countries A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function setCountries(Collection $countries, ConnectionInterface $con = null)
    {
        $this->clearCountries();
        $currentCountries = $this->getCountries();

        $this->countriesScheduledForDeletion = $currentCountries->diff($countries);

        foreach ($countries as $country) {
            if (!$currentCountries->contains($country)) {
                $this->doAddCountry($country);
            }
        }

        $this->collCountries = $countries;

        return $this;
    }

    /**
     * Gets the number of ChildCountry objects related by a many-to-many relationship
     * to the current object by way of the coupon_country cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildCountry objects
     */
    public function countCountries($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collCountries || null !== $criteria) {
            if ($this->isNew() && null === $this->collCountries) {
                return 0;
            } else {
                $query = ChildCountryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCoupon($this)
                    ->count($con);
            }
        } else {
            return count($this->collCountries);
        }
    }

    /**
     * Associate a ChildCountry object to this object
     * through the coupon_country cross reference table.
     *
     * @param  ChildCountry $country The ChildCouponCountry object to relate
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function addCountry(ChildCountry $country)
    {
        if ($this->collCountries === null) {
            $this->initCountries();
        }

        if (!$this->collCountries->contains($country)) { // only add it if the **same** object is not already associated
            $this->doAddCountry($country);
            $this->collCountries[] = $country;
        }

        return $this;
    }

    /**
     * @param    Country $country The country object to add.
     */
    protected function doAddCountry($country)
    {
        $couponCountry = new ChildCouponCountry();
        $couponCountry->setCountry($country);
        $this->addCouponCountry($couponCountry);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$country->getCoupons()->contains($this)) {
            $foreignCollection   = $country->getCoupons();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildCountry object to this object
     * through the coupon_country cross reference table.
     *
     * @param ChildCountry $country The ChildCouponCountry object to relate
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function removeCountry(ChildCountry $country)
    {
        if ($this->getCountries()->contains($country)) {
            $this->collCountries->remove($this->collCountries->search($country));

            if (null === $this->countriesScheduledForDeletion) {
                $this->countriesScheduledForDeletion = clone $this->collCountries;
                $this->countriesScheduledForDeletion->clear();
            }

            $this->countriesScheduledForDeletion[] = $country;
        }

        return $this;
    }

    /**
     * Clears out the collModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addModules()
     */
    public function clearModules()
    {
        $this->collModules = null; // important to set this to NULL since that means it is uninitialized
        $this->collModulesPartial = null;
    }

    /**
     * Initializes the collModules collection.
     *
     * By default this just sets the collModules collection to an empty collection (like clearModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initModules()
    {
        $this->collModules = new ObjectCollection();
        $this->collModules->setModel('\Thelia\Model\Module');
    }

    /**
     * Gets a collection of ChildModule objects related by a many-to-many relationship
     * to the current object by way of the coupon_module cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildModule[] List of ChildModule objects
     */
    public function getModules($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collModules || null !== $criteria) {
            if ($this->isNew() && null === $this->collModules) {
                // return empty collection
                $this->initModules();
            } else {
                $collModules = ChildModuleQuery::create(null, $criteria)
                    ->filterByCoupon($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collModules;
                }
                $this->collModules = $collModules;
            }
        }

        return $this->collModules;
    }

    /**
     * Sets a collection of Module objects related by a many-to-many relationship
     * to the current object by way of the coupon_module cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $modules A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function setModules(Collection $modules, ConnectionInterface $con = null)
    {
        $this->clearModules();
        $currentModules = $this->getModules();

        $this->modulesScheduledForDeletion = $currentModules->diff($modules);

        foreach ($modules as $module) {
            if (!$currentModules->contains($module)) {
                $this->doAddModule($module);
            }
        }

        $this->collModules = $modules;

        return $this;
    }

    /**
     * Gets the number of ChildModule objects related by a many-to-many relationship
     * to the current object by way of the coupon_module cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildModule objects
     */
    public function countModules($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collModules || null !== $criteria) {
            if ($this->isNew() && null === $this->collModules) {
                return 0;
            } else {
                $query = ChildModuleQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCoupon($this)
                    ->count($con);
            }
        } else {
            return count($this->collModules);
        }
    }

    /**
     * Associate a ChildModule object to this object
     * through the coupon_module cross reference table.
     *
     * @param  ChildModule $module The ChildCouponModule object to relate
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function addModule(ChildModule $module)
    {
        if ($this->collModules === null) {
            $this->initModules();
        }

        if (!$this->collModules->contains($module)) { // only add it if the **same** object is not already associated
            $this->doAddModule($module);
            $this->collModules[] = $module;
        }

        return $this;
    }

    /**
     * @param    Module $module The module object to add.
     */
    protected function doAddModule($module)
    {
        $couponModule = new ChildCouponModule();
        $couponModule->setModule($module);
        $this->addCouponModule($couponModule);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$module->getCoupons()->contains($this)) {
            $foreignCollection   = $module->getCoupons();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildModule object to this object
     * through the coupon_module cross reference table.
     *
     * @param ChildModule $module The ChildCouponModule object to relate
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function removeModule(ChildModule $module)
    {
        if ($this->getModules()->contains($module)) {
            $this->collModules->remove($this->collModules->search($module));

            if (null === $this->modulesScheduledForDeletion) {
                $this->modulesScheduledForDeletion = clone $this->collModules;
                $this->modulesScheduledForDeletion->clear();
            }

            $this->modulesScheduledForDeletion[] = $module;
        }

        return $this;
    }

    /**
     * Clears out the collCustomers collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCustomers()
     */
    public function clearCustomers()
    {
        $this->collCustomers = null; // important to set this to NULL since that means it is uninitialized
        $this->collCustomersPartial = null;
    }

    /**
     * Initializes the collCustomers collection.
     *
     * By default this just sets the collCustomers collection to an empty collection (like clearCustomers());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initCustomers()
    {
        $this->collCustomers = new ObjectCollection();
        $this->collCustomers->setModel('\Thelia\Model\Customer');
    }

    /**
     * Gets a collection of ChildCustomer objects related by a many-to-many relationship
     * to the current object by way of the coupon_customer_count cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildCustomer[] List of ChildCustomer objects
     */
    public function getCustomers($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collCustomers || null !== $criteria) {
            if ($this->isNew() && null === $this->collCustomers) {
                // return empty collection
                $this->initCustomers();
            } else {
                $collCustomers = ChildCustomerQuery::create(null, $criteria)
                    ->filterByCoupon($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collCustomers;
                }
                $this->collCustomers = $collCustomers;
            }
        }

        return $this->collCustomers;
    }

    /**
     * Sets a collection of Customer objects related by a many-to-many relationship
     * to the current object by way of the coupon_customer_count cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $customers A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function setCustomers(Collection $customers, ConnectionInterface $con = null)
    {
        $this->clearCustomers();
        $currentCustomers = $this->getCustomers();

        $this->customersScheduledForDeletion = $currentCustomers->diff($customers);

        foreach ($customers as $customer) {
            if (!$currentCustomers->contains($customer)) {
                $this->doAddCustomer($customer);
            }
        }

        $this->collCustomers = $customers;

        return $this;
    }

    /**
     * Gets the number of ChildCustomer objects related by a many-to-many relationship
     * to the current object by way of the coupon_customer_count cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildCustomer objects
     */
    public function countCustomers($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collCustomers || null !== $criteria) {
            if ($this->isNew() && null === $this->collCustomers) {
                return 0;
            } else {
                $query = ChildCustomerQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCoupon($this)
                    ->count($con);
            }
        } else {
            return count($this->collCustomers);
        }
    }

    /**
     * Associate a ChildCustomer object to this object
     * through the coupon_customer_count cross reference table.
     *
     * @param  ChildCustomer $customer The ChildCouponCustomerCount object to relate
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function addCustomer(ChildCustomer $customer)
    {
        if ($this->collCustomers === null) {
            $this->initCustomers();
        }

        if (!$this->collCustomers->contains($customer)) { // only add it if the **same** object is not already associated
            $this->doAddCustomer($customer);
            $this->collCustomers[] = $customer;
        }

        return $this;
    }

    /**
     * @param    Customer $customer The customer object to add.
     */
    protected function doAddCustomer($customer)
    {
        $couponCustomerCount = new ChildCouponCustomerCount();
        $couponCustomerCount->setCustomer($customer);
        $this->addCouponCustomerCount($couponCustomerCount);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$customer->getCoupons()->contains($this)) {
            $foreignCollection   = $customer->getCoupons();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildCustomer object to this object
     * through the coupon_customer_count cross reference table.
     *
     * @param ChildCustomer $customer The ChildCouponCustomerCount object to relate
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function removeCustomer(ChildCustomer $customer)
    {
        if ($this->getCustomers()->contains($customer)) {
            $this->collCustomers->remove($this->collCustomers->search($customer));

            if (null === $this->customersScheduledForDeletion) {
                $this->customersScheduledForDeletion = clone $this->collCustomers;
                $this->customersScheduledForDeletion->clear();
            }

            $this->customersScheduledForDeletion[] = $customer;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->code = null;
        $this->type = null;
        $this->serialized_effects = null;
        $this->is_enabled = null;
        $this->start_date = null;
        $this->expiration_date = null;
        $this->max_usage = null;
        $this->is_cumulative = null;
        $this->is_removing_postage = null;
        $this->is_available_on_special_offers = null;
        $this->is_used = null;
        $this->serialized_conditions = null;
        $this->per_customer_usage_count = null;
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
            if ($this->collCouponCountries) {
                foreach ($this->collCouponCountries as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCouponModules) {
                foreach ($this->collCouponModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCouponCustomerCounts) {
                foreach ($this->collCouponCustomerCounts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCouponI18ns) {
                foreach ($this->collCouponI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCouponVersions) {
                foreach ($this->collCouponVersions as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCountries) {
                foreach ($this->collCountries as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collModules) {
                foreach ($this->collModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCustomers) {
                foreach ($this->collCustomers as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collCouponCountries = null;
        $this->collCouponModules = null;
        $this->collCouponCustomerCounts = null;
        $this->collCouponI18ns = null;
        $this->collCouponVersions = null;
        $this->collCountries = null;
        $this->collModules = null;
        $this->collCustomers = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CouponTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildCoupon The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[CouponTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildCoupon The current object (for fluent API support)
     */
    public function setLocale($locale = 'en_US')
    {
        $this->currentLocale = $locale;

        return $this;
    }

    /**
     * Gets the locale for translations
     *
     * @return    string $locale Locale to use for the translation, e.g. 'fr_FR'
     */
    public function getLocale()
    {
        return $this->currentLocale;
    }

    /**
     * Returns the current translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ChildCouponI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collCouponI18ns) {
                foreach ($this->collCouponI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildCouponI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildCouponI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addCouponI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildCoupon The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildCouponI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collCouponI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collCouponI18ns[$key]);
                break;
            }
        }

        return $this;
    }

    /**
     * Returns the current translation
     *
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ChildCouponI18n */
    public function getCurrentTranslation(ConnectionInterface $con = null)
    {
        return $this->getTranslation($this->getLocale(), $con);
    }


        /**
         * Get the [title] column value.
         *
         * @return   string
         */
        public function getTitle()
        {
        return $this->getCurrentTranslation()->getTitle();
    }


        /**
         * Set the value of [title] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CouponI18n The current object (for fluent API support)
         */
        public function setTitle($v)
        {    $this->getCurrentTranslation()->setTitle($v);

        return $this;
    }


        /**
         * Get the [short_description] column value.
         *
         * @return   string
         */
        public function getShortDescription()
        {
        return $this->getCurrentTranslation()->getShortDescription();
    }


        /**
         * Set the value of [short_description] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CouponI18n The current object (for fluent API support)
         */
        public function setShortDescription($v)
        {    $this->getCurrentTranslation()->setShortDescription($v);

        return $this;
    }


        /**
         * Get the [description] column value.
         *
         * @return   string
         */
        public function getDescription()
        {
        return $this->getCurrentTranslation()->getDescription();
    }


        /**
         * Set the value of [description] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CouponI18n The current object (for fluent API support)
         */
        public function setDescription($v)
        {    $this->getCurrentTranslation()->setDescription($v);

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return \Thelia\Model\Coupon
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

        if (ChildCouponQuery::isVersioningEnabled() && ($this->isNew() || $this->isModified()) || $this->isDeleted()) {
            return true;
        }

        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildCouponVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new ChildCouponVersion();
        $version->setId($this->getId());
        $version->setCode($this->getCode());
        $version->setType($this->getType());
        $version->setSerializedEffects($this->getSerializedEffects());
        $version->setIsEnabled($this->getIsEnabled());
        $version->setStartDate($this->getStartDate());
        $version->setExpirationDate($this->getExpirationDate());
        $version->setMaxUsage($this->getMaxUsage());
        $version->setIsCumulative($this->getIsCumulative());
        $version->setIsRemovingPostage($this->getIsRemovingPostage());
        $version->setIsAvailableOnSpecialOffers($this->getIsAvailableOnSpecialOffers());
        $version->setIsUsed($this->getIsUsed());
        $version->setSerializedConditions($this->getSerializedConditions());
        $version->setPerCustomerUsageCount($this->getPerCustomerUsageCount());
        $version->setCreatedAt($this->getCreatedAt());
        $version->setUpdatedAt($this->getUpdatedAt());
        $version->setVersion($this->getVersion());
        $version->setVersionCreatedAt($this->getVersionCreatedAt());
        $version->setVersionCreatedBy($this->getVersionCreatedBy());
        $version->setCoupon($this);
        $version->save($con);

        return $version;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con The connection to use
     *
     * @return  ChildCoupon The current object (for fluent API support)
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No ChildCoupon object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param ChildCouponVersion $version The version object to use
     * @param ConnectionInterface   $con the connection to use
     * @param array                 $loadedObjects objects that been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return ChildCoupon The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {
        $loadedObjects['ChildCoupon'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
        $this->setCode($version->getCode());
        $this->setType($version->getType());
        $this->setSerializedEffects($version->getSerializedEffects());
        $this->setIsEnabled($version->getIsEnabled());
        $this->setStartDate($version->getStartDate());
        $this->setExpirationDate($version->getExpirationDate());
        $this->setMaxUsage($version->getMaxUsage());
        $this->setIsCumulative($version->getIsCumulative());
        $this->setIsRemovingPostage($version->getIsRemovingPostage());
        $this->setIsAvailableOnSpecialOffers($version->getIsAvailableOnSpecialOffers());
        $this->setIsUsed($version->getIsUsed());
        $this->setSerializedConditions($version->getSerializedConditions());
        $this->setPerCustomerUsageCount($version->getPerCustomerUsageCount());
        $this->setCreatedAt($version->getCreatedAt());
        $this->setUpdatedAt($version->getUpdatedAt());
        $this->setVersion($version->getVersion());
        $this->setVersionCreatedAt($version->getVersionCreatedAt());
        $this->setVersionCreatedBy($version->getVersionCreatedBy());

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
        $v = ChildCouponVersionQuery::create()
            ->filterByCoupon($this)
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
     * @return  ChildCouponVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return ChildCouponVersionQuery::create()
            ->filterByCoupon($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ObjectCollection A list of ChildCouponVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(CouponVersionTableMap::VERSION);

        return $this->getCouponVersions($criteria, $con);
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
     * @return PropelCollection|array \Thelia\Model\CouponVersion[] List of \Thelia\Model\CouponVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, $con = null)
    {
        $criteria = ChildCouponVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(CouponVersionTableMap::VERSION);
        $criteria->limit($number);

        return $this->getCouponVersions($criteria, $con);
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
