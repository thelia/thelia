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
use Thelia\Model\Attribute as ChildAttribute;
use Thelia\Model\AttributeAv as ChildAttributeAv;
use Thelia\Model\AttributeAvQuery as ChildAttributeAvQuery;
use Thelia\Model\AttributeCombination as ChildAttributeCombination;
use Thelia\Model\AttributeCombinationQuery as ChildAttributeCombinationQuery;
use Thelia\Model\AttributeI18n as ChildAttributeI18n;
use Thelia\Model\AttributeI18nQuery as ChildAttributeI18nQuery;
use Thelia\Model\AttributeQuery as ChildAttributeQuery;
use Thelia\Model\AttributeTemplate as ChildAttributeTemplate;
use Thelia\Model\AttributeTemplateQuery as ChildAttributeTemplateQuery;
use Thelia\Model\Template as ChildTemplate;
use Thelia\Model\TemplateQuery as ChildTemplateQuery;
use Thelia\Model\Map\AttributeTableMap;

abstract class Attribute implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\AttributeTableMap';


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
     * @var        ObjectCollection|ChildAttributeAv[] Collection to store aggregation of ChildAttributeAv objects.
     */
    protected $collAttributeAvs;
    protected $collAttributeAvsPartial;

    /**
     * @var        ObjectCollection|ChildAttributeCombination[] Collection to store aggregation of ChildAttributeCombination objects.
     */
    protected $collAttributeCombinations;
    protected $collAttributeCombinationsPartial;

    /**
     * @var        ObjectCollection|ChildAttributeTemplate[] Collection to store aggregation of ChildAttributeTemplate objects.
     */
    protected $collAttributeTemplates;
    protected $collAttributeTemplatesPartial;

    /**
     * @var        ObjectCollection|ChildAttributeI18n[] Collection to store aggregation of ChildAttributeI18n objects.
     */
    protected $collAttributeI18ns;
    protected $collAttributeI18nsPartial;

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
     * @var        array[ChildAttributeI18n]
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
    protected $attributeAvsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $attributeCombinationsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $attributeTemplatesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $attributeI18nsScheduledForDeletion = null;

    /**
     * Initializes internal state of Thelia\Model\Base\Attribute object.
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
     * Compares this with another <code>Attribute</code> instance.  If
     * <code>obj</code> is an instance of <code>Attribute</code>, delegates to
     * <code>equals(Attribute)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Attribute The current object, for fluid interface
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
     * @return Attribute The current object, for fluid interface
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
     * @return   \Thelia\Model\Attribute The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[AttributeTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Attribute The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[AttributeTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Attribute The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[AttributeTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Attribute The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[AttributeTableMap::UPDATED_AT] = true;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : AttributeTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : AttributeTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : AttributeTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : AttributeTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 4; // 4 = AttributeTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Attribute object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(AttributeTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildAttributeQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collAttributeAvs = null;

            $this->collAttributeCombinations = null;

            $this->collAttributeTemplates = null;

            $this->collAttributeI18ns = null;

            $this->collTemplates = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Attribute::setDeleted()
     * @see Attribute::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(AttributeTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildAttributeQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(AttributeTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(AttributeTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(AttributeTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(AttributeTableMap::UPDATED_AT)) {
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
                AttributeTableMap::addInstanceToPool($this);
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

                    AttributeTemplateQuery::create()
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

            if ($this->attributeAvsScheduledForDeletion !== null) {
                if (!$this->attributeAvsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AttributeAvQuery::create()
                        ->filterByPrimaryKeys($this->attributeAvsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->attributeAvsScheduledForDeletion = null;
                }
            }

                if ($this->collAttributeAvs !== null) {
            foreach ($this->collAttributeAvs as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->attributeCombinationsScheduledForDeletion !== null) {
                if (!$this->attributeCombinationsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AttributeCombinationQuery::create()
                        ->filterByPrimaryKeys($this->attributeCombinationsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->attributeCombinationsScheduledForDeletion = null;
                }
            }

                if ($this->collAttributeCombinations !== null) {
            foreach ($this->collAttributeCombinations as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->attributeTemplatesScheduledForDeletion !== null) {
                if (!$this->attributeTemplatesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AttributeTemplateQuery::create()
                        ->filterByPrimaryKeys($this->attributeTemplatesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->attributeTemplatesScheduledForDeletion = null;
                }
            }

                if ($this->collAttributeTemplates !== null) {
            foreach ($this->collAttributeTemplates as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->attributeI18nsScheduledForDeletion !== null) {
                if (!$this->attributeI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AttributeI18nQuery::create()
                        ->filterByPrimaryKeys($this->attributeI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->attributeI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collAttributeI18ns !== null) {
            foreach ($this->collAttributeI18ns as $referrerFK) {
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

        $this->modifiedColumns[AttributeTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . AttributeTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(AttributeTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(AttributeTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(AttributeTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(AttributeTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `attribute` (%s) VALUES (%s)',
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
        $pos = AttributeTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getPosition();
                break;
            case 2:
                return $this->getCreatedAt();
                break;
            case 3:
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
        if (isset($alreadyDumpedObjects['Attribute'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Attribute'][$this->getPrimaryKey()] = true;
        $keys = AttributeTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getPosition(),
            $keys[2] => $this->getCreatedAt(),
            $keys[3] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collAttributeAvs) {
                $result['AttributeAvs'] = $this->collAttributeAvs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAttributeCombinations) {
                $result['AttributeCombinations'] = $this->collAttributeCombinations->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAttributeTemplates) {
                $result['AttributeTemplates'] = $this->collAttributeTemplates->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAttributeI18ns) {
                $result['AttributeI18ns'] = $this->collAttributeI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = AttributeTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setPosition($value);
                break;
            case 2:
                $this->setCreatedAt($value);
                break;
            case 3:
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
        $keys = AttributeTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setPosition($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCreatedAt($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setUpdatedAt($arr[$keys[3]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(AttributeTableMap::DATABASE_NAME);

        if ($this->isColumnModified(AttributeTableMap::ID)) $criteria->add(AttributeTableMap::ID, $this->id);
        if ($this->isColumnModified(AttributeTableMap::POSITION)) $criteria->add(AttributeTableMap::POSITION, $this->position);
        if ($this->isColumnModified(AttributeTableMap::CREATED_AT)) $criteria->add(AttributeTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(AttributeTableMap::UPDATED_AT)) $criteria->add(AttributeTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(AttributeTableMap::DATABASE_NAME);
        $criteria->add(AttributeTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Attribute (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getAttributeAvs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAttributeAv($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAttributeCombinations() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAttributeCombination($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAttributeTemplates() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAttributeTemplate($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAttributeI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAttributeI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Attribute Clone of current object.
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
        if ('AttributeAv' == $relationName) {
            return $this->initAttributeAvs();
        }
        if ('AttributeCombination' == $relationName) {
            return $this->initAttributeCombinations();
        }
        if ('AttributeTemplate' == $relationName) {
            return $this->initAttributeTemplates();
        }
        if ('AttributeI18n' == $relationName) {
            return $this->initAttributeI18ns();
        }
    }

    /**
     * Clears out the collAttributeAvs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAttributeAvs()
     */
    public function clearAttributeAvs()
    {
        $this->collAttributeAvs = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAttributeAvs collection loaded partially.
     */
    public function resetPartialAttributeAvs($v = true)
    {
        $this->collAttributeAvsPartial = $v;
    }

    /**
     * Initializes the collAttributeAvs collection.
     *
     * By default this just sets the collAttributeAvs collection to an empty array (like clearcollAttributeAvs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAttributeAvs($overrideExisting = true)
    {
        if (null !== $this->collAttributeAvs && !$overrideExisting) {
            return;
        }
        $this->collAttributeAvs = new ObjectCollection();
        $this->collAttributeAvs->setModel('\Thelia\Model\AttributeAv');
    }

    /**
     * Gets an array of ChildAttributeAv objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAttribute is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAttributeAv[] List of ChildAttributeAv objects
     * @throws PropelException
     */
    public function getAttributeAvs($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAttributeAvsPartial && !$this->isNew();
        if (null === $this->collAttributeAvs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAttributeAvs) {
                // return empty collection
                $this->initAttributeAvs();
            } else {
                $collAttributeAvs = ChildAttributeAvQuery::create(null, $criteria)
                    ->filterByAttribute($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAttributeAvsPartial && count($collAttributeAvs)) {
                        $this->initAttributeAvs(false);

                        foreach ($collAttributeAvs as $obj) {
                            if (false == $this->collAttributeAvs->contains($obj)) {
                                $this->collAttributeAvs->append($obj);
                            }
                        }

                        $this->collAttributeAvsPartial = true;
                    }

                    reset($collAttributeAvs);

                    return $collAttributeAvs;
                }

                if ($partial && $this->collAttributeAvs) {
                    foreach ($this->collAttributeAvs as $obj) {
                        if ($obj->isNew()) {
                            $collAttributeAvs[] = $obj;
                        }
                    }
                }

                $this->collAttributeAvs = $collAttributeAvs;
                $this->collAttributeAvsPartial = false;
            }
        }

        return $this->collAttributeAvs;
    }

    /**
     * Sets a collection of AttributeAv objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $attributeAvs A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildAttribute The current object (for fluent API support)
     */
    public function setAttributeAvs(Collection $attributeAvs, ConnectionInterface $con = null)
    {
        $attributeAvsToDelete = $this->getAttributeAvs(new Criteria(), $con)->diff($attributeAvs);


        $this->attributeAvsScheduledForDeletion = $attributeAvsToDelete;

        foreach ($attributeAvsToDelete as $attributeAvRemoved) {
            $attributeAvRemoved->setAttribute(null);
        }

        $this->collAttributeAvs = null;
        foreach ($attributeAvs as $attributeAv) {
            $this->addAttributeAv($attributeAv);
        }

        $this->collAttributeAvs = $attributeAvs;
        $this->collAttributeAvsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related AttributeAv objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related AttributeAv objects.
     * @throws PropelException
     */
    public function countAttributeAvs(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAttributeAvsPartial && !$this->isNew();
        if (null === $this->collAttributeAvs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAttributeAvs) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAttributeAvs());
            }

            $query = ChildAttributeAvQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByAttribute($this)
                ->count($con);
        }

        return count($this->collAttributeAvs);
    }

    /**
     * Method called to associate a ChildAttributeAv object to this object
     * through the ChildAttributeAv foreign key attribute.
     *
     * @param    ChildAttributeAv $l ChildAttributeAv
     * @return   \Thelia\Model\Attribute The current object (for fluent API support)
     */
    public function addAttributeAv(ChildAttributeAv $l)
    {
        if ($this->collAttributeAvs === null) {
            $this->initAttributeAvs();
            $this->collAttributeAvsPartial = true;
        }

        if (!in_array($l, $this->collAttributeAvs->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAttributeAv($l);
        }

        return $this;
    }

    /**
     * @param AttributeAv $attributeAv The attributeAv object to add.
     */
    protected function doAddAttributeAv($attributeAv)
    {
        $this->collAttributeAvs[]= $attributeAv;
        $attributeAv->setAttribute($this);
    }

    /**
     * @param  AttributeAv $attributeAv The attributeAv object to remove.
     * @return ChildAttribute The current object (for fluent API support)
     */
    public function removeAttributeAv($attributeAv)
    {
        if ($this->getAttributeAvs()->contains($attributeAv)) {
            $this->collAttributeAvs->remove($this->collAttributeAvs->search($attributeAv));
            if (null === $this->attributeAvsScheduledForDeletion) {
                $this->attributeAvsScheduledForDeletion = clone $this->collAttributeAvs;
                $this->attributeAvsScheduledForDeletion->clear();
            }
            $this->attributeAvsScheduledForDeletion[]= clone $attributeAv;
            $attributeAv->setAttribute(null);
        }

        return $this;
    }

    /**
     * Clears out the collAttributeCombinations collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAttributeCombinations()
     */
    public function clearAttributeCombinations()
    {
        $this->collAttributeCombinations = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAttributeCombinations collection loaded partially.
     */
    public function resetPartialAttributeCombinations($v = true)
    {
        $this->collAttributeCombinationsPartial = $v;
    }

    /**
     * Initializes the collAttributeCombinations collection.
     *
     * By default this just sets the collAttributeCombinations collection to an empty array (like clearcollAttributeCombinations());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAttributeCombinations($overrideExisting = true)
    {
        if (null !== $this->collAttributeCombinations && !$overrideExisting) {
            return;
        }
        $this->collAttributeCombinations = new ObjectCollection();
        $this->collAttributeCombinations->setModel('\Thelia\Model\AttributeCombination');
    }

    /**
     * Gets an array of ChildAttributeCombination objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAttribute is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAttributeCombination[] List of ChildAttributeCombination objects
     * @throws PropelException
     */
    public function getAttributeCombinations($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAttributeCombinationsPartial && !$this->isNew();
        if (null === $this->collAttributeCombinations || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAttributeCombinations) {
                // return empty collection
                $this->initAttributeCombinations();
            } else {
                $collAttributeCombinations = ChildAttributeCombinationQuery::create(null, $criteria)
                    ->filterByAttribute($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAttributeCombinationsPartial && count($collAttributeCombinations)) {
                        $this->initAttributeCombinations(false);

                        foreach ($collAttributeCombinations as $obj) {
                            if (false == $this->collAttributeCombinations->contains($obj)) {
                                $this->collAttributeCombinations->append($obj);
                            }
                        }

                        $this->collAttributeCombinationsPartial = true;
                    }

                    reset($collAttributeCombinations);

                    return $collAttributeCombinations;
                }

                if ($partial && $this->collAttributeCombinations) {
                    foreach ($this->collAttributeCombinations as $obj) {
                        if ($obj->isNew()) {
                            $collAttributeCombinations[] = $obj;
                        }
                    }
                }

                $this->collAttributeCombinations = $collAttributeCombinations;
                $this->collAttributeCombinationsPartial = false;
            }
        }

        return $this->collAttributeCombinations;
    }

    /**
     * Sets a collection of AttributeCombination objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $attributeCombinations A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildAttribute The current object (for fluent API support)
     */
    public function setAttributeCombinations(Collection $attributeCombinations, ConnectionInterface $con = null)
    {
        $attributeCombinationsToDelete = $this->getAttributeCombinations(new Criteria(), $con)->diff($attributeCombinations);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->attributeCombinationsScheduledForDeletion = clone $attributeCombinationsToDelete;

        foreach ($attributeCombinationsToDelete as $attributeCombinationRemoved) {
            $attributeCombinationRemoved->setAttribute(null);
        }

        $this->collAttributeCombinations = null;
        foreach ($attributeCombinations as $attributeCombination) {
            $this->addAttributeCombination($attributeCombination);
        }

        $this->collAttributeCombinations = $attributeCombinations;
        $this->collAttributeCombinationsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related AttributeCombination objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related AttributeCombination objects.
     * @throws PropelException
     */
    public function countAttributeCombinations(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAttributeCombinationsPartial && !$this->isNew();
        if (null === $this->collAttributeCombinations || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAttributeCombinations) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAttributeCombinations());
            }

            $query = ChildAttributeCombinationQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByAttribute($this)
                ->count($con);
        }

        return count($this->collAttributeCombinations);
    }

    /**
     * Method called to associate a ChildAttributeCombination object to this object
     * through the ChildAttributeCombination foreign key attribute.
     *
     * @param    ChildAttributeCombination $l ChildAttributeCombination
     * @return   \Thelia\Model\Attribute The current object (for fluent API support)
     */
    public function addAttributeCombination(ChildAttributeCombination $l)
    {
        if ($this->collAttributeCombinations === null) {
            $this->initAttributeCombinations();
            $this->collAttributeCombinationsPartial = true;
        }

        if (!in_array($l, $this->collAttributeCombinations->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAttributeCombination($l);
        }

        return $this;
    }

    /**
     * @param AttributeCombination $attributeCombination The attributeCombination object to add.
     */
    protected function doAddAttributeCombination($attributeCombination)
    {
        $this->collAttributeCombinations[]= $attributeCombination;
        $attributeCombination->setAttribute($this);
    }

    /**
     * @param  AttributeCombination $attributeCombination The attributeCombination object to remove.
     * @return ChildAttribute The current object (for fluent API support)
     */
    public function removeAttributeCombination($attributeCombination)
    {
        if ($this->getAttributeCombinations()->contains($attributeCombination)) {
            $this->collAttributeCombinations->remove($this->collAttributeCombinations->search($attributeCombination));
            if (null === $this->attributeCombinationsScheduledForDeletion) {
                $this->attributeCombinationsScheduledForDeletion = clone $this->collAttributeCombinations;
                $this->attributeCombinationsScheduledForDeletion->clear();
            }
            $this->attributeCombinationsScheduledForDeletion[]= clone $attributeCombination;
            $attributeCombination->setAttribute(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Attribute is new, it will return
     * an empty collection; or if this Attribute has previously
     * been saved, it will retrieve related AttributeCombinations from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Attribute.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAttributeCombination[] List of ChildAttributeCombination objects
     */
    public function getAttributeCombinationsJoinAttributeAv($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAttributeCombinationQuery::create(null, $criteria);
        $query->joinWith('AttributeAv', $joinBehavior);

        return $this->getAttributeCombinations($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Attribute is new, it will return
     * an empty collection; or if this Attribute has previously
     * been saved, it will retrieve related AttributeCombinations from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Attribute.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAttributeCombination[] List of ChildAttributeCombination objects
     */
    public function getAttributeCombinationsJoinProductSaleElements($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAttributeCombinationQuery::create(null, $criteria);
        $query->joinWith('ProductSaleElements', $joinBehavior);

        return $this->getAttributeCombinations($query, $con);
    }

    /**
     * Clears out the collAttributeTemplates collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAttributeTemplates()
     */
    public function clearAttributeTemplates()
    {
        $this->collAttributeTemplates = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAttributeTemplates collection loaded partially.
     */
    public function resetPartialAttributeTemplates($v = true)
    {
        $this->collAttributeTemplatesPartial = $v;
    }

    /**
     * Initializes the collAttributeTemplates collection.
     *
     * By default this just sets the collAttributeTemplates collection to an empty array (like clearcollAttributeTemplates());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAttributeTemplates($overrideExisting = true)
    {
        if (null !== $this->collAttributeTemplates && !$overrideExisting) {
            return;
        }
        $this->collAttributeTemplates = new ObjectCollection();
        $this->collAttributeTemplates->setModel('\Thelia\Model\AttributeTemplate');
    }

    /**
     * Gets an array of ChildAttributeTemplate objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAttribute is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAttributeTemplate[] List of ChildAttributeTemplate objects
     * @throws PropelException
     */
    public function getAttributeTemplates($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAttributeTemplatesPartial && !$this->isNew();
        if (null === $this->collAttributeTemplates || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAttributeTemplates) {
                // return empty collection
                $this->initAttributeTemplates();
            } else {
                $collAttributeTemplates = ChildAttributeTemplateQuery::create(null, $criteria)
                    ->filterByAttribute($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAttributeTemplatesPartial && count($collAttributeTemplates)) {
                        $this->initAttributeTemplates(false);

                        foreach ($collAttributeTemplates as $obj) {
                            if (false == $this->collAttributeTemplates->contains($obj)) {
                                $this->collAttributeTemplates->append($obj);
                            }
                        }

                        $this->collAttributeTemplatesPartial = true;
                    }

                    reset($collAttributeTemplates);

                    return $collAttributeTemplates;
                }

                if ($partial && $this->collAttributeTemplates) {
                    foreach ($this->collAttributeTemplates as $obj) {
                        if ($obj->isNew()) {
                            $collAttributeTemplates[] = $obj;
                        }
                    }
                }

                $this->collAttributeTemplates = $collAttributeTemplates;
                $this->collAttributeTemplatesPartial = false;
            }
        }

        return $this->collAttributeTemplates;
    }

    /**
     * Sets a collection of AttributeTemplate objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $attributeTemplates A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildAttribute The current object (for fluent API support)
     */
    public function setAttributeTemplates(Collection $attributeTemplates, ConnectionInterface $con = null)
    {
        $attributeTemplatesToDelete = $this->getAttributeTemplates(new Criteria(), $con)->diff($attributeTemplates);


        $this->attributeTemplatesScheduledForDeletion = $attributeTemplatesToDelete;

        foreach ($attributeTemplatesToDelete as $attributeTemplateRemoved) {
            $attributeTemplateRemoved->setAttribute(null);
        }

        $this->collAttributeTemplates = null;
        foreach ($attributeTemplates as $attributeTemplate) {
            $this->addAttributeTemplate($attributeTemplate);
        }

        $this->collAttributeTemplates = $attributeTemplates;
        $this->collAttributeTemplatesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related AttributeTemplate objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related AttributeTemplate objects.
     * @throws PropelException
     */
    public function countAttributeTemplates(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAttributeTemplatesPartial && !$this->isNew();
        if (null === $this->collAttributeTemplates || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAttributeTemplates) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAttributeTemplates());
            }

            $query = ChildAttributeTemplateQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByAttribute($this)
                ->count($con);
        }

        return count($this->collAttributeTemplates);
    }

    /**
     * Method called to associate a ChildAttributeTemplate object to this object
     * through the ChildAttributeTemplate foreign key attribute.
     *
     * @param    ChildAttributeTemplate $l ChildAttributeTemplate
     * @return   \Thelia\Model\Attribute The current object (for fluent API support)
     */
    public function addAttributeTemplate(ChildAttributeTemplate $l)
    {
        if ($this->collAttributeTemplates === null) {
            $this->initAttributeTemplates();
            $this->collAttributeTemplatesPartial = true;
        }

        if (!in_array($l, $this->collAttributeTemplates->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAttributeTemplate($l);
        }

        return $this;
    }

    /**
     * @param AttributeTemplate $attributeTemplate The attributeTemplate object to add.
     */
    protected function doAddAttributeTemplate($attributeTemplate)
    {
        $this->collAttributeTemplates[]= $attributeTemplate;
        $attributeTemplate->setAttribute($this);
    }

    /**
     * @param  AttributeTemplate $attributeTemplate The attributeTemplate object to remove.
     * @return ChildAttribute The current object (for fluent API support)
     */
    public function removeAttributeTemplate($attributeTemplate)
    {
        if ($this->getAttributeTemplates()->contains($attributeTemplate)) {
            $this->collAttributeTemplates->remove($this->collAttributeTemplates->search($attributeTemplate));
            if (null === $this->attributeTemplatesScheduledForDeletion) {
                $this->attributeTemplatesScheduledForDeletion = clone $this->collAttributeTemplates;
                $this->attributeTemplatesScheduledForDeletion->clear();
            }
            $this->attributeTemplatesScheduledForDeletion[]= clone $attributeTemplate;
            $attributeTemplate->setAttribute(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Attribute is new, it will return
     * an empty collection; or if this Attribute has previously
     * been saved, it will retrieve related AttributeTemplates from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Attribute.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAttributeTemplate[] List of ChildAttributeTemplate objects
     */
    public function getAttributeTemplatesJoinTemplate($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAttributeTemplateQuery::create(null, $criteria);
        $query->joinWith('Template', $joinBehavior);

        return $this->getAttributeTemplates($query, $con);
    }

    /**
     * Clears out the collAttributeI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAttributeI18ns()
     */
    public function clearAttributeI18ns()
    {
        $this->collAttributeI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAttributeI18ns collection loaded partially.
     */
    public function resetPartialAttributeI18ns($v = true)
    {
        $this->collAttributeI18nsPartial = $v;
    }

    /**
     * Initializes the collAttributeI18ns collection.
     *
     * By default this just sets the collAttributeI18ns collection to an empty array (like clearcollAttributeI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAttributeI18ns($overrideExisting = true)
    {
        if (null !== $this->collAttributeI18ns && !$overrideExisting) {
            return;
        }
        $this->collAttributeI18ns = new ObjectCollection();
        $this->collAttributeI18ns->setModel('\Thelia\Model\AttributeI18n');
    }

    /**
     * Gets an array of ChildAttributeI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAttribute is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAttributeI18n[] List of ChildAttributeI18n objects
     * @throws PropelException
     */
    public function getAttributeI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAttributeI18nsPartial && !$this->isNew();
        if (null === $this->collAttributeI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAttributeI18ns) {
                // return empty collection
                $this->initAttributeI18ns();
            } else {
                $collAttributeI18ns = ChildAttributeI18nQuery::create(null, $criteria)
                    ->filterByAttribute($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAttributeI18nsPartial && count($collAttributeI18ns)) {
                        $this->initAttributeI18ns(false);

                        foreach ($collAttributeI18ns as $obj) {
                            if (false == $this->collAttributeI18ns->contains($obj)) {
                                $this->collAttributeI18ns->append($obj);
                            }
                        }

                        $this->collAttributeI18nsPartial = true;
                    }

                    reset($collAttributeI18ns);

                    return $collAttributeI18ns;
                }

                if ($partial && $this->collAttributeI18ns) {
                    foreach ($this->collAttributeI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collAttributeI18ns[] = $obj;
                        }
                    }
                }

                $this->collAttributeI18ns = $collAttributeI18ns;
                $this->collAttributeI18nsPartial = false;
            }
        }

        return $this->collAttributeI18ns;
    }

    /**
     * Sets a collection of AttributeI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $attributeI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildAttribute The current object (for fluent API support)
     */
    public function setAttributeI18ns(Collection $attributeI18ns, ConnectionInterface $con = null)
    {
        $attributeI18nsToDelete = $this->getAttributeI18ns(new Criteria(), $con)->diff($attributeI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->attributeI18nsScheduledForDeletion = clone $attributeI18nsToDelete;

        foreach ($attributeI18nsToDelete as $attributeI18nRemoved) {
            $attributeI18nRemoved->setAttribute(null);
        }

        $this->collAttributeI18ns = null;
        foreach ($attributeI18ns as $attributeI18n) {
            $this->addAttributeI18n($attributeI18n);
        }

        $this->collAttributeI18ns = $attributeI18ns;
        $this->collAttributeI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related AttributeI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related AttributeI18n objects.
     * @throws PropelException
     */
    public function countAttributeI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAttributeI18nsPartial && !$this->isNew();
        if (null === $this->collAttributeI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAttributeI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAttributeI18ns());
            }

            $query = ChildAttributeI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByAttribute($this)
                ->count($con);
        }

        return count($this->collAttributeI18ns);
    }

    /**
     * Method called to associate a ChildAttributeI18n object to this object
     * through the ChildAttributeI18n foreign key attribute.
     *
     * @param    ChildAttributeI18n $l ChildAttributeI18n
     * @return   \Thelia\Model\Attribute The current object (for fluent API support)
     */
    public function addAttributeI18n(ChildAttributeI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collAttributeI18ns === null) {
            $this->initAttributeI18ns();
            $this->collAttributeI18nsPartial = true;
        }

        if (!in_array($l, $this->collAttributeI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAttributeI18n($l);
        }

        return $this;
    }

    /**
     * @param AttributeI18n $attributeI18n The attributeI18n object to add.
     */
    protected function doAddAttributeI18n($attributeI18n)
    {
        $this->collAttributeI18ns[]= $attributeI18n;
        $attributeI18n->setAttribute($this);
    }

    /**
     * @param  AttributeI18n $attributeI18n The attributeI18n object to remove.
     * @return ChildAttribute The current object (for fluent API support)
     */
    public function removeAttributeI18n($attributeI18n)
    {
        if ($this->getAttributeI18ns()->contains($attributeI18n)) {
            $this->collAttributeI18ns->remove($this->collAttributeI18ns->search($attributeI18n));
            if (null === $this->attributeI18nsScheduledForDeletion) {
                $this->attributeI18nsScheduledForDeletion = clone $this->collAttributeI18ns;
                $this->attributeI18nsScheduledForDeletion->clear();
            }
            $this->attributeI18nsScheduledForDeletion[]= clone $attributeI18n;
            $attributeI18n->setAttribute(null);
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
     * to the current object by way of the attribute_template cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAttribute is new, it will return
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
                    ->filterByAttribute($this)
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
     * to the current object by way of the attribute_template cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $templates A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildAttribute The current object (for fluent API support)
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
     * to the current object by way of the attribute_template cross-reference table.
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
                    ->filterByAttribute($this)
                    ->count($con);
            }
        } else {
            return count($this->collTemplates);
        }
    }

    /**
     * Associate a ChildTemplate object to this object
     * through the attribute_template cross reference table.
     *
     * @param  ChildTemplate $template The ChildAttributeTemplate object to relate
     * @return ChildAttribute The current object (for fluent API support)
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
        $attributeTemplate = new ChildAttributeTemplate();
        $attributeTemplate->setTemplate($template);
        $this->addAttributeTemplate($attributeTemplate);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$template->getAttributes()->contains($this)) {
            $foreignCollection   = $template->getAttributes();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildTemplate object to this object
     * through the attribute_template cross reference table.
     *
     * @param ChildTemplate $template The ChildAttributeTemplate object to relate
     * @return ChildAttribute The current object (for fluent API support)
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
            if ($this->collAttributeAvs) {
                foreach ($this->collAttributeAvs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAttributeCombinations) {
                foreach ($this->collAttributeCombinations as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAttributeTemplates) {
                foreach ($this->collAttributeTemplates as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAttributeI18ns) {
                foreach ($this->collAttributeI18ns as $o) {
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

        $this->collAttributeAvs = null;
        $this->collAttributeCombinations = null;
        $this->collAttributeTemplates = null;
        $this->collAttributeI18ns = null;
        $this->collTemplates = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(AttributeTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildAttribute The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[AttributeTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildAttribute The current object (for fluent API support)
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
     * @return ChildAttributeI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collAttributeI18ns) {
                foreach ($this->collAttributeI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildAttributeI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildAttributeI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addAttributeI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildAttribute The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildAttributeI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collAttributeI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collAttributeI18ns[$key]);
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
     * @return ChildAttributeI18n */
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
         * @return   \Thelia\Model\AttributeI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\AttributeI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\AttributeI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\AttributeI18n The current object (for fluent API support)
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
