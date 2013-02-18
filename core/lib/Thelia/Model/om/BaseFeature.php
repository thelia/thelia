<?php

namespace Thelia\Model\om;

use \BaseObject;
use \BasePeer;
use \Criteria;
use \DateTime;
use \Exception;
use \PDO;
use \Persistent;
use \Propel;
use \PropelCollection;
use \PropelDateTime;
use \PropelException;
use \PropelObjectCollection;
use \PropelPDO;
use Thelia\Model\Feature;
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureCategory;
use Thelia\Model\FeatureCategoryQuery;
use Thelia\Model\FeatureI18n;
use Thelia\Model\FeatureI18nQuery;
use Thelia\Model\FeaturePeer;
use Thelia\Model\FeatureProd;
use Thelia\Model\FeatureProdQuery;
use Thelia\Model\FeatureQuery;

/**
 * Base class that represents a row from the 'feature' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseFeature extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\FeaturePeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        FeaturePeer
     */
    protected static $peer;

    /**
     * The flag var to prevent infinit loop in deep copy
     * @var       boolean
     */
    protected $startCopy = false;

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
     * @var        PropelObjectCollection|FeatureAv[] Collection to store aggregation of FeatureAv objects.
     */
    protected $collFeatureAvs;
    protected $collFeatureAvsPartial;

    /**
     * @var        PropelObjectCollection|FeatureProd[] Collection to store aggregation of FeatureProd objects.
     */
    protected $collFeatureProds;
    protected $collFeatureProdsPartial;

    /**
     * @var        PropelObjectCollection|FeatureCategory[] Collection to store aggregation of FeatureCategory objects.
     */
    protected $collFeatureCategorys;
    protected $collFeatureCategorysPartial;

    /**
     * @var        PropelObjectCollection|FeatureI18n[] Collection to store aggregation of FeatureI18n objects.
     */
    protected $collFeatureI18ns;
    protected $collFeatureI18nsPartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     * @var        boolean
     */
    protected $alreadyInSave = false;

    /**
     * Flag to prevent endless validation loop, if this object is referenced
     * by another object which falls in this transaction.
     * @var        boolean
     */
    protected $alreadyInValidation = false;

    /**
     * Flag to prevent endless clearAllReferences($deep=true) loop, if this object is referenced
     * @var        boolean
     */
    protected $alreadyInClearAllReferencesDeep = false;

    // i18n behavior

    /**
     * Current locale
     * @var        string
     */
    protected $currentLocale = 'en_US';

    /**
     * Current translation objects
     * @var        array[FeatureI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $featureAvsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $featureProdsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $featureCategorysScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $featureI18nsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see        __construct()
     */
    public function applyDefaultValues()
    {
        $this->visible = 0;
    }

    /**
     * Initializes internal state of BaseFeature object.
     * @see        applyDefaults()
     */
    public function __construct()
    {
        parent::__construct();
        $this->applyDefaultValues();
    }

    /**
     * Get the [id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the [visible] column value.
     *
     * @return int
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Get the [position] column value.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Get the [optionally formatted] temporal [created_at] column value.
     *
     *
     * @param string $format The date/time format string (either date()-style or strftime()-style).
     *				 If format is null, then the raw DateTime object will be returned.
     * @return mixed Formatted date/time value as string or DateTime object (if format is null), null if column is null, and 0 if column value is 0000-00-00 00:00:00
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCreatedAt($format = 'Y-m-d H:i:s')
    {
        if ($this->created_at === null) {
            return null;
        }

        if ($this->created_at === '0000-00-00 00:00:00') {
            // while technically this is not a default value of null,
            // this seems to be closest in meaning.
            return null;
        }

        try {
            $dt = new DateTime($this->created_at);
        } catch (Exception $x) {
            throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($this->created_at, true), $x);
        }

        if ($format === null) {
            // Because propel.useDateTimeClass is true, we return a DateTime object.
            return $dt;
        }

        if (strpos($format, '%') !== false) {
            return strftime($format, $dt->format('U'));
        }

        return $dt->format($format);

    }

    /**
     * Get the [optionally formatted] temporal [updated_at] column value.
     *
     *
     * @param string $format The date/time format string (either date()-style or strftime()-style).
     *				 If format is null, then the raw DateTime object will be returned.
     * @return mixed Formatted date/time value as string or DateTime object (if format is null), null if column is null, and 0 if column value is 0000-00-00 00:00:00
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getUpdatedAt($format = 'Y-m-d H:i:s')
    {
        if ($this->updated_at === null) {
            return null;
        }

        if ($this->updated_at === '0000-00-00 00:00:00') {
            // while technically this is not a default value of null,
            // this seems to be closest in meaning.
            return null;
        }

        try {
            $dt = new DateTime($this->updated_at);
        } catch (Exception $x) {
            throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($this->updated_at, true), $x);
        }

        if ($format === null) {
            // Because propel.useDateTimeClass is true, we return a DateTime object.
            return $dt;
        }

        if (strpos($format, '%') !== false) {
            return strftime($format, $dt->format('U'));
        }

        return $dt->format($format);

    }

    /**
     * Set the value of [id] column.
     *
     * @param int $v new value
     * @return Feature The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = FeaturePeer::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [visible] column.
     *
     * @param int $v new value
     * @return Feature The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[] = FeaturePeer::VISIBLE;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [position] column.
     *
     * @param int $v new value
     * @return Feature The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[] = FeaturePeer::POSITION;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Feature The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = FeaturePeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Feature The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = FeaturePeer::UPDATED_AT;
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

        // otherwise, everything was equal, so return true
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
     * @param array $row The row returned by PDOStatement->fetch(PDO::FETCH_NUM)
     * @param int $startcol 0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false)
    {
        try {

            $this->id = ($row[$startcol + 0] !== null) ? (int) $row[$startcol + 0] : null;
            $this->visible = ($row[$startcol + 1] !== null) ? (int) $row[$startcol + 1] : null;
            $this->position = ($row[$startcol + 2] !== null) ? (int) $row[$startcol + 2] : null;
            $this->created_at = ($row[$startcol + 3] !== null) ? (string) $row[$startcol + 3] : null;
            $this->updated_at = ($row[$startcol + 4] !== null) ? (string) $row[$startcol + 4] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }
            $this->postHydrate($row, $startcol, $rehydrate);
            return $startcol + 5; // 5 = FeaturePeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Feature object", $e);
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
     * @param boolean $deep (optional) Whether to also de-associated any related objects.
     * @param PropelPDO $con (optional) The PropelPDO connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, PropelPDO $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getConnection(FeaturePeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = FeaturePeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collFeatureAvs = null;

            $this->collFeatureProds = null;

            $this->collFeatureCategorys = null;

            $this->collFeatureI18ns = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param PropelPDO $con
     * @return void
     * @throws PropelException
     * @throws Exception
     * @see        BaseObject::setDeleted()
     * @see        BaseObject::isDeleted()
     */
    public function delete(PropelPDO $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getConnection(FeaturePeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = FeatureQuery::create()
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
     * @param PropelPDO $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @throws Exception
     * @see        doSave()
     */
    public function save(PropelPDO $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getConnection(FeaturePeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(FeaturePeer::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(FeaturePeer::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(FeaturePeer::UPDATED_AT)) {
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
                FeaturePeer::addInstanceToPool($this);
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
     * @param PropelPDO $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see        save()
     */
    protected function doSave(PropelPDO $con)
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

            if ($this->featureAvsScheduledForDeletion !== null) {
                if (!$this->featureAvsScheduledForDeletion->isEmpty()) {
                    FeatureAvQuery::create()
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

            if ($this->featureProdsScheduledForDeletion !== null) {
                if (!$this->featureProdsScheduledForDeletion->isEmpty()) {
                    FeatureProdQuery::create()
                        ->filterByPrimaryKeys($this->featureProdsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureProdsScheduledForDeletion = null;
                }
            }

            if ($this->collFeatureProds !== null) {
                foreach ($this->collFeatureProds as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureCategorysScheduledForDeletion !== null) {
                if (!$this->featureCategorysScheduledForDeletion->isEmpty()) {
                    FeatureCategoryQuery::create()
                        ->filterByPrimaryKeys($this->featureCategorysScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureCategorysScheduledForDeletion = null;
                }
            }

            if ($this->collFeatureCategorys !== null) {
                foreach ($this->collFeatureCategorys as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureI18nsScheduledForDeletion !== null) {
                if (!$this->featureI18nsScheduledForDeletion->isEmpty()) {
                    FeatureI18nQuery::create()
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
     * @param PropelPDO $con
     *
     * @throws PropelException
     * @see        doSave()
     */
    protected function doInsert(PropelPDO $con)
    {
        $modifiedColumns = array();
        $index = 0;

        $this->modifiedColumns[] = FeaturePeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . FeaturePeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(FeaturePeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`id`';
        }
        if ($this->isColumnModified(FeaturePeer::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`visible`';
        }
        if ($this->isColumnModified(FeaturePeer::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`position`';
        }
        if ($this->isColumnModified(FeaturePeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`created_at`';
        }
        if ($this->isColumnModified(FeaturePeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`updated_at`';
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
                    case '`id`':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case '`visible`':
                        $stmt->bindValue($identifier, $this->visible, PDO::PARAM_INT);
                        break;
                    case '`position`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
                        break;
                    case '`created_at`':
                        $stmt->bindValue($identifier, $this->created_at, PDO::PARAM_STR);
                        break;
                    case '`updated_at`':
                        $stmt->bindValue($identifier, $this->updated_at, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', $e);
        }
        $this->setId($pk);

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param PropelPDO $con
     *
     * @see        doSave()
     */
    protected function doUpdate(PropelPDO $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();
        BasePeer::doUpdate($selectCriteria, $valuesCriteria, $con);
    }

    /**
     * Array of ValidationFailed objects.
     * @var        array ValidationFailed[]
     */
    protected $validationFailures = array();

    /**
     * Gets any ValidationFailed objects that resulted from last call to validate().
     *
     *
     * @return array ValidationFailed[]
     * @see        validate()
     */
    public function getValidationFailures()
    {
        return $this->validationFailures;
    }

    /**
     * Validates the objects modified field values and all objects related to this table.
     *
     * If $columns is either a column name or an array of column names
     * only those columns are validated.
     *
     * @param mixed $columns Column name or an array of column names.
     * @return boolean Whether all columns pass validation.
     * @see        doValidate()
     * @see        getValidationFailures()
     */
    public function validate($columns = null)
    {
        $res = $this->doValidate($columns);
        if ($res === true) {
            $this->validationFailures = array();

            return true;
        }

        $this->validationFailures = $res;

        return false;
    }

    /**
     * This function performs the validation work for complex object models.
     *
     * In addition to checking the current object, all related objects will
     * also be validated.  If all pass then <code>true</code> is returned; otherwise
     * an aggreagated array of ValidationFailed objects will be returned.
     *
     * @param array $columns Array of column names to validate.
     * @return mixed <code>true</code> if all validations pass; array of <code>ValidationFailed</code> objets otherwise.
     */
    protected function doValidate($columns = null)
    {
        if (!$this->alreadyInValidation) {
            $this->alreadyInValidation = true;
            $retval = null;

            $failureMap = array();


            if (($retval = FeaturePeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collFeatureAvs !== null) {
                    foreach ($this->collFeatureAvs as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collFeatureProds !== null) {
                    foreach ($this->collFeatureProds as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collFeatureCategorys !== null) {
                    foreach ($this->collFeatureCategorys as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collFeatureI18ns !== null) {
                    foreach ($this->collFeatureI18ns as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }


            $this->alreadyInValidation = false;
        }

        return (!empty($failureMap) ? $failureMap : true);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param string $name name
     * @param string $type The type of fieldname the $name is of:
     *               one of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *               BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM.
     *               Defaults to BasePeer::TYPE_PHPNAME
     * @return mixed Value of field.
     */
    public function getByName($name, $type = BasePeer::TYPE_PHPNAME)
    {
        $pos = FeaturePeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param int $pos position in xml schema
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
     * @param     string  $keyType (optional) One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME,
     *                    BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM.
     *                    Defaults to BasePeer::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to true.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = BasePeer::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {
        if (isset($alreadyDumpedObjects['Feature'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Feature'][$this->getPrimaryKey()] = true;
        $keys = FeaturePeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getVisible(),
            $keys[2] => $this->getPosition(),
            $keys[3] => $this->getCreatedAt(),
            $keys[4] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->collFeatureAvs) {
                $result['FeatureAvs'] = $this->collFeatureAvs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureProds) {
                $result['FeatureProds'] = $this->collFeatureProds->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureCategorys) {
                $result['FeatureCategorys'] = $this->collFeatureCategorys->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
     * @param string $name peer name
     * @param mixed $value field value
     * @param string $type The type of fieldname the $name is of:
     *                     one of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                     BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM.
     *                     Defaults to BasePeer::TYPE_PHPNAME
     * @return void
     */
    public function setByName($name, $value, $type = BasePeer::TYPE_PHPNAME)
    {
        $pos = FeaturePeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

        $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param int $pos position in xml schema
     * @param mixed $value field value
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
     * of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME,
     * BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM.
     * The default key type is the column's BasePeer::TYPE_PHPNAME
     *
     * @param array  $arr     An array to populate the object from.
     * @param string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = BasePeer::TYPE_PHPNAME)
    {
        $keys = FeaturePeer::getFieldNames($keyType);

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
        $criteria = new Criteria(FeaturePeer::DATABASE_NAME);

        if ($this->isColumnModified(FeaturePeer::ID)) $criteria->add(FeaturePeer::ID, $this->id);
        if ($this->isColumnModified(FeaturePeer::VISIBLE)) $criteria->add(FeaturePeer::VISIBLE, $this->visible);
        if ($this->isColumnModified(FeaturePeer::POSITION)) $criteria->add(FeaturePeer::POSITION, $this->position);
        if ($this->isColumnModified(FeaturePeer::CREATED_AT)) $criteria->add(FeaturePeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(FeaturePeer::UPDATED_AT)) $criteria->add(FeaturePeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(FeaturePeer::DATABASE_NAME);
        $criteria->add(FeaturePeer::ID, $this->id);

        return $criteria;
    }

    /**
     * Returns the primary key for this object (row).
     * @return int
     */
    public function getPrimaryKey()
    {
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (id column).
     *
     * @param  int $key Primary key.
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
     * @param object $copyObj An object of Feature (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setVisible($this->getVisible());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            foreach ($this->getFeatureAvs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureAv($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureProds() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureProd($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureCategorys() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureCategory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureI18n($relObj->copy($deepCopy));
                }
            }

            //unflag object copy
            $this->startCopy = false;
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
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return Feature Clone of current object.
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
     * Returns a peer instance associated with this om.
     *
     * Since Peer classes are not to have any instance attributes, this method returns the
     * same instance for all member of this class. The method could therefore
     * be static, but this would prevent one from overriding the behavior.
     *
     * @return FeaturePeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new FeaturePeer();
        }

        return self::$peer;
    }


    /**
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('FeatureAv' == $relationName) {
            $this->initFeatureAvs();
        }
        if ('FeatureProd' == $relationName) {
            $this->initFeatureProds();
        }
        if ('FeatureCategory' == $relationName) {
            $this->initFeatureCategorys();
        }
        if ('FeatureI18n' == $relationName) {
            $this->initFeatureI18ns();
        }
    }

    /**
     * Clears out the collFeatureAvs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Feature The current object (for fluent API support)
     * @see        addFeatureAvs()
     */
    public function clearFeatureAvs()
    {
        $this->collFeatureAvs = null; // important to set this to null since that means it is uninitialized
        $this->collFeatureAvsPartial = null;

        return $this;
    }

    /**
     * reset is the collFeatureAvs collection loaded partially
     *
     * @return void
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
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureAvs($overrideExisting = true)
    {
        if (null !== $this->collFeatureAvs && !$overrideExisting) {
            return;
        }
        $this->collFeatureAvs = new PropelObjectCollection();
        $this->collFeatureAvs->setModel('FeatureAv');
    }

    /**
     * Gets an array of FeatureAv objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Feature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|FeatureAv[] List of FeatureAv objects
     * @throws PropelException
     */
    public function getFeatureAvs($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collFeatureAvsPartial && !$this->isNew();
        if (null === $this->collFeatureAvs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureAvs) {
                // return empty collection
                $this->initFeatureAvs();
            } else {
                $collFeatureAvs = FeatureAvQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collFeatureAvsPartial && count($collFeatureAvs)) {
                      $this->initFeatureAvs(false);

                      foreach($collFeatureAvs as $obj) {
                        if (false == $this->collFeatureAvs->contains($obj)) {
                          $this->collFeatureAvs->append($obj);
                        }
                      }

                      $this->collFeatureAvsPartial = true;
                    }

                    $collFeatureAvs->getInternalIterator()->rewind();
                    return $collFeatureAvs;
                }

                if($partial && $this->collFeatureAvs) {
                    foreach($this->collFeatureAvs as $obj) {
                        if($obj->isNew()) {
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
     * @param PropelCollection $featureAvs A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Feature The current object (for fluent API support)
     */
    public function setFeatureAvs(PropelCollection $featureAvs, PropelPDO $con = null)
    {
        $featureAvsToDelete = $this->getFeatureAvs(new Criteria(), $con)->diff($featureAvs);

        $this->featureAvsScheduledForDeletion = unserialize(serialize($featureAvsToDelete));

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
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related FeatureAv objects.
     * @throws PropelException
     */
    public function countFeatureAvs(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collFeatureAvsPartial && !$this->isNew();
        if (null === $this->collFeatureAvs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureAvs) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getFeatureAvs());
            }
            $query = FeatureAvQuery::create(null, $criteria);
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
     * Method called to associate a FeatureAv object to this object
     * through the FeatureAv foreign key attribute.
     *
     * @param    FeatureAv $l FeatureAv
     * @return Feature The current object (for fluent API support)
     */
    public function addFeatureAv(FeatureAv $l)
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
     * @param	FeatureAv $featureAv The featureAv object to add.
     */
    protected function doAddFeatureAv($featureAv)
    {
        $this->collFeatureAvs[]= $featureAv;
        $featureAv->setFeature($this);
    }

    /**
     * @param	FeatureAv $featureAv The featureAv object to remove.
     * @return Feature The current object (for fluent API support)
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
     * Clears out the collFeatureProds collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Feature The current object (for fluent API support)
     * @see        addFeatureProds()
     */
    public function clearFeatureProds()
    {
        $this->collFeatureProds = null; // important to set this to null since that means it is uninitialized
        $this->collFeatureProdsPartial = null;

        return $this;
    }

    /**
     * reset is the collFeatureProds collection loaded partially
     *
     * @return void
     */
    public function resetPartialFeatureProds($v = true)
    {
        $this->collFeatureProdsPartial = $v;
    }

    /**
     * Initializes the collFeatureProds collection.
     *
     * By default this just sets the collFeatureProds collection to an empty array (like clearcollFeatureProds());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureProds($overrideExisting = true)
    {
        if (null !== $this->collFeatureProds && !$overrideExisting) {
            return;
        }
        $this->collFeatureProds = new PropelObjectCollection();
        $this->collFeatureProds->setModel('FeatureProd');
    }

    /**
     * Gets an array of FeatureProd objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Feature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|FeatureProd[] List of FeatureProd objects
     * @throws PropelException
     */
    public function getFeatureProds($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collFeatureProdsPartial && !$this->isNew();
        if (null === $this->collFeatureProds || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureProds) {
                // return empty collection
                $this->initFeatureProds();
            } else {
                $collFeatureProds = FeatureProdQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collFeatureProdsPartial && count($collFeatureProds)) {
                      $this->initFeatureProds(false);

                      foreach($collFeatureProds as $obj) {
                        if (false == $this->collFeatureProds->contains($obj)) {
                          $this->collFeatureProds->append($obj);
                        }
                      }

                      $this->collFeatureProdsPartial = true;
                    }

                    $collFeatureProds->getInternalIterator()->rewind();
                    return $collFeatureProds;
                }

                if($partial && $this->collFeatureProds) {
                    foreach($this->collFeatureProds as $obj) {
                        if($obj->isNew()) {
                            $collFeatureProds[] = $obj;
                        }
                    }
                }

                $this->collFeatureProds = $collFeatureProds;
                $this->collFeatureProdsPartial = false;
            }
        }

        return $this->collFeatureProds;
    }

    /**
     * Sets a collection of FeatureProd objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $featureProds A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Feature The current object (for fluent API support)
     */
    public function setFeatureProds(PropelCollection $featureProds, PropelPDO $con = null)
    {
        $featureProdsToDelete = $this->getFeatureProds(new Criteria(), $con)->diff($featureProds);

        $this->featureProdsScheduledForDeletion = unserialize(serialize($featureProdsToDelete));

        foreach ($featureProdsToDelete as $featureProdRemoved) {
            $featureProdRemoved->setFeature(null);
        }

        $this->collFeatureProds = null;
        foreach ($featureProds as $featureProd) {
            $this->addFeatureProd($featureProd);
        }

        $this->collFeatureProds = $featureProds;
        $this->collFeatureProdsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FeatureProd objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related FeatureProd objects.
     * @throws PropelException
     */
    public function countFeatureProds(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collFeatureProdsPartial && !$this->isNew();
        if (null === $this->collFeatureProds || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureProds) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getFeatureProds());
            }
            $query = FeatureProdQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFeature($this)
                ->count($con);
        }

        return count($this->collFeatureProds);
    }

    /**
     * Method called to associate a FeatureProd object to this object
     * through the FeatureProd foreign key attribute.
     *
     * @param    FeatureProd $l FeatureProd
     * @return Feature The current object (for fluent API support)
     */
    public function addFeatureProd(FeatureProd $l)
    {
        if ($this->collFeatureProds === null) {
            $this->initFeatureProds();
            $this->collFeatureProdsPartial = true;
        }
        if (!in_array($l, $this->collFeatureProds->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureProd($l);
        }

        return $this;
    }

    /**
     * @param	FeatureProd $featureProd The featureProd object to add.
     */
    protected function doAddFeatureProd($featureProd)
    {
        $this->collFeatureProds[]= $featureProd;
        $featureProd->setFeature($this);
    }

    /**
     * @param	FeatureProd $featureProd The featureProd object to remove.
     * @return Feature The current object (for fluent API support)
     */
    public function removeFeatureProd($featureProd)
    {
        if ($this->getFeatureProds()->contains($featureProd)) {
            $this->collFeatureProds->remove($this->collFeatureProds->search($featureProd));
            if (null === $this->featureProdsScheduledForDeletion) {
                $this->featureProdsScheduledForDeletion = clone $this->collFeatureProds;
                $this->featureProdsScheduledForDeletion->clear();
            }
            $this->featureProdsScheduledForDeletion[]= clone $featureProd;
            $featureProd->setFeature(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Feature is new, it will return
     * an empty collection; or if this Feature has previously
     * been saved, it will retrieve related FeatureProds from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Feature.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|FeatureProd[] List of FeatureProd objects
     */
    public function getFeatureProdsJoinProduct($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = FeatureProdQuery::create(null, $criteria);
        $query->joinWith('Product', $join_behavior);

        return $this->getFeatureProds($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Feature is new, it will return
     * an empty collection; or if this Feature has previously
     * been saved, it will retrieve related FeatureProds from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Feature.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|FeatureProd[] List of FeatureProd objects
     */
    public function getFeatureProdsJoinFeatureAv($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = FeatureProdQuery::create(null, $criteria);
        $query->joinWith('FeatureAv', $join_behavior);

        return $this->getFeatureProds($query, $con);
    }

    /**
     * Clears out the collFeatureCategorys collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Feature The current object (for fluent API support)
     * @see        addFeatureCategorys()
     */
    public function clearFeatureCategorys()
    {
        $this->collFeatureCategorys = null; // important to set this to null since that means it is uninitialized
        $this->collFeatureCategorysPartial = null;

        return $this;
    }

    /**
     * reset is the collFeatureCategorys collection loaded partially
     *
     * @return void
     */
    public function resetPartialFeatureCategorys($v = true)
    {
        $this->collFeatureCategorysPartial = $v;
    }

    /**
     * Initializes the collFeatureCategorys collection.
     *
     * By default this just sets the collFeatureCategorys collection to an empty array (like clearcollFeatureCategorys());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureCategorys($overrideExisting = true)
    {
        if (null !== $this->collFeatureCategorys && !$overrideExisting) {
            return;
        }
        $this->collFeatureCategorys = new PropelObjectCollection();
        $this->collFeatureCategorys->setModel('FeatureCategory');
    }

    /**
     * Gets an array of FeatureCategory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Feature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|FeatureCategory[] List of FeatureCategory objects
     * @throws PropelException
     */
    public function getFeatureCategorys($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collFeatureCategorysPartial && !$this->isNew();
        if (null === $this->collFeatureCategorys || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureCategorys) {
                // return empty collection
                $this->initFeatureCategorys();
            } else {
                $collFeatureCategorys = FeatureCategoryQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collFeatureCategorysPartial && count($collFeatureCategorys)) {
                      $this->initFeatureCategorys(false);

                      foreach($collFeatureCategorys as $obj) {
                        if (false == $this->collFeatureCategorys->contains($obj)) {
                          $this->collFeatureCategorys->append($obj);
                        }
                      }

                      $this->collFeatureCategorysPartial = true;
                    }

                    $collFeatureCategorys->getInternalIterator()->rewind();
                    return $collFeatureCategorys;
                }

                if($partial && $this->collFeatureCategorys) {
                    foreach($this->collFeatureCategorys as $obj) {
                        if($obj->isNew()) {
                            $collFeatureCategorys[] = $obj;
                        }
                    }
                }

                $this->collFeatureCategorys = $collFeatureCategorys;
                $this->collFeatureCategorysPartial = false;
            }
        }

        return $this->collFeatureCategorys;
    }

    /**
     * Sets a collection of FeatureCategory objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $featureCategorys A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Feature The current object (for fluent API support)
     */
    public function setFeatureCategorys(PropelCollection $featureCategorys, PropelPDO $con = null)
    {
        $featureCategorysToDelete = $this->getFeatureCategorys(new Criteria(), $con)->diff($featureCategorys);

        $this->featureCategorysScheduledForDeletion = unserialize(serialize($featureCategorysToDelete));

        foreach ($featureCategorysToDelete as $featureCategoryRemoved) {
            $featureCategoryRemoved->setFeature(null);
        }

        $this->collFeatureCategorys = null;
        foreach ($featureCategorys as $featureCategory) {
            $this->addFeatureCategory($featureCategory);
        }

        $this->collFeatureCategorys = $featureCategorys;
        $this->collFeatureCategorysPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FeatureCategory objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related FeatureCategory objects.
     * @throws PropelException
     */
    public function countFeatureCategorys(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collFeatureCategorysPartial && !$this->isNew();
        if (null === $this->collFeatureCategorys || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureCategorys) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getFeatureCategorys());
            }
            $query = FeatureCategoryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByFeature($this)
                ->count($con);
        }

        return count($this->collFeatureCategorys);
    }

    /**
     * Method called to associate a FeatureCategory object to this object
     * through the FeatureCategory foreign key attribute.
     *
     * @param    FeatureCategory $l FeatureCategory
     * @return Feature The current object (for fluent API support)
     */
    public function addFeatureCategory(FeatureCategory $l)
    {
        if ($this->collFeatureCategorys === null) {
            $this->initFeatureCategorys();
            $this->collFeatureCategorysPartial = true;
        }
        if (!in_array($l, $this->collFeatureCategorys->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureCategory($l);
        }

        return $this;
    }

    /**
     * @param	FeatureCategory $featureCategory The featureCategory object to add.
     */
    protected function doAddFeatureCategory($featureCategory)
    {
        $this->collFeatureCategorys[]= $featureCategory;
        $featureCategory->setFeature($this);
    }

    /**
     * @param	FeatureCategory $featureCategory The featureCategory object to remove.
     * @return Feature The current object (for fluent API support)
     */
    public function removeFeatureCategory($featureCategory)
    {
        if ($this->getFeatureCategorys()->contains($featureCategory)) {
            $this->collFeatureCategorys->remove($this->collFeatureCategorys->search($featureCategory));
            if (null === $this->featureCategorysScheduledForDeletion) {
                $this->featureCategorysScheduledForDeletion = clone $this->collFeatureCategorys;
                $this->featureCategorysScheduledForDeletion->clear();
            }
            $this->featureCategorysScheduledForDeletion[]= clone $featureCategory;
            $featureCategory->setFeature(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Feature is new, it will return
     * an empty collection; or if this Feature has previously
     * been saved, it will retrieve related FeatureCategorys from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Feature.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|FeatureCategory[] List of FeatureCategory objects
     */
    public function getFeatureCategorysJoinCategory($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = FeatureCategoryQuery::create(null, $criteria);
        $query->joinWith('Category', $join_behavior);

        return $this->getFeatureCategorys($query, $con);
    }

    /**
     * Clears out the collFeatureI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Feature The current object (for fluent API support)
     * @see        addFeatureI18ns()
     */
    public function clearFeatureI18ns()
    {
        $this->collFeatureI18ns = null; // important to set this to null since that means it is uninitialized
        $this->collFeatureI18nsPartial = null;

        return $this;
    }

    /**
     * reset is the collFeatureI18ns collection loaded partially
     *
     * @return void
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
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureI18ns($overrideExisting = true)
    {
        if (null !== $this->collFeatureI18ns && !$overrideExisting) {
            return;
        }
        $this->collFeatureI18ns = new PropelObjectCollection();
        $this->collFeatureI18ns->setModel('FeatureI18n');
    }

    /**
     * Gets an array of FeatureI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Feature is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|FeatureI18n[] List of FeatureI18n objects
     * @throws PropelException
     */
    public function getFeatureI18ns($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collFeatureI18nsPartial && !$this->isNew();
        if (null === $this->collFeatureI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureI18ns) {
                // return empty collection
                $this->initFeatureI18ns();
            } else {
                $collFeatureI18ns = FeatureI18nQuery::create(null, $criteria)
                    ->filterByFeature($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collFeatureI18nsPartial && count($collFeatureI18ns)) {
                      $this->initFeatureI18ns(false);

                      foreach($collFeatureI18ns as $obj) {
                        if (false == $this->collFeatureI18ns->contains($obj)) {
                          $this->collFeatureI18ns->append($obj);
                        }
                      }

                      $this->collFeatureI18nsPartial = true;
                    }

                    $collFeatureI18ns->getInternalIterator()->rewind();
                    return $collFeatureI18ns;
                }

                if($partial && $this->collFeatureI18ns) {
                    foreach($this->collFeatureI18ns as $obj) {
                        if($obj->isNew()) {
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
     * @param PropelCollection $featureI18ns A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Feature The current object (for fluent API support)
     */
    public function setFeatureI18ns(PropelCollection $featureI18ns, PropelPDO $con = null)
    {
        $featureI18nsToDelete = $this->getFeatureI18ns(new Criteria(), $con)->diff($featureI18ns);

        $this->featureI18nsScheduledForDeletion = unserialize(serialize($featureI18nsToDelete));

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
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related FeatureI18n objects.
     * @throws PropelException
     */
    public function countFeatureI18ns(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collFeatureI18nsPartial && !$this->isNew();
        if (null === $this->collFeatureI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureI18ns) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getFeatureI18ns());
            }
            $query = FeatureI18nQuery::create(null, $criteria);
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
     * Method called to associate a FeatureI18n object to this object
     * through the FeatureI18n foreign key attribute.
     *
     * @param    FeatureI18n $l FeatureI18n
     * @return Feature The current object (for fluent API support)
     */
    public function addFeatureI18n(FeatureI18n $l)
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
     * @param	FeatureI18n $featureI18n The featureI18n object to add.
     */
    protected function doAddFeatureI18n($featureI18n)
    {
        $this->collFeatureI18ns[]= $featureI18n;
        $featureI18n->setFeature($this);
    }

    /**
     * @param	FeatureI18n $featureI18n The featureI18n object to remove.
     * @return Feature The current object (for fluent API support)
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
        $this->alreadyInValidation = false;
        $this->alreadyInClearAllReferencesDeep = false;
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
     * when using Propel in certain daemon or large-volumne/high-memory operations.
     *
     * @param boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep && !$this->alreadyInClearAllReferencesDeep) {
            $this->alreadyInClearAllReferencesDeep = true;
            if ($this->collFeatureAvs) {
                foreach ($this->collFeatureAvs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureProds) {
                foreach ($this->collFeatureProds as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureCategorys) {
                foreach ($this->collFeatureCategorys as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureI18ns) {
                foreach ($this->collFeatureI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }

            $this->alreadyInClearAllReferencesDeep = false;
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        if ($this->collFeatureAvs instanceof PropelCollection) {
            $this->collFeatureAvs->clearIterator();
        }
        $this->collFeatureAvs = null;
        if ($this->collFeatureProds instanceof PropelCollection) {
            $this->collFeatureProds->clearIterator();
        }
        $this->collFeatureProds = null;
        if ($this->collFeatureCategorys instanceof PropelCollection) {
            $this->collFeatureCategorys->clearIterator();
        }
        $this->collFeatureCategorys = null;
        if ($this->collFeatureI18ns instanceof PropelCollection) {
            $this->collFeatureI18ns->clearIterator();
        }
        $this->collFeatureI18ns = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(FeaturePeer::DEFAULT_STRING_FORMAT);
    }

    /**
     * return true is the object is in saving state
     *
     * @return boolean
     */
    public function isAlreadyInSave()
    {
        return $this->alreadyInSave;
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     Feature The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = FeaturePeer::UPDATED_AT;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    Feature The current object (for fluent API support)
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
     * @param     PropelPDO $con an optional connection object
     *
     * @return FeatureI18n */
    public function getTranslation($locale = 'en_US', PropelPDO $con = null)
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
                $translation = new FeatureI18n();
                $translation->setLocale($locale);
            } else {
                $translation = FeatureI18nQuery::create()
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
     * @param     PropelPDO $con an optional connection object
     *
     * @return    Feature The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', PropelPDO $con = null)
    {
        if (!$this->isNew()) {
            FeatureI18nQuery::create()
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
     * @param     PropelPDO $con an optional connection object
     *
     * @return FeatureI18n */
    public function getCurrentTranslation(PropelPDO $con = null)
    {
        return $this->getTranslation($this->getLocale(), $con);
    }


        /**
         * Get the [title] column value.
         *
         * @return string
         */
        public function getTitle()
        {
        return $this->getCurrentTranslation()->getTitle();
    }


        /**
         * Set the value of [title] column.
         *
         * @param string $v new value
         * @return FeatureI18n The current object (for fluent API support)
         */
        public function setTitle($v)
        {    $this->getCurrentTranslation()->setTitle($v);

        return $this;
    }


        /**
         * Get the [description] column value.
         *
         * @return string
         */
        public function getDescription()
        {
        return $this->getCurrentTranslation()->getDescription();
    }


        /**
         * Set the value of [description] column.
         *
         * @param string $v new value
         * @return FeatureI18n The current object (for fluent API support)
         */
        public function setDescription($v)
        {    $this->getCurrentTranslation()->setDescription($v);

        return $this;
    }


        /**
         * Get the [chapo] column value.
         *
         * @return string
         */
        public function getChapo()
        {
        return $this->getCurrentTranslation()->getChapo();
    }


        /**
         * Set the value of [chapo] column.
         *
         * @param string $v new value
         * @return FeatureI18n The current object (for fluent API support)
         */
        public function setChapo($v)
        {    $this->getCurrentTranslation()->setChapo($v);

        return $this;
    }


        /**
         * Get the [postscriptum] column value.
         *
         * @return string
         */
        public function getPostscriptum()
        {
        return $this->getCurrentTranslation()->getPostscriptum();
    }


        /**
         * Set the value of [postscriptum] column.
         *
         * @param string $v new value
         * @return FeatureI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }

}
