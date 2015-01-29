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
use Thelia\Model\Admin as ChildAdmin;
use Thelia\Model\AdminQuery as ChildAdminQuery;
use Thelia\Model\Api as ChildApi;
use Thelia\Model\ApiQuery as ChildApiQuery;
use Thelia\Model\Profile as ChildProfile;
use Thelia\Model\ProfileI18n as ChildProfileI18n;
use Thelia\Model\ProfileI18nQuery as ChildProfileI18nQuery;
use Thelia\Model\ProfileModule as ChildProfileModule;
use Thelia\Model\ProfileModuleQuery as ChildProfileModuleQuery;
use Thelia\Model\ProfileQuery as ChildProfileQuery;
use Thelia\Model\ProfileResource as ChildProfileResource;
use Thelia\Model\ProfileResourceQuery as ChildProfileResourceQuery;
use Thelia\Model\Resource as ChildResource;
use Thelia\Model\ResourceQuery as ChildResourceQuery;
use Thelia\Model\Map\ProfileTableMap;

abstract class Profile implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\ProfileTableMap';


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
     * @var        ObjectCollection|ChildAdmin[] Collection to store aggregation of ChildAdmin objects.
     */
    protected $collAdmins;
    protected $collAdminsPartial;

    /**
     * @var        ObjectCollection|ChildProfileResource[] Collection to store aggregation of ChildProfileResource objects.
     */
    protected $collProfileResources;
    protected $collProfileResourcesPartial;

    /**
     * @var        ObjectCollection|ChildProfileModule[] Collection to store aggregation of ChildProfileModule objects.
     */
    protected $collProfileModules;
    protected $collProfileModulesPartial;

    /**
     * @var        ObjectCollection|ChildApi[] Collection to store aggregation of ChildApi objects.
     */
    protected $collApis;
    protected $collApisPartial;

    /**
     * @var        ObjectCollection|ChildProfileI18n[] Collection to store aggregation of ChildProfileI18n objects.
     */
    protected $collProfileI18ns;
    protected $collProfileI18nsPartial;

    /**
     * @var        ChildResource[] Collection to store aggregation of ChildResource objects.
     */
    protected $collResources;

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
     * @var        array[ChildProfileI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $resourcesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $adminsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $profileResourcesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $profileModulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $apisScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $profileI18nsScheduledForDeletion = null;

    /**
     * Initializes internal state of Thelia\Model\Base\Profile object.
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
     * Compares this with another <code>Profile</code> instance.  If
     * <code>obj</code> is an instance of <code>Profile</code>, delegates to
     * <code>equals(Profile)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Profile The current object, for fluid interface
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
     * @return Profile The current object, for fluid interface
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
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[ProfileTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [code] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[ProfileTableMap::CODE] = true;
        }


        return $this;
    } // setCode()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[ProfileTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[ProfileTableMap::UPDATED_AT] = true;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : ProfileTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : ProfileTableMap::translateFieldName('Code', TableMap::TYPE_PHPNAME, $indexType)];
            $this->code = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : ProfileTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : ProfileTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 4; // 4 = ProfileTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Profile object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(ProfileTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildProfileQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collAdmins = null;

            $this->collProfileResources = null;

            $this->collProfileModules = null;

            $this->collApis = null;

            $this->collProfileI18ns = null;

            $this->collResources = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Profile::setDeleted()
     * @see Profile::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProfileTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildProfileQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProfileTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(ProfileTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(ProfileTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(ProfileTableMap::UPDATED_AT)) {
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
                ProfileTableMap::addInstanceToPool($this);
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

            if ($this->resourcesScheduledForDeletion !== null) {
                if (!$this->resourcesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->resourcesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    ProfileResourceQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->resourcesScheduledForDeletion = null;
                }

                foreach ($this->getResources() as $resource) {
                    if ($resource->isModified()) {
                        $resource->save($con);
                    }
                }
            } elseif ($this->collResources) {
                foreach ($this->collResources as $resource) {
                    if ($resource->isModified()) {
                        $resource->save($con);
                    }
                }
            }

            if ($this->adminsScheduledForDeletion !== null) {
                if (!$this->adminsScheduledForDeletion->isEmpty()) {
                    foreach ($this->adminsScheduledForDeletion as $admin) {
                        // need to save related object because we set the relation to null
                        $admin->save($con);
                    }
                    $this->adminsScheduledForDeletion = null;
                }
            }

                if ($this->collAdmins !== null) {
            foreach ($this->collAdmins as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->profileResourcesScheduledForDeletion !== null) {
                if (!$this->profileResourcesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProfileResourceQuery::create()
                        ->filterByPrimaryKeys($this->profileResourcesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->profileResourcesScheduledForDeletion = null;
                }
            }

                if ($this->collProfileResources !== null) {
            foreach ($this->collProfileResources as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->profileModulesScheduledForDeletion !== null) {
                if (!$this->profileModulesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProfileModuleQuery::create()
                        ->filterByPrimaryKeys($this->profileModulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->profileModulesScheduledForDeletion = null;
                }
            }

                if ($this->collProfileModules !== null) {
            foreach ($this->collProfileModules as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->apisScheduledForDeletion !== null) {
                if (!$this->apisScheduledForDeletion->isEmpty()) {
                    foreach ($this->apisScheduledForDeletion as $api) {
                        // need to save related object because we set the relation to null
                        $api->save($con);
                    }
                    $this->apisScheduledForDeletion = null;
                }
            }

                if ($this->collApis !== null) {
            foreach ($this->collApis as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->profileI18nsScheduledForDeletion !== null) {
                if (!$this->profileI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProfileI18nQuery::create()
                        ->filterByPrimaryKeys($this->profileI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->profileI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collProfileI18ns !== null) {
            foreach ($this->collProfileI18ns as $referrerFK) {
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

        $this->modifiedColumns[ProfileTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . ProfileTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(ProfileTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(ProfileTableMap::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`CODE`';
        }
        if ($this->isColumnModified(ProfileTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(ProfileTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `profile` (%s) VALUES (%s)',
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
        $pos = ProfileTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
        if (isset($alreadyDumpedObjects['Profile'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Profile'][$this->getPrimaryKey()] = true;
        $keys = ProfileTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCode(),
            $keys[2] => $this->getCreatedAt(),
            $keys[3] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collAdmins) {
                $result['Admins'] = $this->collAdmins->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProfileResources) {
                $result['ProfileResources'] = $this->collProfileResources->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProfileModules) {
                $result['ProfileModules'] = $this->collProfileModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collApis) {
                $result['Apis'] = $this->collApis->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProfileI18ns) {
                $result['ProfileI18ns'] = $this->collProfileI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = ProfileTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
        $keys = ProfileTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCode($arr[$keys[1]]);
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
        $criteria = new Criteria(ProfileTableMap::DATABASE_NAME);

        if ($this->isColumnModified(ProfileTableMap::ID)) $criteria->add(ProfileTableMap::ID, $this->id);
        if ($this->isColumnModified(ProfileTableMap::CODE)) $criteria->add(ProfileTableMap::CODE, $this->code);
        if ($this->isColumnModified(ProfileTableMap::CREATED_AT)) $criteria->add(ProfileTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(ProfileTableMap::UPDATED_AT)) $criteria->add(ProfileTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(ProfileTableMap::DATABASE_NAME);
        $criteria->add(ProfileTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Profile (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCode($this->getCode());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getAdmins() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAdmin($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProfileResources() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProfileResource($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProfileModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProfileModule($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getApis() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addApi($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProfileI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProfileI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Profile Clone of current object.
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
        if ('Admin' == $relationName) {
            return $this->initAdmins();
        }
        if ('ProfileResource' == $relationName) {
            return $this->initProfileResources();
        }
        if ('ProfileModule' == $relationName) {
            return $this->initProfileModules();
        }
        if ('Api' == $relationName) {
            return $this->initApis();
        }
        if ('ProfileI18n' == $relationName) {
            return $this->initProfileI18ns();
        }
    }

    /**
     * Clears out the collAdmins collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAdmins()
     */
    public function clearAdmins()
    {
        $this->collAdmins = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAdmins collection loaded partially.
     */
    public function resetPartialAdmins($v = true)
    {
        $this->collAdminsPartial = $v;
    }

    /**
     * Initializes the collAdmins collection.
     *
     * By default this just sets the collAdmins collection to an empty array (like clearcollAdmins());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAdmins($overrideExisting = true)
    {
        if (null !== $this->collAdmins && !$overrideExisting) {
            return;
        }
        $this->collAdmins = new ObjectCollection();
        $this->collAdmins->setModel('\Thelia\Model\Admin');
    }

    /**
     * Gets an array of ChildAdmin objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProfile is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAdmin[] List of ChildAdmin objects
     * @throws PropelException
     */
    public function getAdmins($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAdminsPartial && !$this->isNew();
        if (null === $this->collAdmins || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAdmins) {
                // return empty collection
                $this->initAdmins();
            } else {
                $collAdmins = ChildAdminQuery::create(null, $criteria)
                    ->filterByProfile($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAdminsPartial && count($collAdmins)) {
                        $this->initAdmins(false);

                        foreach ($collAdmins as $obj) {
                            if (false == $this->collAdmins->contains($obj)) {
                                $this->collAdmins->append($obj);
                            }
                        }

                        $this->collAdminsPartial = true;
                    }

                    reset($collAdmins);

                    return $collAdmins;
                }

                if ($partial && $this->collAdmins) {
                    foreach ($this->collAdmins as $obj) {
                        if ($obj->isNew()) {
                            $collAdmins[] = $obj;
                        }
                    }
                }

                $this->collAdmins = $collAdmins;
                $this->collAdminsPartial = false;
            }
        }

        return $this->collAdmins;
    }

    /**
     * Sets a collection of Admin objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $admins A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProfile The current object (for fluent API support)
     */
    public function setAdmins(Collection $admins, ConnectionInterface $con = null)
    {
        $adminsToDelete = $this->getAdmins(new Criteria(), $con)->diff($admins);


        $this->adminsScheduledForDeletion = $adminsToDelete;

        foreach ($adminsToDelete as $adminRemoved) {
            $adminRemoved->setProfile(null);
        }

        $this->collAdmins = null;
        foreach ($admins as $admin) {
            $this->addAdmin($admin);
        }

        $this->collAdmins = $admins;
        $this->collAdminsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Admin objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Admin objects.
     * @throws PropelException
     */
    public function countAdmins(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAdminsPartial && !$this->isNew();
        if (null === $this->collAdmins || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAdmins) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAdmins());
            }

            $query = ChildAdminQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProfile($this)
                ->count($con);
        }

        return count($this->collAdmins);
    }

    /**
     * Method called to associate a ChildAdmin object to this object
     * through the ChildAdmin foreign key attribute.
     *
     * @param    ChildAdmin $l ChildAdmin
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function addAdmin(ChildAdmin $l)
    {
        if ($this->collAdmins === null) {
            $this->initAdmins();
            $this->collAdminsPartial = true;
        }

        if (!in_array($l, $this->collAdmins->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAdmin($l);
        }

        return $this;
    }

    /**
     * @param Admin $admin The admin object to add.
     */
    protected function doAddAdmin($admin)
    {
        $this->collAdmins[]= $admin;
        $admin->setProfile($this);
    }

    /**
     * @param  Admin $admin The admin object to remove.
     * @return ChildProfile The current object (for fluent API support)
     */
    public function removeAdmin($admin)
    {
        if ($this->getAdmins()->contains($admin)) {
            $this->collAdmins->remove($this->collAdmins->search($admin));
            if (null === $this->adminsScheduledForDeletion) {
                $this->adminsScheduledForDeletion = clone $this->collAdmins;
                $this->adminsScheduledForDeletion->clear();
            }
            $this->adminsScheduledForDeletion[]= $admin;
            $admin->setProfile(null);
        }

        return $this;
    }

    /**
     * Clears out the collProfileResources collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProfileResources()
     */
    public function clearProfileResources()
    {
        $this->collProfileResources = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProfileResources collection loaded partially.
     */
    public function resetPartialProfileResources($v = true)
    {
        $this->collProfileResourcesPartial = $v;
    }

    /**
     * Initializes the collProfileResources collection.
     *
     * By default this just sets the collProfileResources collection to an empty array (like clearcollProfileResources());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProfileResources($overrideExisting = true)
    {
        if (null !== $this->collProfileResources && !$overrideExisting) {
            return;
        }
        $this->collProfileResources = new ObjectCollection();
        $this->collProfileResources->setModel('\Thelia\Model\ProfileResource');
    }

    /**
     * Gets an array of ChildProfileResource objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProfile is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProfileResource[] List of ChildProfileResource objects
     * @throws PropelException
     */
    public function getProfileResources($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProfileResourcesPartial && !$this->isNew();
        if (null === $this->collProfileResources || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProfileResources) {
                // return empty collection
                $this->initProfileResources();
            } else {
                $collProfileResources = ChildProfileResourceQuery::create(null, $criteria)
                    ->filterByProfile($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProfileResourcesPartial && count($collProfileResources)) {
                        $this->initProfileResources(false);

                        foreach ($collProfileResources as $obj) {
                            if (false == $this->collProfileResources->contains($obj)) {
                                $this->collProfileResources->append($obj);
                            }
                        }

                        $this->collProfileResourcesPartial = true;
                    }

                    reset($collProfileResources);

                    return $collProfileResources;
                }

                if ($partial && $this->collProfileResources) {
                    foreach ($this->collProfileResources as $obj) {
                        if ($obj->isNew()) {
                            $collProfileResources[] = $obj;
                        }
                    }
                }

                $this->collProfileResources = $collProfileResources;
                $this->collProfileResourcesPartial = false;
            }
        }

        return $this->collProfileResources;
    }

    /**
     * Sets a collection of ProfileResource objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $profileResources A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProfile The current object (for fluent API support)
     */
    public function setProfileResources(Collection $profileResources, ConnectionInterface $con = null)
    {
        $profileResourcesToDelete = $this->getProfileResources(new Criteria(), $con)->diff($profileResources);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->profileResourcesScheduledForDeletion = clone $profileResourcesToDelete;

        foreach ($profileResourcesToDelete as $profileResourceRemoved) {
            $profileResourceRemoved->setProfile(null);
        }

        $this->collProfileResources = null;
        foreach ($profileResources as $profileResource) {
            $this->addProfileResource($profileResource);
        }

        $this->collProfileResources = $profileResources;
        $this->collProfileResourcesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProfileResource objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProfileResource objects.
     * @throws PropelException
     */
    public function countProfileResources(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProfileResourcesPartial && !$this->isNew();
        if (null === $this->collProfileResources || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProfileResources) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProfileResources());
            }

            $query = ChildProfileResourceQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProfile($this)
                ->count($con);
        }

        return count($this->collProfileResources);
    }

    /**
     * Method called to associate a ChildProfileResource object to this object
     * through the ChildProfileResource foreign key attribute.
     *
     * @param    ChildProfileResource $l ChildProfileResource
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function addProfileResource(ChildProfileResource $l)
    {
        if ($this->collProfileResources === null) {
            $this->initProfileResources();
            $this->collProfileResourcesPartial = true;
        }

        if (!in_array($l, $this->collProfileResources->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProfileResource($l);
        }

        return $this;
    }

    /**
     * @param ProfileResource $profileResource The profileResource object to add.
     */
    protected function doAddProfileResource($profileResource)
    {
        $this->collProfileResources[]= $profileResource;
        $profileResource->setProfile($this);
    }

    /**
     * @param  ProfileResource $profileResource The profileResource object to remove.
     * @return ChildProfile The current object (for fluent API support)
     */
    public function removeProfileResource($profileResource)
    {
        if ($this->getProfileResources()->contains($profileResource)) {
            $this->collProfileResources->remove($this->collProfileResources->search($profileResource));
            if (null === $this->profileResourcesScheduledForDeletion) {
                $this->profileResourcesScheduledForDeletion = clone $this->collProfileResources;
                $this->profileResourcesScheduledForDeletion->clear();
            }
            $this->profileResourcesScheduledForDeletion[]= clone $profileResource;
            $profileResource->setProfile(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Profile is new, it will return
     * an empty collection; or if this Profile has previously
     * been saved, it will retrieve related ProfileResources from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Profile.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProfileResource[] List of ChildProfileResource objects
     */
    public function getProfileResourcesJoinResource($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProfileResourceQuery::create(null, $criteria);
        $query->joinWith('Resource', $joinBehavior);

        return $this->getProfileResources($query, $con);
    }

    /**
     * Clears out the collProfileModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProfileModules()
     */
    public function clearProfileModules()
    {
        $this->collProfileModules = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProfileModules collection loaded partially.
     */
    public function resetPartialProfileModules($v = true)
    {
        $this->collProfileModulesPartial = $v;
    }

    /**
     * Initializes the collProfileModules collection.
     *
     * By default this just sets the collProfileModules collection to an empty array (like clearcollProfileModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProfileModules($overrideExisting = true)
    {
        if (null !== $this->collProfileModules && !$overrideExisting) {
            return;
        }
        $this->collProfileModules = new ObjectCollection();
        $this->collProfileModules->setModel('\Thelia\Model\ProfileModule');
    }

    /**
     * Gets an array of ChildProfileModule objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProfile is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProfileModule[] List of ChildProfileModule objects
     * @throws PropelException
     */
    public function getProfileModules($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProfileModulesPartial && !$this->isNew();
        if (null === $this->collProfileModules || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProfileModules) {
                // return empty collection
                $this->initProfileModules();
            } else {
                $collProfileModules = ChildProfileModuleQuery::create(null, $criteria)
                    ->filterByProfile($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProfileModulesPartial && count($collProfileModules)) {
                        $this->initProfileModules(false);

                        foreach ($collProfileModules as $obj) {
                            if (false == $this->collProfileModules->contains($obj)) {
                                $this->collProfileModules->append($obj);
                            }
                        }

                        $this->collProfileModulesPartial = true;
                    }

                    reset($collProfileModules);

                    return $collProfileModules;
                }

                if ($partial && $this->collProfileModules) {
                    foreach ($this->collProfileModules as $obj) {
                        if ($obj->isNew()) {
                            $collProfileModules[] = $obj;
                        }
                    }
                }

                $this->collProfileModules = $collProfileModules;
                $this->collProfileModulesPartial = false;
            }
        }

        return $this->collProfileModules;
    }

    /**
     * Sets a collection of ProfileModule objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $profileModules A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProfile The current object (for fluent API support)
     */
    public function setProfileModules(Collection $profileModules, ConnectionInterface $con = null)
    {
        $profileModulesToDelete = $this->getProfileModules(new Criteria(), $con)->diff($profileModules);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->profileModulesScheduledForDeletion = clone $profileModulesToDelete;

        foreach ($profileModulesToDelete as $profileModuleRemoved) {
            $profileModuleRemoved->setProfile(null);
        }

        $this->collProfileModules = null;
        foreach ($profileModules as $profileModule) {
            $this->addProfileModule($profileModule);
        }

        $this->collProfileModules = $profileModules;
        $this->collProfileModulesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProfileModule objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProfileModule objects.
     * @throws PropelException
     */
    public function countProfileModules(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProfileModulesPartial && !$this->isNew();
        if (null === $this->collProfileModules || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProfileModules) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProfileModules());
            }

            $query = ChildProfileModuleQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProfile($this)
                ->count($con);
        }

        return count($this->collProfileModules);
    }

    /**
     * Method called to associate a ChildProfileModule object to this object
     * through the ChildProfileModule foreign key attribute.
     *
     * @param    ChildProfileModule $l ChildProfileModule
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function addProfileModule(ChildProfileModule $l)
    {
        if ($this->collProfileModules === null) {
            $this->initProfileModules();
            $this->collProfileModulesPartial = true;
        }

        if (!in_array($l, $this->collProfileModules->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProfileModule($l);
        }

        return $this;
    }

    /**
     * @param ProfileModule $profileModule The profileModule object to add.
     */
    protected function doAddProfileModule($profileModule)
    {
        $this->collProfileModules[]= $profileModule;
        $profileModule->setProfile($this);
    }

    /**
     * @param  ProfileModule $profileModule The profileModule object to remove.
     * @return ChildProfile The current object (for fluent API support)
     */
    public function removeProfileModule($profileModule)
    {
        if ($this->getProfileModules()->contains($profileModule)) {
            $this->collProfileModules->remove($this->collProfileModules->search($profileModule));
            if (null === $this->profileModulesScheduledForDeletion) {
                $this->profileModulesScheduledForDeletion = clone $this->collProfileModules;
                $this->profileModulesScheduledForDeletion->clear();
            }
            $this->profileModulesScheduledForDeletion[]= clone $profileModule;
            $profileModule->setProfile(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Profile is new, it will return
     * an empty collection; or if this Profile has previously
     * been saved, it will retrieve related ProfileModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Profile.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProfileModule[] List of ChildProfileModule objects
     */
    public function getProfileModulesJoinModule($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProfileModuleQuery::create(null, $criteria);
        $query->joinWith('Module', $joinBehavior);

        return $this->getProfileModules($query, $con);
    }

    /**
     * Clears out the collApis collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addApis()
     */
    public function clearApis()
    {
        $this->collApis = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collApis collection loaded partially.
     */
    public function resetPartialApis($v = true)
    {
        $this->collApisPartial = $v;
    }

    /**
     * Initializes the collApis collection.
     *
     * By default this just sets the collApis collection to an empty array (like clearcollApis());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initApis($overrideExisting = true)
    {
        if (null !== $this->collApis && !$overrideExisting) {
            return;
        }
        $this->collApis = new ObjectCollection();
        $this->collApis->setModel('\Thelia\Model\Api');
    }

    /**
     * Gets an array of ChildApi objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProfile is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildApi[] List of ChildApi objects
     * @throws PropelException
     */
    public function getApis($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collApisPartial && !$this->isNew();
        if (null === $this->collApis || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collApis) {
                // return empty collection
                $this->initApis();
            } else {
                $collApis = ChildApiQuery::create(null, $criteria)
                    ->filterByProfile($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collApisPartial && count($collApis)) {
                        $this->initApis(false);

                        foreach ($collApis as $obj) {
                            if (false == $this->collApis->contains($obj)) {
                                $this->collApis->append($obj);
                            }
                        }

                        $this->collApisPartial = true;
                    }

                    reset($collApis);

                    return $collApis;
                }

                if ($partial && $this->collApis) {
                    foreach ($this->collApis as $obj) {
                        if ($obj->isNew()) {
                            $collApis[] = $obj;
                        }
                    }
                }

                $this->collApis = $collApis;
                $this->collApisPartial = false;
            }
        }

        return $this->collApis;
    }

    /**
     * Sets a collection of Api objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $apis A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProfile The current object (for fluent API support)
     */
    public function setApis(Collection $apis, ConnectionInterface $con = null)
    {
        $apisToDelete = $this->getApis(new Criteria(), $con)->diff($apis);


        $this->apisScheduledForDeletion = $apisToDelete;

        foreach ($apisToDelete as $apiRemoved) {
            $apiRemoved->setProfile(null);
        }

        $this->collApis = null;
        foreach ($apis as $api) {
            $this->addApi($api);
        }

        $this->collApis = $apis;
        $this->collApisPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Api objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Api objects.
     * @throws PropelException
     */
    public function countApis(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collApisPartial && !$this->isNew();
        if (null === $this->collApis || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collApis) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getApis());
            }

            $query = ChildApiQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProfile($this)
                ->count($con);
        }

        return count($this->collApis);
    }

    /**
     * Method called to associate a ChildApi object to this object
     * through the ChildApi foreign key attribute.
     *
     * @param    ChildApi $l ChildApi
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function addApi(ChildApi $l)
    {
        if ($this->collApis === null) {
            $this->initApis();
            $this->collApisPartial = true;
        }

        if (!in_array($l, $this->collApis->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddApi($l);
        }

        return $this;
    }

    /**
     * @param Api $api The api object to add.
     */
    protected function doAddApi($api)
    {
        $this->collApis[]= $api;
        $api->setProfile($this);
    }

    /**
     * @param  Api $api The api object to remove.
     * @return ChildProfile The current object (for fluent API support)
     */
    public function removeApi($api)
    {
        if ($this->getApis()->contains($api)) {
            $this->collApis->remove($this->collApis->search($api));
            if (null === $this->apisScheduledForDeletion) {
                $this->apisScheduledForDeletion = clone $this->collApis;
                $this->apisScheduledForDeletion->clear();
            }
            $this->apisScheduledForDeletion[]= $api;
            $api->setProfile(null);
        }

        return $this;
    }

    /**
     * Clears out the collProfileI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProfileI18ns()
     */
    public function clearProfileI18ns()
    {
        $this->collProfileI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProfileI18ns collection loaded partially.
     */
    public function resetPartialProfileI18ns($v = true)
    {
        $this->collProfileI18nsPartial = $v;
    }

    /**
     * Initializes the collProfileI18ns collection.
     *
     * By default this just sets the collProfileI18ns collection to an empty array (like clearcollProfileI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProfileI18ns($overrideExisting = true)
    {
        if (null !== $this->collProfileI18ns && !$overrideExisting) {
            return;
        }
        $this->collProfileI18ns = new ObjectCollection();
        $this->collProfileI18ns->setModel('\Thelia\Model\ProfileI18n');
    }

    /**
     * Gets an array of ChildProfileI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProfile is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProfileI18n[] List of ChildProfileI18n objects
     * @throws PropelException
     */
    public function getProfileI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProfileI18nsPartial && !$this->isNew();
        if (null === $this->collProfileI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProfileI18ns) {
                // return empty collection
                $this->initProfileI18ns();
            } else {
                $collProfileI18ns = ChildProfileI18nQuery::create(null, $criteria)
                    ->filterByProfile($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProfileI18nsPartial && count($collProfileI18ns)) {
                        $this->initProfileI18ns(false);

                        foreach ($collProfileI18ns as $obj) {
                            if (false == $this->collProfileI18ns->contains($obj)) {
                                $this->collProfileI18ns->append($obj);
                            }
                        }

                        $this->collProfileI18nsPartial = true;
                    }

                    reset($collProfileI18ns);

                    return $collProfileI18ns;
                }

                if ($partial && $this->collProfileI18ns) {
                    foreach ($this->collProfileI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collProfileI18ns[] = $obj;
                        }
                    }
                }

                $this->collProfileI18ns = $collProfileI18ns;
                $this->collProfileI18nsPartial = false;
            }
        }

        return $this->collProfileI18ns;
    }

    /**
     * Sets a collection of ProfileI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $profileI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProfile The current object (for fluent API support)
     */
    public function setProfileI18ns(Collection $profileI18ns, ConnectionInterface $con = null)
    {
        $profileI18nsToDelete = $this->getProfileI18ns(new Criteria(), $con)->diff($profileI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->profileI18nsScheduledForDeletion = clone $profileI18nsToDelete;

        foreach ($profileI18nsToDelete as $profileI18nRemoved) {
            $profileI18nRemoved->setProfile(null);
        }

        $this->collProfileI18ns = null;
        foreach ($profileI18ns as $profileI18n) {
            $this->addProfileI18n($profileI18n);
        }

        $this->collProfileI18ns = $profileI18ns;
        $this->collProfileI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProfileI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProfileI18n objects.
     * @throws PropelException
     */
    public function countProfileI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProfileI18nsPartial && !$this->isNew();
        if (null === $this->collProfileI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProfileI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProfileI18ns());
            }

            $query = ChildProfileI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProfile($this)
                ->count($con);
        }

        return count($this->collProfileI18ns);
    }

    /**
     * Method called to associate a ChildProfileI18n object to this object
     * through the ChildProfileI18n foreign key attribute.
     *
     * @param    ChildProfileI18n $l ChildProfileI18n
     * @return   \Thelia\Model\Profile The current object (for fluent API support)
     */
    public function addProfileI18n(ChildProfileI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collProfileI18ns === null) {
            $this->initProfileI18ns();
            $this->collProfileI18nsPartial = true;
        }

        if (!in_array($l, $this->collProfileI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProfileI18n($l);
        }

        return $this;
    }

    /**
     * @param ProfileI18n $profileI18n The profileI18n object to add.
     */
    protected function doAddProfileI18n($profileI18n)
    {
        $this->collProfileI18ns[]= $profileI18n;
        $profileI18n->setProfile($this);
    }

    /**
     * @param  ProfileI18n $profileI18n The profileI18n object to remove.
     * @return ChildProfile The current object (for fluent API support)
     */
    public function removeProfileI18n($profileI18n)
    {
        if ($this->getProfileI18ns()->contains($profileI18n)) {
            $this->collProfileI18ns->remove($this->collProfileI18ns->search($profileI18n));
            if (null === $this->profileI18nsScheduledForDeletion) {
                $this->profileI18nsScheduledForDeletion = clone $this->collProfileI18ns;
                $this->profileI18nsScheduledForDeletion->clear();
            }
            $this->profileI18nsScheduledForDeletion[]= clone $profileI18n;
            $profileI18n->setProfile(null);
        }

        return $this;
    }

    /**
     * Clears out the collResources collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addResources()
     */
    public function clearResources()
    {
        $this->collResources = null; // important to set this to NULL since that means it is uninitialized
        $this->collResourcesPartial = null;
    }

    /**
     * Initializes the collResources collection.
     *
     * By default this just sets the collResources collection to an empty collection (like clearResources());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initResources()
    {
        $this->collResources = new ObjectCollection();
        $this->collResources->setModel('\Thelia\Model\Resource');
    }

    /**
     * Gets a collection of ChildResource objects related by a many-to-many relationship
     * to the current object by way of the profile_resource cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProfile is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildResource[] List of ChildResource objects
     */
    public function getResources($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collResources || null !== $criteria) {
            if ($this->isNew() && null === $this->collResources) {
                // return empty collection
                $this->initResources();
            } else {
                $collResources = ChildResourceQuery::create(null, $criteria)
                    ->filterByProfile($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collResources;
                }
                $this->collResources = $collResources;
            }
        }

        return $this->collResources;
    }

    /**
     * Sets a collection of Resource objects related by a many-to-many relationship
     * to the current object by way of the profile_resource cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $resources A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildProfile The current object (for fluent API support)
     */
    public function setResources(Collection $resources, ConnectionInterface $con = null)
    {
        $this->clearResources();
        $currentResources = $this->getResources();

        $this->resourcesScheduledForDeletion = $currentResources->diff($resources);

        foreach ($resources as $resource) {
            if (!$currentResources->contains($resource)) {
                $this->doAddResource($resource);
            }
        }

        $this->collResources = $resources;

        return $this;
    }

    /**
     * Gets the number of ChildResource objects related by a many-to-many relationship
     * to the current object by way of the profile_resource cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildResource objects
     */
    public function countResources($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collResources || null !== $criteria) {
            if ($this->isNew() && null === $this->collResources) {
                return 0;
            } else {
                $query = ChildResourceQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProfile($this)
                    ->count($con);
            }
        } else {
            return count($this->collResources);
        }
    }

    /**
     * Associate a ChildResource object to this object
     * through the profile_resource cross reference table.
     *
     * @param  ChildResource $resource The ChildProfileResource object to relate
     * @return ChildProfile The current object (for fluent API support)
     */
    public function addResource(ChildResource $resource)
    {
        if ($this->collResources === null) {
            $this->initResources();
        }

        if (!$this->collResources->contains($resource)) { // only add it if the **same** object is not already associated
            $this->doAddResource($resource);
            $this->collResources[] = $resource;
        }

        return $this;
    }

    /**
     * @param    Resource $resource The resource object to add.
     */
    protected function doAddResource($resource)
    {
        $profileResource = new ChildProfileResource();
        $profileResource->setResource($resource);
        $this->addProfileResource($profileResource);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$resource->getProfiles()->contains($this)) {
            $foreignCollection   = $resource->getProfiles();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildResource object to this object
     * through the profile_resource cross reference table.
     *
     * @param ChildResource $resource The ChildProfileResource object to relate
     * @return ChildProfile The current object (for fluent API support)
     */
    public function removeResource(ChildResource $resource)
    {
        if ($this->getResources()->contains($resource)) {
            $this->collResources->remove($this->collResources->search($resource));

            if (null === $this->resourcesScheduledForDeletion) {
                $this->resourcesScheduledForDeletion = clone $this->collResources;
                $this->resourcesScheduledForDeletion->clear();
            }

            $this->resourcesScheduledForDeletion[] = $resource;
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
            if ($this->collAdmins) {
                foreach ($this->collAdmins as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProfileResources) {
                foreach ($this->collProfileResources as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProfileModules) {
                foreach ($this->collProfileModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collApis) {
                foreach ($this->collApis as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProfileI18ns) {
                foreach ($this->collProfileI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collResources) {
                foreach ($this->collResources as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collAdmins = null;
        $this->collProfileResources = null;
        $this->collProfileModules = null;
        $this->collApis = null;
        $this->collProfileI18ns = null;
        $this->collResources = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(ProfileTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildProfile The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[ProfileTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildProfile The current object (for fluent API support)
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
     * @return ChildProfileI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collProfileI18ns) {
                foreach ($this->collProfileI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildProfileI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildProfileI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addProfileI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildProfile The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildProfileI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collProfileI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collProfileI18ns[$key]);
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
     * @return ChildProfileI18n */
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
         * @return   \Thelia\Model\ProfileI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProfileI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProfileI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProfileI18n The current object (for fluent API support)
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
