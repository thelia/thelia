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
use Thelia\Model\Feature as ChildFeature;
use Thelia\Model\FeatureAv as ChildFeatureAv;
use Thelia\Model\FeatureAvQuery as ChildFeatureAvQuery;
use Thelia\Model\FeatureI18n as ChildFeatureI18n;
use Thelia\Model\FeatureI18nQuery as ChildFeatureI18nQuery;
use Thelia\Model\FeatureProduct as ChildFeatureProduct;
use Thelia\Model\FeatureProductQuery as ChildFeatureProductQuery;
use Thelia\Model\FeatureQuery as ChildFeatureQuery;
use Thelia\Model\FeatureTemplate as ChildFeatureTemplate;
use Thelia\Model\FeatureTemplateQuery as ChildFeatureTemplateQuery;
use Thelia\Model\Template as ChildTemplate;
use Thelia\Model\TemplateQuery as ChildTemplateQuery;
use Thelia\Model\Map\FeatureTableMap;

abstract class Feature implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\FeatureTableMap';


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
     * The value for the visible field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $visible;

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
     * @var        ObjectCollection|ChildFeatureAv[] Collection to store aggregation of ChildFeatureAv objects.
     */
    protected $collFeatureAvs;
    protected $collFeatureAvsPartial;

    /**
     * @var        ObjectCollection|ChildFeatureProduct[] Collection to store aggregation of ChildFeatureProduct objects.
     */
    protected $collFeatureProducts;
    protected $collFeatureProductsPartial;

    /**
     * @var        ObjectCollection|ChildFeatureTemplate[] Collection to store aggregation of ChildFeatureTemplate objects.
     */
    protected $collFeatureTemplates;
    protected $collFeatureTemplatesPartial;

    /**
     * @var        ObjectCollection|ChildFeatureI18n[] Collection to store aggregation of ChildFeatureI18n objects.
     */
    protected $collFeatureI18ns;
    protected $collFeatureI18nsPartial;

    /**
     * @var        ChildTemplate[] Collection to store aggregation of ChildTemplate objects.
     */
    protected $collTemplates;

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
     * @var        array[ChildFeatureI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $templatesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $featureAvsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $featureProductsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $featureTemplatesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $featureI18nsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->visible = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Feature object.
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
     * Compares this with another <code>Feature</code> instance.  If
     * <code>obj</code> is an instance of <code>Feature</code>, delegates to
     * <code>equals(Feature)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Feature The current object, for fluid interface
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
     * @return Feature The current object, for fluid interface
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
     * Get the [visible] column value.
     *
     * @return   int
     */
    public function getVisible()
    {

        return $this->visible;
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
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[FeatureTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [visible] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[FeatureTableMap::VISIBLE] = true;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[FeatureTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[FeatureTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[FeatureTableMap::UPDATED_AT] = true;
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
            if ($this->visible !== 0) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : FeatureTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : FeatureTableMap::translateFieldName('Visible', TableMap::TYPE_PHPNAME, $indexType)];
            $this->visible = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : FeatureTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : FeatureTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : FeatureTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 5; // 5 = FeatureTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Feature object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(FeatureTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildFeatureQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collFeatureAvs = null;

            $this->collFeatureProducts = null;

            $this->collFeatureTemplates = null;

            $this->collFeatureI18ns = null;

            $this->collTemplates = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Feature::setDeleted()
     * @see Feature::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(FeatureTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildFeatureQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(FeatureTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(FeatureTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(FeatureTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(FeatureTableMap::UPDATED_AT)) {
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
                FeatureTableMap::addInstanceToPool($this);
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

            if ($this->templatesScheduledForDeletion !== null) {
                if (!$this->templatesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->templatesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    FeatureTemplateQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->templatesScheduledForDeletion = null;
                }

                foreach ($this->getTemplates() as $template) {
                    if ($template->isModified()) {
                        $template->save($con);
                    }
                }
            } elseif ($this->collTemplates) {
                foreach ($this->collTemplates as $template) {
                    if ($template->isModified()) {
                        $template->save($con);
                    }
                }
            }

            if ($this->featureAvsScheduledForDeletion !== null) {
                if (!$this->featureAvsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\FeatureAvQuery::create()
                        ->filterByPrimaryKeys($this->featureAvsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureAvsScheduledForDeletion = null;
                }
            }

                if ($this->collFeatureAvs !== null) {
            foreach ($this->collFeatureAvs as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureProductsScheduledForDeletion !== null) {
                if (!$this->featureProductsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\FeatureProductQuery::create()
                        ->filterByPrimaryKeys($this->featureProductsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureProductsScheduledForDeletion = null;
                }
            }

                if ($this->collFeatureProducts !== null) {
            foreach ($this->collFeatureProducts as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureTemplatesScheduledForDeletion !== null) {
                if (!$this->featureTemplatesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\FeatureTemplateQuery::create()
                        ->filterByPrimaryKeys($this->featureTemplatesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureTemplatesScheduledForDeletion = null;
                }
            }

                if ($this->collFeatureTemplates !== null) {
            foreach ($this->collFeatureTemplates as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureI18nsScheduledForDeletion !== null) {
                if (!$this->featureI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\FeatureI18nQuery::create()
                        ->filterByPrimaryKeys($this->featureI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collFeatureI18ns !== null) {
            foreach ($this->collFeatureI18ns as $referrerFK) {
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

        $this->modifiedColumns[FeatureTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . FeatureTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(FeatureTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(FeatureTableMap::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`VISIBLE`';
        }
        if ($this->isColumnModified(FeatureTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(FeatureTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(FeatureTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `feature` (%s) VALUES (%s)',
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
                    case '`VISIBLE`':
                        $stmt->bindValue($identifier, $this->visible, PDO::PARAM_INT);
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
        $pos = FeatureTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getVisible();
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
        if (isset($alreadyDumpedObjects['Feature'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Feature'][$this->getPrimaryKey()] = true;
        $keys = FeatureTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getVisible(),
            $keys[2] => $this->getPosition(),
            $keys[3] => $this->getCreatedAt(),
            $keys[4] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collFeatureAvs) {
                $result['FeatureAvs'] = $this->collFeatureAvs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureProducts) {
                $result['FeatureProducts'] = $this->collFeatureProducts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureTemplates) {
                $result['FeatureTemplates'] = $this->collFeatureTemplates->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureI18ns) {
                $result['FeatureI18ns'] = $this->collFeatureI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = FeatureTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setVisible($value);
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
        $keys = FeatureTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setVisible($arr[$keys[1]]);
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
        $criteria = new Criteria(FeatureTableMap::DATABASE_NAME);

        if ($this->isColumnModified(FeatureTableMap::ID)) $criteria->add(FeatureTableMap::ID, $this->id);
        if ($this->isColumnModified(FeatureTableMap::VISIBLE)) $criteria->add(FeatureTableMap::VISIBLE, $this->visible);
        if ($this->isColumnModified(FeatureTableMap::POSITION)) $criteria->add(FeatureTableMap::POSITION, $this->position);
        if ($this->isColumnModified(FeatureTableMap::CREATED_AT)) $criteria->add(FeatureTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(FeatureTableMap::UPDATED_AT)) $criteria->add(FeatureTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(FeatureTableMap::DATABASE_NAME);
        $criteria->add(FeatureTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Feature (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setVisible($this->getVisible());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getFeatureAvs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureAv($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureProducts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureProduct($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureTemplates() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureTemplate($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Feature Clone of current object.
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
        if ('FeatureAv' == $relationName) {
            return $this->initFeatureAvs();
        }
        if ('FeatureProduct' == $relationName) {
            return $this->initFeatureProducts();
        }
        if ('FeatureTemplate' == $relationName) {
            return $this->initFeatureTemplates();
        }
        if ('FeatureI18n' == $relationName) {
            return $this->initFeatureI18ns();
        }
    }

    /**
     * Clears out the collFeatureAvs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureAvs()
     */
    public function clearFeatureAvs()
    {
        $this->collFeatureAvs = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collFeatureAvs collection loaded partially.
     */
    public function resetPartialFeatureAvs($v = true)
    {
        $this->collFeatureAvsPartial = $v;
    }

    /**
     * Initializes the collFeatureAvs collection.
     *
     * By default this just sets the collFeatureAvs collection to an empty array (like clearcollFeatureAvs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureAvs($overrideExisting = true)
    {
        if (null !== $this->collFeatureAvs && !$overrideExisting) {
            return;
        }
        $this->collFeatureAvs = new ObjectCollection();
        $this->collFeatureAvs->setModel('\Thelia\Model\FeatureAv');
    }

    /**
     * Gets an array of ChildFeatureAv objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFeature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildFeatureAv[] List of ChildFeatureAv objects
     * @throws PropelException
     */
    public function getFeatureAvs($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureAvsPartial && !$this->isNew();
        if (null === $this->collFeatureAvs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureAvs) {
                // return empty collection
                $this->initFeatureAvs();
            } else {
                $collFeatureAvs = ChildFeatureAvQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collFeatureAvsPartial && count($collFeatureAvs)) {
                        $this->initFeatureAvs(false);

                        foreach ($collFeatureAvs as $obj) {
                            if (false == $this->collFeatureAvs->contains($obj)) {
                                $this->collFeatureAvs->append($obj);
                            }
                        }

                        $this->collFeatureAvsPartial = true;
                    }

                    reset($collFeatureAvs);

                    return $collFeatureAvs;
                }

                if ($partial && $this->collFeatureAvs) {
                    foreach ($this->collFeatureAvs as $obj) {
                        if ($obj->isNew()) {
                            $collFeatureAvs[] = $obj;
                        }
                    }
                }

                $this->collFeatureAvs = $collFeatureAvs;
                $this->collFeatureAvsPartial = false;
            }
        }

        return $this->collFeatureAvs;
    }

    /**
     * Sets a collection of FeatureAv objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $featureAvs A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFeature The current object (for fluent API support)
     */
    public function setFeatureAvs(Collection $featureAvs, ConnectionInterface $con = null)
    {
        $featureAvsToDelete = $this->getFeatureAvs(new Criteria(), $con)->diff($featureAvs);


        $this->featureAvsScheduledForDeletion = $featureAvsToDelete;

        foreach ($featureAvsToDelete as $featureAvRemoved) {
            $featureAvRemoved->setFeature(null);
        }

        $this->collFeatureAvs = null;
        foreach ($featureAvs as $featureAv) {
            $this->addFeatureAv($featureAv);
        }

        $this->collFeatureAvs = $featureAvs;
        $this->collFeatureAvsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FeatureAv objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related FeatureAv objects.
     * @throws PropelException
     */
    public function countFeatureAvs(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureAvsPartial && !$this->isNew();
        if (null === $this->collFeatureAvs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureAvs) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getFeatureAvs());
            }

            $query = ChildFeatureAvQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFeature($this)
                ->count($con);
        }

        return count($this->collFeatureAvs);
    }

    /**
     * Method called to associate a ChildFeatureAv object to this object
     * through the ChildFeatureAv foreign key attribute.
     *
     * @param    ChildFeatureAv $l ChildFeatureAv
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function addFeatureAv(ChildFeatureAv $l)
    {
        if ($this->collFeatureAvs === null) {
            $this->initFeatureAvs();
            $this->collFeatureAvsPartial = true;
        }

        if (!in_array($l, $this->collFeatureAvs->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureAv($l);
        }

        return $this;
    }

    /**
     * @param FeatureAv $featureAv The featureAv object to add.
     */
    protected function doAddFeatureAv($featureAv)
    {
        $this->collFeatureAvs[]= $featureAv;
        $featureAv->setFeature($this);
    }

    /**
     * @param  FeatureAv $featureAv The featureAv object to remove.
     * @return ChildFeature The current object (for fluent API support)
     */
    public function removeFeatureAv($featureAv)
    {
        if ($this->getFeatureAvs()->contains($featureAv)) {
            $this->collFeatureAvs->remove($this->collFeatureAvs->search($featureAv));
            if (null === $this->featureAvsScheduledForDeletion) {
                $this->featureAvsScheduledForDeletion = clone $this->collFeatureAvs;
                $this->featureAvsScheduledForDeletion->clear();
            }
            $this->featureAvsScheduledForDeletion[]= clone $featureAv;
            $featureAv->setFeature(null);
        }

        return $this;
    }

    /**
     * Clears out the collFeatureProducts collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureProducts()
     */
    public function clearFeatureProducts()
    {
        $this->collFeatureProducts = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collFeatureProducts collection loaded partially.
     */
    public function resetPartialFeatureProducts($v = true)
    {
        $this->collFeatureProductsPartial = $v;
    }

    /**
     * Initializes the collFeatureProducts collection.
     *
     * By default this just sets the collFeatureProducts collection to an empty array (like clearcollFeatureProducts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureProducts($overrideExisting = true)
    {
        if (null !== $this->collFeatureProducts && !$overrideExisting) {
            return;
        }
        $this->collFeatureProducts = new ObjectCollection();
        $this->collFeatureProducts->setModel('\Thelia\Model\FeatureProduct');
    }

    /**
     * Gets an array of ChildFeatureProduct objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFeature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildFeatureProduct[] List of ChildFeatureProduct objects
     * @throws PropelException
     */
    public function getFeatureProducts($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureProductsPartial && !$this->isNew();
        if (null === $this->collFeatureProducts || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureProducts) {
                // return empty collection
                $this->initFeatureProducts();
            } else {
                $collFeatureProducts = ChildFeatureProductQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collFeatureProductsPartial && count($collFeatureProducts)) {
                        $this->initFeatureProducts(false);

                        foreach ($collFeatureProducts as $obj) {
                            if (false == $this->collFeatureProducts->contains($obj)) {
                                $this->collFeatureProducts->append($obj);
                            }
                        }

                        $this->collFeatureProductsPartial = true;
                    }

                    reset($collFeatureProducts);

                    return $collFeatureProducts;
                }

                if ($partial && $this->collFeatureProducts) {
                    foreach ($this->collFeatureProducts as $obj) {
                        if ($obj->isNew()) {
                            $collFeatureProducts[] = $obj;
                        }
                    }
                }

                $this->collFeatureProducts = $collFeatureProducts;
                $this->collFeatureProductsPartial = false;
            }
        }

        return $this->collFeatureProducts;
    }

    /**
     * Sets a collection of FeatureProduct objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $featureProducts A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFeature The current object (for fluent API support)
     */
    public function setFeatureProducts(Collection $featureProducts, ConnectionInterface $con = null)
    {
        $featureProductsToDelete = $this->getFeatureProducts(new Criteria(), $con)->diff($featureProducts);


        $this->featureProductsScheduledForDeletion = $featureProductsToDelete;

        foreach ($featureProductsToDelete as $featureProductRemoved) {
            $featureProductRemoved->setFeature(null);
        }

        $this->collFeatureProducts = null;
        foreach ($featureProducts as $featureProduct) {
            $this->addFeatureProduct($featureProduct);
        }

        $this->collFeatureProducts = $featureProducts;
        $this->collFeatureProductsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FeatureProduct objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related FeatureProduct objects.
     * @throws PropelException
     */
    public function countFeatureProducts(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureProductsPartial && !$this->isNew();
        if (null === $this->collFeatureProducts || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureProducts) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getFeatureProducts());
            }

            $query = ChildFeatureProductQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFeature($this)
                ->count($con);
        }

        return count($this->collFeatureProducts);
    }

    /**
     * Method called to associate a ChildFeatureProduct object to this object
     * through the ChildFeatureProduct foreign key attribute.
     *
     * @param    ChildFeatureProduct $l ChildFeatureProduct
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function addFeatureProduct(ChildFeatureProduct $l)
    {
        if ($this->collFeatureProducts === null) {
            $this->initFeatureProducts();
            $this->collFeatureProductsPartial = true;
        }

        if (!in_array($l, $this->collFeatureProducts->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureProduct($l);
        }

        return $this;
    }

    /**
     * @param FeatureProduct $featureProduct The featureProduct object to add.
     */
    protected function doAddFeatureProduct($featureProduct)
    {
        $this->collFeatureProducts[]= $featureProduct;
        $featureProduct->setFeature($this);
    }

    /**
     * @param  FeatureProduct $featureProduct The featureProduct object to remove.
     * @return ChildFeature The current object (for fluent API support)
     */
    public function removeFeatureProduct($featureProduct)
    {
        if ($this->getFeatureProducts()->contains($featureProduct)) {
            $this->collFeatureProducts->remove($this->collFeatureProducts->search($featureProduct));
            if (null === $this->featureProductsScheduledForDeletion) {
                $this->featureProductsScheduledForDeletion = clone $this->collFeatureProducts;
                $this->featureProductsScheduledForDeletion->clear();
            }
            $this->featureProductsScheduledForDeletion[]= clone $featureProduct;
            $featureProduct->setFeature(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Feature is new, it will return
     * an empty collection; or if this Feature has previously
     * been saved, it will retrieve related FeatureProducts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Feature.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildFeatureProduct[] List of ChildFeatureProduct objects
     */
    public function getFeatureProductsJoinProduct($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildFeatureProductQuery::create(null, $criteria);
        $query->joinWith('Product', $joinBehavior);

        return $this->getFeatureProducts($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Feature is new, it will return
     * an empty collection; or if this Feature has previously
     * been saved, it will retrieve related FeatureProducts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Feature.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildFeatureProduct[] List of ChildFeatureProduct objects
     */
    public function getFeatureProductsJoinFeatureAv($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildFeatureProductQuery::create(null, $criteria);
        $query->joinWith('FeatureAv', $joinBehavior);

        return $this->getFeatureProducts($query, $con);
    }

    /**
     * Clears out the collFeatureTemplates collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureTemplates()
     */
    public function clearFeatureTemplates()
    {
        $this->collFeatureTemplates = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collFeatureTemplates collection loaded partially.
     */
    public function resetPartialFeatureTemplates($v = true)
    {
        $this->collFeatureTemplatesPartial = $v;
    }

    /**
     * Initializes the collFeatureTemplates collection.
     *
     * By default this just sets the collFeatureTemplates collection to an empty array (like clearcollFeatureTemplates());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureTemplates($overrideExisting = true)
    {
        if (null !== $this->collFeatureTemplates && !$overrideExisting) {
            return;
        }
        $this->collFeatureTemplates = new ObjectCollection();
        $this->collFeatureTemplates->setModel('\Thelia\Model\FeatureTemplate');
    }

    /**
     * Gets an array of ChildFeatureTemplate objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFeature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildFeatureTemplate[] List of ChildFeatureTemplate objects
     * @throws PropelException
     */
    public function getFeatureTemplates($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureTemplatesPartial && !$this->isNew();
        if (null === $this->collFeatureTemplates || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureTemplates) {
                // return empty collection
                $this->initFeatureTemplates();
            } else {
                $collFeatureTemplates = ChildFeatureTemplateQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collFeatureTemplatesPartial && count($collFeatureTemplates)) {
                        $this->initFeatureTemplates(false);

                        foreach ($collFeatureTemplates as $obj) {
                            if (false == $this->collFeatureTemplates->contains($obj)) {
                                $this->collFeatureTemplates->append($obj);
                            }
                        }

                        $this->collFeatureTemplatesPartial = true;
                    }

                    reset($collFeatureTemplates);

                    return $collFeatureTemplates;
                }

                if ($partial && $this->collFeatureTemplates) {
                    foreach ($this->collFeatureTemplates as $obj) {
                        if ($obj->isNew()) {
                            $collFeatureTemplates[] = $obj;
                        }
                    }
                }

                $this->collFeatureTemplates = $collFeatureTemplates;
                $this->collFeatureTemplatesPartial = false;
            }
        }

        return $this->collFeatureTemplates;
    }

    /**
     * Sets a collection of FeatureTemplate objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $featureTemplates A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFeature The current object (for fluent API support)
     */
    public function setFeatureTemplates(Collection $featureTemplates, ConnectionInterface $con = null)
    {
        $featureTemplatesToDelete = $this->getFeatureTemplates(new Criteria(), $con)->diff($featureTemplates);


        $this->featureTemplatesScheduledForDeletion = $featureTemplatesToDelete;

        foreach ($featureTemplatesToDelete as $featureTemplateRemoved) {
            $featureTemplateRemoved->setFeature(null);
        }

        $this->collFeatureTemplates = null;
        foreach ($featureTemplates as $featureTemplate) {
            $this->addFeatureTemplate($featureTemplate);
        }

        $this->collFeatureTemplates = $featureTemplates;
        $this->collFeatureTemplatesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FeatureTemplate objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related FeatureTemplate objects.
     * @throws PropelException
     */
    public function countFeatureTemplates(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureTemplatesPartial && !$this->isNew();
        if (null === $this->collFeatureTemplates || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureTemplates) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getFeatureTemplates());
            }

            $query = ChildFeatureTemplateQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFeature($this)
                ->count($con);
        }

        return count($this->collFeatureTemplates);
    }

    /**
     * Method called to associate a ChildFeatureTemplate object to this object
     * through the ChildFeatureTemplate foreign key attribute.
     *
     * @param    ChildFeatureTemplate $l ChildFeatureTemplate
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function addFeatureTemplate(ChildFeatureTemplate $l)
    {
        if ($this->collFeatureTemplates === null) {
            $this->initFeatureTemplates();
            $this->collFeatureTemplatesPartial = true;
        }

        if (!in_array($l, $this->collFeatureTemplates->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureTemplate($l);
        }

        return $this;
    }

    /**
     * @param FeatureTemplate $featureTemplate The featureTemplate object to add.
     */
    protected function doAddFeatureTemplate($featureTemplate)
    {
        $this->collFeatureTemplates[]= $featureTemplate;
        $featureTemplate->setFeature($this);
    }

    /**
     * @param  FeatureTemplate $featureTemplate The featureTemplate object to remove.
     * @return ChildFeature The current object (for fluent API support)
     */
    public function removeFeatureTemplate($featureTemplate)
    {
        if ($this->getFeatureTemplates()->contains($featureTemplate)) {
            $this->collFeatureTemplates->remove($this->collFeatureTemplates->search($featureTemplate));
            if (null === $this->featureTemplatesScheduledForDeletion) {
                $this->featureTemplatesScheduledForDeletion = clone $this->collFeatureTemplates;
                $this->featureTemplatesScheduledForDeletion->clear();
            }
            $this->featureTemplatesScheduledForDeletion[]= clone $featureTemplate;
            $featureTemplate->setFeature(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Feature is new, it will return
     * an empty collection; or if this Feature has previously
     * been saved, it will retrieve related FeatureTemplates from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Feature.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildFeatureTemplate[] List of ChildFeatureTemplate objects
     */
    public function getFeatureTemplatesJoinTemplate($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildFeatureTemplateQuery::create(null, $criteria);
        $query->joinWith('Template', $joinBehavior);

        return $this->getFeatureTemplates($query, $con);
    }

    /**
     * Clears out the collFeatureI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureI18ns()
     */
    public function clearFeatureI18ns()
    {
        $this->collFeatureI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collFeatureI18ns collection loaded partially.
     */
    public function resetPartialFeatureI18ns($v = true)
    {
        $this->collFeatureI18nsPartial = $v;
    }

    /**
     * Initializes the collFeatureI18ns collection.
     *
     * By default this just sets the collFeatureI18ns collection to an empty array (like clearcollFeatureI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureI18ns($overrideExisting = true)
    {
        if (null !== $this->collFeatureI18ns && !$overrideExisting) {
            return;
        }
        $this->collFeatureI18ns = new ObjectCollection();
        $this->collFeatureI18ns->setModel('\Thelia\Model\FeatureI18n');
    }

    /**
     * Gets an array of ChildFeatureI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFeature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildFeatureI18n[] List of ChildFeatureI18n objects
     * @throws PropelException
     */
    public function getFeatureI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureI18nsPartial && !$this->isNew();
        if (null === $this->collFeatureI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureI18ns) {
                // return empty collection
                $this->initFeatureI18ns();
            } else {
                $collFeatureI18ns = ChildFeatureI18nQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collFeatureI18nsPartial && count($collFeatureI18ns)) {
                        $this->initFeatureI18ns(false);

                        foreach ($collFeatureI18ns as $obj) {
                            if (false == $this->collFeatureI18ns->contains($obj)) {
                                $this->collFeatureI18ns->append($obj);
                            }
                        }

                        $this->collFeatureI18nsPartial = true;
                    }

                    reset($collFeatureI18ns);

                    return $collFeatureI18ns;
                }

                if ($partial && $this->collFeatureI18ns) {
                    foreach ($this->collFeatureI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collFeatureI18ns[] = $obj;
                        }
                    }
                }

                $this->collFeatureI18ns = $collFeatureI18ns;
                $this->collFeatureI18nsPartial = false;
            }
        }

        return $this->collFeatureI18ns;
    }

    /**
     * Sets a collection of FeatureI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $featureI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildFeature The current object (for fluent API support)
     */
    public function setFeatureI18ns(Collection $featureI18ns, ConnectionInterface $con = null)
    {
        $featureI18nsToDelete = $this->getFeatureI18ns(new Criteria(), $con)->diff($featureI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->featureI18nsScheduledForDeletion = clone $featureI18nsToDelete;

        foreach ($featureI18nsToDelete as $featureI18nRemoved) {
            $featureI18nRemoved->setFeature(null);
        }

        $this->collFeatureI18ns = null;
        foreach ($featureI18ns as $featureI18n) {
            $this->addFeatureI18n($featureI18n);
        }

        $this->collFeatureI18ns = $featureI18ns;
        $this->collFeatureI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FeatureI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related FeatureI18n objects.
     * @throws PropelException
     */
    public function countFeatureI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureI18nsPartial && !$this->isNew();
        if (null === $this->collFeatureI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getFeatureI18ns());
            }

            $query = ChildFeatureI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFeature($this)
                ->count($con);
        }

        return count($this->collFeatureI18ns);
    }

    /**
     * Method called to associate a ChildFeatureI18n object to this object
     * through the ChildFeatureI18n foreign key attribute.
     *
     * @param    ChildFeatureI18n $l ChildFeatureI18n
     * @return   \Thelia\Model\Feature The current object (for fluent API support)
     */
    public function addFeatureI18n(ChildFeatureI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collFeatureI18ns === null) {
            $this->initFeatureI18ns();
            $this->collFeatureI18nsPartial = true;
        }

        if (!in_array($l, $this->collFeatureI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureI18n($l);
        }

        return $this;
    }

    /**
     * @param FeatureI18n $featureI18n The featureI18n object to add.
     */
    protected function doAddFeatureI18n($featureI18n)
    {
        $this->collFeatureI18ns[]= $featureI18n;
        $featureI18n->setFeature($this);
    }

    /**
     * @param  FeatureI18n $featureI18n The featureI18n object to remove.
     * @return ChildFeature The current object (for fluent API support)
     */
    public function removeFeatureI18n($featureI18n)
    {
        if ($this->getFeatureI18ns()->contains($featureI18n)) {
            $this->collFeatureI18ns->remove($this->collFeatureI18ns->search($featureI18n));
            if (null === $this->featureI18nsScheduledForDeletion) {
                $this->featureI18nsScheduledForDeletion = clone $this->collFeatureI18ns;
                $this->featureI18nsScheduledForDeletion->clear();
            }
            $this->featureI18nsScheduledForDeletion[]= clone $featureI18n;
            $featureI18n->setFeature(null);
        }

        return $this;
    }

    /**
     * Clears out the collTemplates collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addTemplates()
     */
    public function clearTemplates()
    {
        $this->collTemplates = null; // important to set this to NULL since that means it is uninitialized
        $this->collTemplatesPartial = null;
    }

    /**
     * Initializes the collTemplates collection.
     *
     * By default this just sets the collTemplates collection to an empty collection (like clearTemplates());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initTemplates()
    {
        $this->collTemplates = new ObjectCollection();
        $this->collTemplates->setModel('\Thelia\Model\Template');
    }

    /**
     * Gets a collection of ChildTemplate objects related by a many-to-many relationship
     * to the current object by way of the feature_template cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildFeature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildTemplate[] List of ChildTemplate objects
     */
    public function getTemplates($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collTemplates || null !== $criteria) {
            if ($this->isNew() && null === $this->collTemplates) {
                // return empty collection
                $this->initTemplates();
            } else {
                $collTemplates = ChildTemplateQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collTemplates;
                }
                $this->collTemplates = $collTemplates;
            }
        }

        return $this->collTemplates;
    }

    /**
     * Sets a collection of Template objects related by a many-to-many relationship
     * to the current object by way of the feature_template cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $templates A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildFeature The current object (for fluent API support)
     */
    public function setTemplates(Collection $templates, ConnectionInterface $con = null)
    {
        $this->clearTemplates();
        $currentTemplates = $this->getTemplates();

        $this->templatesScheduledForDeletion = $currentTemplates->diff($templates);

        foreach ($templates as $template) {
            if (!$currentTemplates->contains($template)) {
                $this->doAddTemplate($template);
            }
        }

        $this->collTemplates = $templates;

        return $this;
    }

    /**
     * Gets the number of ChildTemplate objects related by a many-to-many relationship
     * to the current object by way of the feature_template cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildTemplate objects
     */
    public function countTemplates($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collTemplates || null !== $criteria) {
            if ($this->isNew() && null === $this->collTemplates) {
                return 0;
            } else {
                $query = ChildTemplateQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByFeature($this)
                    ->count($con);
            }
        } else {
            return count($this->collTemplates);
        }
    }

    /**
     * Associate a ChildTemplate object to this object
     * through the feature_template cross reference table.
     *
     * @param  ChildTemplate $template The ChildFeatureTemplate object to relate
     * @return ChildFeature The current object (for fluent API support)
     */
    public function addTemplate(ChildTemplate $template)
    {
        if ($this->collTemplates === null) {
            $this->initTemplates();
        }

        if (!$this->collTemplates->contains($template)) { // only add it if the **same** object is not already associated
            $this->doAddTemplate($template);
            $this->collTemplates[] = $template;
        }

        return $this;
    }

    /**
     * @param    Template $template The template object to add.
     */
    protected function doAddTemplate($template)
    {
        $featureTemplate = new ChildFeatureTemplate();
        $featureTemplate->setTemplate($template);
        $this->addFeatureTemplate($featureTemplate);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$template->getFeatures()->contains($this)) {
            $foreignCollection   = $template->getFeatures();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildTemplate object to this object
     * through the feature_template cross reference table.
     *
     * @param ChildTemplate $template The ChildFeatureTemplate object to relate
     * @return ChildFeature The current object (for fluent API support)
     */
    public function removeTemplate(ChildTemplate $template)
    {
        if ($this->getTemplates()->contains($template)) {
            $this->collTemplates->remove($this->collTemplates->search($template));

            if (null === $this->templatesScheduledForDeletion) {
                $this->templatesScheduledForDeletion = clone $this->collTemplates;
                $this->templatesScheduledForDeletion->clear();
            }

            $this->templatesScheduledForDeletion[] = $template;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->visible = null;
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
            if ($this->collFeatureAvs) {
                foreach ($this->collFeatureAvs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureProducts) {
                foreach ($this->collFeatureProducts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureTemplates) {
                foreach ($this->collFeatureTemplates as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureI18ns) {
                foreach ($this->collFeatureI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collTemplates) {
                foreach ($this->collTemplates as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collFeatureAvs = null;
        $this->collFeatureProducts = null;
        $this->collFeatureTemplates = null;
        $this->collFeatureI18ns = null;
        $this->collTemplates = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(FeatureTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildFeature The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[FeatureTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildFeature The current object (for fluent API support)
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
     * @return ChildFeatureI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collFeatureI18ns) {
                foreach ($this->collFeatureI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildFeatureI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildFeatureI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addFeatureI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildFeature The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildFeatureI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collFeatureI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collFeatureI18ns[$key]);
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
     * @return ChildFeatureI18n */
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
         * @return   \Thelia\Model\FeatureI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\FeatureI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\FeatureI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\FeatureI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

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
