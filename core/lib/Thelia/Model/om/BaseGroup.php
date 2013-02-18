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
use Thelia\Model\AdminGroup;
use Thelia\Model\AdminGroupQuery;
use Thelia\Model\Group;
use Thelia\Model\GroupI18n;
use Thelia\Model\GroupI18nQuery;
use Thelia\Model\GroupModule;
use Thelia\Model\GroupModuleQuery;
use Thelia\Model\GroupPeer;
use Thelia\Model\GroupQuery;
use Thelia\Model\GroupResource;
use Thelia\Model\GroupResourceQuery;

/**
 * Base class that represents a row from the 'group' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseGroup extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\GroupPeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        GroupPeer
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
     * @var        PropelObjectCollection|AdminGroup[] Collection to store aggregation of AdminGroup objects.
     */
    protected $collAdminGroups;
    protected $collAdminGroupsPartial;

    /**
     * @var        PropelObjectCollection|GroupResource[] Collection to store aggregation of GroupResource objects.
     */
    protected $collGroupResources;
    protected $collGroupResourcesPartial;

    /**
     * @var        PropelObjectCollection|GroupModule[] Collection to store aggregation of GroupModule objects.
     */
    protected $collGroupModules;
    protected $collGroupModulesPartial;

    /**
     * @var        PropelObjectCollection|GroupI18n[] Collection to store aggregation of GroupI18n objects.
     */
    protected $collGroupI18ns;
    protected $collGroupI18nsPartial;

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
     * @var        array[GroupI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $adminGroupsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $groupResourcesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $groupModulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $groupI18nsScheduledForDeletion = null;

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
     * Get the [code] column value.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
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
     * @return Group The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = GroupPeer::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [code] column.
     *
     * @param string $v new value
     * @return Group The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null && is_numeric($v)) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[] = GroupPeer::CODE;
        }


        return $this;
    } // setCode()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Group The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = GroupPeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Group The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = GroupPeer::UPDATED_AT;
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
            $this->code = ($row[$startcol + 1] !== null) ? (string) $row[$startcol + 1] : null;
            $this->created_at = ($row[$startcol + 2] !== null) ? (string) $row[$startcol + 2] : null;
            $this->updated_at = ($row[$startcol + 3] !== null) ? (string) $row[$startcol + 3] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }
            $this->postHydrate($row, $startcol, $rehydrate);
            return $startcol + 4; // 4 = GroupPeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Group object", $e);
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
            $con = Propel::getConnection(GroupPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = GroupPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collAdminGroups = null;

            $this->collGroupResources = null;

            $this->collGroupModules = null;

            $this->collGroupI18ns = null;

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
            $con = Propel::getConnection(GroupPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = GroupQuery::create()
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
            $con = Propel::getConnection(GroupPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(GroupPeer::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(GroupPeer::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(GroupPeer::UPDATED_AT)) {
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
                GroupPeer::addInstanceToPool($this);
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

            if ($this->adminGroupsScheduledForDeletion !== null) {
                if (!$this->adminGroupsScheduledForDeletion->isEmpty()) {
                    foreach ($this->adminGroupsScheduledForDeletion as $adminGroup) {
                        // need to save related object because we set the relation to null
                        $adminGroup->save($con);
                    }
                    $this->adminGroupsScheduledForDeletion = null;
                }
            }

            if ($this->collAdminGroups !== null) {
                foreach ($this->collAdminGroups as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->groupResourcesScheduledForDeletion !== null) {
                if (!$this->groupResourcesScheduledForDeletion->isEmpty()) {
                    GroupResourceQuery::create()
                        ->filterByPrimaryKeys($this->groupResourcesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->groupResourcesScheduledForDeletion = null;
                }
            }

            if ($this->collGroupResources !== null) {
                foreach ($this->collGroupResources as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->groupModulesScheduledForDeletion !== null) {
                if (!$this->groupModulesScheduledForDeletion->isEmpty()) {
                    GroupModuleQuery::create()
                        ->filterByPrimaryKeys($this->groupModulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->groupModulesScheduledForDeletion = null;
                }
            }

            if ($this->collGroupModules !== null) {
                foreach ($this->collGroupModules as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->groupI18nsScheduledForDeletion !== null) {
                if (!$this->groupI18nsScheduledForDeletion->isEmpty()) {
                    GroupI18nQuery::create()
                        ->filterByPrimaryKeys($this->groupI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->groupI18nsScheduledForDeletion = null;
                }
            }

            if ($this->collGroupI18ns !== null) {
                foreach ($this->collGroupI18ns as $referrerFK) {
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

        $this->modifiedColumns[] = GroupPeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . GroupPeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(GroupPeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`id`';
        }
        if ($this->isColumnModified(GroupPeer::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`code`';
        }
        if ($this->isColumnModified(GroupPeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`created_at`';
        }
        if ($this->isColumnModified(GroupPeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`updated_at`';
        }

        $sql = sprintf(
            'INSERT INTO `group` (%s) VALUES (%s)',
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
                    case '`code`':
                        $stmt->bindValue($identifier, $this->code, PDO::PARAM_STR);
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


            if (($retval = GroupPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collAdminGroups !== null) {
                    foreach ($this->collAdminGroups as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collGroupResources !== null) {
                    foreach ($this->collGroupResources as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collGroupModules !== null) {
                    foreach ($this->collGroupModules as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collGroupI18ns !== null) {
                    foreach ($this->collGroupI18ns as $referrerFK) {
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
        $pos = GroupPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
        if (isset($alreadyDumpedObjects['Group'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Group'][$this->getPrimaryKey()] = true;
        $keys = GroupPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCode(),
            $keys[2] => $this->getCreatedAt(),
            $keys[3] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->collAdminGroups) {
                $result['AdminGroups'] = $this->collAdminGroups->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collGroupResources) {
                $result['GroupResources'] = $this->collGroupResources->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collGroupModules) {
                $result['GroupModules'] = $this->collGroupModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collGroupI18ns) {
                $result['GroupI18ns'] = $this->collGroupI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = GroupPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
        $keys = GroupPeer::getFieldNames($keyType);

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
        $criteria = new Criteria(GroupPeer::DATABASE_NAME);

        if ($this->isColumnModified(GroupPeer::ID)) $criteria->add(GroupPeer::ID, $this->id);
        if ($this->isColumnModified(GroupPeer::CODE)) $criteria->add(GroupPeer::CODE, $this->code);
        if ($this->isColumnModified(GroupPeer::CREATED_AT)) $criteria->add(GroupPeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(GroupPeer::UPDATED_AT)) $criteria->add(GroupPeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(GroupPeer::DATABASE_NAME);
        $criteria->add(GroupPeer::ID, $this->id);

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
     * @param object $copyObj An object of Group (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCode($this->getCode());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            foreach ($this->getAdminGroups() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAdminGroup($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getGroupResources() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addGroupResource($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getGroupModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addGroupModule($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getGroupI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addGroupI18n($relObj->copy($deepCopy));
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
     * @return Group Clone of current object.
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
     * @return GroupPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new GroupPeer();
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
        if ('AdminGroup' == $relationName) {
            $this->initAdminGroups();
        }
        if ('GroupResource' == $relationName) {
            $this->initGroupResources();
        }
        if ('GroupModule' == $relationName) {
            $this->initGroupModules();
        }
        if ('GroupI18n' == $relationName) {
            $this->initGroupI18ns();
        }
    }

    /**
     * Clears out the collAdminGroups collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Group The current object (for fluent API support)
     * @see        addAdminGroups()
     */
    public function clearAdminGroups()
    {
        $this->collAdminGroups = null; // important to set this to null since that means it is uninitialized
        $this->collAdminGroupsPartial = null;

        return $this;
    }

    /**
     * reset is the collAdminGroups collection loaded partially
     *
     * @return void
     */
    public function resetPartialAdminGroups($v = true)
    {
        $this->collAdminGroupsPartial = $v;
    }

    /**
     * Initializes the collAdminGroups collection.
     *
     * By default this just sets the collAdminGroups collection to an empty array (like clearcollAdminGroups());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAdminGroups($overrideExisting = true)
    {
        if (null !== $this->collAdminGroups && !$overrideExisting) {
            return;
        }
        $this->collAdminGroups = new PropelObjectCollection();
        $this->collAdminGroups->setModel('AdminGroup');
    }

    /**
     * Gets an array of AdminGroup objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Group is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|AdminGroup[] List of AdminGroup objects
     * @throws PropelException
     */
    public function getAdminGroups($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collAdminGroupsPartial && !$this->isNew();
        if (null === $this->collAdminGroups || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAdminGroups) {
                // return empty collection
                $this->initAdminGroups();
            } else {
                $collAdminGroups = AdminGroupQuery::create(null, $criteria)
                    ->filterByGroup($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collAdminGroupsPartial && count($collAdminGroups)) {
                      $this->initAdminGroups(false);

                      foreach($collAdminGroups as $obj) {
                        if (false == $this->collAdminGroups->contains($obj)) {
                          $this->collAdminGroups->append($obj);
                        }
                      }

                      $this->collAdminGroupsPartial = true;
                    }

                    $collAdminGroups->getInternalIterator()->rewind();
                    return $collAdminGroups;
                }

                if($partial && $this->collAdminGroups) {
                    foreach($this->collAdminGroups as $obj) {
                        if($obj->isNew()) {
                            $collAdminGroups[] = $obj;
                        }
                    }
                }

                $this->collAdminGroups = $collAdminGroups;
                $this->collAdminGroupsPartial = false;
            }
        }

        return $this->collAdminGroups;
    }

    /**
     * Sets a collection of AdminGroup objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $adminGroups A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Group The current object (for fluent API support)
     */
    public function setAdminGroups(PropelCollection $adminGroups, PropelPDO $con = null)
    {
        $adminGroupsToDelete = $this->getAdminGroups(new Criteria(), $con)->diff($adminGroups);

        $this->adminGroupsScheduledForDeletion = unserialize(serialize($adminGroupsToDelete));

        foreach ($adminGroupsToDelete as $adminGroupRemoved) {
            $adminGroupRemoved->setGroup(null);
        }

        $this->collAdminGroups = null;
        foreach ($adminGroups as $adminGroup) {
            $this->addAdminGroup($adminGroup);
        }

        $this->collAdminGroups = $adminGroups;
        $this->collAdminGroupsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related AdminGroup objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related AdminGroup objects.
     * @throws PropelException
     */
    public function countAdminGroups(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collAdminGroupsPartial && !$this->isNew();
        if (null === $this->collAdminGroups || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAdminGroups) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getAdminGroups());
            }
            $query = AdminGroupQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByGroup($this)
                ->count($con);
        }

        return count($this->collAdminGroups);
    }

    /**
     * Method called to associate a AdminGroup object to this object
     * through the AdminGroup foreign key attribute.
     *
     * @param    AdminGroup $l AdminGroup
     * @return Group The current object (for fluent API support)
     */
    public function addAdminGroup(AdminGroup $l)
    {
        if ($this->collAdminGroups === null) {
            $this->initAdminGroups();
            $this->collAdminGroupsPartial = true;
        }
        if (!in_array($l, $this->collAdminGroups->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAdminGroup($l);
        }

        return $this;
    }

    /**
     * @param	AdminGroup $adminGroup The adminGroup object to add.
     */
    protected function doAddAdminGroup($adminGroup)
    {
        $this->collAdminGroups[]= $adminGroup;
        $adminGroup->setGroup($this);
    }

    /**
     * @param	AdminGroup $adminGroup The adminGroup object to remove.
     * @return Group The current object (for fluent API support)
     */
    public function removeAdminGroup($adminGroup)
    {
        if ($this->getAdminGroups()->contains($adminGroup)) {
            $this->collAdminGroups->remove($this->collAdminGroups->search($adminGroup));
            if (null === $this->adminGroupsScheduledForDeletion) {
                $this->adminGroupsScheduledForDeletion = clone $this->collAdminGroups;
                $this->adminGroupsScheduledForDeletion->clear();
            }
            $this->adminGroupsScheduledForDeletion[]= $adminGroup;
            $adminGroup->setGroup(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Group is new, it will return
     * an empty collection; or if this Group has previously
     * been saved, it will retrieve related AdminGroups from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Group.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|AdminGroup[] List of AdminGroup objects
     */
    public function getAdminGroupsJoinAdmin($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = AdminGroupQuery::create(null, $criteria);
        $query->joinWith('Admin', $join_behavior);

        return $this->getAdminGroups($query, $con);
    }

    /**
     * Clears out the collGroupResources collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Group The current object (for fluent API support)
     * @see        addGroupResources()
     */
    public function clearGroupResources()
    {
        $this->collGroupResources = null; // important to set this to null since that means it is uninitialized
        $this->collGroupResourcesPartial = null;

        return $this;
    }

    /**
     * reset is the collGroupResources collection loaded partially
     *
     * @return void
     */
    public function resetPartialGroupResources($v = true)
    {
        $this->collGroupResourcesPartial = $v;
    }

    /**
     * Initializes the collGroupResources collection.
     *
     * By default this just sets the collGroupResources collection to an empty array (like clearcollGroupResources());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initGroupResources($overrideExisting = true)
    {
        if (null !== $this->collGroupResources && !$overrideExisting) {
            return;
        }
        $this->collGroupResources = new PropelObjectCollection();
        $this->collGroupResources->setModel('GroupResource');
    }

    /**
     * Gets an array of GroupResource objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Group is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|GroupResource[] List of GroupResource objects
     * @throws PropelException
     */
    public function getGroupResources($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collGroupResourcesPartial && !$this->isNew();
        if (null === $this->collGroupResources || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collGroupResources) {
                // return empty collection
                $this->initGroupResources();
            } else {
                $collGroupResources = GroupResourceQuery::create(null, $criteria)
                    ->filterByGroup($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collGroupResourcesPartial && count($collGroupResources)) {
                      $this->initGroupResources(false);

                      foreach($collGroupResources as $obj) {
                        if (false == $this->collGroupResources->contains($obj)) {
                          $this->collGroupResources->append($obj);
                        }
                      }

                      $this->collGroupResourcesPartial = true;
                    }

                    $collGroupResources->getInternalIterator()->rewind();
                    return $collGroupResources;
                }

                if($partial && $this->collGroupResources) {
                    foreach($this->collGroupResources as $obj) {
                        if($obj->isNew()) {
                            $collGroupResources[] = $obj;
                        }
                    }
                }

                $this->collGroupResources = $collGroupResources;
                $this->collGroupResourcesPartial = false;
            }
        }

        return $this->collGroupResources;
    }

    /**
     * Sets a collection of GroupResource objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $groupResources A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Group The current object (for fluent API support)
     */
    public function setGroupResources(PropelCollection $groupResources, PropelPDO $con = null)
    {
        $groupResourcesToDelete = $this->getGroupResources(new Criteria(), $con)->diff($groupResources);

        $this->groupResourcesScheduledForDeletion = unserialize(serialize($groupResourcesToDelete));

        foreach ($groupResourcesToDelete as $groupResourceRemoved) {
            $groupResourceRemoved->setGroup(null);
        }

        $this->collGroupResources = null;
        foreach ($groupResources as $groupResource) {
            $this->addGroupResource($groupResource);
        }

        $this->collGroupResources = $groupResources;
        $this->collGroupResourcesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related GroupResource objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related GroupResource objects.
     * @throws PropelException
     */
    public function countGroupResources(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collGroupResourcesPartial && !$this->isNew();
        if (null === $this->collGroupResources || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collGroupResources) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getGroupResources());
            }
            $query = GroupResourceQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByGroup($this)
                ->count($con);
        }

        return count($this->collGroupResources);
    }

    /**
     * Method called to associate a GroupResource object to this object
     * through the GroupResource foreign key attribute.
     *
     * @param    GroupResource $l GroupResource
     * @return Group The current object (for fluent API support)
     */
    public function addGroupResource(GroupResource $l)
    {
        if ($this->collGroupResources === null) {
            $this->initGroupResources();
            $this->collGroupResourcesPartial = true;
        }
        if (!in_array($l, $this->collGroupResources->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddGroupResource($l);
        }

        return $this;
    }

    /**
     * @param	GroupResource $groupResource The groupResource object to add.
     */
    protected function doAddGroupResource($groupResource)
    {
        $this->collGroupResources[]= $groupResource;
        $groupResource->setGroup($this);
    }

    /**
     * @param	GroupResource $groupResource The groupResource object to remove.
     * @return Group The current object (for fluent API support)
     */
    public function removeGroupResource($groupResource)
    {
        if ($this->getGroupResources()->contains($groupResource)) {
            $this->collGroupResources->remove($this->collGroupResources->search($groupResource));
            if (null === $this->groupResourcesScheduledForDeletion) {
                $this->groupResourcesScheduledForDeletion = clone $this->collGroupResources;
                $this->groupResourcesScheduledForDeletion->clear();
            }
            $this->groupResourcesScheduledForDeletion[]= clone $groupResource;
            $groupResource->setGroup(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Group is new, it will return
     * an empty collection; or if this Group has previously
     * been saved, it will retrieve related GroupResources from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Group.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|GroupResource[] List of GroupResource objects
     */
    public function getGroupResourcesJoinResource($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = GroupResourceQuery::create(null, $criteria);
        $query->joinWith('Resource', $join_behavior);

        return $this->getGroupResources($query, $con);
    }

    /**
     * Clears out the collGroupModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Group The current object (for fluent API support)
     * @see        addGroupModules()
     */
    public function clearGroupModules()
    {
        $this->collGroupModules = null; // important to set this to null since that means it is uninitialized
        $this->collGroupModulesPartial = null;

        return $this;
    }

    /**
     * reset is the collGroupModules collection loaded partially
     *
     * @return void
     */
    public function resetPartialGroupModules($v = true)
    {
        $this->collGroupModulesPartial = $v;
    }

    /**
     * Initializes the collGroupModules collection.
     *
     * By default this just sets the collGroupModules collection to an empty array (like clearcollGroupModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initGroupModules($overrideExisting = true)
    {
        if (null !== $this->collGroupModules && !$overrideExisting) {
            return;
        }
        $this->collGroupModules = new PropelObjectCollection();
        $this->collGroupModules->setModel('GroupModule');
    }

    /**
     * Gets an array of GroupModule objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Group is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|GroupModule[] List of GroupModule objects
     * @throws PropelException
     */
    public function getGroupModules($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collGroupModulesPartial && !$this->isNew();
        if (null === $this->collGroupModules || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collGroupModules) {
                // return empty collection
                $this->initGroupModules();
            } else {
                $collGroupModules = GroupModuleQuery::create(null, $criteria)
                    ->filterByGroup($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collGroupModulesPartial && count($collGroupModules)) {
                      $this->initGroupModules(false);

                      foreach($collGroupModules as $obj) {
                        if (false == $this->collGroupModules->contains($obj)) {
                          $this->collGroupModules->append($obj);
                        }
                      }

                      $this->collGroupModulesPartial = true;
                    }

                    $collGroupModules->getInternalIterator()->rewind();
                    return $collGroupModules;
                }

                if($partial && $this->collGroupModules) {
                    foreach($this->collGroupModules as $obj) {
                        if($obj->isNew()) {
                            $collGroupModules[] = $obj;
                        }
                    }
                }

                $this->collGroupModules = $collGroupModules;
                $this->collGroupModulesPartial = false;
            }
        }

        return $this->collGroupModules;
    }

    /**
     * Sets a collection of GroupModule objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $groupModules A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Group The current object (for fluent API support)
     */
    public function setGroupModules(PropelCollection $groupModules, PropelPDO $con = null)
    {
        $groupModulesToDelete = $this->getGroupModules(new Criteria(), $con)->diff($groupModules);

        $this->groupModulesScheduledForDeletion = unserialize(serialize($groupModulesToDelete));

        foreach ($groupModulesToDelete as $groupModuleRemoved) {
            $groupModuleRemoved->setGroup(null);
        }

        $this->collGroupModules = null;
        foreach ($groupModules as $groupModule) {
            $this->addGroupModule($groupModule);
        }

        $this->collGroupModules = $groupModules;
        $this->collGroupModulesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related GroupModule objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related GroupModule objects.
     * @throws PropelException
     */
    public function countGroupModules(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collGroupModulesPartial && !$this->isNew();
        if (null === $this->collGroupModules || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collGroupModules) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getGroupModules());
            }
            $query = GroupModuleQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByGroup($this)
                ->count($con);
        }

        return count($this->collGroupModules);
    }

    /**
     * Method called to associate a GroupModule object to this object
     * through the GroupModule foreign key attribute.
     *
     * @param    GroupModule $l GroupModule
     * @return Group The current object (for fluent API support)
     */
    public function addGroupModule(GroupModule $l)
    {
        if ($this->collGroupModules === null) {
            $this->initGroupModules();
            $this->collGroupModulesPartial = true;
        }
        if (!in_array($l, $this->collGroupModules->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddGroupModule($l);
        }

        return $this;
    }

    /**
     * @param	GroupModule $groupModule The groupModule object to add.
     */
    protected function doAddGroupModule($groupModule)
    {
        $this->collGroupModules[]= $groupModule;
        $groupModule->setGroup($this);
    }

    /**
     * @param	GroupModule $groupModule The groupModule object to remove.
     * @return Group The current object (for fluent API support)
     */
    public function removeGroupModule($groupModule)
    {
        if ($this->getGroupModules()->contains($groupModule)) {
            $this->collGroupModules->remove($this->collGroupModules->search($groupModule));
            if (null === $this->groupModulesScheduledForDeletion) {
                $this->groupModulesScheduledForDeletion = clone $this->collGroupModules;
                $this->groupModulesScheduledForDeletion->clear();
            }
            $this->groupModulesScheduledForDeletion[]= clone $groupModule;
            $groupModule->setGroup(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Group is new, it will return
     * an empty collection; or if this Group has previously
     * been saved, it will retrieve related GroupModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Group.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|GroupModule[] List of GroupModule objects
     */
    public function getGroupModulesJoinModule($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = GroupModuleQuery::create(null, $criteria);
        $query->joinWith('Module', $join_behavior);

        return $this->getGroupModules($query, $con);
    }

    /**
     * Clears out the collGroupI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return Group The current object (for fluent API support)
     * @see        addGroupI18ns()
     */
    public function clearGroupI18ns()
    {
        $this->collGroupI18ns = null; // important to set this to null since that means it is uninitialized
        $this->collGroupI18nsPartial = null;

        return $this;
    }

    /**
     * reset is the collGroupI18ns collection loaded partially
     *
     * @return void
     */
    public function resetPartialGroupI18ns($v = true)
    {
        $this->collGroupI18nsPartial = $v;
    }

    /**
     * Initializes the collGroupI18ns collection.
     *
     * By default this just sets the collGroupI18ns collection to an empty array (like clearcollGroupI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initGroupI18ns($overrideExisting = true)
    {
        if (null !== $this->collGroupI18ns && !$overrideExisting) {
            return;
        }
        $this->collGroupI18ns = new PropelObjectCollection();
        $this->collGroupI18ns->setModel('GroupI18n');
    }

    /**
     * Gets an array of GroupI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Group is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|GroupI18n[] List of GroupI18n objects
     * @throws PropelException
     */
    public function getGroupI18ns($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collGroupI18nsPartial && !$this->isNew();
        if (null === $this->collGroupI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collGroupI18ns) {
                // return empty collection
                $this->initGroupI18ns();
            } else {
                $collGroupI18ns = GroupI18nQuery::create(null, $criteria)
                    ->filterByGroup($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collGroupI18nsPartial && count($collGroupI18ns)) {
                      $this->initGroupI18ns(false);

                      foreach($collGroupI18ns as $obj) {
                        if (false == $this->collGroupI18ns->contains($obj)) {
                          $this->collGroupI18ns->append($obj);
                        }
                      }

                      $this->collGroupI18nsPartial = true;
                    }

                    $collGroupI18ns->getInternalIterator()->rewind();
                    return $collGroupI18ns;
                }

                if($partial && $this->collGroupI18ns) {
                    foreach($this->collGroupI18ns as $obj) {
                        if($obj->isNew()) {
                            $collGroupI18ns[] = $obj;
                        }
                    }
                }

                $this->collGroupI18ns = $collGroupI18ns;
                $this->collGroupI18nsPartial = false;
            }
        }

        return $this->collGroupI18ns;
    }

    /**
     * Sets a collection of GroupI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $groupI18ns A Propel collection.
     * @param PropelPDO $con Optional connection object
     * @return Group The current object (for fluent API support)
     */
    public function setGroupI18ns(PropelCollection $groupI18ns, PropelPDO $con = null)
    {
        $groupI18nsToDelete = $this->getGroupI18ns(new Criteria(), $con)->diff($groupI18ns);

        $this->groupI18nsScheduledForDeletion = unserialize(serialize($groupI18nsToDelete));

        foreach ($groupI18nsToDelete as $groupI18nRemoved) {
            $groupI18nRemoved->setGroup(null);
        }

        $this->collGroupI18ns = null;
        foreach ($groupI18ns as $groupI18n) {
            $this->addGroupI18n($groupI18n);
        }

        $this->collGroupI18ns = $groupI18ns;
        $this->collGroupI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related GroupI18n objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related GroupI18n objects.
     * @throws PropelException
     */
    public function countGroupI18ns(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collGroupI18nsPartial && !$this->isNew();
        if (null === $this->collGroupI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collGroupI18ns) {
                return 0;
            }

            if($partial && !$criteria) {
                return count($this->getGroupI18ns());
            }
            $query = GroupI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByGroup($this)
                ->count($con);
        }

        return count($this->collGroupI18ns);
    }

    /**
     * Method called to associate a GroupI18n object to this object
     * through the GroupI18n foreign key attribute.
     *
     * @param    GroupI18n $l GroupI18n
     * @return Group The current object (for fluent API support)
     */
    public function addGroupI18n(GroupI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collGroupI18ns === null) {
            $this->initGroupI18ns();
            $this->collGroupI18nsPartial = true;
        }
        if (!in_array($l, $this->collGroupI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddGroupI18n($l);
        }

        return $this;
    }

    /**
     * @param	GroupI18n $groupI18n The groupI18n object to add.
     */
    protected function doAddGroupI18n($groupI18n)
    {
        $this->collGroupI18ns[]= $groupI18n;
        $groupI18n->setGroup($this);
    }

    /**
     * @param	GroupI18n $groupI18n The groupI18n object to remove.
     * @return Group The current object (for fluent API support)
     */
    public function removeGroupI18n($groupI18n)
    {
        if ($this->getGroupI18ns()->contains($groupI18n)) {
            $this->collGroupI18ns->remove($this->collGroupI18ns->search($groupI18n));
            if (null === $this->groupI18nsScheduledForDeletion) {
                $this->groupI18nsScheduledForDeletion = clone $this->collGroupI18ns;
                $this->groupI18nsScheduledForDeletion->clear();
            }
            $this->groupI18nsScheduledForDeletion[]= clone $groupI18n;
            $groupI18n->setGroup(null);
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
            if ($this->collAdminGroups) {
                foreach ($this->collAdminGroups as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collGroupResources) {
                foreach ($this->collGroupResources as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collGroupModules) {
                foreach ($this->collGroupModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collGroupI18ns) {
                foreach ($this->collGroupI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }

            $this->alreadyInClearAllReferencesDeep = false;
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        if ($this->collAdminGroups instanceof PropelCollection) {
            $this->collAdminGroups->clearIterator();
        }
        $this->collAdminGroups = null;
        if ($this->collGroupResources instanceof PropelCollection) {
            $this->collGroupResources->clearIterator();
        }
        $this->collGroupResources = null;
        if ($this->collGroupModules instanceof PropelCollection) {
            $this->collGroupModules->clearIterator();
        }
        $this->collGroupModules = null;
        if ($this->collGroupI18ns instanceof PropelCollection) {
            $this->collGroupI18ns->clearIterator();
        }
        $this->collGroupI18ns = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(GroupPeer::DEFAULT_STRING_FORMAT);
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
     * @return     Group The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = GroupPeer::UPDATED_AT;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    Group The current object (for fluent API support)
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
     * @return GroupI18n */
    public function getTranslation($locale = 'en_US', PropelPDO $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collGroupI18ns) {
                foreach ($this->collGroupI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new GroupI18n();
                $translation->setLocale($locale);
            } else {
                $translation = GroupI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addGroupI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     PropelPDO $con an optional connection object
     *
     * @return    Group The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', PropelPDO $con = null)
    {
        if (!$this->isNew()) {
            GroupI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collGroupI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collGroupI18ns[$key]);
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
     * @return GroupI18n */
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
         * @return GroupI18n The current object (for fluent API support)
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
         * @return GroupI18n The current object (for fluent API support)
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
         * @return GroupI18n The current object (for fluent API support)
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
         * @return GroupI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }

}
