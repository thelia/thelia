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
use Thelia\Model\GroupModule;
use Thelia\Model\GroupModuleQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleDesc;
use Thelia\Model\ModuleDescQuery;
use Thelia\Model\ModulePeer;
use Thelia\Model\ModuleQuery;

/**
 * Base class that represents a row from the 'module' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseModule extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\ModulePeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        ModulePeer
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
     * The value for the  type field.
     * @var        int
     */
    protected $ type;

    /**
     * The value for the activate field.
     * @var        int
     */
    protected $activate;

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
     * @var        PropelObjectCollection|ModuleDesc[] Collection to store aggregation of ModuleDesc objects.
     */
    protected $collModuleDescs;
    protected $collModuleDescsPartial;

    /**
     * @var        PropelObjectCollection|GroupModule[] Collection to store aggregation of GroupModule objects.
     */
    protected $collGroupModules;
    protected $collGroupModulesPartial;

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
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $moduleDescsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $groupModulesScheduledForDeletion = null;

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
     * Get the [ type] column value.
     *
     * @return int
     */
    public function get type()
    {
        return $this-> type;
    }

    /**
     * Get the [activate] column value.
     *
     * @return int
     */
    public function getActivate()
    {
        return $this->activate;
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
        } else {
            try {
                $dt = new DateTime($this->created_at);
            } catch (Exception $x) {
                throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($this->created_at, true), $x);
            }
        }

        if ($format === null) {
            // Because propel.useDateTimeClass is true, we return a DateTime object.
            return $dt;
        } elseif (strpos($format, '%') !== false) {
            return strftime($format, $dt->format('U'));
        } else {
            return $dt->format($format);
        }
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
        } else {
            try {
                $dt = new DateTime($this->updated_at);
            } catch (Exception $x) {
                throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($this->updated_at, true), $x);
            }
        }

        if ($format === null) {
            // Because propel.useDateTimeClass is true, we return a DateTime object.
            return $dt;
        } elseif (strpos($format, '%') !== false) {
            return strftime($format, $dt->format('U'));
        } else {
            return $dt->format($format);
        }
    }

    /**
     * Set the value of [id] column.
     *
     * @param int $v new value
     * @return Module The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = ModulePeer::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [code] column.
     *
     * @param string $v new value
     * @return Module The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[] = ModulePeer::CODE;
        }


        return $this;
    } // setCode()

    /**
     * Set the value of [ type] column.
     *
     * @param int $v new value
     * @return Module The current object (for fluent API support)
     */
    public function set type($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this-> type !== $v) {
            $this-> type = $v;
            $this->modifiedColumns[] = ModulePeer:: TYPE;
        }


        return $this;
    } // set type()

    /**
     * Set the value of [activate] column.
     *
     * @param int $v new value
     * @return Module The current object (for fluent API support)
     */
    public function setActivate($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->activate !== $v) {
            $this->activate = $v;
            $this->modifiedColumns[] = ModulePeer::ACTIVATE;
        }


        return $this;
    } // setActivate()

    /**
     * Set the value of [position] column.
     *
     * @param int $v new value
     * @return Module The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[] = ModulePeer::POSITION;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Module The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = ModulePeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Module The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = ModulePeer::UPDATED_AT;
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
            $this-> type = ($row[$startcol + 2] !== null) ? (int) $row[$startcol + 2] : null;
            $this->activate = ($row[$startcol + 3] !== null) ? (int) $row[$startcol + 3] : null;
            $this->position = ($row[$startcol + 4] !== null) ? (int) $row[$startcol + 4] : null;
            $this->created_at = ($row[$startcol + 5] !== null) ? (string) $row[$startcol + 5] : null;
            $this->updated_at = ($row[$startcol + 6] !== null) ? (string) $row[$startcol + 6] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 7; // 7 = ModulePeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Module object", $e);
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
            $con = Propel::getConnection(ModulePeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = ModulePeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collModuleDescs = null;

            $this->collGroupModules = null;

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
            $con = Propel::getConnection(ModulePeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ModuleQuery::create()
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
            $con = Propel::getConnection(ModulePeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(ModulePeer::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(ModulePeer::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(ModulePeer::UPDATED_AT)) {
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
                ModulePeer::addInstanceToPool($this);
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

            if ($this->moduleDescsScheduledForDeletion !== null) {
                if (!$this->moduleDescsScheduledForDeletion->isEmpty()) {
                    ModuleDescQuery::create()
                        ->filterByPrimaryKeys($this->moduleDescsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->moduleDescsScheduledForDeletion = null;
                }
            }

            if ($this->collModuleDescs !== null) {
                foreach ($this->collModuleDescs as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->groupModulesScheduledForDeletion !== null) {
                if (!$this->groupModulesScheduledForDeletion->isEmpty()) {
                    foreach ($this->groupModulesScheduledForDeletion as $groupModule) {
                        // need to save related object because we set the relation to null
                        $groupModule->save($con);
                    }
                    $this->groupModulesScheduledForDeletion = null;
                }
            }

            if ($this->collGroupModules !== null) {
                foreach ($this->collGroupModules as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
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


         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(ModulePeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(ModulePeer::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`CODE`';
        }
        if ($this->isColumnModified(ModulePeer:: TYPE)) {
            $modifiedColumns[':p' . $index++]  = '` TYPE`';
        }
        if ($this->isColumnModified(ModulePeer::ACTIVATE)) {
            $modifiedColumns[':p' . $index++]  = '`ACTIVATE`';
        }
        if ($this->isColumnModified(ModulePeer::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(ModulePeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(ModulePeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `module` (%s) VALUES (%s)',
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
                    case '` TYPE`':
                        $stmt->bindValue($identifier, $this-> type, PDO::PARAM_INT);
                        break;
                    case '`ACTIVATE`':
                        $stmt->bindValue($identifier, $this->activate, PDO::PARAM_INT);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
                        break;
                    case '`CREATED_AT`':
                        $stmt->bindValue($identifier, $this->created_at, PDO::PARAM_STR);
                        break;
                    case '`UPDATED_AT`':
                        $stmt->bindValue($identifier, $this->updated_at, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), $e);
        }

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
        } else {
            $this->validationFailures = $res;

            return false;
        }
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


            if (($retval = ModulePeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collModuleDescs !== null) {
                    foreach ($this->collModuleDescs as $referrerFK) {
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
        $pos = ModulePeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
                return $this->get type();
                break;
            case 3:
                return $this->getActivate();
                break;
            case 4:
                return $this->getPosition();
                break;
            case 5:
                return $this->getCreatedAt();
                break;
            case 6:
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
        if (isset($alreadyDumpedObjects['Module'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Module'][$this->getPrimaryKey()] = true;
        $keys = ModulePeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCode(),
            $keys[2] => $this->get type(),
            $keys[3] => $this->getActivate(),
            $keys[4] => $this->getPosition(),
            $keys[5] => $this->getCreatedAt(),
            $keys[6] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->collModuleDescs) {
                $result['ModuleDescs'] = $this->collModuleDescs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collGroupModules) {
                $result['GroupModules'] = $this->collGroupModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = ModulePeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
                $this->set type($value);
                break;
            case 3:
                $this->setActivate($value);
                break;
            case 4:
                $this->setPosition($value);
                break;
            case 5:
                $this->setCreatedAt($value);
                break;
            case 6:
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
        $keys = ModulePeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCode($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->set type($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setActivate($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setPosition($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setCreatedAt($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setUpdatedAt($arr[$keys[6]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(ModulePeer::DATABASE_NAME);

        if ($this->isColumnModified(ModulePeer::ID)) $criteria->add(ModulePeer::ID, $this->id);
        if ($this->isColumnModified(ModulePeer::CODE)) $criteria->add(ModulePeer::CODE, $this->code);
        if ($this->isColumnModified(ModulePeer:: TYPE)) $criteria->add(ModulePeer:: TYPE, $this-> type);
        if ($this->isColumnModified(ModulePeer::ACTIVATE)) $criteria->add(ModulePeer::ACTIVATE, $this->activate);
        if ($this->isColumnModified(ModulePeer::POSITION)) $criteria->add(ModulePeer::POSITION, $this->position);
        if ($this->isColumnModified(ModulePeer::CREATED_AT)) $criteria->add(ModulePeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(ModulePeer::UPDATED_AT)) $criteria->add(ModulePeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(ModulePeer::DATABASE_NAME);
        $criteria->add(ModulePeer::ID, $this->id);

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
     * @param object $copyObj An object of Module (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCode($this->getCode());
        $copyObj->set type($this->get type());
        $copyObj->setActivate($this->getActivate());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            foreach ($this->getModuleDescs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addModuleDesc($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getGroupModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addGroupModule($relObj->copy($deepCopy));
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
     * @return Module Clone of current object.
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
     * @return ModulePeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new ModulePeer();
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
        if ('ModuleDesc' == $relationName) {
            $this->initModuleDescs();
        }
        if ('GroupModule' == $relationName) {
            $this->initGroupModules();
        }
    }

    /**
     * Clears out the collModuleDescs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addModuleDescs()
     */
    public function clearModuleDescs()
    {
        $this->collModuleDescs = null; // important to set this to null since that means it is uninitialized
        $this->collModuleDescsPartial = null;
    }

    /**
     * reset is the collModuleDescs collection loaded partially
     *
     * @return void
     */
    public function resetPartialModuleDescs($v = true)
    {
        $this->collModuleDescsPartial = $v;
    }

    /**
     * Initializes the collModuleDescs collection.
     *
     * By default this just sets the collModuleDescs collection to an empty array (like clearcollModuleDescs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initModuleDescs($overrideExisting = true)
    {
        if (null !== $this->collModuleDescs && !$overrideExisting) {
            return;
        }
        $this->collModuleDescs = new PropelObjectCollection();
        $this->collModuleDescs->setModel('ModuleDesc');
    }

    /**
     * Gets an array of ModuleDesc objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Module is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|ModuleDesc[] List of ModuleDesc objects
     * @throws PropelException
     */
    public function getModuleDescs($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collModuleDescsPartial && !$this->isNew();
        if (null === $this->collModuleDescs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collModuleDescs) {
                // return empty collection
                $this->initModuleDescs();
            } else {
                $collModuleDescs = ModuleDescQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collModuleDescsPartial && count($collModuleDescs)) {
                      $this->initModuleDescs(false);

                      foreach($collModuleDescs as $obj) {
                        if (false == $this->collModuleDescs->contains($obj)) {
                          $this->collModuleDescs->append($obj);
                        }
                      }

                      $this->collModuleDescsPartial = true;
                    }

                    return $collModuleDescs;
                }

                if($partial && $this->collModuleDescs) {
                    foreach($this->collModuleDescs as $obj) {
                        if($obj->isNew()) {
                            $collModuleDescs[] = $obj;
                        }
                    }
                }

                $this->collModuleDescs = $collModuleDescs;
                $this->collModuleDescsPartial = false;
            }
        }

        return $this->collModuleDescs;
    }

    /**
     * Sets a collection of ModuleDesc objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $moduleDescs A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setModuleDescs(PropelCollection $moduleDescs, PropelPDO $con = null)
    {
        $this->moduleDescsScheduledForDeletion = $this->getModuleDescs(new Criteria(), $con)->diff($moduleDescs);

        foreach ($this->moduleDescsScheduledForDeletion as $moduleDescRemoved) {
            $moduleDescRemoved->setModule(null);
        }

        $this->collModuleDescs = null;
        foreach ($moduleDescs as $moduleDesc) {
            $this->addModuleDesc($moduleDesc);
        }

        $this->collModuleDescs = $moduleDescs;
        $this->collModuleDescsPartial = false;
    }

    /**
     * Returns the number of related ModuleDesc objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related ModuleDesc objects.
     * @throws PropelException
     */
    public function countModuleDescs(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collModuleDescsPartial && !$this->isNew();
        if (null === $this->collModuleDescs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collModuleDescs) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getModuleDescs());
                }
                $query = ModuleDescQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByModule($this)
                    ->count($con);
            }
        } else {
            return count($this->collModuleDescs);
        }
    }

    /**
     * Method called to associate a ModuleDesc object to this object
     * through the ModuleDesc foreign key attribute.
     *
     * @param    ModuleDesc $l ModuleDesc
     * @return Module The current object (for fluent API support)
     */
    public function addModuleDesc(ModuleDesc $l)
    {
        if ($this->collModuleDescs === null) {
            $this->initModuleDescs();
            $this->collModuleDescsPartial = true;
        }
        if (!$this->collModuleDescs->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddModuleDesc($l);
        }

        return $this;
    }

    /**
     * @param	ModuleDesc $moduleDesc The moduleDesc object to add.
     */
    protected function doAddModuleDesc($moduleDesc)
    {
        $this->collModuleDescs[]= $moduleDesc;
        $moduleDesc->setModule($this);
    }

    /**
     * @param	ModuleDesc $moduleDesc The moduleDesc object to remove.
     */
    public function removeModuleDesc($moduleDesc)
    {
        if ($this->getModuleDescs()->contains($moduleDesc)) {
            $this->collModuleDescs->remove($this->collModuleDescs->search($moduleDesc));
            if (null === $this->moduleDescsScheduledForDeletion) {
                $this->moduleDescsScheduledForDeletion = clone $this->collModuleDescs;
                $this->moduleDescsScheduledForDeletion->clear();
            }
            $this->moduleDescsScheduledForDeletion[]= $moduleDesc;
            $moduleDesc->setModule(null);
        }
    }

    /**
     * Clears out the collGroupModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addGroupModules()
     */
    public function clearGroupModules()
    {
        $this->collGroupModules = null; // important to set this to null since that means it is uninitialized
        $this->collGroupModulesPartial = null;
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
     * If this Module is new, it will return
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
                    ->filterByModule($this)
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
     */
    public function setGroupModules(PropelCollection $groupModules, PropelPDO $con = null)
    {
        $this->groupModulesScheduledForDeletion = $this->getGroupModules(new Criteria(), $con)->diff($groupModules);

        foreach ($this->groupModulesScheduledForDeletion as $groupModuleRemoved) {
            $groupModuleRemoved->setModule(null);
        }

        $this->collGroupModules = null;
        foreach ($groupModules as $groupModule) {
            $this->addGroupModule($groupModule);
        }

        $this->collGroupModules = $groupModules;
        $this->collGroupModulesPartial = false;
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
            } else {
                if($partial && !$criteria) {
                    return count($this->getGroupModules());
                }
                $query = GroupModuleQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByModule($this)
                    ->count($con);
            }
        } else {
            return count($this->collGroupModules);
        }
    }

    /**
     * Method called to associate a GroupModule object to this object
     * through the GroupModule foreign key attribute.
     *
     * @param    GroupModule $l GroupModule
     * @return Module The current object (for fluent API support)
     */
    public function addGroupModule(GroupModule $l)
    {
        if ($this->collGroupModules === null) {
            $this->initGroupModules();
            $this->collGroupModulesPartial = true;
        }
        if (!$this->collGroupModules->contains($l)) { // only add it if the **same** object is not already associated
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
        $groupModule->setModule($this);
    }

    /**
     * @param	GroupModule $groupModule The groupModule object to remove.
     */
    public function removeGroupModule($groupModule)
    {
        if ($this->getGroupModules()->contains($groupModule)) {
            $this->collGroupModules->remove($this->collGroupModules->search($groupModule));
            if (null === $this->groupModulesScheduledForDeletion) {
                $this->groupModulesScheduledForDeletion = clone $this->collGroupModules;
                $this->groupModulesScheduledForDeletion->clear();
            }
            $this->groupModulesScheduledForDeletion[]= $groupModule;
            $groupModule->setModule(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related GroupModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|GroupModule[] List of GroupModule objects
     */
    public function getGroupModulesJoinGroup($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = GroupModuleQuery::create(null, $criteria);
        $query->joinWith('Group', $join_behavior);

        return $this->getGroupModules($query, $con);
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->code = null;
        $this-> type = null;
        $this->activate = null;
        $this->position = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->alreadyInSave = false;
        $this->alreadyInValidation = false;
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
        if ($deep) {
            if ($this->collModuleDescs) {
                foreach ($this->collModuleDescs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collGroupModules) {
                foreach ($this->collGroupModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        if ($this->collModuleDescs instanceof PropelCollection) {
            $this->collModuleDescs->clearIterator();
        }
        $this->collModuleDescs = null;
        if ($this->collGroupModules instanceof PropelCollection) {
            $this->collGroupModules->clearIterator();
        }
        $this->collGroupModules = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(ModulePeer::DEFAULT_STRING_FORMAT);
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
     * @return     Module The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = ModulePeer::UPDATED_AT;

        return $this;
    }

}
