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
use Thelia\Model\Sale as ChildSale;
use Thelia\Model\SaleI18n as ChildSaleI18n;
use Thelia\Model\SaleI18nQuery as ChildSaleI18nQuery;
use Thelia\Model\SaleOffsetCurrency as ChildSaleOffsetCurrency;
use Thelia\Model\SaleOffsetCurrencyQuery as ChildSaleOffsetCurrencyQuery;
use Thelia\Model\SaleProduct as ChildSaleProduct;
use Thelia\Model\SaleProductQuery as ChildSaleProductQuery;
use Thelia\Model\SaleQuery as ChildSaleQuery;
use Thelia\Model\Map\SaleTableMap;

abstract class Sale implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\SaleTableMap';


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
     * The value for the active field.
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $active;

    /**
     * The value for the display_initial_price field.
     * Note: this column has a database default value of: true
     * @var        boolean
     */
    protected $display_initial_price;

    /**
     * The value for the start_date field.
     * @var        string
     */
    protected $start_date;

    /**
     * The value for the end_date field.
     * @var        string
     */
    protected $end_date;

    /**
     * The value for the price_offset_type field.
     * @var        int
     */
    protected $price_offset_type;

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
     * @var        ObjectCollection|ChildSaleOffsetCurrency[] Collection to store aggregation of ChildSaleOffsetCurrency objects.
     */
    protected $collSaleOffsetCurrencies;
    protected $collSaleOffsetCurrenciesPartial;

    /**
     * @var        ObjectCollection|ChildSaleProduct[] Collection to store aggregation of ChildSaleProduct objects.
     */
    protected $collSaleProducts;
    protected $collSaleProductsPartial;

    /**
     * @var        ObjectCollection|ChildSaleI18n[] Collection to store aggregation of ChildSaleI18n objects.
     */
    protected $collSaleI18ns;
    protected $collSaleI18nsPartial;

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
     * @var        array[ChildSaleI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $saleOffsetCurrenciesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $saleProductsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $saleI18nsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->active = false;
        $this->display_initial_price = true;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Sale object.
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
     * Compares this with another <code>Sale</code> instance.  If
     * <code>obj</code> is an instance of <code>Sale</code>, delegates to
     * <code>equals(Sale)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Sale The current object, for fluid interface
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
     * @return Sale The current object, for fluid interface
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
     * Get the [active] column value.
     *
     * @return   boolean
     */
    public function getActive()
    {

        return $this->active;
    }

    /**
     * Get the [display_initial_price] column value.
     *
     * @return   boolean
     */
    public function getDisplayInitialPrice()
    {

        return $this->display_initial_price;
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
     * Get the [optionally formatted] temporal [end_date] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getEndDate($format = NULL)
    {
        if ($format === null) {
            return $this->end_date;
        } else {
            return $this->end_date instanceof \DateTime ? $this->end_date->format($format) : null;
        }
    }

    /**
     * Get the [price_offset_type] column value.
     *
     * @return   int
     */
    public function getPriceOffsetType()
    {

        return $this->price_offset_type;
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
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[SaleTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Sets the value of the [active] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function setActive($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->active !== $v) {
            $this->active = $v;
            $this->modifiedColumns[SaleTableMap::ACTIVE] = true;
        }


        return $this;
    } // setActive()

    /**
     * Sets the value of the [display_initial_price] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function setDisplayInitialPrice($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->display_initial_price !== $v) {
            $this->display_initial_price = $v;
            $this->modifiedColumns[SaleTableMap::DISPLAY_INITIAL_PRICE] = true;
        }


        return $this;
    } // setDisplayInitialPrice()

    /**
     * Sets the value of [start_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function setStartDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->start_date !== null || $dt !== null) {
            if ($dt !== $this->start_date) {
                $this->start_date = $dt;
                $this->modifiedColumns[SaleTableMap::START_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setStartDate()

    /**
     * Sets the value of [end_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function setEndDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->end_date !== null || $dt !== null) {
            if ($dt !== $this->end_date) {
                $this->end_date = $dt;
                $this->modifiedColumns[SaleTableMap::END_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setEndDate()

    /**
     * Set the value of [price_offset_type] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function setPriceOffsetType($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->price_offset_type !== $v) {
            $this->price_offset_type = $v;
            $this->modifiedColumns[SaleTableMap::PRICE_OFFSET_TYPE] = true;
        }


        return $this;
    } // setPriceOffsetType()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[SaleTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[SaleTableMap::UPDATED_AT] = true;
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
            if ($this->active !== false) {
                return false;
            }

            if ($this->display_initial_price !== true) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : SaleTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : SaleTableMap::translateFieldName('Active', TableMap::TYPE_PHPNAME, $indexType)];
            $this->active = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : SaleTableMap::translateFieldName('DisplayInitialPrice', TableMap::TYPE_PHPNAME, $indexType)];
            $this->display_initial_price = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : SaleTableMap::translateFieldName('StartDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->start_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : SaleTableMap::translateFieldName('EndDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->end_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : SaleTableMap::translateFieldName('PriceOffsetType', TableMap::TYPE_PHPNAME, $indexType)];
            $this->price_offset_type = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : SaleTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : SaleTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 8; // 8 = SaleTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Sale object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(SaleTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildSaleQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collSaleOffsetCurrencies = null;

            $this->collSaleProducts = null;

            $this->collSaleI18ns = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Sale::setDeleted()
     * @see Sale::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(SaleTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildSaleQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(SaleTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(SaleTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(SaleTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(SaleTableMap::UPDATED_AT)) {
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
                SaleTableMap::addInstanceToPool($this);
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

            if ($this->saleOffsetCurrenciesScheduledForDeletion !== null) {
                if (!$this->saleOffsetCurrenciesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\SaleOffsetCurrencyQuery::create()
                        ->filterByPrimaryKeys($this->saleOffsetCurrenciesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->saleOffsetCurrenciesScheduledForDeletion = null;
                }
            }

                if ($this->collSaleOffsetCurrencies !== null) {
            foreach ($this->collSaleOffsetCurrencies as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->saleProductsScheduledForDeletion !== null) {
                if (!$this->saleProductsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\SaleProductQuery::create()
                        ->filterByPrimaryKeys($this->saleProductsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->saleProductsScheduledForDeletion = null;
                }
            }

                if ($this->collSaleProducts !== null) {
            foreach ($this->collSaleProducts as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->saleI18nsScheduledForDeletion !== null) {
                if (!$this->saleI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\SaleI18nQuery::create()
                        ->filterByPrimaryKeys($this->saleI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->saleI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collSaleI18ns !== null) {
            foreach ($this->collSaleI18ns as $referrerFK) {
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

        $this->modifiedColumns[SaleTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . SaleTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(SaleTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(SaleTableMap::ACTIVE)) {
            $modifiedColumns[':p' . $index++]  = '`ACTIVE`';
        }
        if ($this->isColumnModified(SaleTableMap::DISPLAY_INITIAL_PRICE)) {
            $modifiedColumns[':p' . $index++]  = '`DISPLAY_INITIAL_PRICE`';
        }
        if ($this->isColumnModified(SaleTableMap::START_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`START_DATE`';
        }
        if ($this->isColumnModified(SaleTableMap::END_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`END_DATE`';
        }
        if ($this->isColumnModified(SaleTableMap::PRICE_OFFSET_TYPE)) {
            $modifiedColumns[':p' . $index++]  = '`PRICE_OFFSET_TYPE`';
        }
        if ($this->isColumnModified(SaleTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(SaleTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `sale` (%s) VALUES (%s)',
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
                    case '`ACTIVE`':
                        $stmt->bindValue($identifier, (int) $this->active, PDO::PARAM_INT);
                        break;
                    case '`DISPLAY_INITIAL_PRICE`':
                        $stmt->bindValue($identifier, (int) $this->display_initial_price, PDO::PARAM_INT);
                        break;
                    case '`START_DATE`':
                        $stmt->bindValue($identifier, $this->start_date ? $this->start_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`END_DATE`':
                        $stmt->bindValue($identifier, $this->end_date ? $this->end_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`PRICE_OFFSET_TYPE`':
                        $stmt->bindValue($identifier, $this->price_offset_type, PDO::PARAM_INT);
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
        $pos = SaleTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getActive();
                break;
            case 2:
                return $this->getDisplayInitialPrice();
                break;
            case 3:
                return $this->getStartDate();
                break;
            case 4:
                return $this->getEndDate();
                break;
            case 5:
                return $this->getPriceOffsetType();
                break;
            case 6:
                return $this->getCreatedAt();
                break;
            case 7:
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
        if (isset($alreadyDumpedObjects['Sale'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Sale'][$this->getPrimaryKey()] = true;
        $keys = SaleTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getActive(),
            $keys[2] => $this->getDisplayInitialPrice(),
            $keys[3] => $this->getStartDate(),
            $keys[4] => $this->getEndDate(),
            $keys[5] => $this->getPriceOffsetType(),
            $keys[6] => $this->getCreatedAt(),
            $keys[7] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collSaleOffsetCurrencies) {
                $result['SaleOffsetCurrencies'] = $this->collSaleOffsetCurrencies->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collSaleProducts) {
                $result['SaleProducts'] = $this->collSaleProducts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collSaleI18ns) {
                $result['SaleI18ns'] = $this->collSaleI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = SaleTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setActive($value);
                break;
            case 2:
                $this->setDisplayInitialPrice($value);
                break;
            case 3:
                $this->setStartDate($value);
                break;
            case 4:
                $this->setEndDate($value);
                break;
            case 5:
                $this->setPriceOffsetType($value);
                break;
            case 6:
                $this->setCreatedAt($value);
                break;
            case 7:
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
        $keys = SaleTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setActive($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setDisplayInitialPrice($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setStartDate($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setEndDate($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setPriceOffsetType($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setCreatedAt($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setUpdatedAt($arr[$keys[7]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(SaleTableMap::DATABASE_NAME);

        if ($this->isColumnModified(SaleTableMap::ID)) $criteria->add(SaleTableMap::ID, $this->id);
        if ($this->isColumnModified(SaleTableMap::ACTIVE)) $criteria->add(SaleTableMap::ACTIVE, $this->active);
        if ($this->isColumnModified(SaleTableMap::DISPLAY_INITIAL_PRICE)) $criteria->add(SaleTableMap::DISPLAY_INITIAL_PRICE, $this->display_initial_price);
        if ($this->isColumnModified(SaleTableMap::START_DATE)) $criteria->add(SaleTableMap::START_DATE, $this->start_date);
        if ($this->isColumnModified(SaleTableMap::END_DATE)) $criteria->add(SaleTableMap::END_DATE, $this->end_date);
        if ($this->isColumnModified(SaleTableMap::PRICE_OFFSET_TYPE)) $criteria->add(SaleTableMap::PRICE_OFFSET_TYPE, $this->price_offset_type);
        if ($this->isColumnModified(SaleTableMap::CREATED_AT)) $criteria->add(SaleTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(SaleTableMap::UPDATED_AT)) $criteria->add(SaleTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(SaleTableMap::DATABASE_NAME);
        $criteria->add(SaleTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Sale (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setActive($this->getActive());
        $copyObj->setDisplayInitialPrice($this->getDisplayInitialPrice());
        $copyObj->setStartDate($this->getStartDate());
        $copyObj->setEndDate($this->getEndDate());
        $copyObj->setPriceOffsetType($this->getPriceOffsetType());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getSaleOffsetCurrencies() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addSaleOffsetCurrency($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getSaleProducts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addSaleProduct($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getSaleI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addSaleI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Sale Clone of current object.
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
        if ('SaleOffsetCurrency' == $relationName) {
            return $this->initSaleOffsetCurrencies();
        }
        if ('SaleProduct' == $relationName) {
            return $this->initSaleProducts();
        }
        if ('SaleI18n' == $relationName) {
            return $this->initSaleI18ns();
        }
    }

    /**
     * Clears out the collSaleOffsetCurrencies collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addSaleOffsetCurrencies()
     */
    public function clearSaleOffsetCurrencies()
    {
        $this->collSaleOffsetCurrencies = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collSaleOffsetCurrencies collection loaded partially.
     */
    public function resetPartialSaleOffsetCurrencies($v = true)
    {
        $this->collSaleOffsetCurrenciesPartial = $v;
    }

    /**
     * Initializes the collSaleOffsetCurrencies collection.
     *
     * By default this just sets the collSaleOffsetCurrencies collection to an empty array (like clearcollSaleOffsetCurrencies());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initSaleOffsetCurrencies($overrideExisting = true)
    {
        if (null !== $this->collSaleOffsetCurrencies && !$overrideExisting) {
            return;
        }
        $this->collSaleOffsetCurrencies = new ObjectCollection();
        $this->collSaleOffsetCurrencies->setModel('\Thelia\Model\SaleOffsetCurrency');
    }

    /**
     * Gets an array of ChildSaleOffsetCurrency objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildSale is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildSaleOffsetCurrency[] List of ChildSaleOffsetCurrency objects
     * @throws PropelException
     */
    public function getSaleOffsetCurrencies($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collSaleOffsetCurrenciesPartial && !$this->isNew();
        if (null === $this->collSaleOffsetCurrencies || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collSaleOffsetCurrencies) {
                // return empty collection
                $this->initSaleOffsetCurrencies();
            } else {
                $collSaleOffsetCurrencies = ChildSaleOffsetCurrencyQuery::create(null, $criteria)
                    ->filterBySale($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collSaleOffsetCurrenciesPartial && count($collSaleOffsetCurrencies)) {
                        $this->initSaleOffsetCurrencies(false);

                        foreach ($collSaleOffsetCurrencies as $obj) {
                            if (false == $this->collSaleOffsetCurrencies->contains($obj)) {
                                $this->collSaleOffsetCurrencies->append($obj);
                            }
                        }

                        $this->collSaleOffsetCurrenciesPartial = true;
                    }

                    reset($collSaleOffsetCurrencies);

                    return $collSaleOffsetCurrencies;
                }

                if ($partial && $this->collSaleOffsetCurrencies) {
                    foreach ($this->collSaleOffsetCurrencies as $obj) {
                        if ($obj->isNew()) {
                            $collSaleOffsetCurrencies[] = $obj;
                        }
                    }
                }

                $this->collSaleOffsetCurrencies = $collSaleOffsetCurrencies;
                $this->collSaleOffsetCurrenciesPartial = false;
            }
        }

        return $this->collSaleOffsetCurrencies;
    }

    /**
     * Sets a collection of SaleOffsetCurrency objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $saleOffsetCurrencies A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildSale The current object (for fluent API support)
     */
    public function setSaleOffsetCurrencies(Collection $saleOffsetCurrencies, ConnectionInterface $con = null)
    {
        $saleOffsetCurrenciesToDelete = $this->getSaleOffsetCurrencies(new Criteria(), $con)->diff($saleOffsetCurrencies);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->saleOffsetCurrenciesScheduledForDeletion = clone $saleOffsetCurrenciesToDelete;

        foreach ($saleOffsetCurrenciesToDelete as $saleOffsetCurrencyRemoved) {
            $saleOffsetCurrencyRemoved->setSale(null);
        }

        $this->collSaleOffsetCurrencies = null;
        foreach ($saleOffsetCurrencies as $saleOffsetCurrency) {
            $this->addSaleOffsetCurrency($saleOffsetCurrency);
        }

        $this->collSaleOffsetCurrencies = $saleOffsetCurrencies;
        $this->collSaleOffsetCurrenciesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related SaleOffsetCurrency objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related SaleOffsetCurrency objects.
     * @throws PropelException
     */
    public function countSaleOffsetCurrencies(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collSaleOffsetCurrenciesPartial && !$this->isNew();
        if (null === $this->collSaleOffsetCurrencies || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collSaleOffsetCurrencies) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getSaleOffsetCurrencies());
            }

            $query = ChildSaleOffsetCurrencyQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterBySale($this)
                ->count($con);
        }

        return count($this->collSaleOffsetCurrencies);
    }

    /**
     * Method called to associate a ChildSaleOffsetCurrency object to this object
     * through the ChildSaleOffsetCurrency foreign key attribute.
     *
     * @param    ChildSaleOffsetCurrency $l ChildSaleOffsetCurrency
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function addSaleOffsetCurrency(ChildSaleOffsetCurrency $l)
    {
        if ($this->collSaleOffsetCurrencies === null) {
            $this->initSaleOffsetCurrencies();
            $this->collSaleOffsetCurrenciesPartial = true;
        }

        if (!in_array($l, $this->collSaleOffsetCurrencies->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddSaleOffsetCurrency($l);
        }

        return $this;
    }

    /**
     * @param SaleOffsetCurrency $saleOffsetCurrency The saleOffsetCurrency object to add.
     */
    protected function doAddSaleOffsetCurrency($saleOffsetCurrency)
    {
        $this->collSaleOffsetCurrencies[]= $saleOffsetCurrency;
        $saleOffsetCurrency->setSale($this);
    }

    /**
     * @param  SaleOffsetCurrency $saleOffsetCurrency The saleOffsetCurrency object to remove.
     * @return ChildSale The current object (for fluent API support)
     */
    public function removeSaleOffsetCurrency($saleOffsetCurrency)
    {
        if ($this->getSaleOffsetCurrencies()->contains($saleOffsetCurrency)) {
            $this->collSaleOffsetCurrencies->remove($this->collSaleOffsetCurrencies->search($saleOffsetCurrency));
            if (null === $this->saleOffsetCurrenciesScheduledForDeletion) {
                $this->saleOffsetCurrenciesScheduledForDeletion = clone $this->collSaleOffsetCurrencies;
                $this->saleOffsetCurrenciesScheduledForDeletion->clear();
            }
            $this->saleOffsetCurrenciesScheduledForDeletion[]= clone $saleOffsetCurrency;
            $saleOffsetCurrency->setSale(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Sale is new, it will return
     * an empty collection; or if this Sale has previously
     * been saved, it will retrieve related SaleOffsetCurrencies from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Sale.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildSaleOffsetCurrency[] List of ChildSaleOffsetCurrency objects
     */
    public function getSaleOffsetCurrenciesJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildSaleOffsetCurrencyQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getSaleOffsetCurrencies($query, $con);
    }

    /**
     * Clears out the collSaleProducts collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addSaleProducts()
     */
    public function clearSaleProducts()
    {
        $this->collSaleProducts = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collSaleProducts collection loaded partially.
     */
    public function resetPartialSaleProducts($v = true)
    {
        $this->collSaleProductsPartial = $v;
    }

    /**
     * Initializes the collSaleProducts collection.
     *
     * By default this just sets the collSaleProducts collection to an empty array (like clearcollSaleProducts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initSaleProducts($overrideExisting = true)
    {
        if (null !== $this->collSaleProducts && !$overrideExisting) {
            return;
        }
        $this->collSaleProducts = new ObjectCollection();
        $this->collSaleProducts->setModel('\Thelia\Model\SaleProduct');
    }

    /**
     * Gets an array of ChildSaleProduct objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildSale is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildSaleProduct[] List of ChildSaleProduct objects
     * @throws PropelException
     */
    public function getSaleProducts($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collSaleProductsPartial && !$this->isNew();
        if (null === $this->collSaleProducts || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collSaleProducts) {
                // return empty collection
                $this->initSaleProducts();
            } else {
                $collSaleProducts = ChildSaleProductQuery::create(null, $criteria)
                    ->filterBySale($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collSaleProductsPartial && count($collSaleProducts)) {
                        $this->initSaleProducts(false);

                        foreach ($collSaleProducts as $obj) {
                            if (false == $this->collSaleProducts->contains($obj)) {
                                $this->collSaleProducts->append($obj);
                            }
                        }

                        $this->collSaleProductsPartial = true;
                    }

                    reset($collSaleProducts);

                    return $collSaleProducts;
                }

                if ($partial && $this->collSaleProducts) {
                    foreach ($this->collSaleProducts as $obj) {
                        if ($obj->isNew()) {
                            $collSaleProducts[] = $obj;
                        }
                    }
                }

                $this->collSaleProducts = $collSaleProducts;
                $this->collSaleProductsPartial = false;
            }
        }

        return $this->collSaleProducts;
    }

    /**
     * Sets a collection of SaleProduct objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $saleProducts A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildSale The current object (for fluent API support)
     */
    public function setSaleProducts(Collection $saleProducts, ConnectionInterface $con = null)
    {
        $saleProductsToDelete = $this->getSaleProducts(new Criteria(), $con)->diff($saleProducts);


        $this->saleProductsScheduledForDeletion = $saleProductsToDelete;

        foreach ($saleProductsToDelete as $saleProductRemoved) {
            $saleProductRemoved->setSale(null);
        }

        $this->collSaleProducts = null;
        foreach ($saleProducts as $saleProduct) {
            $this->addSaleProduct($saleProduct);
        }

        $this->collSaleProducts = $saleProducts;
        $this->collSaleProductsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related SaleProduct objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related SaleProduct objects.
     * @throws PropelException
     */
    public function countSaleProducts(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collSaleProductsPartial && !$this->isNew();
        if (null === $this->collSaleProducts || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collSaleProducts) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getSaleProducts());
            }

            $query = ChildSaleProductQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterBySale($this)
                ->count($con);
        }

        return count($this->collSaleProducts);
    }

    /**
     * Method called to associate a ChildSaleProduct object to this object
     * through the ChildSaleProduct foreign key attribute.
     *
     * @param    ChildSaleProduct $l ChildSaleProduct
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function addSaleProduct(ChildSaleProduct $l)
    {
        if ($this->collSaleProducts === null) {
            $this->initSaleProducts();
            $this->collSaleProductsPartial = true;
        }

        if (!in_array($l, $this->collSaleProducts->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddSaleProduct($l);
        }

        return $this;
    }

    /**
     * @param SaleProduct $saleProduct The saleProduct object to add.
     */
    protected function doAddSaleProduct($saleProduct)
    {
        $this->collSaleProducts[]= $saleProduct;
        $saleProduct->setSale($this);
    }

    /**
     * @param  SaleProduct $saleProduct The saleProduct object to remove.
     * @return ChildSale The current object (for fluent API support)
     */
    public function removeSaleProduct($saleProduct)
    {
        if ($this->getSaleProducts()->contains($saleProduct)) {
            $this->collSaleProducts->remove($this->collSaleProducts->search($saleProduct));
            if (null === $this->saleProductsScheduledForDeletion) {
                $this->saleProductsScheduledForDeletion = clone $this->collSaleProducts;
                $this->saleProductsScheduledForDeletion->clear();
            }
            $this->saleProductsScheduledForDeletion[]= clone $saleProduct;
            $saleProduct->setSale(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Sale is new, it will return
     * an empty collection; or if this Sale has previously
     * been saved, it will retrieve related SaleProducts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Sale.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildSaleProduct[] List of ChildSaleProduct objects
     */
    public function getSaleProductsJoinProduct($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildSaleProductQuery::create(null, $criteria);
        $query->joinWith('Product', $joinBehavior);

        return $this->getSaleProducts($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Sale is new, it will return
     * an empty collection; or if this Sale has previously
     * been saved, it will retrieve related SaleProducts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Sale.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildSaleProduct[] List of ChildSaleProduct objects
     */
    public function getSaleProductsJoinAttributeAv($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildSaleProductQuery::create(null, $criteria);
        $query->joinWith('AttributeAv', $joinBehavior);

        return $this->getSaleProducts($query, $con);
    }

    /**
     * Clears out the collSaleI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addSaleI18ns()
     */
    public function clearSaleI18ns()
    {
        $this->collSaleI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collSaleI18ns collection loaded partially.
     */
    public function resetPartialSaleI18ns($v = true)
    {
        $this->collSaleI18nsPartial = $v;
    }

    /**
     * Initializes the collSaleI18ns collection.
     *
     * By default this just sets the collSaleI18ns collection to an empty array (like clearcollSaleI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initSaleI18ns($overrideExisting = true)
    {
        if (null !== $this->collSaleI18ns && !$overrideExisting) {
            return;
        }
        $this->collSaleI18ns = new ObjectCollection();
        $this->collSaleI18ns->setModel('\Thelia\Model\SaleI18n');
    }

    /**
     * Gets an array of ChildSaleI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildSale is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildSaleI18n[] List of ChildSaleI18n objects
     * @throws PropelException
     */
    public function getSaleI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collSaleI18nsPartial && !$this->isNew();
        if (null === $this->collSaleI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collSaleI18ns) {
                // return empty collection
                $this->initSaleI18ns();
            } else {
                $collSaleI18ns = ChildSaleI18nQuery::create(null, $criteria)
                    ->filterBySale($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collSaleI18nsPartial && count($collSaleI18ns)) {
                        $this->initSaleI18ns(false);

                        foreach ($collSaleI18ns as $obj) {
                            if (false == $this->collSaleI18ns->contains($obj)) {
                                $this->collSaleI18ns->append($obj);
                            }
                        }

                        $this->collSaleI18nsPartial = true;
                    }

                    reset($collSaleI18ns);

                    return $collSaleI18ns;
                }

                if ($partial && $this->collSaleI18ns) {
                    foreach ($this->collSaleI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collSaleI18ns[] = $obj;
                        }
                    }
                }

                $this->collSaleI18ns = $collSaleI18ns;
                $this->collSaleI18nsPartial = false;
            }
        }

        return $this->collSaleI18ns;
    }

    /**
     * Sets a collection of SaleI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $saleI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildSale The current object (for fluent API support)
     */
    public function setSaleI18ns(Collection $saleI18ns, ConnectionInterface $con = null)
    {
        $saleI18nsToDelete = $this->getSaleI18ns(new Criteria(), $con)->diff($saleI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->saleI18nsScheduledForDeletion = clone $saleI18nsToDelete;

        foreach ($saleI18nsToDelete as $saleI18nRemoved) {
            $saleI18nRemoved->setSale(null);
        }

        $this->collSaleI18ns = null;
        foreach ($saleI18ns as $saleI18n) {
            $this->addSaleI18n($saleI18n);
        }

        $this->collSaleI18ns = $saleI18ns;
        $this->collSaleI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related SaleI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related SaleI18n objects.
     * @throws PropelException
     */
    public function countSaleI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collSaleI18nsPartial && !$this->isNew();
        if (null === $this->collSaleI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collSaleI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getSaleI18ns());
            }

            $query = ChildSaleI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterBySale($this)
                ->count($con);
        }

        return count($this->collSaleI18ns);
    }

    /**
     * Method called to associate a ChildSaleI18n object to this object
     * through the ChildSaleI18n foreign key attribute.
     *
     * @param    ChildSaleI18n $l ChildSaleI18n
     * @return   \Thelia\Model\Sale The current object (for fluent API support)
     */
    public function addSaleI18n(ChildSaleI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collSaleI18ns === null) {
            $this->initSaleI18ns();
            $this->collSaleI18nsPartial = true;
        }

        if (!in_array($l, $this->collSaleI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddSaleI18n($l);
        }

        return $this;
    }

    /**
     * @param SaleI18n $saleI18n The saleI18n object to add.
     */
    protected function doAddSaleI18n($saleI18n)
    {
        $this->collSaleI18ns[]= $saleI18n;
        $saleI18n->setSale($this);
    }

    /**
     * @param  SaleI18n $saleI18n The saleI18n object to remove.
     * @return ChildSale The current object (for fluent API support)
     */
    public function removeSaleI18n($saleI18n)
    {
        if ($this->getSaleI18ns()->contains($saleI18n)) {
            $this->collSaleI18ns->remove($this->collSaleI18ns->search($saleI18n));
            if (null === $this->saleI18nsScheduledForDeletion) {
                $this->saleI18nsScheduledForDeletion = clone $this->collSaleI18ns;
                $this->saleI18nsScheduledForDeletion->clear();
            }
            $this->saleI18nsScheduledForDeletion[]= clone $saleI18n;
            $saleI18n->setSale(null);
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->active = null;
        $this->display_initial_price = null;
        $this->start_date = null;
        $this->end_date = null;
        $this->price_offset_type = null;
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
            if ($this->collSaleOffsetCurrencies) {
                foreach ($this->collSaleOffsetCurrencies as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collSaleProducts) {
                foreach ($this->collSaleProducts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collSaleI18ns) {
                foreach ($this->collSaleI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collSaleOffsetCurrencies = null;
        $this->collSaleProducts = null;
        $this->collSaleI18ns = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(SaleTableMap::DEFAULT_STRING_FORMAT);
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildSale The current object (for fluent API support)
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
     * @return ChildSaleI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collSaleI18ns) {
                foreach ($this->collSaleI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildSaleI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildSaleI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addSaleI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildSale The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildSaleI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collSaleI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collSaleI18ns[$key]);
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
     * @return ChildSaleI18n */
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
         * @return   \Thelia\Model\SaleI18n The current object (for fluent API support)
         */
        public function setTitle($v)
        {    $this->getCurrentTranslation()->setTitle($v);

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
         * @return   \Thelia\Model\SaleI18n The current object (for fluent API support)
         */
        public function setDescription($v)
        {    $this->getCurrentTranslation()->setDescription($v);

        return $this;
    }


        /**
         * Get the [chapo] column value.
         *
         * @return   string
         */
        public function getChapo()
        {
        return $this->getCurrentTranslation()->getChapo();
    }


        /**
         * Set the value of [chapo] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\SaleI18n The current object (for fluent API support)
         */
        public function setChapo($v)
        {    $this->getCurrentTranslation()->setChapo($v);

        return $this;
    }


        /**
         * Get the [postscriptum] column value.
         *
         * @return   string
         */
        public function getPostscriptum()
        {
        return $this->getCurrentTranslation()->getPostscriptum();
    }


        /**
         * Set the value of [postscriptum] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\SaleI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }


        /**
         * Get the [sale_label] column value.
         *
         * @return   string
         */
        public function getSaleLabel()
        {
        return $this->getCurrentTranslation()->getSaleLabel();
    }


        /**
         * Set the value of [sale_label] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\SaleI18n The current object (for fluent API support)
         */
        public function setSaleLabel($v)
        {    $this->getCurrentTranslation()->setSaleLabel($v);

        return $this;
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildSale The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[SaleTableMap::UPDATED_AT] = true;

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
