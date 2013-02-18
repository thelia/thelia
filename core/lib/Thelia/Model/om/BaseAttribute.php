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
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeCategory;
use Thelia\Model\AttributeCategoryQuery;
use Thelia\Model\AttributeCombination;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\AttributeI18n;
use Thelia\Model\AttributeI18nQuery;
use Thelia\Model\AttributePeer;
use Thelia\Model\AttributeQuery;

/**
 * Base class that represents a row from the 'attribute' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseAttribute extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\AttributePeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        AttributePeer
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
     * @var        PropelObjectCollection|AttributeAv[] Collection to store aggregation of AttributeAv objects.
     */
    protected $collAttributeAvs;
    protected $collAttributeAvsPartial;

    /**
     * @var        PropelObjectCollection|AttributeCombination[] Collection to store aggregation of AttributeCombination objects.
     */
    protected $collAttributeCombinations;
    protected $collAttributeCombinationsPartial;

    /**
     * @var        PropelObjectCollection|AttributeCategory[] Collection to store aggregation of AttributeCategory objects.
     */
    protected $collAttributeCategorys;
    protected $collAttributeCategorysPartial;

    /**
     * @var        PropelObjectCollection|AttributeI18n[] Collection to store aggregation of AttributeI18n objects.
     */
    protected $collAttributeI18ns;
    protected $collAttributeI18nsPartial;

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
     * @var        array[AttributeI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $attributeAvsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $attributeCombinationsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $attributeCategorysScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $attributeI18nsScheduledForDeletion = null;

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
     * @return Attribute The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = AttributePeer::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [position] column.
     *
     * @param int $v new value
     * @return Attribute The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[] = AttributePeer::POSITION;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Attribute The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = AttributePeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Attribute The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = AttributePeer::UPDATED_AT;
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
            $this->position = ($row[$startcol + 1] !== null) ? (int) $row[$startcol + 1] : null;
            $this->created_at = ($row[$startcol + 2] !== null) ? (string) $row[$startcol + 2] : null;
            $this->updated_at = ($row[$startcol + 3] !== null) ? (string) $row[$startcol + 3] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }
            $this->postHydrate($row, $startcol, $rehydrate);
            return $startcol + 4; // 4 = AttributePeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Attribute object", $e);
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
            $con = Propel::getConnection(AttributePeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = AttributePeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collAttributeAvs = null;

            $this->collAttributeCombinations = null;

            $this->collAttributeCategorys = null;

            $this->collAttributeI18ns = null;

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
            $con = Propel::getConnection(AttributePeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = AttributeQuery::create()
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
            $con = Propel::getConnection(AttributePeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(AttributePeer::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(AttributePeer::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(AttributePeer::UPDATED_AT)) {
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
                AttributePeer::addInstanceToPool($this);
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

            if ($this->attributeAvsScheduledForDeletion !== null) {
                if (!$this->attributeAvsScheduledForDeletion->isEmpty()) {
                    AttributeAvQuery::create()
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
                    AttributeCombinationQuery::create()
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

            if ($this->attributeCategorysScheduledForDeletion !== null) {
                if (!$this->attributeCategorysScheduledForDeletion->isEmpty()) {
                    AttributeCategoryQuery::create()
                        ->filterByPrimaryKeys($this->attributeCategorysScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->attributeCategorysScheduledForDeletion = null;
                }
            }

            if ($this->collAttributeCategorys !== null) {
                foreach ($this->collAttributeCategorys as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->attributeI18nsScheduledForDeletion !== null) {
                if (!$this->attributeI18nsScheduledForDeletion->isEmpty()) {
                    AttributeI18nQuery::create()
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
     * @param PropelPDO $con
     *
     * @throws PropelException
     * @see        doSave()
     */
    protected function doInsert(PropelPDO $con)
    {
        $modifiedColumns = array();
        $index = 0;

        $this->modifiedColumns[] = AttributePeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . AttributePeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(AttributePeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`id`';
        }
        if ($this->isColumnModified(AttributePeer::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`position`';
        }
        if ($this->isColumnModified(AttributePeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`created_at`';
        }
        if ($this->isColumnModified(AttributePeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`updated_at`';
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
                    case '`id`':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
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


            if (($retval = AttributePeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collAttributeAvs !== null) {
                    foreach ($this->collAttributeAvs as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collAttributeCombinations !== null) {
                    foreach ($this->collAttributeCombinations as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collAttributeCategorys !== null) {
                    foreach ($this->collAttributeCategorys as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collAttributeI18ns !== null) {
                    foreach ($this->collAttributeI18ns as $referrerFK) {
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
        $pos = AttributePeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
        if (isset($alreadyDumpedObjects['Attribute'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Attribute'][$this->getPrimaryKey()] = true;
        $keys = AttributePeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getPosition(),
            $keys[2] => $this->getCreatedAt(),
            $keys[3] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->collAttributeAvs) {
                $result['AttributeAvs'] = $this->collAttributeAvs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAttributeCombinations) {
                $result['AttributeCombinations'] = $this->collAttributeCombinations->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAttributeCategorys) {
                $result['AttributeCategorys'] = $this->collAttributeCategorys->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = AttributePeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
        $keys = AttributePeer::getFieldNames($keyType);

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
        $criteria = new Criteria(AttributePeer::DATABASE_NAME);

        if ($this->isColumnModified(AttributePeer::ID)) $criteria->add(AttributePeer::ID, $this->id);
        if ($this->isColumnModified(AttributePeer::POSITION)) $criteria->add(AttributePeer::POSITION, $this->position);
        if ($this->isColumnModified(AttributePeer::CREATED_AT)) $criteria->add(AttributePeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(AttributePeer::UPDATED_AT)) $criteria->add(AttributePeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(AttributePeer::DATABASE_NAME);
        $criteria->add(AttributePeer::ID, $this->id);

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
     * @param object $copyObj An object of Attribute (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

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

            foreach ($this->getAttributeCategorys() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAttributeCategory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAttributeI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAttributeI18n($relObj->copy($deepCopy));
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
     * @return Attribute Clone of current object.
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
     * @return AttributePeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new AttributePeer();
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
        if ('AttributeAv' == $relationName) {
            $this->initAttributeAvs();
        }
        if ('AttributeCombination' == $relationName) {
            $this->initAttributeCombinations();
        }
        if ('AttributeCategory' == $relationName) {
            $this->initAttributeCategorys();
        }
        if ('AttributeI18n' == $relationName) {
            $this->initAttributeI18ns();
        }
    }

    /**
     * Clears out the collAttributeAvs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Attribute The current object (for fluent API support)
     * @see        addAttributeAvs()
     */
    public function clearAttributeAvs()
    {
        $this->collAttributeAvs = null; // important to set this to null since that means it is uninitialized
        $this->collAttributeAvsPartial = null;

        return $this;
    }

    /**
     * reset is the collAttributeAvs collection loaded partially
     *
     * @return void
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
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAttributeAvs($overrideExisting = true)
    {
        if (null !== $this->collAttributeAvs && !$overrideExisting) {
            return;
        }
        $this->collAttributeAvs = new PropelObjectCollection();
        $this->collAttributeAvs->setModel('AttributeAv');
    }

    /**
     * Gets an array of AttributeAv objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Attribute is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|AttributeAv[] List of AttributeAv objects
     * @throws PropelException
     */
    public function getAttributeAvs($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collAttributeAvsPartial && !$this->isNew();
        if (null === $this->collAttributeAvs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAttributeAvs) {
                // return empty collection
                $this->initAttributeAvs();
            } else {
                $collAttributeAvs = AttributeAvQuery::create(null, $criteria)
                    ->filterByAttribute($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collAttributeAvsPartial && count($collAttributeAvs)) {
                      $this->initAttributeAvs(false);

                      foreach($collAttributeAvs as $obj) {
                        if (false == $this->collAttributeAvs->contains($obj)) {
                          $this->collAttributeAvs->append($obj);
                        }
                      }

                      $this->collAttributeAvsPartial = true;
                    }

                    $collAttributeAvs->getInternalIterator()->rewind();
                    return $collAttributeAvs;
                }

                if($partial && $this->collAttributeAvs) {
                    foreach($this->collAttributeAvs as $obj) {
                        if($obj->isNew()) {
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
     * @param PropelCollection $attributeAvs A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Attribute The current object (for fluent API support)
     */
    public function setAttributeAvs(PropelCollection $attributeAvs, PropelPDO $con = null)
    {
        $attributeAvsToDelete = $this->getAttributeAvs(new Criteria(), $con)->diff($attributeAvs);

        $this->attributeAvsScheduledForDeletion = unserialize(serialize($attributeAvsToDelete));

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
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related AttributeAv objects.
     * @throws PropelException
     */
    public function countAttributeAvs(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collAttributeAvsPartial && !$this->isNew();
        if (null === $this->collAttributeAvs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAttributeAvs) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getAttributeAvs());
            }
            $query = AttributeAvQuery::create(null, $criteria);
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
     * Method called to associate a AttributeAv object to this object
     * through the AttributeAv foreign key attribute.
     *
     * @param    AttributeAv $l AttributeAv
     * @return Attribute The current object (for fluent API support)
     */
    public function addAttributeAv(AttributeAv $l)
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
     * @param	AttributeAv $attributeAv The attributeAv object to add.
     */
    protected function doAddAttributeAv($attributeAv)
    {
        $this->collAttributeAvs[]= $attributeAv;
        $attributeAv->setAttribute($this);
    }

    /**
     * @param	AttributeAv $attributeAv The attributeAv object to remove.
     * @return Attribute The current object (for fluent API support)
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
     * @return Attribute The current object (for fluent API support)
     * @see        addAttributeCombinations()
     */
    public function clearAttributeCombinations()
    {
        $this->collAttributeCombinations = null; // important to set this to null since that means it is uninitialized
        $this->collAttributeCombinationsPartial = null;

        return $this;
    }

    /**
     * reset is the collAttributeCombinations collection loaded partially
     *
     * @return void
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
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAttributeCombinations($overrideExisting = true)
    {
        if (null !== $this->collAttributeCombinations && !$overrideExisting) {
            return;
        }
        $this->collAttributeCombinations = new PropelObjectCollection();
        $this->collAttributeCombinations->setModel('AttributeCombination');
    }

    /**
     * Gets an array of AttributeCombination objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Attribute is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|AttributeCombination[] List of AttributeCombination objects
     * @throws PropelException
     */
    public function getAttributeCombinations($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collAttributeCombinationsPartial && !$this->isNew();
        if (null === $this->collAttributeCombinations || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAttributeCombinations) {
                // return empty collection
                $this->initAttributeCombinations();
            } else {
                $collAttributeCombinations = AttributeCombinationQuery::create(null, $criteria)
                    ->filterByAttribute($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collAttributeCombinationsPartial && count($collAttributeCombinations)) {
                      $this->initAttributeCombinations(false);

                      foreach($collAttributeCombinations as $obj) {
                        if (false == $this->collAttributeCombinations->contains($obj)) {
                          $this->collAttributeCombinations->append($obj);
                        }
                      }

                      $this->collAttributeCombinationsPartial = true;
                    }

                    $collAttributeCombinations->getInternalIterator()->rewind();
                    return $collAttributeCombinations;
                }

                if($partial && $this->collAttributeCombinations) {
                    foreach($this->collAttributeCombinations as $obj) {
                        if($obj->isNew()) {
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
     * @param PropelCollection $attributeCombinations A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Attribute The current object (for fluent API support)
     */
    public function setAttributeCombinations(PropelCollection $attributeCombinations, PropelPDO $con = null)
    {
        $attributeCombinationsToDelete = $this->getAttributeCombinations(new Criteria(), $con)->diff($attributeCombinations);

        $this->attributeCombinationsScheduledForDeletion = unserialize(serialize($attributeCombinationsToDelete));

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
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related AttributeCombination objects.
     * @throws PropelException
     */
    public function countAttributeCombinations(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collAttributeCombinationsPartial && !$this->isNew();
        if (null === $this->collAttributeCombinations || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAttributeCombinations) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getAttributeCombinations());
            }
            $query = AttributeCombinationQuery::create(null, $criteria);
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
     * Method called to associate a AttributeCombination object to this object
     * through the AttributeCombination foreign key attribute.
     *
     * @param    AttributeCombination $l AttributeCombination
     * @return Attribute The current object (for fluent API support)
     */
    public function addAttributeCombination(AttributeCombination $l)
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
     * @param	AttributeCombination $attributeCombination The attributeCombination object to add.
     */
    protected function doAddAttributeCombination($attributeCombination)
    {
        $this->collAttributeCombinations[]= $attributeCombination;
        $attributeCombination->setAttribute($this);
    }

    /**
     * @param	AttributeCombination $attributeCombination The attributeCombination object to remove.
     * @return Attribute The current object (for fluent API support)
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
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|AttributeCombination[] List of AttributeCombination objects
     */
    public function getAttributeCombinationsJoinAttributeAv($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = AttributeCombinationQuery::create(null, $criteria);
        $query->joinWith('AttributeAv', $join_behavior);

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
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|AttributeCombination[] List of AttributeCombination objects
     */
    public function getAttributeCombinationsJoinCombination($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = AttributeCombinationQuery::create(null, $criteria);
        $query->joinWith('Combination', $join_behavior);

        return $this->getAttributeCombinations($query, $con);
    }

    /**
     * Clears out the collAttributeCategorys collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Attribute The current object (for fluent API support)
     * @see        addAttributeCategorys()
     */
    public function clearAttributeCategorys()
    {
        $this->collAttributeCategorys = null; // important to set this to null since that means it is uninitialized
        $this->collAttributeCategorysPartial = null;

        return $this;
    }

    /**
     * reset is the collAttributeCategorys collection loaded partially
     *
     * @return void
     */
    public function resetPartialAttributeCategorys($v = true)
    {
        $this->collAttributeCategorysPartial = $v;
    }

    /**
     * Initializes the collAttributeCategorys collection.
     *
     * By default this just sets the collAttributeCategorys collection to an empty array (like clearcollAttributeCategorys());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAttributeCategorys($overrideExisting = true)
    {
        if (null !== $this->collAttributeCategorys && !$overrideExisting) {
            return;
        }
        $this->collAttributeCategorys = new PropelObjectCollection();
        $this->collAttributeCategorys->setModel('AttributeCategory');
    }

    /**
     * Gets an array of AttributeCategory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Attribute is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|AttributeCategory[] List of AttributeCategory objects
     * @throws PropelException
     */
    public function getAttributeCategorys($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collAttributeCategorysPartial && !$this->isNew();
        if (null === $this->collAttributeCategorys || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAttributeCategorys) {
                // return empty collection
                $this->initAttributeCategorys();
            } else {
                $collAttributeCategorys = AttributeCategoryQuery::create(null, $criteria)
                    ->filterByAttribute($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collAttributeCategorysPartial && count($collAttributeCategorys)) {
                      $this->initAttributeCategorys(false);

                      foreach($collAttributeCategorys as $obj) {
                        if (false == $this->collAttributeCategorys->contains($obj)) {
                          $this->collAttributeCategorys->append($obj);
                        }
                      }

                      $this->collAttributeCategorysPartial = true;
                    }

                    $collAttributeCategorys->getInternalIterator()->rewind();
                    return $collAttributeCategorys;
                }

                if($partial && $this->collAttributeCategorys) {
                    foreach($this->collAttributeCategorys as $obj) {
                        if($obj->isNew()) {
                            $collAttributeCategorys[] = $obj;
                        }
                    }
                }

                $this->collAttributeCategorys = $collAttributeCategorys;
                $this->collAttributeCategorysPartial = false;
            }
        }

        return $this->collAttributeCategorys;
    }

    /**
     * Sets a collection of AttributeCategory objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $attributeCategorys A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Attribute The current object (for fluent API support)
     */
    public function setAttributeCategorys(PropelCollection $attributeCategorys, PropelPDO $con = null)
    {
        $attributeCategorysToDelete = $this->getAttributeCategorys(new Criteria(), $con)->diff($attributeCategorys);

        $this->attributeCategorysScheduledForDeletion = unserialize(serialize($attributeCategorysToDelete));

        foreach ($attributeCategorysToDelete as $attributeCategoryRemoved) {
            $attributeCategoryRemoved->setAttribute(null);
        }

        $this->collAttributeCategorys = null;
        foreach ($attributeCategorys as $attributeCategory) {
            $this->addAttributeCategory($attributeCategory);
        }

        $this->collAttributeCategorys = $attributeCategorys;
        $this->collAttributeCategorysPartial = false;

        return $this;
    }

    /**
     * Returns the number of related AttributeCategory objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related AttributeCategory objects.
     * @throws PropelException
     */
    public function countAttributeCategorys(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collAttributeCategorysPartial && !$this->isNew();
        if (null === $this->collAttributeCategorys || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAttributeCategorys) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getAttributeCategorys());
            }
            $query = AttributeCategoryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByAttribute($this)
                ->count($con);
        }

        return count($this->collAttributeCategorys);
    }

    /**
     * Method called to associate a AttributeCategory object to this object
     * through the AttributeCategory foreign key attribute.
     *
     * @param    AttributeCategory $l AttributeCategory
     * @return Attribute The current object (for fluent API support)
     */
    public function addAttributeCategory(AttributeCategory $l)
    {
        if ($this->collAttributeCategorys === null) {
            $this->initAttributeCategorys();
            $this->collAttributeCategorysPartial = true;
        }
        if (!in_array($l, $this->collAttributeCategorys->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAttributeCategory($l);
        }

        return $this;
    }

    /**
     * @param	AttributeCategory $attributeCategory The attributeCategory object to add.
     */
    protected function doAddAttributeCategory($attributeCategory)
    {
        $this->collAttributeCategorys[]= $attributeCategory;
        $attributeCategory->setAttribute($this);
    }

    /**
     * @param	AttributeCategory $attributeCategory The attributeCategory object to remove.
     * @return Attribute The current object (for fluent API support)
     */
    public function removeAttributeCategory($attributeCategory)
    {
        if ($this->getAttributeCategorys()->contains($attributeCategory)) {
            $this->collAttributeCategorys->remove($this->collAttributeCategorys->search($attributeCategory));
            if (null === $this->attributeCategorysScheduledForDeletion) {
                $this->attributeCategorysScheduledForDeletion = clone $this->collAttributeCategorys;
                $this->attributeCategorysScheduledForDeletion->clear();
            }
            $this->attributeCategorysScheduledForDeletion[]= clone $attributeCategory;
            $attributeCategory->setAttribute(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Attribute is new, it will return
     * an empty collection; or if this Attribute has previously
     * been saved, it will retrieve related AttributeCategorys from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Attribute.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|AttributeCategory[] List of AttributeCategory objects
     */
    public function getAttributeCategorysJoinCategory($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = AttributeCategoryQuery::create(null, $criteria);
        $query->joinWith('Category', $join_behavior);

        return $this->getAttributeCategorys($query, $con);
    }

    /**
     * Clears out the collAttributeI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Attribute The current object (for fluent API support)
     * @see        addAttributeI18ns()
     */
    public function clearAttributeI18ns()
    {
        $this->collAttributeI18ns = null; // important to set this to null since that means it is uninitialized
        $this->collAttributeI18nsPartial = null;

        return $this;
    }

    /**
     * reset is the collAttributeI18ns collection loaded partially
     *
     * @return void
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
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAttributeI18ns($overrideExisting = true)
    {
        if (null !== $this->collAttributeI18ns && !$overrideExisting) {
            return;
        }
        $this->collAttributeI18ns = new PropelObjectCollection();
        $this->collAttributeI18ns->setModel('AttributeI18n');
    }

    /**
     * Gets an array of AttributeI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Attribute is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|AttributeI18n[] List of AttributeI18n objects
     * @throws PropelException
     */
    public function getAttributeI18ns($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collAttributeI18nsPartial && !$this->isNew();
        if (null === $this->collAttributeI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAttributeI18ns) {
                // return empty collection
                $this->initAttributeI18ns();
            } else {
                $collAttributeI18ns = AttributeI18nQuery::create(null, $criteria)
                    ->filterByAttribute($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collAttributeI18nsPartial && count($collAttributeI18ns)) {
                      $this->initAttributeI18ns(false);

                      foreach($collAttributeI18ns as $obj) {
                        if (false == $this->collAttributeI18ns->contains($obj)) {
                          $this->collAttributeI18ns->append($obj);
                        }
                      }

                      $this->collAttributeI18nsPartial = true;
                    }

                    $collAttributeI18ns->getInternalIterator()->rewind();
                    return $collAttributeI18ns;
                }

                if($partial && $this->collAttributeI18ns) {
                    foreach($this->collAttributeI18ns as $obj) {
                        if($obj->isNew()) {
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
     * @param PropelCollection $attributeI18ns A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Attribute The current object (for fluent API support)
     */
    public function setAttributeI18ns(PropelCollection $attributeI18ns, PropelPDO $con = null)
    {
        $attributeI18nsToDelete = $this->getAttributeI18ns(new Criteria(), $con)->diff($attributeI18ns);

        $this->attributeI18nsScheduledForDeletion = unserialize(serialize($attributeI18nsToDelete));

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
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related AttributeI18n objects.
     * @throws PropelException
     */
    public function countAttributeI18ns(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collAttributeI18nsPartial && !$this->isNew();
        if (null === $this->collAttributeI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAttributeI18ns) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getAttributeI18ns());
            }
            $query = AttributeI18nQuery::create(null, $criteria);
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
     * Method called to associate a AttributeI18n object to this object
     * through the AttributeI18n foreign key attribute.
     *
     * @param    AttributeI18n $l AttributeI18n
     * @return Attribute The current object (for fluent API support)
     */
    public function addAttributeI18n(AttributeI18n $l)
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
     * @param	AttributeI18n $attributeI18n The attributeI18n object to add.
     */
    protected function doAddAttributeI18n($attributeI18n)
    {
        $this->collAttributeI18ns[]= $attributeI18n;
        $attributeI18n->setAttribute($this);
    }

    /**
     * @param	AttributeI18n $attributeI18n The attributeI18n object to remove.
     * @return Attribute The current object (for fluent API support)
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
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->position = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->alreadyInSave = false;
        $this->alreadyInValidation = false;
        $this->alreadyInClearAllReferencesDeep = false;
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
     * when using Propel in certain daemon or large-volumne/high-memory operations.
     *
     * @param boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep && !$this->alreadyInClearAllReferencesDeep) {
            $this->alreadyInClearAllReferencesDeep = true;
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
            if ($this->collAttributeCategorys) {
                foreach ($this->collAttributeCategorys as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAttributeI18ns) {
                foreach ($this->collAttributeI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }

            $this->alreadyInClearAllReferencesDeep = false;
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        if ($this->collAttributeAvs instanceof PropelCollection) {
            $this->collAttributeAvs->clearIterator();
        }
        $this->collAttributeAvs = null;
        if ($this->collAttributeCombinations instanceof PropelCollection) {
            $this->collAttributeCombinations->clearIterator();
        }
        $this->collAttributeCombinations = null;
        if ($this->collAttributeCategorys instanceof PropelCollection) {
            $this->collAttributeCategorys->clearIterator();
        }
        $this->collAttributeCategorys = null;
        if ($this->collAttributeI18ns instanceof PropelCollection) {
            $this->collAttributeI18ns->clearIterator();
        }
        $this->collAttributeI18ns = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(AttributePeer::DEFAULT_STRING_FORMAT);
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
     * @return     Attribute The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = AttributePeer::UPDATED_AT;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    Attribute The current object (for fluent API support)
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
     * @return AttributeI18n */
    public function getTranslation($locale = 'en_US', PropelPDO $con = null)
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
                $translation = new AttributeI18n();
                $translation->setLocale($locale);
            } else {
                $translation = AttributeI18nQuery::create()
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
     * @param     PropelPDO $con an optional connection object
     *
     * @return    Attribute The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', PropelPDO $con = null)
    {
        if (!$this->isNew()) {
            AttributeI18nQuery::create()
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
     * @param     PropelPDO $con an optional connection object
     *
     * @return AttributeI18n */
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
         * @return AttributeI18n The current object (for fluent API support)
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
         * @return AttributeI18n The current object (for fluent API support)
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
         * @return AttributeI18n The current object (for fluent API support)
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
         * @return AttributeI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }

}
