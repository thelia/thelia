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
use Thelia\Model\Cart as ChildCart;
use Thelia\Model\CartQuery as ChildCartQuery;
use Thelia\Model\Currency as ChildCurrency;
use Thelia\Model\CurrencyI18n as ChildCurrencyI18n;
use Thelia\Model\CurrencyI18nQuery as ChildCurrencyI18nQuery;
use Thelia\Model\CurrencyQuery as ChildCurrencyQuery;
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\ProductPrice as ChildProductPrice;
use Thelia\Model\ProductPriceQuery as ChildProductPriceQuery;
use Thelia\Model\SaleOffsetCurrency as ChildSaleOffsetCurrency;
use Thelia\Model\SaleOffsetCurrencyQuery as ChildSaleOffsetCurrencyQuery;
use Thelia\Model\Map\CurrencyTableMap;

abstract class Currency implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\CurrencyTableMap';


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
     * The value for the symbol field.
     * @var        string
     */
    protected $symbol;

    /**
     * The value for the format field.
     * @var        string
     */
    protected $format;

    /**
     * The value for the rate field.
     * @var        double
     */
    protected $rate;

    /**
     * The value for the position field.
     * @var        int
     */
    protected $position;

    /**
     * The value for the by_default field.
     * @var        int
     */
    protected $by_default;

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
     * @var        ObjectCollection|ChildProductPrice[] Collection to store aggregation of ChildProductPrice objects.
     */
    protected $collProductPrices;
    protected $collProductPricesPartial;

    /**
     * @var        ObjectCollection|ChildSaleOffsetCurrency[] Collection to store aggregation of ChildSaleOffsetCurrency objects.
     */
    protected $collSaleOffsetCurrencies;
    protected $collSaleOffsetCurrenciesPartial;

    /**
     * @var        ObjectCollection|ChildCurrencyI18n[] Collection to store aggregation of ChildCurrencyI18n objects.
     */
    protected $collCurrencyI18ns;
    protected $collCurrencyI18nsPartial;

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
     * @var        array[ChildCurrencyI18n]
     */
    protected $currentTranslations;

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
    protected $productPricesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $saleOffsetCurrenciesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $currencyI18nsScheduledForDeletion = null;

    /**
     * Initializes internal state of Thelia\Model\Base\Currency object.
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
     * Compares this with another <code>Currency</code> instance.  If
     * <code>obj</code> is an instance of <code>Currency</code>, delegates to
     * <code>equals(Currency)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Currency The current object, for fluid interface
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
     * @return Currency The current object, for fluid interface
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
     * Get the [symbol] column value.
     *
     * @return   string
     */
    public function getSymbol()
    {

        return $this->symbol;
    }

    /**
     * Get the [format] column value.
     *
     * @return   string
     */
    public function getFormat()
    {

        return $this->format;
    }

    /**
     * Get the [rate] column value.
     *
     * @return   double
     */
    public function getRate()
    {

        return $this->rate;
    }

    /**
     * Get the [position] column value.
     *
     * @return   int
     */
    public function getPosition()
    {

        return $this->position;
    }

    /**
     * Get the [by_default] column value.
     *
     * @return   int
     */
    public function getByDefault()
    {

        return $this->by_default;
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
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[CurrencyTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [code] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[CurrencyTableMap::CODE] = true;
        }


        return $this;
    } // setCode()

    /**
     * Set the value of [symbol] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setSymbol($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->symbol !== $v) {
            $this->symbol = $v;
            $this->modifiedColumns[CurrencyTableMap::SYMBOL] = true;
        }


        return $this;
    } // setSymbol()

    /**
     * Set the value of [format] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setFormat($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->format !== $v) {
            $this->format = $v;
            $this->modifiedColumns[CurrencyTableMap::FORMAT] = true;
        }


        return $this;
    } // setFormat()

    /**
     * Set the value of [rate] column.
     *
     * @param      double $v new value
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setRate($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->rate !== $v) {
            $this->rate = $v;
            $this->modifiedColumns[CurrencyTableMap::RATE] = true;
        }


        return $this;
    } // setRate()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[CurrencyTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Set the value of [by_default] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setByDefault($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->by_default !== $v) {
            $this->by_default = $v;
            $this->modifiedColumns[CurrencyTableMap::BY_DEFAULT] = true;
        }


        return $this;
    } // setByDefault()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[CurrencyTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[CurrencyTableMap::UPDATED_AT] = true;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : CurrencyTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : CurrencyTableMap::translateFieldName('Code', TableMap::TYPE_PHPNAME, $indexType)];
            $this->code = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : CurrencyTableMap::translateFieldName('Symbol', TableMap::TYPE_PHPNAME, $indexType)];
            $this->symbol = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : CurrencyTableMap::translateFieldName('Format', TableMap::TYPE_PHPNAME, $indexType)];
            $this->format = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : CurrencyTableMap::translateFieldName('Rate', TableMap::TYPE_PHPNAME, $indexType)];
            $this->rate = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : CurrencyTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : CurrencyTableMap::translateFieldName('ByDefault', TableMap::TYPE_PHPNAME, $indexType)];
            $this->by_default = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : CurrencyTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : CurrencyTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 9; // 9 = CurrencyTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Currency object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(CurrencyTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildCurrencyQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collOrders = null;

            $this->collCarts = null;

            $this->collProductPrices = null;

            $this->collSaleOffsetCurrencies = null;

            $this->collCurrencyI18ns = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Currency::setDeleted()
     * @see Currency::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CurrencyTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildCurrencyQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(CurrencyTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(CurrencyTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(CurrencyTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(CurrencyTableMap::UPDATED_AT)) {
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
                CurrencyTableMap::addInstanceToPool($this);
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

            if ($this->productPricesScheduledForDeletion !== null) {
                if (!$this->productPricesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductPriceQuery::create()
                        ->filterByPrimaryKeys($this->productPricesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productPricesScheduledForDeletion = null;
                }
            }

                if ($this->collProductPrices !== null) {
            foreach ($this->collProductPrices as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
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

            if ($this->currencyI18nsScheduledForDeletion !== null) {
                if (!$this->currencyI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CurrencyI18nQuery::create()
                        ->filterByPrimaryKeys($this->currencyI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->currencyI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collCurrencyI18ns !== null) {
            foreach ($this->collCurrencyI18ns as $referrerFK) {
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

        $this->modifiedColumns[CurrencyTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . CurrencyTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CurrencyTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CurrencyTableMap::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`CODE`';
        }
        if ($this->isColumnModified(CurrencyTableMap::SYMBOL)) {
            $modifiedColumns[':p' . $index++]  = '`SYMBOL`';
        }
        if ($this->isColumnModified(CurrencyTableMap::FORMAT)) {
            $modifiedColumns[':p' . $index++]  = '`FORMAT`';
        }
        if ($this->isColumnModified(CurrencyTableMap::RATE)) {
            $modifiedColumns[':p' . $index++]  = '`RATE`';
        }
        if ($this->isColumnModified(CurrencyTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(CurrencyTableMap::BY_DEFAULT)) {
            $modifiedColumns[':p' . $index++]  = '`BY_DEFAULT`';
        }
        if ($this->isColumnModified(CurrencyTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CurrencyTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `currency` (%s) VALUES (%s)',
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
                    case '`SYMBOL`':
                        $stmt->bindValue($identifier, $this->symbol, PDO::PARAM_STR);
                        break;
                    case '`FORMAT`':
                        $stmt->bindValue($identifier, $this->format, PDO::PARAM_STR);
                        break;
                    case '`RATE`':
                        $stmt->bindValue($identifier, $this->rate, PDO::PARAM_STR);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
                        break;
                    case '`BY_DEFAULT`':
                        $stmt->bindValue($identifier, $this->by_default, PDO::PARAM_INT);
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
        $pos = CurrencyTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getSymbol();
                break;
            case 3:
                return $this->getFormat();
                break;
            case 4:
                return $this->getRate();
                break;
            case 5:
                return $this->getPosition();
                break;
            case 6:
                return $this->getByDefault();
                break;
            case 7:
                return $this->getCreatedAt();
                break;
            case 8:
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
        if (isset($alreadyDumpedObjects['Currency'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Currency'][$this->getPrimaryKey()] = true;
        $keys = CurrencyTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCode(),
            $keys[2] => $this->getSymbol(),
            $keys[3] => $this->getFormat(),
            $keys[4] => $this->getRate(),
            $keys[5] => $this->getPosition(),
            $keys[6] => $this->getByDefault(),
            $keys[7] => $this->getCreatedAt(),
            $keys[8] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collOrders) {
                $result['Orders'] = $this->collOrders->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCarts) {
                $result['Carts'] = $this->collCarts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductPrices) {
                $result['ProductPrices'] = $this->collProductPrices->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collSaleOffsetCurrencies) {
                $result['SaleOffsetCurrencies'] = $this->collSaleOffsetCurrencies->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCurrencyI18ns) {
                $result['CurrencyI18ns'] = $this->collCurrencyI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = CurrencyTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setSymbol($value);
                break;
            case 3:
                $this->setFormat($value);
                break;
            case 4:
                $this->setRate($value);
                break;
            case 5:
                $this->setPosition($value);
                break;
            case 6:
                $this->setByDefault($value);
                break;
            case 7:
                $this->setCreatedAt($value);
                break;
            case 8:
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
        $keys = CurrencyTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCode($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setSymbol($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setFormat($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setRate($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setPosition($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setByDefault($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setCreatedAt($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setUpdatedAt($arr[$keys[8]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(CurrencyTableMap::DATABASE_NAME);

        if ($this->isColumnModified(CurrencyTableMap::ID)) $criteria->add(CurrencyTableMap::ID, $this->id);
        if ($this->isColumnModified(CurrencyTableMap::CODE)) $criteria->add(CurrencyTableMap::CODE, $this->code);
        if ($this->isColumnModified(CurrencyTableMap::SYMBOL)) $criteria->add(CurrencyTableMap::SYMBOL, $this->symbol);
        if ($this->isColumnModified(CurrencyTableMap::FORMAT)) $criteria->add(CurrencyTableMap::FORMAT, $this->format);
        if ($this->isColumnModified(CurrencyTableMap::RATE)) $criteria->add(CurrencyTableMap::RATE, $this->rate);
        if ($this->isColumnModified(CurrencyTableMap::POSITION)) $criteria->add(CurrencyTableMap::POSITION, $this->position);
        if ($this->isColumnModified(CurrencyTableMap::BY_DEFAULT)) $criteria->add(CurrencyTableMap::BY_DEFAULT, $this->by_default);
        if ($this->isColumnModified(CurrencyTableMap::CREATED_AT)) $criteria->add(CurrencyTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CurrencyTableMap::UPDATED_AT)) $criteria->add(CurrencyTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(CurrencyTableMap::DATABASE_NAME);
        $criteria->add(CurrencyTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Currency (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCode($this->getCode());
        $copyObj->setSymbol($this->getSymbol());
        $copyObj->setFormat($this->getFormat());
        $copyObj->setRate($this->getRate());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setByDefault($this->getByDefault());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

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

            foreach ($this->getProductPrices() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductPrice($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getSaleOffsetCurrencies() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addSaleOffsetCurrency($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCurrencyI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCurrencyI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Currency Clone of current object.
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
        if ('Order' == $relationName) {
            return $this->initOrders();
        }
        if ('Cart' == $relationName) {
            return $this->initCarts();
        }
        if ('ProductPrice' == $relationName) {
            return $this->initProductPrices();
        }
        if ('SaleOffsetCurrency' == $relationName) {
            return $this->initSaleOffsetCurrencies();
        }
        if ('CurrencyI18n' == $relationName) {
            return $this->initCurrencyI18ns();
        }
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
     * If this ChildCurrency is new, it will return
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
                    ->filterByCurrency($this)
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
     * @return   ChildCurrency The current object (for fluent API support)
     */
    public function setOrders(Collection $orders, ConnectionInterface $con = null)
    {
        $ordersToDelete = $this->getOrders(new Criteria(), $con)->diff($orders);


        $this->ordersScheduledForDeletion = $ordersToDelete;

        foreach ($ordersToDelete as $orderRemoved) {
            $orderRemoved->setCurrency(null);
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
                ->filterByCurrency($this)
                ->count($con);
        }

        return count($this->collOrders);
    }

    /**
     * Method called to associate a ChildOrder object to this object
     * through the ChildOrder foreign key attribute.
     *
     * @param    ChildOrder $l ChildOrder
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
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
        $order->setCurrency($this);
    }

    /**
     * @param  Order $order The order object to remove.
     * @return ChildCurrency The current object (for fluent API support)
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
            $order->setCurrency(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
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
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
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
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
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
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
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
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
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
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
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
     * If this ChildCurrency is new, it will return
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
                    ->filterByCurrency($this)
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
     * @return   ChildCurrency The current object (for fluent API support)
     */
    public function setCarts(Collection $carts, ConnectionInterface $con = null)
    {
        $cartsToDelete = $this->getCarts(new Criteria(), $con)->diff($carts);


        $this->cartsScheduledForDeletion = $cartsToDelete;

        foreach ($cartsToDelete as $cartRemoved) {
            $cartRemoved->setCurrency(null);
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
                ->filterByCurrency($this)
                ->count($con);
        }

        return count($this->collCarts);
    }

    /**
     * Method called to associate a ChildCart object to this object
     * through the ChildCart foreign key attribute.
     *
     * @param    ChildCart $l ChildCart
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
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
        $cart->setCurrency($this);
    }

    /**
     * @param  Cart $cart The cart object to remove.
     * @return ChildCurrency The current object (for fluent API support)
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
            $cart->setCurrency(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Carts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCart[] List of ChildCart objects
     */
    public function getCartsJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getCarts($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Carts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
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
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related Carts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
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
     * Clears out the collProductPrices collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductPrices()
     */
    public function clearProductPrices()
    {
        $this->collProductPrices = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductPrices collection loaded partially.
     */
    public function resetPartialProductPrices($v = true)
    {
        $this->collProductPricesPartial = $v;
    }

    /**
     * Initializes the collProductPrices collection.
     *
     * By default this just sets the collProductPrices collection to an empty array (like clearcollProductPrices());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductPrices($overrideExisting = true)
    {
        if (null !== $this->collProductPrices && !$overrideExisting) {
            return;
        }
        $this->collProductPrices = new ObjectCollection();
        $this->collProductPrices->setModel('\Thelia\Model\ProductPrice');
    }

    /**
     * Gets an array of ChildProductPrice objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCurrency is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductPrice[] List of ChildProductPrice objects
     * @throws PropelException
     */
    public function getProductPrices($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductPricesPartial && !$this->isNew();
        if (null === $this->collProductPrices || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductPrices) {
                // return empty collection
                $this->initProductPrices();
            } else {
                $collProductPrices = ChildProductPriceQuery::create(null, $criteria)
                    ->filterByCurrency($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductPricesPartial && count($collProductPrices)) {
                        $this->initProductPrices(false);

                        foreach ($collProductPrices as $obj) {
                            if (false == $this->collProductPrices->contains($obj)) {
                                $this->collProductPrices->append($obj);
                            }
                        }

                        $this->collProductPricesPartial = true;
                    }

                    reset($collProductPrices);

                    return $collProductPrices;
                }

                if ($partial && $this->collProductPrices) {
                    foreach ($this->collProductPrices as $obj) {
                        if ($obj->isNew()) {
                            $collProductPrices[] = $obj;
                        }
                    }
                }

                $this->collProductPrices = $collProductPrices;
                $this->collProductPricesPartial = false;
            }
        }

        return $this->collProductPrices;
    }

    /**
     * Sets a collection of ProductPrice objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productPrices A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCurrency The current object (for fluent API support)
     */
    public function setProductPrices(Collection $productPrices, ConnectionInterface $con = null)
    {
        $productPricesToDelete = $this->getProductPrices(new Criteria(), $con)->diff($productPrices);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->productPricesScheduledForDeletion = clone $productPricesToDelete;

        foreach ($productPricesToDelete as $productPriceRemoved) {
            $productPriceRemoved->setCurrency(null);
        }

        $this->collProductPrices = null;
        foreach ($productPrices as $productPrice) {
            $this->addProductPrice($productPrice);
        }

        $this->collProductPrices = $productPrices;
        $this->collProductPricesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductPrice objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductPrice objects.
     * @throws PropelException
     */
    public function countProductPrices(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductPricesPartial && !$this->isNew();
        if (null === $this->collProductPrices || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductPrices) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductPrices());
            }

            $query = ChildProductPriceQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCurrency($this)
                ->count($con);
        }

        return count($this->collProductPrices);
    }

    /**
     * Method called to associate a ChildProductPrice object to this object
     * through the ChildProductPrice foreign key attribute.
     *
     * @param    ChildProductPrice $l ChildProductPrice
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function addProductPrice(ChildProductPrice $l)
    {
        if ($this->collProductPrices === null) {
            $this->initProductPrices();
            $this->collProductPricesPartial = true;
        }

        if (!in_array($l, $this->collProductPrices->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductPrice($l);
        }

        return $this;
    }

    /**
     * @param ProductPrice $productPrice The productPrice object to add.
     */
    protected function doAddProductPrice($productPrice)
    {
        $this->collProductPrices[]= $productPrice;
        $productPrice->setCurrency($this);
    }

    /**
     * @param  ProductPrice $productPrice The productPrice object to remove.
     * @return ChildCurrency The current object (for fluent API support)
     */
    public function removeProductPrice($productPrice)
    {
        if ($this->getProductPrices()->contains($productPrice)) {
            $this->collProductPrices->remove($this->collProductPrices->search($productPrice));
            if (null === $this->productPricesScheduledForDeletion) {
                $this->productPricesScheduledForDeletion = clone $this->collProductPrices;
                $this->productPricesScheduledForDeletion->clear();
            }
            $this->productPricesScheduledForDeletion[]= clone $productPrice;
            $productPrice->setCurrency(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related ProductPrices from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProductPrice[] List of ChildProductPrice objects
     */
    public function getProductPricesJoinProductSaleElements($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProductPriceQuery::create(null, $criteria);
        $query->joinWith('ProductSaleElements', $joinBehavior);

        return $this->getProductPrices($query, $con);
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
     * If this ChildCurrency is new, it will return
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
                    ->filterByCurrency($this)
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
     * @return   ChildCurrency The current object (for fluent API support)
     */
    public function setSaleOffsetCurrencies(Collection $saleOffsetCurrencies, ConnectionInterface $con = null)
    {
        $saleOffsetCurrenciesToDelete = $this->getSaleOffsetCurrencies(new Criteria(), $con)->diff($saleOffsetCurrencies);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->saleOffsetCurrenciesScheduledForDeletion = clone $saleOffsetCurrenciesToDelete;

        foreach ($saleOffsetCurrenciesToDelete as $saleOffsetCurrencyRemoved) {
            $saleOffsetCurrencyRemoved->setCurrency(null);
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
                ->filterByCurrency($this)
                ->count($con);
        }

        return count($this->collSaleOffsetCurrencies);
    }

    /**
     * Method called to associate a ChildSaleOffsetCurrency object to this object
     * through the ChildSaleOffsetCurrency foreign key attribute.
     *
     * @param    ChildSaleOffsetCurrency $l ChildSaleOffsetCurrency
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
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
        $saleOffsetCurrency->setCurrency($this);
    }

    /**
     * @param  SaleOffsetCurrency $saleOffsetCurrency The saleOffsetCurrency object to remove.
     * @return ChildCurrency The current object (for fluent API support)
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
            $saleOffsetCurrency->setCurrency(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Currency is new, it will return
     * an empty collection; or if this Currency has previously
     * been saved, it will retrieve related SaleOffsetCurrencies from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Currency.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildSaleOffsetCurrency[] List of ChildSaleOffsetCurrency objects
     */
    public function getSaleOffsetCurrenciesJoinSale($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildSaleOffsetCurrencyQuery::create(null, $criteria);
        $query->joinWith('Sale', $joinBehavior);

        return $this->getSaleOffsetCurrencies($query, $con);
    }

    /**
     * Clears out the collCurrencyI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCurrencyI18ns()
     */
    public function clearCurrencyI18ns()
    {
        $this->collCurrencyI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCurrencyI18ns collection loaded partially.
     */
    public function resetPartialCurrencyI18ns($v = true)
    {
        $this->collCurrencyI18nsPartial = $v;
    }

    /**
     * Initializes the collCurrencyI18ns collection.
     *
     * By default this just sets the collCurrencyI18ns collection to an empty array (like clearcollCurrencyI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCurrencyI18ns($overrideExisting = true)
    {
        if (null !== $this->collCurrencyI18ns && !$overrideExisting) {
            return;
        }
        $this->collCurrencyI18ns = new ObjectCollection();
        $this->collCurrencyI18ns->setModel('\Thelia\Model\CurrencyI18n');
    }

    /**
     * Gets an array of ChildCurrencyI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCurrency is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCurrencyI18n[] List of ChildCurrencyI18n objects
     * @throws PropelException
     */
    public function getCurrencyI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCurrencyI18nsPartial && !$this->isNew();
        if (null === $this->collCurrencyI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCurrencyI18ns) {
                // return empty collection
                $this->initCurrencyI18ns();
            } else {
                $collCurrencyI18ns = ChildCurrencyI18nQuery::create(null, $criteria)
                    ->filterByCurrency($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCurrencyI18nsPartial && count($collCurrencyI18ns)) {
                        $this->initCurrencyI18ns(false);

                        foreach ($collCurrencyI18ns as $obj) {
                            if (false == $this->collCurrencyI18ns->contains($obj)) {
                                $this->collCurrencyI18ns->append($obj);
                            }
                        }

                        $this->collCurrencyI18nsPartial = true;
                    }

                    reset($collCurrencyI18ns);

                    return $collCurrencyI18ns;
                }

                if ($partial && $this->collCurrencyI18ns) {
                    foreach ($this->collCurrencyI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collCurrencyI18ns[] = $obj;
                        }
                    }
                }

                $this->collCurrencyI18ns = $collCurrencyI18ns;
                $this->collCurrencyI18nsPartial = false;
            }
        }

        return $this->collCurrencyI18ns;
    }

    /**
     * Sets a collection of CurrencyI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $currencyI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCurrency The current object (for fluent API support)
     */
    public function setCurrencyI18ns(Collection $currencyI18ns, ConnectionInterface $con = null)
    {
        $currencyI18nsToDelete = $this->getCurrencyI18ns(new Criteria(), $con)->diff($currencyI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->currencyI18nsScheduledForDeletion = clone $currencyI18nsToDelete;

        foreach ($currencyI18nsToDelete as $currencyI18nRemoved) {
            $currencyI18nRemoved->setCurrency(null);
        }

        $this->collCurrencyI18ns = null;
        foreach ($currencyI18ns as $currencyI18n) {
            $this->addCurrencyI18n($currencyI18n);
        }

        $this->collCurrencyI18ns = $currencyI18ns;
        $this->collCurrencyI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CurrencyI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CurrencyI18n objects.
     * @throws PropelException
     */
    public function countCurrencyI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCurrencyI18nsPartial && !$this->isNew();
        if (null === $this->collCurrencyI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCurrencyI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCurrencyI18ns());
            }

            $query = ChildCurrencyI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCurrency($this)
                ->count($con);
        }

        return count($this->collCurrencyI18ns);
    }

    /**
     * Method called to associate a ChildCurrencyI18n object to this object
     * through the ChildCurrencyI18n foreign key attribute.
     *
     * @param    ChildCurrencyI18n $l ChildCurrencyI18n
     * @return   \Thelia\Model\Currency The current object (for fluent API support)
     */
    public function addCurrencyI18n(ChildCurrencyI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collCurrencyI18ns === null) {
            $this->initCurrencyI18ns();
            $this->collCurrencyI18nsPartial = true;
        }

        if (!in_array($l, $this->collCurrencyI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCurrencyI18n($l);
        }

        return $this;
    }

    /**
     * @param CurrencyI18n $currencyI18n The currencyI18n object to add.
     */
    protected function doAddCurrencyI18n($currencyI18n)
    {
        $this->collCurrencyI18ns[]= $currencyI18n;
        $currencyI18n->setCurrency($this);
    }

    /**
     * @param  CurrencyI18n $currencyI18n The currencyI18n object to remove.
     * @return ChildCurrency The current object (for fluent API support)
     */
    public function removeCurrencyI18n($currencyI18n)
    {
        if ($this->getCurrencyI18ns()->contains($currencyI18n)) {
            $this->collCurrencyI18ns->remove($this->collCurrencyI18ns->search($currencyI18n));
            if (null === $this->currencyI18nsScheduledForDeletion) {
                $this->currencyI18nsScheduledForDeletion = clone $this->collCurrencyI18ns;
                $this->currencyI18nsScheduledForDeletion->clear();
            }
            $this->currencyI18nsScheduledForDeletion[]= clone $currencyI18n;
            $currencyI18n->setCurrency(null);
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
        $this->symbol = null;
        $this->format = null;
        $this->rate = null;
        $this->position = null;
        $this->by_default = null;
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
            if ($this->collProductPrices) {
                foreach ($this->collProductPrices as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collSaleOffsetCurrencies) {
                foreach ($this->collSaleOffsetCurrencies as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCurrencyI18ns) {
                foreach ($this->collCurrencyI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collOrders = null;
        $this->collCarts = null;
        $this->collProductPrices = null;
        $this->collSaleOffsetCurrencies = null;
        $this->collCurrencyI18ns = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CurrencyTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildCurrency The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[CurrencyTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildCurrency The current object (for fluent API support)
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
     * @return ChildCurrencyI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collCurrencyI18ns) {
                foreach ($this->collCurrencyI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildCurrencyI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildCurrencyI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addCurrencyI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildCurrency The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildCurrencyI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collCurrencyI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collCurrencyI18ns[$key]);
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
     * @return ChildCurrencyI18n */
    public function getCurrentTranslation(ConnectionInterface $con = null)
    {
        return $this->getTranslation($this->getLocale(), $con);
    }


        /**
         * Get the [name] column value.
         *
         * @return   string
         */
        public function getName()
        {
        return $this->getCurrentTranslation()->getName();
    }


        /**
         * Set the value of [name] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CurrencyI18n The current object (for fluent API support)
         */
        public function setName($v)
        {    $this->getCurrentTranslation()->setName($v);

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
