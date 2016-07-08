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
use Thelia\Model\Module as ChildModule;
use Thelia\Model\ModuleQuery as ChildModuleQuery;
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderCoupon as ChildOrderCoupon;
use Thelia\Model\OrderCouponCountry as ChildOrderCouponCountry;
use Thelia\Model\OrderCouponCountryQuery as ChildOrderCouponCountryQuery;
use Thelia\Model\OrderCouponModule as ChildOrderCouponModule;
use Thelia\Model\OrderCouponModuleQuery as ChildOrderCouponModuleQuery;
use Thelia\Model\OrderCouponQuery as ChildOrderCouponQuery;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\Map\OrderCouponTableMap;

abstract class OrderCoupon implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\OrderCouponTableMap';


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
     * The value for the order_id field.
     * @var        int
     */
    protected $order_id;

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
     * The value for the amount field.
     * Note: this column has a database default value of: '0.000000'
     * @var        string
     */
    protected $amount;

    /**
     * The value for the title field.
     * @var        string
     */
    protected $title;

    /**
     * The value for the short_description field.
     * @var        string
     */
    protected $short_description;

    /**
     * The value for the description field.
     * @var        string
     */
    protected $description;

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
     * The value for the usage_canceled field.
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $usage_canceled;

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
     * @var        Order
     */
    protected $aOrder;

    /**
     * @var        ObjectCollection|ChildOrderCouponCountry[] Collection to store aggregation of ChildOrderCouponCountry objects.
     */
    protected $collOrderCouponCountries;
    protected $collOrderCouponCountriesPartial;

    /**
     * @var        ObjectCollection|ChildOrderCouponModule[] Collection to store aggregation of ChildOrderCouponModule objects.
     */
    protected $collOrderCouponModules;
    protected $collOrderCouponModulesPartial;

    /**
     * @var        ChildCountry[] Collection to store aggregation of ChildCountry objects.
     */
    protected $collCountries;

    /**
     * @var        ChildModule[] Collection to store aggregation of ChildModule objects.
     */
    protected $collModules;

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
    protected $orderCouponCountriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderCouponModulesScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->amount = '0.000000';
        $this->usage_canceled = false;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\OrderCoupon object.
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
     * Compares this with another <code>OrderCoupon</code> instance.  If
     * <code>obj</code> is an instance of <code>OrderCoupon</code>, delegates to
     * <code>equals(OrderCoupon)</code>.  Otherwise, returns <code>false</code>.
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
     * @return OrderCoupon The current object, for fluid interface
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
     * @return OrderCoupon The current object, for fluid interface
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
     * Get the [order_id] column value.
     *
     * @return   int
     */
    public function getOrderId()
    {

        return $this->order_id;
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
     * Get the [amount] column value.
     *
     * @return   string
     */
    public function getAmount()
    {

        return $this->amount;
    }

    /**
     * Get the [title] column value.
     *
     * @return   string
     */
    public function getTitle()
    {

        return $this->title;
    }

    /**
     * Get the [short_description] column value.
     *
     * @return   string
     */
    public function getShortDescription()
    {

        return $this->short_description;
    }

    /**
     * Get the [description] column value.
     *
     * @return   string
     */
    public function getDescription()
    {

        return $this->description;
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
     * Get the [usage_canceled] column value.
     *
     * @return   boolean
     */
    public function getUsageCanceled()
    {

        return $this->usage_canceled;
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
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[OrderCouponTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [order_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setOrderId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->order_id !== $v) {
            $this->order_id = $v;
            $this->modifiedColumns[OrderCouponTableMap::ORDER_ID] = true;
        }

        if ($this->aOrder !== null && $this->aOrder->getId() !== $v) {
            $this->aOrder = null;
        }


        return $this;
    } // setOrderId()

    /**
     * Set the value of [code] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[OrderCouponTableMap::CODE] = true;
        }


        return $this;
    } // setCode()

    /**
     * Set the value of [type] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->type !== $v) {
            $this->type = $v;
            $this->modifiedColumns[OrderCouponTableMap::TYPE] = true;
        }


        return $this;
    } // setType()

    /**
     * Set the value of [amount] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setAmount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->amount !== $v) {
            $this->amount = $v;
            $this->modifiedColumns[OrderCouponTableMap::AMOUNT] = true;
        }


        return $this;
    } // setAmount()

    /**
     * Set the value of [title] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setTitle($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->title !== $v) {
            $this->title = $v;
            $this->modifiedColumns[OrderCouponTableMap::TITLE] = true;
        }


        return $this;
    } // setTitle()

    /**
     * Set the value of [short_description] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setShortDescription($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->short_description !== $v) {
            $this->short_description = $v;
            $this->modifiedColumns[OrderCouponTableMap::SHORT_DESCRIPTION] = true;
        }


        return $this;
    } // setShortDescription()

    /**
     * Set the value of [description] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setDescription($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->description !== $v) {
            $this->description = $v;
            $this->modifiedColumns[OrderCouponTableMap::DESCRIPTION] = true;
        }


        return $this;
    } // setDescription()

    /**
     * Sets the value of [start_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setStartDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->start_date !== null || $dt !== null) {
            if ($dt !== $this->start_date) {
                $this->start_date = $dt;
                $this->modifiedColumns[OrderCouponTableMap::START_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setStartDate()

    /**
     * Sets the value of [expiration_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setExpirationDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->expiration_date !== null || $dt !== null) {
            if ($dt !== $this->expiration_date) {
                $this->expiration_date = $dt;
                $this->modifiedColumns[OrderCouponTableMap::EXPIRATION_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setExpirationDate()

    /**
     * Sets the value of the [is_cumulative] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
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
            $this->modifiedColumns[OrderCouponTableMap::IS_CUMULATIVE] = true;
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
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
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
            $this->modifiedColumns[OrderCouponTableMap::IS_REMOVING_POSTAGE] = true;
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
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
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
            $this->modifiedColumns[OrderCouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS] = true;
        }


        return $this;
    } // setIsAvailableOnSpecialOffers()

    /**
     * Set the value of [serialized_conditions] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setSerializedConditions($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->serialized_conditions !== $v) {
            $this->serialized_conditions = $v;
            $this->modifiedColumns[OrderCouponTableMap::SERIALIZED_CONDITIONS] = true;
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
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
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
            $this->modifiedColumns[OrderCouponTableMap::PER_CUSTOMER_USAGE_COUNT] = true;
        }


        return $this;
    } // setPerCustomerUsageCount()

    /**
     * Sets the value of the [usage_canceled] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setUsageCanceled($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->usage_canceled !== $v) {
            $this->usage_canceled = $v;
            $this->modifiedColumns[OrderCouponTableMap::USAGE_CANCELED] = true;
        }


        return $this;
    } // setUsageCanceled()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[OrderCouponTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[OrderCouponTableMap::UPDATED_AT] = true;
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
            if ($this->amount !== '0.000000') {
                return false;
            }

            if ($this->usage_canceled !== false) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : OrderCouponTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : OrderCouponTableMap::translateFieldName('OrderId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->order_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : OrderCouponTableMap::translateFieldName('Code', TableMap::TYPE_PHPNAME, $indexType)];
            $this->code = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : OrderCouponTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->type = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : OrderCouponTableMap::translateFieldName('Amount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->amount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : OrderCouponTableMap::translateFieldName('Title', TableMap::TYPE_PHPNAME, $indexType)];
            $this->title = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : OrderCouponTableMap::translateFieldName('ShortDescription', TableMap::TYPE_PHPNAME, $indexType)];
            $this->short_description = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : OrderCouponTableMap::translateFieldName('Description', TableMap::TYPE_PHPNAME, $indexType)];
            $this->description = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : OrderCouponTableMap::translateFieldName('StartDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->start_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : OrderCouponTableMap::translateFieldName('ExpirationDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->expiration_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : OrderCouponTableMap::translateFieldName('IsCumulative', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_cumulative = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : OrderCouponTableMap::translateFieldName('IsRemovingPostage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_removing_postage = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : OrderCouponTableMap::translateFieldName('IsAvailableOnSpecialOffers', TableMap::TYPE_PHPNAME, $indexType)];
            $this->is_available_on_special_offers = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : OrderCouponTableMap::translateFieldName('SerializedConditions', TableMap::TYPE_PHPNAME, $indexType)];
            $this->serialized_conditions = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : OrderCouponTableMap::translateFieldName('PerCustomerUsageCount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_customer_usage_count = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : OrderCouponTableMap::translateFieldName('UsageCanceled', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usage_canceled = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : OrderCouponTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : OrderCouponTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 18; // 18 = OrderCouponTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\OrderCoupon object", 0, $e);
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
        if ($this->aOrder !== null && $this->order_id !== $this->aOrder->getId()) {
            $this->aOrder = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(OrderCouponTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildOrderCouponQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aOrder = null;
            $this->collOrderCouponCountries = null;

            $this->collOrderCouponModules = null;

            $this->collCountries = null;
            $this->collModules = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see OrderCoupon::setDeleted()
     * @see OrderCoupon::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildOrderCouponQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(OrderCouponTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(OrderCouponTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(OrderCouponTableMap::UPDATED_AT)) {
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
                OrderCouponTableMap::addInstanceToPool($this);
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

            if ($this->aOrder !== null) {
                if ($this->aOrder->isModified() || $this->aOrder->isNew()) {
                    $affectedRows += $this->aOrder->save($con);
                }
                $this->setOrder($this->aOrder);
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

            if ($this->countriesScheduledForDeletion !== null) {
                if (!$this->countriesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->countriesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    OrderCouponCountryQuery::create()
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

                    OrderCouponModuleQuery::create()
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

            if ($this->orderCouponCountriesScheduledForDeletion !== null) {
                if (!$this->orderCouponCountriesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderCouponCountryQuery::create()
                        ->filterByPrimaryKeys($this->orderCouponCountriesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderCouponCountriesScheduledForDeletion = null;
                }
            }

                if ($this->collOrderCouponCountries !== null) {
            foreach ($this->collOrderCouponCountries as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->orderCouponModulesScheduledForDeletion !== null) {
                if (!$this->orderCouponModulesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderCouponModuleQuery::create()
                        ->filterByPrimaryKeys($this->orderCouponModulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderCouponModulesScheduledForDeletion = null;
                }
            }

                if ($this->collOrderCouponModules !== null) {
            foreach ($this->collOrderCouponModules as $referrerFK) {
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

        $this->modifiedColumns[OrderCouponTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . OrderCouponTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(OrderCouponTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::ORDER_ID)) {
            $modifiedColumns[':p' . $index++]  = '`ORDER_ID`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`CODE`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::TYPE)) {
            $modifiedColumns[':p' . $index++]  = '`TYPE`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::AMOUNT)) {
            $modifiedColumns[':p' . $index++]  = '`AMOUNT`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::TITLE)) {
            $modifiedColumns[':p' . $index++]  = '`TITLE`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::SHORT_DESCRIPTION)) {
            $modifiedColumns[':p' . $index++]  = '`SHORT_DESCRIPTION`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::DESCRIPTION)) {
            $modifiedColumns[':p' . $index++]  = '`DESCRIPTION`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::START_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`START_DATE`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::EXPIRATION_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`EXPIRATION_DATE`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::IS_CUMULATIVE)) {
            $modifiedColumns[':p' . $index++]  = '`IS_CUMULATIVE`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::IS_REMOVING_POSTAGE)) {
            $modifiedColumns[':p' . $index++]  = '`IS_REMOVING_POSTAGE`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS)) {
            $modifiedColumns[':p' . $index++]  = '`IS_AVAILABLE_ON_SPECIAL_OFFERS`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::SERIALIZED_CONDITIONS)) {
            $modifiedColumns[':p' . $index++]  = '`SERIALIZED_CONDITIONS`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::PER_CUSTOMER_USAGE_COUNT)) {
            $modifiedColumns[':p' . $index++]  = '`PER_CUSTOMER_USAGE_COUNT`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::USAGE_CANCELED)) {
            $modifiedColumns[':p' . $index++]  = '`USAGE_CANCELED`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(OrderCouponTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `order_coupon` (%s) VALUES (%s)',
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
                    case '`ORDER_ID`':
                        $stmt->bindValue($identifier, $this->order_id, PDO::PARAM_INT);
                        break;
                    case '`CODE`':
                        $stmt->bindValue($identifier, $this->code, PDO::PARAM_STR);
                        break;
                    case '`TYPE`':
                        $stmt->bindValue($identifier, $this->type, PDO::PARAM_STR);
                        break;
                    case '`AMOUNT`':
                        $stmt->bindValue($identifier, $this->amount, PDO::PARAM_STR);
                        break;
                    case '`TITLE`':
                        $stmt->bindValue($identifier, $this->title, PDO::PARAM_STR);
                        break;
                    case '`SHORT_DESCRIPTION`':
                        $stmt->bindValue($identifier, $this->short_description, PDO::PARAM_STR);
                        break;
                    case '`DESCRIPTION`':
                        $stmt->bindValue($identifier, $this->description, PDO::PARAM_STR);
                        break;
                    case '`START_DATE`':
                        $stmt->bindValue($identifier, $this->start_date ? $this->start_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`EXPIRATION_DATE`':
                        $stmt->bindValue($identifier, $this->expiration_date ? $this->expiration_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
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
                    case '`SERIALIZED_CONDITIONS`':
                        $stmt->bindValue($identifier, $this->serialized_conditions, PDO::PARAM_STR);
                        break;
                    case '`PER_CUSTOMER_USAGE_COUNT`':
                        $stmt->bindValue($identifier, (int) $this->per_customer_usage_count, PDO::PARAM_INT);
                        break;
                    case '`USAGE_CANCELED`':
                        $stmt->bindValue($identifier, (int) $this->usage_canceled, PDO::PARAM_INT);
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
        $pos = OrderCouponTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getOrderId();
                break;
            case 2:
                return $this->getCode();
                break;
            case 3:
                return $this->getType();
                break;
            case 4:
                return $this->getAmount();
                break;
            case 5:
                return $this->getTitle();
                break;
            case 6:
                return $this->getShortDescription();
                break;
            case 7:
                return $this->getDescription();
                break;
            case 8:
                return $this->getStartDate();
                break;
            case 9:
                return $this->getExpirationDate();
                break;
            case 10:
                return $this->getIsCumulative();
                break;
            case 11:
                return $this->getIsRemovingPostage();
                break;
            case 12:
                return $this->getIsAvailableOnSpecialOffers();
                break;
            case 13:
                return $this->getSerializedConditions();
                break;
            case 14:
                return $this->getPerCustomerUsageCount();
                break;
            case 15:
                return $this->getUsageCanceled();
                break;
            case 16:
                return $this->getCreatedAt();
                break;
            case 17:
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
        if (isset($alreadyDumpedObjects['OrderCoupon'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['OrderCoupon'][$this->getPrimaryKey()] = true;
        $keys = OrderCouponTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getOrderId(),
            $keys[2] => $this->getCode(),
            $keys[3] => $this->getType(),
            $keys[4] => $this->getAmount(),
            $keys[5] => $this->getTitle(),
            $keys[6] => $this->getShortDescription(),
            $keys[7] => $this->getDescription(),
            $keys[8] => $this->getStartDate(),
            $keys[9] => $this->getExpirationDate(),
            $keys[10] => $this->getIsCumulative(),
            $keys[11] => $this->getIsRemovingPostage(),
            $keys[12] => $this->getIsAvailableOnSpecialOffers(),
            $keys[13] => $this->getSerializedConditions(),
            $keys[14] => $this->getPerCustomerUsageCount(),
            $keys[15] => $this->getUsageCanceled(),
            $keys[16] => $this->getCreatedAt(),
            $keys[17] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aOrder) {
                $result['Order'] = $this->aOrder->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collOrderCouponCountries) {
                $result['OrderCouponCountries'] = $this->collOrderCouponCountries->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrderCouponModules) {
                $result['OrderCouponModules'] = $this->collOrderCouponModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = OrderCouponTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setOrderId($value);
                break;
            case 2:
                $this->setCode($value);
                break;
            case 3:
                $this->setType($value);
                break;
            case 4:
                $this->setAmount($value);
                break;
            case 5:
                $this->setTitle($value);
                break;
            case 6:
                $this->setShortDescription($value);
                break;
            case 7:
                $this->setDescription($value);
                break;
            case 8:
                $this->setStartDate($value);
                break;
            case 9:
                $this->setExpirationDate($value);
                break;
            case 10:
                $this->setIsCumulative($value);
                break;
            case 11:
                $this->setIsRemovingPostage($value);
                break;
            case 12:
                $this->setIsAvailableOnSpecialOffers($value);
                break;
            case 13:
                $this->setSerializedConditions($value);
                break;
            case 14:
                $this->setPerCustomerUsageCount($value);
                break;
            case 15:
                $this->setUsageCanceled($value);
                break;
            case 16:
                $this->setCreatedAt($value);
                break;
            case 17:
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
        $keys = OrderCouponTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setOrderId($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCode($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setType($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setAmount($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setTitle($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setShortDescription($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setDescription($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setStartDate($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setExpirationDate($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setIsCumulative($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setIsRemovingPostage($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setIsAvailableOnSpecialOffers($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setSerializedConditions($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setPerCustomerUsageCount($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setUsageCanceled($arr[$keys[15]]);
        if (array_key_exists($keys[16], $arr)) $this->setCreatedAt($arr[$keys[16]]);
        if (array_key_exists($keys[17], $arr)) $this->setUpdatedAt($arr[$keys[17]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(OrderCouponTableMap::DATABASE_NAME);

        if ($this->isColumnModified(OrderCouponTableMap::ID)) $criteria->add(OrderCouponTableMap::ID, $this->id);
        if ($this->isColumnModified(OrderCouponTableMap::ORDER_ID)) $criteria->add(OrderCouponTableMap::ORDER_ID, $this->order_id);
        if ($this->isColumnModified(OrderCouponTableMap::CODE)) $criteria->add(OrderCouponTableMap::CODE, $this->code);
        if ($this->isColumnModified(OrderCouponTableMap::TYPE)) $criteria->add(OrderCouponTableMap::TYPE, $this->type);
        if ($this->isColumnModified(OrderCouponTableMap::AMOUNT)) $criteria->add(OrderCouponTableMap::AMOUNT, $this->amount);
        if ($this->isColumnModified(OrderCouponTableMap::TITLE)) $criteria->add(OrderCouponTableMap::TITLE, $this->title);
        if ($this->isColumnModified(OrderCouponTableMap::SHORT_DESCRIPTION)) $criteria->add(OrderCouponTableMap::SHORT_DESCRIPTION, $this->short_description);
        if ($this->isColumnModified(OrderCouponTableMap::DESCRIPTION)) $criteria->add(OrderCouponTableMap::DESCRIPTION, $this->description);
        if ($this->isColumnModified(OrderCouponTableMap::START_DATE)) $criteria->add(OrderCouponTableMap::START_DATE, $this->start_date);
        if ($this->isColumnModified(OrderCouponTableMap::EXPIRATION_DATE)) $criteria->add(OrderCouponTableMap::EXPIRATION_DATE, $this->expiration_date);
        if ($this->isColumnModified(OrderCouponTableMap::IS_CUMULATIVE)) $criteria->add(OrderCouponTableMap::IS_CUMULATIVE, $this->is_cumulative);
        if ($this->isColumnModified(OrderCouponTableMap::IS_REMOVING_POSTAGE)) $criteria->add(OrderCouponTableMap::IS_REMOVING_POSTAGE, $this->is_removing_postage);
        if ($this->isColumnModified(OrderCouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS)) $criteria->add(OrderCouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, $this->is_available_on_special_offers);
        if ($this->isColumnModified(OrderCouponTableMap::SERIALIZED_CONDITIONS)) $criteria->add(OrderCouponTableMap::SERIALIZED_CONDITIONS, $this->serialized_conditions);
        if ($this->isColumnModified(OrderCouponTableMap::PER_CUSTOMER_USAGE_COUNT)) $criteria->add(OrderCouponTableMap::PER_CUSTOMER_USAGE_COUNT, $this->per_customer_usage_count);
        if ($this->isColumnModified(OrderCouponTableMap::USAGE_CANCELED)) $criteria->add(OrderCouponTableMap::USAGE_CANCELED, $this->usage_canceled);
        if ($this->isColumnModified(OrderCouponTableMap::CREATED_AT)) $criteria->add(OrderCouponTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(OrderCouponTableMap::UPDATED_AT)) $criteria->add(OrderCouponTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(OrderCouponTableMap::DATABASE_NAME);
        $criteria->add(OrderCouponTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\OrderCoupon (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setOrderId($this->getOrderId());
        $copyObj->setCode($this->getCode());
        $copyObj->setType($this->getType());
        $copyObj->setAmount($this->getAmount());
        $copyObj->setTitle($this->getTitle());
        $copyObj->setShortDescription($this->getShortDescription());
        $copyObj->setDescription($this->getDescription());
        $copyObj->setStartDate($this->getStartDate());
        $copyObj->setExpirationDate($this->getExpirationDate());
        $copyObj->setIsCumulative($this->getIsCumulative());
        $copyObj->setIsRemovingPostage($this->getIsRemovingPostage());
        $copyObj->setIsAvailableOnSpecialOffers($this->getIsAvailableOnSpecialOffers());
        $copyObj->setSerializedConditions($this->getSerializedConditions());
        $copyObj->setPerCustomerUsageCount($this->getPerCustomerUsageCount());
        $copyObj->setUsageCanceled($this->getUsageCanceled());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getOrderCouponCountries() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderCouponCountry($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrderCouponModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderCouponModule($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\OrderCoupon Clone of current object.
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
     * Declares an association between this object and a ChildOrder object.
     *
     * @param                  ChildOrder $v
     * @return                 \Thelia\Model\OrderCoupon The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrder(ChildOrder $v = null)
    {
        if ($v === null) {
            $this->setOrderId(NULL);
        } else {
            $this->setOrderId($v->getId());
        }

        $this->aOrder = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildOrder object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderCoupon($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildOrder object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildOrder The associated ChildOrder object.
     * @throws PropelException
     */
    public function getOrder(ConnectionInterface $con = null)
    {
        if ($this->aOrder === null && ($this->order_id !== null)) {
            $this->aOrder = ChildOrderQuery::create()->findPk($this->order_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aOrder->addOrderCoupons($this);
             */
        }

        return $this->aOrder;
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
        if ('OrderCouponCountry' == $relationName) {
            return $this->initOrderCouponCountries();
        }
        if ('OrderCouponModule' == $relationName) {
            return $this->initOrderCouponModules();
        }
    }

    /**
     * Clears out the collOrderCouponCountries collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderCouponCountries()
     */
    public function clearOrderCouponCountries()
    {
        $this->collOrderCouponCountries = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderCouponCountries collection loaded partially.
     */
    public function resetPartialOrderCouponCountries($v = true)
    {
        $this->collOrderCouponCountriesPartial = $v;
    }

    /**
     * Initializes the collOrderCouponCountries collection.
     *
     * By default this just sets the collOrderCouponCountries collection to an empty array (like clearcollOrderCouponCountries());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderCouponCountries($overrideExisting = true)
    {
        if (null !== $this->collOrderCouponCountries && !$overrideExisting) {
            return;
        }
        $this->collOrderCouponCountries = new ObjectCollection();
        $this->collOrderCouponCountries->setModel('\Thelia\Model\OrderCouponCountry');
    }

    /**
     * Gets an array of ChildOrderCouponCountry objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrderCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderCouponCountry[] List of ChildOrderCouponCountry objects
     * @throws PropelException
     */
    public function getOrderCouponCountries($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponCountriesPartial && !$this->isNew();
        if (null === $this->collOrderCouponCountries || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderCouponCountries) {
                // return empty collection
                $this->initOrderCouponCountries();
            } else {
                $collOrderCouponCountries = ChildOrderCouponCountryQuery::create(null, $criteria)
                    ->filterByOrderCoupon($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderCouponCountriesPartial && count($collOrderCouponCountries)) {
                        $this->initOrderCouponCountries(false);

                        foreach ($collOrderCouponCountries as $obj) {
                            if (false == $this->collOrderCouponCountries->contains($obj)) {
                                $this->collOrderCouponCountries->append($obj);
                            }
                        }

                        $this->collOrderCouponCountriesPartial = true;
                    }

                    reset($collOrderCouponCountries);

                    return $collOrderCouponCountries;
                }

                if ($partial && $this->collOrderCouponCountries) {
                    foreach ($this->collOrderCouponCountries as $obj) {
                        if ($obj->isNew()) {
                            $collOrderCouponCountries[] = $obj;
                        }
                    }
                }

                $this->collOrderCouponCountries = $collOrderCouponCountries;
                $this->collOrderCouponCountriesPartial = false;
            }
        }

        return $this->collOrderCouponCountries;
    }

    /**
     * Sets a collection of OrderCouponCountry objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderCouponCountries A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildOrderCoupon The current object (for fluent API support)
     */
    public function setOrderCouponCountries(Collection $orderCouponCountries, ConnectionInterface $con = null)
    {
        $orderCouponCountriesToDelete = $this->getOrderCouponCountries(new Criteria(), $con)->diff($orderCouponCountries);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->orderCouponCountriesScheduledForDeletion = clone $orderCouponCountriesToDelete;

        foreach ($orderCouponCountriesToDelete as $orderCouponCountryRemoved) {
            $orderCouponCountryRemoved->setOrderCoupon(null);
        }

        $this->collOrderCouponCountries = null;
        foreach ($orderCouponCountries as $orderCouponCountry) {
            $this->addOrderCouponCountry($orderCouponCountry);
        }

        $this->collOrderCouponCountries = $orderCouponCountries;
        $this->collOrderCouponCountriesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderCouponCountry objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderCouponCountry objects.
     * @throws PropelException
     */
    public function countOrderCouponCountries(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponCountriesPartial && !$this->isNew();
        if (null === $this->collOrderCouponCountries || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderCouponCountries) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderCouponCountries());
            }

            $query = ChildOrderCouponCountryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByOrderCoupon($this)
                ->count($con);
        }

        return count($this->collOrderCouponCountries);
    }

    /**
     * Method called to associate a ChildOrderCouponCountry object to this object
     * through the ChildOrderCouponCountry foreign key attribute.
     *
     * @param    ChildOrderCouponCountry $l ChildOrderCouponCountry
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function addOrderCouponCountry(ChildOrderCouponCountry $l)
    {
        if ($this->collOrderCouponCountries === null) {
            $this->initOrderCouponCountries();
            $this->collOrderCouponCountriesPartial = true;
        }

        if (!in_array($l, $this->collOrderCouponCountries->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderCouponCountry($l);
        }

        return $this;
    }

    /**
     * @param OrderCouponCountry $orderCouponCountry The orderCouponCountry object to add.
     */
    protected function doAddOrderCouponCountry($orderCouponCountry)
    {
        $this->collOrderCouponCountries[]= $orderCouponCountry;
        $orderCouponCountry->setOrderCoupon($this);
    }

    /**
     * @param  OrderCouponCountry $orderCouponCountry The orderCouponCountry object to remove.
     * @return ChildOrderCoupon The current object (for fluent API support)
     */
    public function removeOrderCouponCountry($orderCouponCountry)
    {
        if ($this->getOrderCouponCountries()->contains($orderCouponCountry)) {
            $this->collOrderCouponCountries->remove($this->collOrderCouponCountries->search($orderCouponCountry));
            if (null === $this->orderCouponCountriesScheduledForDeletion) {
                $this->orderCouponCountriesScheduledForDeletion = clone $this->collOrderCouponCountries;
                $this->orderCouponCountriesScheduledForDeletion->clear();
            }
            $this->orderCouponCountriesScheduledForDeletion[]= clone $orderCouponCountry;
            $orderCouponCountry->setOrderCoupon(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderCoupon is new, it will return
     * an empty collection; or if this OrderCoupon has previously
     * been saved, it will retrieve related OrderCouponCountries from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderCoupon.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrderCouponCountry[] List of ChildOrderCouponCountry objects
     */
    public function getOrderCouponCountriesJoinCountry($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderCouponCountryQuery::create(null, $criteria);
        $query->joinWith('Country', $joinBehavior);

        return $this->getOrderCouponCountries($query, $con);
    }

    /**
     * Clears out the collOrderCouponModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderCouponModules()
     */
    public function clearOrderCouponModules()
    {
        $this->collOrderCouponModules = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderCouponModules collection loaded partially.
     */
    public function resetPartialOrderCouponModules($v = true)
    {
        $this->collOrderCouponModulesPartial = $v;
    }

    /**
     * Initializes the collOrderCouponModules collection.
     *
     * By default this just sets the collOrderCouponModules collection to an empty array (like clearcollOrderCouponModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderCouponModules($overrideExisting = true)
    {
        if (null !== $this->collOrderCouponModules && !$overrideExisting) {
            return;
        }
        $this->collOrderCouponModules = new ObjectCollection();
        $this->collOrderCouponModules->setModel('\Thelia\Model\OrderCouponModule');
    }

    /**
     * Gets an array of ChildOrderCouponModule objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrderCoupon is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderCouponModule[] List of ChildOrderCouponModule objects
     * @throws PropelException
     */
    public function getOrderCouponModules($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponModulesPartial && !$this->isNew();
        if (null === $this->collOrderCouponModules || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderCouponModules) {
                // return empty collection
                $this->initOrderCouponModules();
            } else {
                $collOrderCouponModules = ChildOrderCouponModuleQuery::create(null, $criteria)
                    ->filterByOrderCoupon($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderCouponModulesPartial && count($collOrderCouponModules)) {
                        $this->initOrderCouponModules(false);

                        foreach ($collOrderCouponModules as $obj) {
                            if (false == $this->collOrderCouponModules->contains($obj)) {
                                $this->collOrderCouponModules->append($obj);
                            }
                        }

                        $this->collOrderCouponModulesPartial = true;
                    }

                    reset($collOrderCouponModules);

                    return $collOrderCouponModules;
                }

                if ($partial && $this->collOrderCouponModules) {
                    foreach ($this->collOrderCouponModules as $obj) {
                        if ($obj->isNew()) {
                            $collOrderCouponModules[] = $obj;
                        }
                    }
                }

                $this->collOrderCouponModules = $collOrderCouponModules;
                $this->collOrderCouponModulesPartial = false;
            }
        }

        return $this->collOrderCouponModules;
    }

    /**
     * Sets a collection of OrderCouponModule objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderCouponModules A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildOrderCoupon The current object (for fluent API support)
     */
    public function setOrderCouponModules(Collection $orderCouponModules, ConnectionInterface $con = null)
    {
        $orderCouponModulesToDelete = $this->getOrderCouponModules(new Criteria(), $con)->diff($orderCouponModules);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->orderCouponModulesScheduledForDeletion = clone $orderCouponModulesToDelete;

        foreach ($orderCouponModulesToDelete as $orderCouponModuleRemoved) {
            $orderCouponModuleRemoved->setOrderCoupon(null);
        }

        $this->collOrderCouponModules = null;
        foreach ($orderCouponModules as $orderCouponModule) {
            $this->addOrderCouponModule($orderCouponModule);
        }

        $this->collOrderCouponModules = $orderCouponModules;
        $this->collOrderCouponModulesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderCouponModule objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderCouponModule objects.
     * @throws PropelException
     */
    public function countOrderCouponModules(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponModulesPartial && !$this->isNew();
        if (null === $this->collOrderCouponModules || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderCouponModules) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderCouponModules());
            }

            $query = ChildOrderCouponModuleQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByOrderCoupon($this)
                ->count($con);
        }

        return count($this->collOrderCouponModules);
    }

    /**
     * Method called to associate a ChildOrderCouponModule object to this object
     * through the ChildOrderCouponModule foreign key attribute.
     *
     * @param    ChildOrderCouponModule $l ChildOrderCouponModule
     * @return   \Thelia\Model\OrderCoupon The current object (for fluent API support)
     */
    public function addOrderCouponModule(ChildOrderCouponModule $l)
    {
        if ($this->collOrderCouponModules === null) {
            $this->initOrderCouponModules();
            $this->collOrderCouponModulesPartial = true;
        }

        if (!in_array($l, $this->collOrderCouponModules->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderCouponModule($l);
        }

        return $this;
    }

    /**
     * @param OrderCouponModule $orderCouponModule The orderCouponModule object to add.
     */
    protected function doAddOrderCouponModule($orderCouponModule)
    {
        $this->collOrderCouponModules[]= $orderCouponModule;
        $orderCouponModule->setOrderCoupon($this);
    }

    /**
     * @param  OrderCouponModule $orderCouponModule The orderCouponModule object to remove.
     * @return ChildOrderCoupon The current object (for fluent API support)
     */
    public function removeOrderCouponModule($orderCouponModule)
    {
        if ($this->getOrderCouponModules()->contains($orderCouponModule)) {
            $this->collOrderCouponModules->remove($this->collOrderCouponModules->search($orderCouponModule));
            if (null === $this->orderCouponModulesScheduledForDeletion) {
                $this->orderCouponModulesScheduledForDeletion = clone $this->collOrderCouponModules;
                $this->orderCouponModulesScheduledForDeletion->clear();
            }
            $this->orderCouponModulesScheduledForDeletion[]= clone $orderCouponModule;
            $orderCouponModule->setOrderCoupon(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderCoupon is new, it will return
     * an empty collection; or if this OrderCoupon has previously
     * been saved, it will retrieve related OrderCouponModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderCoupon.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrderCouponModule[] List of ChildOrderCouponModule objects
     */
    public function getOrderCouponModulesJoinModule($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderCouponModuleQuery::create(null, $criteria);
        $query->joinWith('Module', $joinBehavior);

        return $this->getOrderCouponModules($query, $con);
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
     * to the current object by way of the order_coupon_country cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrderCoupon is new, it will return
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
                    ->filterByOrderCoupon($this)
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
     * to the current object by way of the order_coupon_country cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $countries A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildOrderCoupon The current object (for fluent API support)
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
     * to the current object by way of the order_coupon_country cross-reference table.
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
                    ->filterByOrderCoupon($this)
                    ->count($con);
            }
        } else {
            return count($this->collCountries);
        }
    }

    /**
     * Associate a ChildCountry object to this object
     * through the order_coupon_country cross reference table.
     *
     * @param  ChildCountry $country The ChildOrderCouponCountry object to relate
     * @return ChildOrderCoupon The current object (for fluent API support)
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
        $orderCouponCountry = new ChildOrderCouponCountry();
        $orderCouponCountry->setCountry($country);
        $this->addOrderCouponCountry($orderCouponCountry);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$country->getOrderCoupons()->contains($this)) {
            $foreignCollection   = $country->getOrderCoupons();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildCountry object to this object
     * through the order_coupon_country cross reference table.
     *
     * @param ChildCountry $country The ChildOrderCouponCountry object to relate
     * @return ChildOrderCoupon The current object (for fluent API support)
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
     * to the current object by way of the order_coupon_module cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrderCoupon is new, it will return
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
                    ->filterByOrderCoupon($this)
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
     * to the current object by way of the order_coupon_module cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $modules A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildOrderCoupon The current object (for fluent API support)
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
     * to the current object by way of the order_coupon_module cross-reference table.
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
                    ->filterByOrderCoupon($this)
                    ->count($con);
            }
        } else {
            return count($this->collModules);
        }
    }

    /**
     * Associate a ChildModule object to this object
     * through the order_coupon_module cross reference table.
     *
     * @param  ChildModule $module The ChildOrderCouponModule object to relate
     * @return ChildOrderCoupon The current object (for fluent API support)
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
        $orderCouponModule = new ChildOrderCouponModule();
        $orderCouponModule->setModule($module);
        $this->addOrderCouponModule($orderCouponModule);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$module->getOrderCoupons()->contains($this)) {
            $foreignCollection   = $module->getOrderCoupons();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildModule object to this object
     * through the order_coupon_module cross reference table.
     *
     * @param ChildModule $module The ChildOrderCouponModule object to relate
     * @return ChildOrderCoupon The current object (for fluent API support)
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
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->order_id = null;
        $this->code = null;
        $this->type = null;
        $this->amount = null;
        $this->title = null;
        $this->short_description = null;
        $this->description = null;
        $this->start_date = null;
        $this->expiration_date = null;
        $this->is_cumulative = null;
        $this->is_removing_postage = null;
        $this->is_available_on_special_offers = null;
        $this->serialized_conditions = null;
        $this->per_customer_usage_count = null;
        $this->usage_canceled = null;
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
            if ($this->collOrderCouponCountries) {
                foreach ($this->collOrderCouponCountries as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrderCouponModules) {
                foreach ($this->collOrderCouponModules as $o) {
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
        } // if ($deep)

        $this->collOrderCouponCountries = null;
        $this->collOrderCouponModules = null;
        $this->collCountries = null;
        $this->collModules = null;
        $this->aOrder = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(OrderCouponTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildOrderCoupon The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[OrderCouponTableMap::UPDATED_AT] = true;

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
