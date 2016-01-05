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
use Thelia\Model\Hook as ChildHook;
use Thelia\Model\HookI18n as ChildHookI18n;
use Thelia\Model\HookI18nQuery as ChildHookI18nQuery;
use Thelia\Model\HookQuery as ChildHookQuery;
use Thelia\Model\IgnoredModuleHook as ChildIgnoredModuleHook;
use Thelia\Model\IgnoredModuleHookQuery as ChildIgnoredModuleHookQuery;
use Thelia\Model\Module as ChildModule;
use Thelia\Model\ModuleHook as ChildModuleHook;
use Thelia\Model\ModuleHookQuery as ChildModuleHookQuery;
use Thelia\Model\ModuleQuery as ChildModuleQuery;
use Thelia\Model\Map\HookTableMap;

abstract class Hook implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\HookTableMap';


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
     * @var        int
     */
    protected $type;

    /**
     * The value for the by_module field.
     * @var        boolean
     */
    protected $by_module;

    /**
     * The value for the native field.
     * @var        boolean
     */
    protected $native;

    /**
     * The value for the activate field.
     * @var        boolean
     */
    protected $activate;

    /**
     * The value for the block field.
     * @var        boolean
     */
    protected $block;

    /**
     * The value for the position field.
     * @var        int
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
     * @var        ObjectCollection|ChildModuleHook[] Collection to store aggregation of ChildModuleHook objects.
     */
    protected $collModuleHooks;
    protected $collModuleHooksPartial;

    /**
     * @var        ObjectCollection|ChildIgnoredModuleHook[] Collection to store aggregation of ChildIgnoredModuleHook objects.
     */
    protected $collIgnoredModuleHooks;
    protected $collIgnoredModuleHooksPartial;

    /**
     * @var        ObjectCollection|ChildHookI18n[] Collection to store aggregation of ChildHookI18n objects.
     */
    protected $collHookI18ns;
    protected $collHookI18nsPartial;

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

    // i18n behavior

    /**
     * Current locale
     * @var        string
     */
    protected $currentLocale = 'en_US';

    /**
     * Current translation objects
     * @var        array[ChildHookI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $modulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $moduleHooksScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $ignoredModuleHooksScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $hookI18nsScheduledForDeletion = null;

    /**
     * Initializes internal state of Thelia\Model\Base\Hook object.
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
     * Compares this with another <code>Hook</code> instance.  If
     * <code>obj</code> is an instance of <code>Hook</code>, delegates to
     * <code>equals(Hook)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Hook The current object, for fluid interface
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
     * @return Hook The current object, for fluid interface
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
     * @return   int
     */
    public function getType()
    {

        return $this->type;
    }

    /**
     * Get the [by_module] column value.
     *
     * @return   boolean
     */
    public function getByModule()
    {

        return $this->by_module;
    }

    /**
     * Get the [native] column value.
     *
     * @return   boolean
     */
    public function getNative()
    {

        return $this->native;
    }

    /**
     * Get the [activate] column value.
     *
     * @return   boolean
     */
    public function getActivate()
    {

        return $this->activate;
    }

    /**
     * Get the [block] column value.
     *
     * @return   boolean
     */
    public function getBlock()
    {

        return $this->block;
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
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[HookTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [code] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[HookTableMap::CODE] = true;
        }


        return $this;
    } // setCode()

    /**
     * Set the value of [type] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->type !== $v) {
            $this->type = $v;
            $this->modifiedColumns[HookTableMap::TYPE] = true;
        }


        return $this;
    } // setType()

    /**
     * Sets the value of the [by_module] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setByModule($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->by_module !== $v) {
            $this->by_module = $v;
            $this->modifiedColumns[HookTableMap::BY_MODULE] = true;
        }


        return $this;
    } // setByModule()

    /**
     * Sets the value of the [native] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setNative($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->native !== $v) {
            $this->native = $v;
            $this->modifiedColumns[HookTableMap::NATIVE] = true;
        }


        return $this;
    } // setNative()

    /**
     * Sets the value of the [activate] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setActivate($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->activate !== $v) {
            $this->activate = $v;
            $this->modifiedColumns[HookTableMap::ACTIVATE] = true;
        }


        return $this;
    } // setActivate()

    /**
     * Sets the value of the [block] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setBlock($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->block !== $v) {
            $this->block = $v;
            $this->modifiedColumns[HookTableMap::BLOCK] = true;
        }


        return $this;
    } // setBlock()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[HookTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[HookTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[HookTableMap::UPDATED_AT] = true;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : HookTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : HookTableMap::translateFieldName('Code', TableMap::TYPE_PHPNAME, $indexType)];
            $this->code = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : HookTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->type = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : HookTableMap::translateFieldName('ByModule', TableMap::TYPE_PHPNAME, $indexType)];
            $this->by_module = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : HookTableMap::translateFieldName('Native', TableMap::TYPE_PHPNAME, $indexType)];
            $this->native = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : HookTableMap::translateFieldName('Activate', TableMap::TYPE_PHPNAME, $indexType)];
            $this->activate = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : HookTableMap::translateFieldName('Block', TableMap::TYPE_PHPNAME, $indexType)];
            $this->block = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : HookTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : HookTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : HookTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 10; // 10 = HookTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Hook object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(HookTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildHookQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collModuleHooks = null;

            $this->collIgnoredModuleHooks = null;

            $this->collHookI18ns = null;

            $this->collModules = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Hook::setDeleted()
     * @see Hook::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(HookTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildHookQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(HookTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(HookTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(HookTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(HookTableMap::UPDATED_AT)) {
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
                HookTableMap::addInstanceToPool($this);
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

            if ($this->modulesScheduledForDeletion !== null) {
                if (!$this->modulesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->modulesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    IgnoredModuleHookQuery::create()
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

            if ($this->moduleHooksScheduledForDeletion !== null) {
                if (!$this->moduleHooksScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ModuleHookQuery::create()
                        ->filterByPrimaryKeys($this->moduleHooksScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->moduleHooksScheduledForDeletion = null;
                }
            }

                if ($this->collModuleHooks !== null) {
            foreach ($this->collModuleHooks as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->ignoredModuleHooksScheduledForDeletion !== null) {
                if (!$this->ignoredModuleHooksScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\IgnoredModuleHookQuery::create()
                        ->filterByPrimaryKeys($this->ignoredModuleHooksScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->ignoredModuleHooksScheduledForDeletion = null;
                }
            }

                if ($this->collIgnoredModuleHooks !== null) {
            foreach ($this->collIgnoredModuleHooks as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->hookI18nsScheduledForDeletion !== null) {
                if (!$this->hookI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\HookI18nQuery::create()
                        ->filterByPrimaryKeys($this->hookI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->hookI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collHookI18ns !== null) {
            foreach ($this->collHookI18ns as $referrerFK) {
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

        $this->modifiedColumns[HookTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . HookTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(HookTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(HookTableMap::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`CODE`';
        }
        if ($this->isColumnModified(HookTableMap::TYPE)) {
            $modifiedColumns[':p' . $index++]  = '`TYPE`';
        }
        if ($this->isColumnModified(HookTableMap::BY_MODULE)) {
            $modifiedColumns[':p' . $index++]  = '`BY_MODULE`';
        }
        if ($this->isColumnModified(HookTableMap::NATIVE)) {
            $modifiedColumns[':p' . $index++]  = '`NATIVE`';
        }
        if ($this->isColumnModified(HookTableMap::ACTIVATE)) {
            $modifiedColumns[':p' . $index++]  = '`ACTIVATE`';
        }
        if ($this->isColumnModified(HookTableMap::BLOCK)) {
            $modifiedColumns[':p' . $index++]  = '`BLOCK`';
        }
        if ($this->isColumnModified(HookTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(HookTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(HookTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `hook` (%s) VALUES (%s)',
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
                        $stmt->bindValue($identifier, $this->type, PDO::PARAM_INT);
                        break;
                    case '`BY_MODULE`':
                        $stmt->bindValue($identifier, (int) $this->by_module, PDO::PARAM_INT);
                        break;
                    case '`NATIVE`':
                        $stmt->bindValue($identifier, (int) $this->native, PDO::PARAM_INT);
                        break;
                    case '`ACTIVATE`':
                        $stmt->bindValue($identifier, (int) $this->activate, PDO::PARAM_INT);
                        break;
                    case '`BLOCK`':
                        $stmt->bindValue($identifier, (int) $this->block, PDO::PARAM_INT);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
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
        $pos = HookTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getByModule();
                break;
            case 4:
                return $this->getNative();
                break;
            case 5:
                return $this->getActivate();
                break;
            case 6:
                return $this->getBlock();
                break;
            case 7:
                return $this->getPosition();
                break;
            case 8:
                return $this->getCreatedAt();
                break;
            case 9:
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
        if (isset($alreadyDumpedObjects['Hook'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Hook'][$this->getPrimaryKey()] = true;
        $keys = HookTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCode(),
            $keys[2] => $this->getType(),
            $keys[3] => $this->getByModule(),
            $keys[4] => $this->getNative(),
            $keys[5] => $this->getActivate(),
            $keys[6] => $this->getBlock(),
            $keys[7] => $this->getPosition(),
            $keys[8] => $this->getCreatedAt(),
            $keys[9] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collModuleHooks) {
                $result['ModuleHooks'] = $this->collModuleHooks->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collIgnoredModuleHooks) {
                $result['IgnoredModuleHooks'] = $this->collIgnoredModuleHooks->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collHookI18ns) {
                $result['HookI18ns'] = $this->collHookI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = HookTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setByModule($value);
                break;
            case 4:
                $this->setNative($value);
                break;
            case 5:
                $this->setActivate($value);
                break;
            case 6:
                $this->setBlock($value);
                break;
            case 7:
                $this->setPosition($value);
                break;
            case 8:
                $this->setCreatedAt($value);
                break;
            case 9:
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
        $keys = HookTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCode($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setType($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setByModule($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setNative($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setActivate($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setBlock($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setPosition($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setCreatedAt($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setUpdatedAt($arr[$keys[9]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(HookTableMap::DATABASE_NAME);

        if ($this->isColumnModified(HookTableMap::ID)) $criteria->add(HookTableMap::ID, $this->id);
        if ($this->isColumnModified(HookTableMap::CODE)) $criteria->add(HookTableMap::CODE, $this->code);
        if ($this->isColumnModified(HookTableMap::TYPE)) $criteria->add(HookTableMap::TYPE, $this->type);
        if ($this->isColumnModified(HookTableMap::BY_MODULE)) $criteria->add(HookTableMap::BY_MODULE, $this->by_module);
        if ($this->isColumnModified(HookTableMap::NATIVE)) $criteria->add(HookTableMap::NATIVE, $this->native);
        if ($this->isColumnModified(HookTableMap::ACTIVATE)) $criteria->add(HookTableMap::ACTIVATE, $this->activate);
        if ($this->isColumnModified(HookTableMap::BLOCK)) $criteria->add(HookTableMap::BLOCK, $this->block);
        if ($this->isColumnModified(HookTableMap::POSITION)) $criteria->add(HookTableMap::POSITION, $this->position);
        if ($this->isColumnModified(HookTableMap::CREATED_AT)) $criteria->add(HookTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(HookTableMap::UPDATED_AT)) $criteria->add(HookTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(HookTableMap::DATABASE_NAME);
        $criteria->add(HookTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Hook (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCode($this->getCode());
        $copyObj->setType($this->getType());
        $copyObj->setByModule($this->getByModule());
        $copyObj->setNative($this->getNative());
        $copyObj->setActivate($this->getActivate());
        $copyObj->setBlock($this->getBlock());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getModuleHooks() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addModuleHook($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getIgnoredModuleHooks() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addIgnoredModuleHook($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getHookI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addHookI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Hook Clone of current object.
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
        if ('ModuleHook' == $relationName) {
            return $this->initModuleHooks();
        }
        if ('IgnoredModuleHook' == $relationName) {
            return $this->initIgnoredModuleHooks();
        }
        if ('HookI18n' == $relationName) {
            return $this->initHookI18ns();
        }
    }

    /**
     * Clears out the collModuleHooks collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addModuleHooks()
     */
    public function clearModuleHooks()
    {
        $this->collModuleHooks = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collModuleHooks collection loaded partially.
     */
    public function resetPartialModuleHooks($v = true)
    {
        $this->collModuleHooksPartial = $v;
    }

    /**
     * Initializes the collModuleHooks collection.
     *
     * By default this just sets the collModuleHooks collection to an empty array (like clearcollModuleHooks());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initModuleHooks($overrideExisting = true)
    {
        if (null !== $this->collModuleHooks && !$overrideExisting) {
            return;
        }
        $this->collModuleHooks = new ObjectCollection();
        $this->collModuleHooks->setModel('\Thelia\Model\ModuleHook');
    }

    /**
     * Gets an array of ChildModuleHook objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildHook is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildModuleHook[] List of ChildModuleHook objects
     * @throws PropelException
     */
    public function getModuleHooks($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleHooksPartial && !$this->isNew();
        if (null === $this->collModuleHooks || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collModuleHooks) {
                // return empty collection
                $this->initModuleHooks();
            } else {
                $collModuleHooks = ChildModuleHookQuery::create(null, $criteria)
                    ->filterByHook($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collModuleHooksPartial && count($collModuleHooks)) {
                        $this->initModuleHooks(false);

                        foreach ($collModuleHooks as $obj) {
                            if (false == $this->collModuleHooks->contains($obj)) {
                                $this->collModuleHooks->append($obj);
                            }
                        }

                        $this->collModuleHooksPartial = true;
                    }

                    reset($collModuleHooks);

                    return $collModuleHooks;
                }

                if ($partial && $this->collModuleHooks) {
                    foreach ($this->collModuleHooks as $obj) {
                        if ($obj->isNew()) {
                            $collModuleHooks[] = $obj;
                        }
                    }
                }

                $this->collModuleHooks = $collModuleHooks;
                $this->collModuleHooksPartial = false;
            }
        }

        return $this->collModuleHooks;
    }

    /**
     * Sets a collection of ModuleHook objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $moduleHooks A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildHook The current object (for fluent API support)
     */
    public function setModuleHooks(Collection $moduleHooks, ConnectionInterface $con = null)
    {
        $moduleHooksToDelete = $this->getModuleHooks(new Criteria(), $con)->diff($moduleHooks);


        $this->moduleHooksScheduledForDeletion = $moduleHooksToDelete;

        foreach ($moduleHooksToDelete as $moduleHookRemoved) {
            $moduleHookRemoved->setHook(null);
        }

        $this->collModuleHooks = null;
        foreach ($moduleHooks as $moduleHook) {
            $this->addModuleHook($moduleHook);
        }

        $this->collModuleHooks = $moduleHooks;
        $this->collModuleHooksPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ModuleHook objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ModuleHook objects.
     * @throws PropelException
     */
    public function countModuleHooks(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleHooksPartial && !$this->isNew();
        if (null === $this->collModuleHooks || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collModuleHooks) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getModuleHooks());
            }

            $query = ChildModuleHookQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByHook($this)
                ->count($con);
        }

        return count($this->collModuleHooks);
    }

    /**
     * Method called to associate a ChildModuleHook object to this object
     * through the ChildModuleHook foreign key attribute.
     *
     * @param    ChildModuleHook $l ChildModuleHook
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function addModuleHook(ChildModuleHook $l)
    {
        if ($this->collModuleHooks === null) {
            $this->initModuleHooks();
            $this->collModuleHooksPartial = true;
        }

        if (!in_array($l, $this->collModuleHooks->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddModuleHook($l);
        }

        return $this;
    }

    /**
     * @param ModuleHook $moduleHook The moduleHook object to add.
     */
    protected function doAddModuleHook($moduleHook)
    {
        $this->collModuleHooks[]= $moduleHook;
        $moduleHook->setHook($this);
    }

    /**
     * @param  ModuleHook $moduleHook The moduleHook object to remove.
     * @return ChildHook The current object (for fluent API support)
     */
    public function removeModuleHook($moduleHook)
    {
        if ($this->getModuleHooks()->contains($moduleHook)) {
            $this->collModuleHooks->remove($this->collModuleHooks->search($moduleHook));
            if (null === $this->moduleHooksScheduledForDeletion) {
                $this->moduleHooksScheduledForDeletion = clone $this->collModuleHooks;
                $this->moduleHooksScheduledForDeletion->clear();
            }
            $this->moduleHooksScheduledForDeletion[]= clone $moduleHook;
            $moduleHook->setHook(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Hook is new, it will return
     * an empty collection; or if this Hook has previously
     * been saved, it will retrieve related ModuleHooks from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Hook.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildModuleHook[] List of ChildModuleHook objects
     */
    public function getModuleHooksJoinModule($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildModuleHookQuery::create(null, $criteria);
        $query->joinWith('Module', $joinBehavior);

        return $this->getModuleHooks($query, $con);
    }

    /**
     * Clears out the collIgnoredModuleHooks collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addIgnoredModuleHooks()
     */
    public function clearIgnoredModuleHooks()
    {
        $this->collIgnoredModuleHooks = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collIgnoredModuleHooks collection loaded partially.
     */
    public function resetPartialIgnoredModuleHooks($v = true)
    {
        $this->collIgnoredModuleHooksPartial = $v;
    }

    /**
     * Initializes the collIgnoredModuleHooks collection.
     *
     * By default this just sets the collIgnoredModuleHooks collection to an empty array (like clearcollIgnoredModuleHooks());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initIgnoredModuleHooks($overrideExisting = true)
    {
        if (null !== $this->collIgnoredModuleHooks && !$overrideExisting) {
            return;
        }
        $this->collIgnoredModuleHooks = new ObjectCollection();
        $this->collIgnoredModuleHooks->setModel('\Thelia\Model\IgnoredModuleHook');
    }

    /**
     * Gets an array of ChildIgnoredModuleHook objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildHook is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildIgnoredModuleHook[] List of ChildIgnoredModuleHook objects
     * @throws PropelException
     */
    public function getIgnoredModuleHooks($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collIgnoredModuleHooksPartial && !$this->isNew();
        if (null === $this->collIgnoredModuleHooks || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collIgnoredModuleHooks) {
                // return empty collection
                $this->initIgnoredModuleHooks();
            } else {
                $collIgnoredModuleHooks = ChildIgnoredModuleHookQuery::create(null, $criteria)
                    ->filterByHook($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collIgnoredModuleHooksPartial && count($collIgnoredModuleHooks)) {
                        $this->initIgnoredModuleHooks(false);

                        foreach ($collIgnoredModuleHooks as $obj) {
                            if (false == $this->collIgnoredModuleHooks->contains($obj)) {
                                $this->collIgnoredModuleHooks->append($obj);
                            }
                        }

                        $this->collIgnoredModuleHooksPartial = true;
                    }

                    reset($collIgnoredModuleHooks);

                    return $collIgnoredModuleHooks;
                }

                if ($partial && $this->collIgnoredModuleHooks) {
                    foreach ($this->collIgnoredModuleHooks as $obj) {
                        if ($obj->isNew()) {
                            $collIgnoredModuleHooks[] = $obj;
                        }
                    }
                }

                $this->collIgnoredModuleHooks = $collIgnoredModuleHooks;
                $this->collIgnoredModuleHooksPartial = false;
            }
        }

        return $this->collIgnoredModuleHooks;
    }

    /**
     * Sets a collection of IgnoredModuleHook objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $ignoredModuleHooks A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildHook The current object (for fluent API support)
     */
    public function setIgnoredModuleHooks(Collection $ignoredModuleHooks, ConnectionInterface $con = null)
    {
        $ignoredModuleHooksToDelete = $this->getIgnoredModuleHooks(new Criteria(), $con)->diff($ignoredModuleHooks);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->ignoredModuleHooksScheduledForDeletion = clone $ignoredModuleHooksToDelete;

        foreach ($ignoredModuleHooksToDelete as $ignoredModuleHookRemoved) {
            $ignoredModuleHookRemoved->setHook(null);
        }

        $this->collIgnoredModuleHooks = null;
        foreach ($ignoredModuleHooks as $ignoredModuleHook) {
            $this->addIgnoredModuleHook($ignoredModuleHook);
        }

        $this->collIgnoredModuleHooks = $ignoredModuleHooks;
        $this->collIgnoredModuleHooksPartial = false;

        return $this;
    }

    /**
     * Returns the number of related IgnoredModuleHook objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related IgnoredModuleHook objects.
     * @throws PropelException
     */
    public function countIgnoredModuleHooks(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collIgnoredModuleHooksPartial && !$this->isNew();
        if (null === $this->collIgnoredModuleHooks || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collIgnoredModuleHooks) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getIgnoredModuleHooks());
            }

            $query = ChildIgnoredModuleHookQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByHook($this)
                ->count($con);
        }

        return count($this->collIgnoredModuleHooks);
    }

    /**
     * Method called to associate a ChildIgnoredModuleHook object to this object
     * through the ChildIgnoredModuleHook foreign key attribute.
     *
     * @param    ChildIgnoredModuleHook $l ChildIgnoredModuleHook
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function addIgnoredModuleHook(ChildIgnoredModuleHook $l)
    {
        if ($this->collIgnoredModuleHooks === null) {
            $this->initIgnoredModuleHooks();
            $this->collIgnoredModuleHooksPartial = true;
        }

        if (!in_array($l, $this->collIgnoredModuleHooks->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddIgnoredModuleHook($l);
        }

        return $this;
    }

    /**
     * @param IgnoredModuleHook $ignoredModuleHook The ignoredModuleHook object to add.
     */
    protected function doAddIgnoredModuleHook($ignoredModuleHook)
    {
        $this->collIgnoredModuleHooks[]= $ignoredModuleHook;
        $ignoredModuleHook->setHook($this);
    }

    /**
     * @param  IgnoredModuleHook $ignoredModuleHook The ignoredModuleHook object to remove.
     * @return ChildHook The current object (for fluent API support)
     */
    public function removeIgnoredModuleHook($ignoredModuleHook)
    {
        if ($this->getIgnoredModuleHooks()->contains($ignoredModuleHook)) {
            $this->collIgnoredModuleHooks->remove($this->collIgnoredModuleHooks->search($ignoredModuleHook));
            if (null === $this->ignoredModuleHooksScheduledForDeletion) {
                $this->ignoredModuleHooksScheduledForDeletion = clone $this->collIgnoredModuleHooks;
                $this->ignoredModuleHooksScheduledForDeletion->clear();
            }
            $this->ignoredModuleHooksScheduledForDeletion[]= clone $ignoredModuleHook;
            $ignoredModuleHook->setHook(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Hook is new, it will return
     * an empty collection; or if this Hook has previously
     * been saved, it will retrieve related IgnoredModuleHooks from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Hook.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildIgnoredModuleHook[] List of ChildIgnoredModuleHook objects
     */
    public function getIgnoredModuleHooksJoinModule($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildIgnoredModuleHookQuery::create(null, $criteria);
        $query->joinWith('Module', $joinBehavior);

        return $this->getIgnoredModuleHooks($query, $con);
    }

    /**
     * Clears out the collHookI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addHookI18ns()
     */
    public function clearHookI18ns()
    {
        $this->collHookI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collHookI18ns collection loaded partially.
     */
    public function resetPartialHookI18ns($v = true)
    {
        $this->collHookI18nsPartial = $v;
    }

    /**
     * Initializes the collHookI18ns collection.
     *
     * By default this just sets the collHookI18ns collection to an empty array (like clearcollHookI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initHookI18ns($overrideExisting = true)
    {
        if (null !== $this->collHookI18ns && !$overrideExisting) {
            return;
        }
        $this->collHookI18ns = new ObjectCollection();
        $this->collHookI18ns->setModel('\Thelia\Model\HookI18n');
    }

    /**
     * Gets an array of ChildHookI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildHook is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildHookI18n[] List of ChildHookI18n objects
     * @throws PropelException
     */
    public function getHookI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collHookI18nsPartial && !$this->isNew();
        if (null === $this->collHookI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collHookI18ns) {
                // return empty collection
                $this->initHookI18ns();
            } else {
                $collHookI18ns = ChildHookI18nQuery::create(null, $criteria)
                    ->filterByHook($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collHookI18nsPartial && count($collHookI18ns)) {
                        $this->initHookI18ns(false);

                        foreach ($collHookI18ns as $obj) {
                            if (false == $this->collHookI18ns->contains($obj)) {
                                $this->collHookI18ns->append($obj);
                            }
                        }

                        $this->collHookI18nsPartial = true;
                    }

                    reset($collHookI18ns);

                    return $collHookI18ns;
                }

                if ($partial && $this->collHookI18ns) {
                    foreach ($this->collHookI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collHookI18ns[] = $obj;
                        }
                    }
                }

                $this->collHookI18ns = $collHookI18ns;
                $this->collHookI18nsPartial = false;
            }
        }

        return $this->collHookI18ns;
    }

    /**
     * Sets a collection of HookI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $hookI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildHook The current object (for fluent API support)
     */
    public function setHookI18ns(Collection $hookI18ns, ConnectionInterface $con = null)
    {
        $hookI18nsToDelete = $this->getHookI18ns(new Criteria(), $con)->diff($hookI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->hookI18nsScheduledForDeletion = clone $hookI18nsToDelete;

        foreach ($hookI18nsToDelete as $hookI18nRemoved) {
            $hookI18nRemoved->setHook(null);
        }

        $this->collHookI18ns = null;
        foreach ($hookI18ns as $hookI18n) {
            $this->addHookI18n($hookI18n);
        }

        $this->collHookI18ns = $hookI18ns;
        $this->collHookI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related HookI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related HookI18n objects.
     * @throws PropelException
     */
    public function countHookI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collHookI18nsPartial && !$this->isNew();
        if (null === $this->collHookI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collHookI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getHookI18ns());
            }

            $query = ChildHookI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByHook($this)
                ->count($con);
        }

        return count($this->collHookI18ns);
    }

    /**
     * Method called to associate a ChildHookI18n object to this object
     * through the ChildHookI18n foreign key attribute.
     *
     * @param    ChildHookI18n $l ChildHookI18n
     * @return   \Thelia\Model\Hook The current object (for fluent API support)
     */
    public function addHookI18n(ChildHookI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collHookI18ns === null) {
            $this->initHookI18ns();
            $this->collHookI18nsPartial = true;
        }

        if (!in_array($l, $this->collHookI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddHookI18n($l);
        }

        return $this;
    }

    /**
     * @param HookI18n $hookI18n The hookI18n object to add.
     */
    protected function doAddHookI18n($hookI18n)
    {
        $this->collHookI18ns[]= $hookI18n;
        $hookI18n->setHook($this);
    }

    /**
     * @param  HookI18n $hookI18n The hookI18n object to remove.
     * @return ChildHook The current object (for fluent API support)
     */
    public function removeHookI18n($hookI18n)
    {
        if ($this->getHookI18ns()->contains($hookI18n)) {
            $this->collHookI18ns->remove($this->collHookI18ns->search($hookI18n));
            if (null === $this->hookI18nsScheduledForDeletion) {
                $this->hookI18nsScheduledForDeletion = clone $this->collHookI18ns;
                $this->hookI18nsScheduledForDeletion->clear();
            }
            $this->hookI18nsScheduledForDeletion[]= clone $hookI18n;
            $hookI18n->setHook(null);
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
     * to the current object by way of the ignored_module_hook cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildHook is new, it will return
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
                    ->filterByHook($this)
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
     * to the current object by way of the ignored_module_hook cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $modules A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildHook The current object (for fluent API support)
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
     * to the current object by way of the ignored_module_hook cross-reference table.
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
                    ->filterByHook($this)
                    ->count($con);
            }
        } else {
            return count($this->collModules);
        }
    }

    /**
     * Associate a ChildModule object to this object
     * through the ignored_module_hook cross reference table.
     *
     * @param  ChildModule $module The ChildIgnoredModuleHook object to relate
     * @return ChildHook The current object (for fluent API support)
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
        $ignoredModuleHook = new ChildIgnoredModuleHook();
        $ignoredModuleHook->setModule($module);
        $this->addIgnoredModuleHook($ignoredModuleHook);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$module->getHooks()->contains($this)) {
            $foreignCollection   = $module->getHooks();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildModule object to this object
     * through the ignored_module_hook cross reference table.
     *
     * @param ChildModule $module The ChildIgnoredModuleHook object to relate
     * @return ChildHook The current object (for fluent API support)
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
        $this->code = null;
        $this->type = null;
        $this->by_module = null;
        $this->native = null;
        $this->activate = null;
        $this->block = null;
        $this->position = null;
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
            if ($this->collModuleHooks) {
                foreach ($this->collModuleHooks as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collIgnoredModuleHooks) {
                foreach ($this->collIgnoredModuleHooks as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collHookI18ns) {
                foreach ($this->collHookI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collModules) {
                foreach ($this->collModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collModuleHooks = null;
        $this->collIgnoredModuleHooks = null;
        $this->collHookI18ns = null;
        $this->collModules = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(HookTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildHook The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[HookTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildHook The current object (for fluent API support)
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
     * @return ChildHookI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collHookI18ns) {
                foreach ($this->collHookI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildHookI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildHookI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addHookI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildHook The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildHookI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collHookI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collHookI18ns[$key]);
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
     * @return ChildHookI18n */
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
         * @return   \Thelia\Model\HookI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\HookI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\HookI18n The current object (for fluent API support)
         */
        public function setChapo($v)
        {    $this->getCurrentTranslation()->setChapo($v);

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
