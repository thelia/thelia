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
use Thelia\Model\Customer as ChildCustomer;
use Thelia\Model\CustomerQuery as ChildCustomerQuery;
use Thelia\Model\CustomerTitle as ChildCustomerTitle;
use Thelia\Model\CustomerTitleI18n as ChildCustomerTitleI18n;
use Thelia\Model\CustomerTitleI18nQuery as ChildCustomerTitleI18nQuery;
use Thelia\Model\CustomerTitleQuery as ChildCustomerTitleQuery;
use Thelia\Model\OrderAddress as ChildOrderAddress;
use Thelia\Model\OrderAddressQuery as ChildOrderAddressQuery;
use Thelia\Model\Map\CustomerTitleTableMap;

abstract class CustomerTitle implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\CustomerTitleTableMap';


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
     * The value for the by_default field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $by_default;

    /**
     * The value for the position field.
     * @var        string
     */
    protected $position;

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
     * @var        ObjectCollection|ChildCustomer[] Collection to store aggregation of ChildCustomer objects.
     */
    protected $collCustomers;
    protected $collCustomersPartial;

    /**
     * @var        ObjectCollection|ChildAddress[] Collection to store aggregation of ChildAddress objects.
     */
    protected $collAddresses;
    protected $collAddressesPartial;

    /**
     * @var        ObjectCollection|ChildOrderAddress[] Collection to store aggregation of ChildOrderAddress objects.
     */
    protected $collOrderAddresses;
    protected $collOrderAddressesPartial;

    /**
     * @var        ObjectCollection|ChildCustomerTitleI18n[] Collection to store aggregation of ChildCustomerTitleI18n objects.
     */
    protected $collCustomerTitleI18ns;
    protected $collCustomerTitleI18nsPartial;

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
     * @var        array[ChildCustomerTitleI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $customersScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $addressesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderAddressesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $customerTitleI18nsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->by_default = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\CustomerTitle object.
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
     * Compares this with another <code>CustomerTitle</code> instance.  If
     * <code>obj</code> is an instance of <code>CustomerTitle</code>, delegates to
     * <code>equals(CustomerTitle)</code>.  Otherwise, returns <code>false</code>.
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
     * @return CustomerTitle The current object, for fluid interface
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
     * @return CustomerTitle The current object, for fluid interface
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
     * Get the [by_default] column value.
     *
     * @return   int
     */
    public function getByDefault()
    {

        return $this->by_default;
    }

    /**
     * Get the [position] column value.
     *
     * @return   string
     */
    public function getPosition()
    {

        return $this->position;
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
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[CustomerTitleTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [by_default] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
     */
    public function setByDefault($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->by_default !== $v) {
            $this->by_default = $v;
            $this->modifiedColumns[CustomerTitleTableMap::BY_DEFAULT] = true;
        }


        return $this;
    } // setByDefault()

    /**
     * Set the value of [position] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[CustomerTitleTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[CustomerTitleTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[CustomerTitleTableMap::UPDATED_AT] = true;
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
            if ($this->by_default !== 0) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : CustomerTitleTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : CustomerTitleTableMap::translateFieldName('ByDefault', TableMap::TYPE_PHPNAME, $indexType)];
            $this->by_default = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : CustomerTitleTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : CustomerTitleTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : CustomerTitleTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 5; // 5 = CustomerTitleTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\CustomerTitle object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(CustomerTitleTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildCustomerTitleQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collCustomers = null;

            $this->collAddresses = null;

            $this->collOrderAddresses = null;

            $this->collCustomerTitleI18ns = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see CustomerTitle::setDeleted()
     * @see CustomerTitle::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerTitleTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildCustomerTitleQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerTitleTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(CustomerTitleTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(CustomerTitleTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(CustomerTitleTableMap::UPDATED_AT)) {
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
                CustomerTitleTableMap::addInstanceToPool($this);
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

            if ($this->customersScheduledForDeletion !== null) {
                if (!$this->customersScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CustomerQuery::create()
                        ->filterByPrimaryKeys($this->customersScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->customersScheduledForDeletion = null;
                }
            }

                if ($this->collCustomers !== null) {
            foreach ($this->collCustomers as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
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

            if ($this->orderAddressesScheduledForDeletion !== null) {
                if (!$this->orderAddressesScheduledForDeletion->isEmpty()) {
                    foreach ($this->orderAddressesScheduledForDeletion as $orderAddress) {
                        // need to save related object because we set the relation to null
                        $orderAddress->save($con);
                    }
                    $this->orderAddressesScheduledForDeletion = null;
                }
            }

                if ($this->collOrderAddresses !== null) {
            foreach ($this->collOrderAddresses as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->customerTitleI18nsScheduledForDeletion !== null) {
                if (!$this->customerTitleI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CustomerTitleI18nQuery::create()
                        ->filterByPrimaryKeys($this->customerTitleI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->customerTitleI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collCustomerTitleI18ns !== null) {
            foreach ($this->collCustomerTitleI18ns as $referrerFK) {
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

        $this->modifiedColumns[CustomerTitleTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . CustomerTitleTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CustomerTitleTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CustomerTitleTableMap::BY_DEFAULT)) {
            $modifiedColumns[':p' . $index++]  = '`BY_DEFAULT`';
        }
        if ($this->isColumnModified(CustomerTitleTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(CustomerTitleTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CustomerTitleTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `customer_title` (%s) VALUES (%s)',
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
                    case '`BY_DEFAULT`':
                        $stmt->bindValue($identifier, $this->by_default, PDO::PARAM_INT);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_STR);
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
        $pos = CustomerTitleTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getByDefault();
                break;
            case 2:
                return $this->getPosition();
                break;
            case 3:
                return $this->getCreatedAt();
                break;
            case 4:
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
        if (isset($alreadyDumpedObjects['CustomerTitle'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['CustomerTitle'][$this->getPrimaryKey()] = true;
        $keys = CustomerTitleTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getByDefault(),
            $keys[2] => $this->getPosition(),
            $keys[3] => $this->getCreatedAt(),
            $keys[4] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collCustomers) {
                $result['Customers'] = $this->collCustomers->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAddresses) {
                $result['Addresses'] = $this->collAddresses->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrderAddresses) {
                $result['OrderAddresses'] = $this->collOrderAddresses->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCustomerTitleI18ns) {
                $result['CustomerTitleI18ns'] = $this->collCustomerTitleI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = CustomerTitleTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setByDefault($value);
                break;
            case 2:
                $this->setPosition($value);
                break;
            case 3:
                $this->setCreatedAt($value);
                break;
            case 4:
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
        $keys = CustomerTitleTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setByDefault($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setPosition($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setCreatedAt($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setUpdatedAt($arr[$keys[4]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(CustomerTitleTableMap::DATABASE_NAME);

        if ($this->isColumnModified(CustomerTitleTableMap::ID)) $criteria->add(CustomerTitleTableMap::ID, $this->id);
        if ($this->isColumnModified(CustomerTitleTableMap::BY_DEFAULT)) $criteria->add(CustomerTitleTableMap::BY_DEFAULT, $this->by_default);
        if ($this->isColumnModified(CustomerTitleTableMap::POSITION)) $criteria->add(CustomerTitleTableMap::POSITION, $this->position);
        if ($this->isColumnModified(CustomerTitleTableMap::CREATED_AT)) $criteria->add(CustomerTitleTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CustomerTitleTableMap::UPDATED_AT)) $criteria->add(CustomerTitleTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(CustomerTitleTableMap::DATABASE_NAME);
        $criteria->add(CustomerTitleTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\CustomerTitle (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setByDefault($this->getByDefault());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getCustomers() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCustomer($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAddresses() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAddress($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrderAddresses() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderAddress($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCustomerTitleI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCustomerTitleI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\CustomerTitle Clone of current object.
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
        if ('Customer' == $relationName) {
            return $this->initCustomers();
        }
        if ('Address' == $relationName) {
            return $this->initAddresses();
        }
        if ('OrderAddress' == $relationName) {
            return $this->initOrderAddresses();
        }
        if ('CustomerTitleI18n' == $relationName) {
            return $this->initCustomerTitleI18ns();
        }
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
    }

    /**
     * Reset is the collCustomers collection loaded partially.
     */
    public function resetPartialCustomers($v = true)
    {
        $this->collCustomersPartial = $v;
    }

    /**
     * Initializes the collCustomers collection.
     *
     * By default this just sets the collCustomers collection to an empty array (like clearcollCustomers());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCustomers($overrideExisting = true)
    {
        if (null !== $this->collCustomers && !$overrideExisting) {
            return;
        }
        $this->collCustomers = new ObjectCollection();
        $this->collCustomers->setModel('\Thelia\Model\Customer');
    }

    /**
     * Gets an array of ChildCustomer objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomerTitle is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCustomer[] List of ChildCustomer objects
     * @throws PropelException
     */
    public function getCustomers($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCustomersPartial && !$this->isNew();
        if (null === $this->collCustomers || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCustomers) {
                // return empty collection
                $this->initCustomers();
            } else {
                $collCustomers = ChildCustomerQuery::create(null, $criteria)
                    ->filterByCustomerTitle($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCustomersPartial && count($collCustomers)) {
                        $this->initCustomers(false);

                        foreach ($collCustomers as $obj) {
                            if (false == $this->collCustomers->contains($obj)) {
                                $this->collCustomers->append($obj);
                            }
                        }

                        $this->collCustomersPartial = true;
                    }

                    reset($collCustomers);

                    return $collCustomers;
                }

                if ($partial && $this->collCustomers) {
                    foreach ($this->collCustomers as $obj) {
                        if ($obj->isNew()) {
                            $collCustomers[] = $obj;
                        }
                    }
                }

                $this->collCustomers = $collCustomers;
                $this->collCustomersPartial = false;
            }
        }

        return $this->collCustomers;
    }

    /**
     * Sets a collection of Customer objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $customers A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCustomerTitle The current object (for fluent API support)
     */
    public function setCustomers(Collection $customers, ConnectionInterface $con = null)
    {
        $customersToDelete = $this->getCustomers(new Criteria(), $con)->diff($customers);


        $this->customersScheduledForDeletion = $customersToDelete;

        foreach ($customersToDelete as $customerRemoved) {
            $customerRemoved->setCustomerTitle(null);
        }

        $this->collCustomers = null;
        foreach ($customers as $customer) {
            $this->addCustomer($customer);
        }

        $this->collCustomers = $customers;
        $this->collCustomersPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Customer objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Customer objects.
     * @throws PropelException
     */
    public function countCustomers(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCustomersPartial && !$this->isNew();
        if (null === $this->collCustomers || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCustomers) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCustomers());
            }

            $query = ChildCustomerQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCustomerTitle($this)
                ->count($con);
        }

        return count($this->collCustomers);
    }

    /**
     * Method called to associate a ChildCustomer object to this object
     * through the ChildCustomer foreign key attribute.
     *
     * @param    ChildCustomer $l ChildCustomer
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
     */
    public function addCustomer(ChildCustomer $l)
    {
        if ($this->collCustomers === null) {
            $this->initCustomers();
            $this->collCustomersPartial = true;
        }

        if (!in_array($l, $this->collCustomers->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCustomer($l);
        }

        return $this;
    }

    /**
     * @param Customer $customer The customer object to add.
     */
    protected function doAddCustomer($customer)
    {
        $this->collCustomers[]= $customer;
        $customer->setCustomerTitle($this);
    }

    /**
     * @param  Customer $customer The customer object to remove.
     * @return ChildCustomerTitle The current object (for fluent API support)
     */
    public function removeCustomer($customer)
    {
        if ($this->getCustomers()->contains($customer)) {
            $this->collCustomers->remove($this->collCustomers->search($customer));
            if (null === $this->customersScheduledForDeletion) {
                $this->customersScheduledForDeletion = clone $this->collCustomers;
                $this->customersScheduledForDeletion->clear();
            }
            $this->customersScheduledForDeletion[]= clone $customer;
            $customer->setCustomerTitle(null);
        }

        return $this;
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
     * If this ChildCustomerTitle is new, it will return
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
                    ->filterByCustomerTitle($this)
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
     * @return   ChildCustomerTitle The current object (for fluent API support)
     */
    public function setAddresses(Collection $addresses, ConnectionInterface $con = null)
    {
        $addressesToDelete = $this->getAddresses(new Criteria(), $con)->diff($addresses);


        $this->addressesScheduledForDeletion = $addressesToDelete;

        foreach ($addressesToDelete as $addressRemoved) {
            $addressRemoved->setCustomerTitle(null);
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
                ->filterByCustomerTitle($this)
                ->count($con);
        }

        return count($this->collAddresses);
    }

    /**
     * Method called to associate a ChildAddress object to this object
     * through the ChildAddress foreign key attribute.
     *
     * @param    ChildAddress $l ChildAddress
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
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
        $address->setCustomerTitle($this);
    }

    /**
     * @param  Address $address The address object to remove.
     * @return ChildCustomerTitle The current object (for fluent API support)
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
            $address->setCustomerTitle(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this CustomerTitle is new, it will return
     * an empty collection; or if this CustomerTitle has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in CustomerTitle.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAddress[] List of ChildAddress objects
     */
    public function getAddressesJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAddressQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getAddresses($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this CustomerTitle is new, it will return
     * an empty collection; or if this CustomerTitle has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in CustomerTitle.
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
     * Otherwise if this CustomerTitle is new, it will return
     * an empty collection; or if this CustomerTitle has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in CustomerTitle.
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
     * Clears out the collOrderAddresses collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderAddresses()
     */
    public function clearOrderAddresses()
    {
        $this->collOrderAddresses = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderAddresses collection loaded partially.
     */
    public function resetPartialOrderAddresses($v = true)
    {
        $this->collOrderAddressesPartial = $v;
    }

    /**
     * Initializes the collOrderAddresses collection.
     *
     * By default this just sets the collOrderAddresses collection to an empty array (like clearcollOrderAddresses());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderAddresses($overrideExisting = true)
    {
        if (null !== $this->collOrderAddresses && !$overrideExisting) {
            return;
        }
        $this->collOrderAddresses = new ObjectCollection();
        $this->collOrderAddresses->setModel('\Thelia\Model\OrderAddress');
    }

    /**
     * Gets an array of ChildOrderAddress objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomerTitle is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderAddress[] List of ChildOrderAddress objects
     * @throws PropelException
     */
    public function getOrderAddresses($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderAddressesPartial && !$this->isNew();
        if (null === $this->collOrderAddresses || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderAddresses) {
                // return empty collection
                $this->initOrderAddresses();
            } else {
                $collOrderAddresses = ChildOrderAddressQuery::create(null, $criteria)
                    ->filterByCustomerTitle($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderAddressesPartial && count($collOrderAddresses)) {
                        $this->initOrderAddresses(false);

                        foreach ($collOrderAddresses as $obj) {
                            if (false == $this->collOrderAddresses->contains($obj)) {
                                $this->collOrderAddresses->append($obj);
                            }
                        }

                        $this->collOrderAddressesPartial = true;
                    }

                    reset($collOrderAddresses);

                    return $collOrderAddresses;
                }

                if ($partial && $this->collOrderAddresses) {
                    foreach ($this->collOrderAddresses as $obj) {
                        if ($obj->isNew()) {
                            $collOrderAddresses[] = $obj;
                        }
                    }
                }

                $this->collOrderAddresses = $collOrderAddresses;
                $this->collOrderAddressesPartial = false;
            }
        }

        return $this->collOrderAddresses;
    }

    /**
     * Sets a collection of OrderAddress objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderAddresses A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCustomerTitle The current object (for fluent API support)
     */
    public function setOrderAddresses(Collection $orderAddresses, ConnectionInterface $con = null)
    {
        $orderAddressesToDelete = $this->getOrderAddresses(new Criteria(), $con)->diff($orderAddresses);


        $this->orderAddressesScheduledForDeletion = $orderAddressesToDelete;

        foreach ($orderAddressesToDelete as $orderAddressRemoved) {
            $orderAddressRemoved->setCustomerTitle(null);
        }

        $this->collOrderAddresses = null;
        foreach ($orderAddresses as $orderAddress) {
            $this->addOrderAddress($orderAddress);
        }

        $this->collOrderAddresses = $orderAddresses;
        $this->collOrderAddressesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderAddress objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderAddress objects.
     * @throws PropelException
     */
    public function countOrderAddresses(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderAddressesPartial && !$this->isNew();
        if (null === $this->collOrderAddresses || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderAddresses) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderAddresses());
            }

            $query = ChildOrderAddressQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCustomerTitle($this)
                ->count($con);
        }

        return count($this->collOrderAddresses);
    }

    /**
     * Method called to associate a ChildOrderAddress object to this object
     * through the ChildOrderAddress foreign key attribute.
     *
     * @param    ChildOrderAddress $l ChildOrderAddress
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
     */
    public function addOrderAddress(ChildOrderAddress $l)
    {
        if ($this->collOrderAddresses === null) {
            $this->initOrderAddresses();
            $this->collOrderAddressesPartial = true;
        }

        if (!in_array($l, $this->collOrderAddresses->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderAddress($l);
        }

        return $this;
    }

    /**
     * @param OrderAddress $orderAddress The orderAddress object to add.
     */
    protected function doAddOrderAddress($orderAddress)
    {
        $this->collOrderAddresses[]= $orderAddress;
        $orderAddress->setCustomerTitle($this);
    }

    /**
     * @param  OrderAddress $orderAddress The orderAddress object to remove.
     * @return ChildCustomerTitle The current object (for fluent API support)
     */
    public function removeOrderAddress($orderAddress)
    {
        if ($this->getOrderAddresses()->contains($orderAddress)) {
            $this->collOrderAddresses->remove($this->collOrderAddresses->search($orderAddress));
            if (null === $this->orderAddressesScheduledForDeletion) {
                $this->orderAddressesScheduledForDeletion = clone $this->collOrderAddresses;
                $this->orderAddressesScheduledForDeletion->clear();
            }
            $this->orderAddressesScheduledForDeletion[]= $orderAddress;
            $orderAddress->setCustomerTitle(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this CustomerTitle is new, it will return
     * an empty collection; or if this CustomerTitle has previously
     * been saved, it will retrieve related OrderAddresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in CustomerTitle.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrderAddress[] List of ChildOrderAddress objects
     */
    public function getOrderAddressesJoinCountry($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderAddressQuery::create(null, $criteria);
        $query->joinWith('Country', $joinBehavior);

        return $this->getOrderAddresses($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this CustomerTitle is new, it will return
     * an empty collection; or if this CustomerTitle has previously
     * been saved, it will retrieve related OrderAddresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in CustomerTitle.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrderAddress[] List of ChildOrderAddress objects
     */
    public function getOrderAddressesJoinState($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderAddressQuery::create(null, $criteria);
        $query->joinWith('State', $joinBehavior);

        return $this->getOrderAddresses($query, $con);
    }

    /**
     * Clears out the collCustomerTitleI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCustomerTitleI18ns()
     */
    public function clearCustomerTitleI18ns()
    {
        $this->collCustomerTitleI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCustomerTitleI18ns collection loaded partially.
     */
    public function resetPartialCustomerTitleI18ns($v = true)
    {
        $this->collCustomerTitleI18nsPartial = $v;
    }

    /**
     * Initializes the collCustomerTitleI18ns collection.
     *
     * By default this just sets the collCustomerTitleI18ns collection to an empty array (like clearcollCustomerTitleI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCustomerTitleI18ns($overrideExisting = true)
    {
        if (null !== $this->collCustomerTitleI18ns && !$overrideExisting) {
            return;
        }
        $this->collCustomerTitleI18ns = new ObjectCollection();
        $this->collCustomerTitleI18ns->setModel('\Thelia\Model\CustomerTitleI18n');
    }

    /**
     * Gets an array of ChildCustomerTitleI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCustomerTitle is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCustomerTitleI18n[] List of ChildCustomerTitleI18n objects
     * @throws PropelException
     */
    public function getCustomerTitleI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCustomerTitleI18nsPartial && !$this->isNew();
        if (null === $this->collCustomerTitleI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCustomerTitleI18ns) {
                // return empty collection
                $this->initCustomerTitleI18ns();
            } else {
                $collCustomerTitleI18ns = ChildCustomerTitleI18nQuery::create(null, $criteria)
                    ->filterByCustomerTitle($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCustomerTitleI18nsPartial && count($collCustomerTitleI18ns)) {
                        $this->initCustomerTitleI18ns(false);

                        foreach ($collCustomerTitleI18ns as $obj) {
                            if (false == $this->collCustomerTitleI18ns->contains($obj)) {
                                $this->collCustomerTitleI18ns->append($obj);
                            }
                        }

                        $this->collCustomerTitleI18nsPartial = true;
                    }

                    reset($collCustomerTitleI18ns);

                    return $collCustomerTitleI18ns;
                }

                if ($partial && $this->collCustomerTitleI18ns) {
                    foreach ($this->collCustomerTitleI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collCustomerTitleI18ns[] = $obj;
                        }
                    }
                }

                $this->collCustomerTitleI18ns = $collCustomerTitleI18ns;
                $this->collCustomerTitleI18nsPartial = false;
            }
        }

        return $this->collCustomerTitleI18ns;
    }

    /**
     * Sets a collection of CustomerTitleI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $customerTitleI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCustomerTitle The current object (for fluent API support)
     */
    public function setCustomerTitleI18ns(Collection $customerTitleI18ns, ConnectionInterface $con = null)
    {
        $customerTitleI18nsToDelete = $this->getCustomerTitleI18ns(new Criteria(), $con)->diff($customerTitleI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->customerTitleI18nsScheduledForDeletion = clone $customerTitleI18nsToDelete;

        foreach ($customerTitleI18nsToDelete as $customerTitleI18nRemoved) {
            $customerTitleI18nRemoved->setCustomerTitle(null);
        }

        $this->collCustomerTitleI18ns = null;
        foreach ($customerTitleI18ns as $customerTitleI18n) {
            $this->addCustomerTitleI18n($customerTitleI18n);
        }

        $this->collCustomerTitleI18ns = $customerTitleI18ns;
        $this->collCustomerTitleI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CustomerTitleI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CustomerTitleI18n objects.
     * @throws PropelException
     */
    public function countCustomerTitleI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCustomerTitleI18nsPartial && !$this->isNew();
        if (null === $this->collCustomerTitleI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCustomerTitleI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCustomerTitleI18ns());
            }

            $query = ChildCustomerTitleI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCustomerTitle($this)
                ->count($con);
        }

        return count($this->collCustomerTitleI18ns);
    }

    /**
     * Method called to associate a ChildCustomerTitleI18n object to this object
     * through the ChildCustomerTitleI18n foreign key attribute.
     *
     * @param    ChildCustomerTitleI18n $l ChildCustomerTitleI18n
     * @return   \Thelia\Model\CustomerTitle The current object (for fluent API support)
     */
    public function addCustomerTitleI18n(ChildCustomerTitleI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collCustomerTitleI18ns === null) {
            $this->initCustomerTitleI18ns();
            $this->collCustomerTitleI18nsPartial = true;
        }

        if (!in_array($l, $this->collCustomerTitleI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCustomerTitleI18n($l);
        }

        return $this;
    }

    /**
     * @param CustomerTitleI18n $customerTitleI18n The customerTitleI18n object to add.
     */
    protected function doAddCustomerTitleI18n($customerTitleI18n)
    {
        $this->collCustomerTitleI18ns[]= $customerTitleI18n;
        $customerTitleI18n->setCustomerTitle($this);
    }

    /**
     * @param  CustomerTitleI18n $customerTitleI18n The customerTitleI18n object to remove.
     * @return ChildCustomerTitle The current object (for fluent API support)
     */
    public function removeCustomerTitleI18n($customerTitleI18n)
    {
        if ($this->getCustomerTitleI18ns()->contains($customerTitleI18n)) {
            $this->collCustomerTitleI18ns->remove($this->collCustomerTitleI18ns->search($customerTitleI18n));
            if (null === $this->customerTitleI18nsScheduledForDeletion) {
                $this->customerTitleI18nsScheduledForDeletion = clone $this->collCustomerTitleI18ns;
                $this->customerTitleI18nsScheduledForDeletion->clear();
            }
            $this->customerTitleI18nsScheduledForDeletion[]= clone $customerTitleI18n;
            $customerTitleI18n->setCustomerTitle(null);
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->by_default = null;
        $this->position = null;
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
            if ($this->collCustomers) {
                foreach ($this->collCustomers as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAddresses) {
                foreach ($this->collAddresses as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrderAddresses) {
                foreach ($this->collOrderAddresses as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCustomerTitleI18ns) {
                foreach ($this->collCustomerTitleI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collCustomers = null;
        $this->collAddresses = null;
        $this->collOrderAddresses = null;
        $this->collCustomerTitleI18ns = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CustomerTitleTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildCustomerTitle The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[CustomerTitleTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildCustomerTitle The current object (for fluent API support)
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
     * @return ChildCustomerTitleI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collCustomerTitleI18ns) {
                foreach ($this->collCustomerTitleI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildCustomerTitleI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildCustomerTitleI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addCustomerTitleI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildCustomerTitle The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildCustomerTitleI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collCustomerTitleI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collCustomerTitleI18ns[$key]);
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
     * @return ChildCustomerTitleI18n */
    public function getCurrentTranslation(ConnectionInterface $con = null)
    {
        return $this->getTranslation($this->getLocale(), $con);
    }


        /**
         * Get the [short] column value.
         *
         * @return   string
         */
        public function getShort()
        {
        return $this->getCurrentTranslation()->getShort();
    }


        /**
         * Set the value of [short] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CustomerTitleI18n The current object (for fluent API support)
         */
        public function setShort($v)
        {    $this->getCurrentTranslation()->setShort($v);

        return $this;
    }


        /**
         * Get the [long] column value.
         *
         * @return   string
         */
        public function getLong()
        {
        return $this->getCurrentTranslation()->getLong();
    }


        /**
         * Set the value of [long] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CustomerTitleI18n The current object (for fluent API support)
         */
        public function setLong($v)
        {    $this->getCurrentTranslation()->setLong($v);

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
