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
use Thelia\Model\Coupon as ChildCoupon;
use Thelia\Model\CouponQuery as ChildCouponQuery;
use Thelia\Model\CouponVersionQuery as ChildCouponVersionQuery;
use Thelia\Model\Map\CouponVersionTableMap;

abstract class CouponVersion implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\CouponVersionTableMap';


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
     * @var        Coupon
     */
    protected $aCoupon;

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
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\CouponVersion object.
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
     * Compares this with another <code>CouponVersion</code> instance.  If
     * <code>obj</code> is an instance of <code>CouponVersion</code>, delegates to
     * <code>equals(CouponVersion)</code>.  Otherwise, returns <code>false</code>.
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
     * @return CouponVersion The current object, for fluid interface
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
     * @return CouponVersion The current object, for fluid interface
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
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[CouponVersionTableMap::ID] = true;
        }

        if ($this->aCoupon !== null && $this->aCoupon->getId() !== $v) {
            $this->aCoupon = null;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [code] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[CouponVersionTableMap::CODE] = true;
        }


        return $this;
    } // setCode()

    /**
     * Set the value of [type] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->type !== $v) {
            $this->type = $v;
            $this->modifiedColumns[CouponVersionTableMap::TYPE] = true;
        }


        return $this;
    } // setType()

    /**
     * Set the value of [serialized_effects] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setSerializedEffects($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->serialized_effects !== $v) {
            $this->serialized_effects = $v;
            $this->modifiedColumns[CouponVersionTableMap::SERIALIZED_EFFECTS] = true;
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
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
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
            $this->modifiedColumns[CouponVersionTableMap::IS_ENABLED] = true;
        }


        return $this;
    } // setIsEnabled()

    /**
     * Sets the value of [start_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setStartDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->start_date !== null || $dt !== null) {
            if ($dt !== $this->start_date) {
                $this->start_date = $dt;
                $this->modifiedColumns[CouponVersionTableMap::START_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setStartDate()

    /**
     * Sets the value of [expiration_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setExpirationDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->expiration_date !== null || $dt !== null) {
            if ($dt !== $this->expiration_date) {
                $this->expiration_date = $dt;
                $this->modifiedColumns[CouponVersionTableMap::EXPIRATION_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setExpirationDate()

    /**
     * Set the value of [max_usage] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setMaxUsage($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->max_usage !== $v) {
            $this->max_usage = $v;
            $this->modifiedColumns[CouponVersionTableMap::MAX_USAGE] = true;
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
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
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
            $this->modifiedColumns[CouponVersionTableMap::IS_CUMULATIVE] = true;
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
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
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
            $this->modifiedColumns[CouponVersionTableMap::IS_REMOVING_POSTAGE] = true;
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
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
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
            $this->modifiedColumns[CouponVersionTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS] = true;
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
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
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
            $this->modifiedColumns[CouponVersionTableMap::IS_USED] = true;
        }


        return $this;
    } // setIsUsed()

    /**
     * Set the value of [serialized_conditions] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setSerializedConditions($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->serialized_conditions !== $v) {
            $this->serialized_conditions = $v;
            $this->modifiedColumns[CouponVersionTableMap::SERIALIZED_CONDITIONS] = true;
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
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
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
            $this->modifiedColumns[CouponVersionTableMap::PER_CUSTOMER_USAGE_COUNT] = true;
        }


        return $this;
    } // setPerCustomerUsageCount()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[CouponVersionTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[CouponVersionTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[CouponVersionTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[CouponVersionTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CouponVersion The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[CouponVersionTableMap::VERSION_CREATED_BY] = true;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : CouponVersionTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : CouponVersionTableMap::translateFieldName('Code', TableMap::TYPE_PHPNAME, $indexType)];
            $this->code = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : CouponVersionTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->type = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : CouponVersionTableMap::translateFieldName('SerializedEffects', TableMap::TYPE_PHPNAME, $indexType)];
            $this->serialized_effects = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : CouponVersionTableMap::translateFieldName('IsEnabled', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_enabled = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : CouponVersionTableMap::translateFieldName('StartDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->start_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : CouponVersionTableMap::translateFieldName('ExpirationDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->expiration_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : CouponVersionTableMap::translateFieldName('MaxUsage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->max_usage = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : CouponVersionTableMap::translateFieldName('IsCumulative', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_cumulative = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : CouponVersionTableMap::translateFieldName('IsRemovingPostage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_removing_postage = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : CouponVersionTableMap::translateFieldName('IsAvailableOnSpecialOffers', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_available_on_special_offers = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : CouponVersionTableMap::translateFieldName('IsUsed', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_used = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : CouponVersionTableMap::translateFieldName('SerializedConditions', TableMap::TYPE_PHPNAME, $indexType)];
            $this->serialized_conditions = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : CouponVersionTableMap::translateFieldName('PerCustomerUsageCount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_customer_usage_count = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : CouponVersionTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : CouponVersionTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : CouponVersionTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : CouponVersionTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : CouponVersionTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 19; // 19 = CouponVersionTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\CouponVersion object", 0, $e);
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
        if ($this->aCoupon !== null && $this->id !== $this->aCoupon->getId()) {
            $this->aCoupon = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(CouponVersionTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildCouponVersionQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aCoupon = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see CouponVersion::setDeleted()
     * @see CouponVersion::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CouponVersionTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildCouponVersionQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(CouponVersionTableMap::DATABASE_NAME);
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
                CouponVersionTableMap::addInstanceToPool($this);
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

            if ($this->aCoupon !== null) {
                if ($this->aCoupon->isModified() || $this->aCoupon->isNew()) {
                    $affectedRows += $this->aCoupon->save($con);
                }
                $this->setCoupon($this->aCoupon);
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
        if ($this->isColumnModified(CouponVersionTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`CODE`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::TYPE)) {
            $modifiedColumns[':p' . $index++]  = '`TYPE`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::SERIALIZED_EFFECTS)) {
            $modifiedColumns[':p' . $index++]  = '`SERIALIZED_EFFECTS`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::IS_ENABLED)) {
            $modifiedColumns[':p' . $index++]  = '`IS_ENABLED`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::START_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`START_DATE`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::EXPIRATION_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`EXPIRATION_DATE`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::MAX_USAGE)) {
            $modifiedColumns[':p' . $index++]  = '`MAX_USAGE`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::IS_CUMULATIVE)) {
            $modifiedColumns[':p' . $index++]  = '`IS_CUMULATIVE`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::IS_REMOVING_POSTAGE)) {
            $modifiedColumns[':p' . $index++]  = '`IS_REMOVING_POSTAGE`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS)) {
            $modifiedColumns[':p' . $index++]  = '`IS_AVAILABLE_ON_SPECIAL_OFFERS`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::IS_USED)) {
            $modifiedColumns[':p' . $index++]  = '`IS_USED`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::SERIALIZED_CONDITIONS)) {
            $modifiedColumns[':p' . $index++]  = '`SERIALIZED_CONDITIONS`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::PER_CUSTOMER_USAGE_COUNT)) {
            $modifiedColumns[':p' . $index++]  = '`PER_CUSTOMER_USAGE_COUNT`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(CouponVersionTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
        }

        $sql = sprintf(
            'INSERT INTO `coupon_version` (%s) VALUES (%s)',
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
        $pos = CouponVersionTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
        if (isset($alreadyDumpedObjects['CouponVersion'][serialize($this->getPrimaryKey())])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['CouponVersion'][serialize($this->getPrimaryKey())] = true;
        $keys = CouponVersionTableMap::getFieldNames($keyType);
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
            if (null !== $this->aCoupon) {
                $result['Coupon'] = $this->aCoupon->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
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
        $pos = CouponVersionTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
        $keys = CouponVersionTableMap::getFieldNames($keyType);

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
        $criteria = new Criteria(CouponVersionTableMap::DATABASE_NAME);

        if ($this->isColumnModified(CouponVersionTableMap::ID)) $criteria->add(CouponVersionTableMap::ID, $this->id);
        if ($this->isColumnModified(CouponVersionTableMap::CODE)) $criteria->add(CouponVersionTableMap::CODE, $this->code);
        if ($this->isColumnModified(CouponVersionTableMap::TYPE)) $criteria->add(CouponVersionTableMap::TYPE, $this->type);
        if ($this->isColumnModified(CouponVersionTableMap::SERIALIZED_EFFECTS)) $criteria->add(CouponVersionTableMap::SERIALIZED_EFFECTS, $this->serialized_effects);
        if ($this->isColumnModified(CouponVersionTableMap::IS_ENABLED)) $criteria->add(CouponVersionTableMap::IS_ENABLED, $this->is_enabled);
        if ($this->isColumnModified(CouponVersionTableMap::START_DATE)) $criteria->add(CouponVersionTableMap::START_DATE, $this->start_date);
        if ($this->isColumnModified(CouponVersionTableMap::EXPIRATION_DATE)) $criteria->add(CouponVersionTableMap::EXPIRATION_DATE, $this->expiration_date);
        if ($this->isColumnModified(CouponVersionTableMap::MAX_USAGE)) $criteria->add(CouponVersionTableMap::MAX_USAGE, $this->max_usage);
        if ($this->isColumnModified(CouponVersionTableMap::IS_CUMULATIVE)) $criteria->add(CouponVersionTableMap::IS_CUMULATIVE, $this->is_cumulative);
        if ($this->isColumnModified(CouponVersionTableMap::IS_REMOVING_POSTAGE)) $criteria->add(CouponVersionTableMap::IS_REMOVING_POSTAGE, $this->is_removing_postage);
        if ($this->isColumnModified(CouponVersionTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS)) $criteria->add(CouponVersionTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, $this->is_available_on_special_offers);
        if ($this->isColumnModified(CouponVersionTableMap::IS_USED)) $criteria->add(CouponVersionTableMap::IS_USED, $this->is_used);
        if ($this->isColumnModified(CouponVersionTableMap::SERIALIZED_CONDITIONS)) $criteria->add(CouponVersionTableMap::SERIALIZED_CONDITIONS, $this->serialized_conditions);
        if ($this->isColumnModified(CouponVersionTableMap::PER_CUSTOMER_USAGE_COUNT)) $criteria->add(CouponVersionTableMap::PER_CUSTOMER_USAGE_COUNT, $this->per_customer_usage_count);
        if ($this->isColumnModified(CouponVersionTableMap::CREATED_AT)) $criteria->add(CouponVersionTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CouponVersionTableMap::UPDATED_AT)) $criteria->add(CouponVersionTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(CouponVersionTableMap::VERSION)) $criteria->add(CouponVersionTableMap::VERSION, $this->version);
        if ($this->isColumnModified(CouponVersionTableMap::VERSION_CREATED_AT)) $criteria->add(CouponVersionTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(CouponVersionTableMap::VERSION_CREATED_BY)) $criteria->add(CouponVersionTableMap::VERSION_CREATED_BY, $this->version_created_by);

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
        $criteria = new Criteria(CouponVersionTableMap::DATABASE_NAME);
        $criteria->add(CouponVersionTableMap::ID, $this->id);
        $criteria->add(CouponVersionTableMap::VERSION, $this->version);

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
     * @param      object $copyObj An object of \Thelia\Model\CouponVersion (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setId($this->getId());
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
     * @return                 \Thelia\Model\CouponVersion Clone of current object.
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
     * Declares an association between this object and a ChildCoupon object.
     *
     * @param                  ChildCoupon $v
     * @return                 \Thelia\Model\CouponVersion The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCoupon(ChildCoupon $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getId());
        }

        $this->aCoupon = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildCoupon object, it will not be re-added.
        if ($v !== null) {
            $v->addCouponVersion($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildCoupon object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildCoupon The associated ChildCoupon object.
     * @throws PropelException
     */
    public function getCoupon(ConnectionInterface $con = null)
    {
        if ($this->aCoupon === null && ($this->id !== null)) {
            $this->aCoupon = ChildCouponQuery::create()->findPk($this->id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCoupon->addCouponVersions($this);
             */
        }

        return $this->aCoupon;
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
        } // if ($deep)

        $this->aCoupon = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CouponVersionTableMap::DEFAULT_STRING_FORMAT);
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
