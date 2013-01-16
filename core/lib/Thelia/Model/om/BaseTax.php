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
use Thelia\Model\Tax;
use Thelia\Model\TaxDesc;
use Thelia\Model\TaxDescQuery;
use Thelia\Model\TaxPeer;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleCountry;
use Thelia\Model\TaxRuleCountryQuery;

/**
 * Base class that represents a row from the 'tax' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseTax extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\TaxPeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        TaxPeer
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
     * The value for the rate field.
     * @var        double
     */
    protected $rate;

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
     * @var        PropelObjectCollection|TaxDesc[] Collection to store aggregation of TaxDesc objects.
     */
    protected $collTaxDescs;
    protected $collTaxDescsPartial;

    /**
     * @var        PropelObjectCollection|TaxRuleCountry[] Collection to store aggregation of TaxRuleCountry objects.
     */
    protected $collTaxRuleCountrys;
    protected $collTaxRuleCountrysPartial;

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
    protected $taxDescsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $taxRuleCountrysScheduledForDeletion = null;

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
     * Get the [rate] column value.
     *
     * @return double
     */
    public function getRate()
    {
        return $this->rate;
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
     * @return Tax The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = TaxPeer::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [rate] column.
     *
     * @param double $v new value
     * @return Tax The current object (for fluent API support)
     */
    public function setRate($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->rate !== $v) {
            $this->rate = $v;
            $this->modifiedColumns[] = TaxPeer::RATE;
        }


        return $this;
    } // setRate()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Tax The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = TaxPeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Tax The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = TaxPeer::UPDATED_AT;
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
            $this->rate = ($row[$startcol + 1] !== null) ? (double) $row[$startcol + 1] : null;
            $this->created_at = ($row[$startcol + 2] !== null) ? (string) $row[$startcol + 2] : null;
            $this->updated_at = ($row[$startcol + 3] !== null) ? (string) $row[$startcol + 3] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 4; // 4 = TaxPeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Tax object", $e);
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
            $con = Propel::getConnection(TaxPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = TaxPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collTaxDescs = null;

            $this->collTaxRuleCountrys = null;

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
            $con = Propel::getConnection(TaxPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = TaxQuery::create()
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
            $con = Propel::getConnection(TaxPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
            } else {
                $ret = $ret && $this->preUpdate($con);
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                TaxPeer::addInstanceToPool($this);
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

            if ($this->taxDescsScheduledForDeletion !== null) {
                if (!$this->taxDescsScheduledForDeletion->isEmpty()) {
                    TaxDescQuery::create()
                        ->filterByPrimaryKeys($this->taxDescsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->taxDescsScheduledForDeletion = null;
                }
            }

            if ($this->collTaxDescs !== null) {
                foreach ($this->collTaxDescs as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->taxRuleCountrysScheduledForDeletion !== null) {
                if (!$this->taxRuleCountrysScheduledForDeletion->isEmpty()) {
                    foreach ($this->taxRuleCountrysScheduledForDeletion as $taxRuleCountry) {
                        // need to save related object because we set the relation to null
                        $taxRuleCountry->save($con);
                    }
                    $this->taxRuleCountrysScheduledForDeletion = null;
                }
            }

            if ($this->collTaxRuleCountrys !== null) {
                foreach ($this->collTaxRuleCountrys as $referrerFK) {
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

        $this->modifiedColumns[] = TaxPeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . TaxPeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(TaxPeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(TaxPeer::RATE)) {
            $modifiedColumns[':p' . $index++]  = '`RATE`';
        }
        if ($this->isColumnModified(TaxPeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(TaxPeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `tax` (%s) VALUES (%s)',
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
                    case '`RATE`':
                        $stmt->bindValue($identifier, $this->rate, PDO::PARAM_STR);
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


            if (($retval = TaxPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collTaxDescs !== null) {
                    foreach ($this->collTaxDescs as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collTaxRuleCountrys !== null) {
                    foreach ($this->collTaxRuleCountrys as $referrerFK) {
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
        $pos = TaxPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
                return $this->getRate();
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
        if (isset($alreadyDumpedObjects['Tax'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Tax'][$this->getPrimaryKey()] = true;
        $keys = TaxPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getRate(),
            $keys[2] => $this->getCreatedAt(),
            $keys[3] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->collTaxDescs) {
                $result['TaxDescs'] = $this->collTaxDescs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collTaxRuleCountrys) {
                $result['TaxRuleCountrys'] = $this->collTaxRuleCountrys->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = TaxPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
                $this->setRate($value);
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
        $keys = TaxPeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setRate($arr[$keys[1]]);
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
        $criteria = new Criteria(TaxPeer::DATABASE_NAME);

        if ($this->isColumnModified(TaxPeer::ID)) $criteria->add(TaxPeer::ID, $this->id);
        if ($this->isColumnModified(TaxPeer::RATE)) $criteria->add(TaxPeer::RATE, $this->rate);
        if ($this->isColumnModified(TaxPeer::CREATED_AT)) $criteria->add(TaxPeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(TaxPeer::UPDATED_AT)) $criteria->add(TaxPeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(TaxPeer::DATABASE_NAME);
        $criteria->add(TaxPeer::ID, $this->id);

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
     * @param object $copyObj An object of Tax (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setRate($this->getRate());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            foreach ($this->getTaxDescs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addTaxDesc($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getTaxRuleCountrys() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addTaxRuleCountry($relObj->copy($deepCopy));
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
     * @return Tax Clone of current object.
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
     * @return TaxPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new TaxPeer();
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
        if ('TaxDesc' == $relationName) {
            $this->initTaxDescs();
        }
        if ('TaxRuleCountry' == $relationName) {
            $this->initTaxRuleCountrys();
        }
    }

    /**
     * Clears out the collTaxDescs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addTaxDescs()
     */
    public function clearTaxDescs()
    {
        $this->collTaxDescs = null; // important to set this to null since that means it is uninitialized
        $this->collTaxDescsPartial = null;
    }

    /**
     * reset is the collTaxDescs collection loaded partially
     *
     * @return void
     */
    public function resetPartialTaxDescs($v = true)
    {
        $this->collTaxDescsPartial = $v;
    }

    /**
     * Initializes the collTaxDescs collection.
     *
     * By default this just sets the collTaxDescs collection to an empty array (like clearcollTaxDescs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initTaxDescs($overrideExisting = true)
    {
        if (null !== $this->collTaxDescs && !$overrideExisting) {
            return;
        }
        $this->collTaxDescs = new PropelObjectCollection();
        $this->collTaxDescs->setModel('TaxDesc');
    }

    /**
     * Gets an array of TaxDesc objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Tax is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|TaxDesc[] List of TaxDesc objects
     * @throws PropelException
     */
    public function getTaxDescs($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collTaxDescsPartial && !$this->isNew();
        if (null === $this->collTaxDescs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collTaxDescs) {
                // return empty collection
                $this->initTaxDescs();
            } else {
                $collTaxDescs = TaxDescQuery::create(null, $criteria)
                    ->filterByTax($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collTaxDescsPartial && count($collTaxDescs)) {
                      $this->initTaxDescs(false);

                      foreach($collTaxDescs as $obj) {
                        if (false == $this->collTaxDescs->contains($obj)) {
                          $this->collTaxDescs->append($obj);
                        }
                      }

                      $this->collTaxDescsPartial = true;
                    }

                    return $collTaxDescs;
                }

                if($partial && $this->collTaxDescs) {
                    foreach($this->collTaxDescs as $obj) {
                        if($obj->isNew()) {
                            $collTaxDescs[] = $obj;
                        }
                    }
                }

                $this->collTaxDescs = $collTaxDescs;
                $this->collTaxDescsPartial = false;
            }
        }

        return $this->collTaxDescs;
    }

    /**
     * Sets a collection of TaxDesc objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $taxDescs A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setTaxDescs(PropelCollection $taxDescs, PropelPDO $con = null)
    {
        $this->taxDescsScheduledForDeletion = $this->getTaxDescs(new Criteria(), $con)->diff($taxDescs);

        foreach ($this->taxDescsScheduledForDeletion as $taxDescRemoved) {
            $taxDescRemoved->setTax(null);
        }

        $this->collTaxDescs = null;
        foreach ($taxDescs as $taxDesc) {
            $this->addTaxDesc($taxDesc);
        }

        $this->collTaxDescs = $taxDescs;
        $this->collTaxDescsPartial = false;
    }

    /**
     * Returns the number of related TaxDesc objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related TaxDesc objects.
     * @throws PropelException
     */
    public function countTaxDescs(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collTaxDescsPartial && !$this->isNew();
        if (null === $this->collTaxDescs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collTaxDescs) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getTaxDescs());
                }
                $query = TaxDescQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByTax($this)
                    ->count($con);
            }
        } else {
            return count($this->collTaxDescs);
        }
    }

    /**
     * Method called to associate a TaxDesc object to this object
     * through the TaxDesc foreign key attribute.
     *
     * @param    TaxDesc $l TaxDesc
     * @return Tax The current object (for fluent API support)
     */
    public function addTaxDesc(TaxDesc $l)
    {
        if ($this->collTaxDescs === null) {
            $this->initTaxDescs();
            $this->collTaxDescsPartial = true;
        }
        if (!$this->collTaxDescs->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddTaxDesc($l);
        }

        return $this;
    }

    /**
     * @param	TaxDesc $taxDesc The taxDesc object to add.
     */
    protected function doAddTaxDesc($taxDesc)
    {
        $this->collTaxDescs[]= $taxDesc;
        $taxDesc->setTax($this);
    }

    /**
     * @param	TaxDesc $taxDesc The taxDesc object to remove.
     */
    public function removeTaxDesc($taxDesc)
    {
        if ($this->getTaxDescs()->contains($taxDesc)) {
            $this->collTaxDescs->remove($this->collTaxDescs->search($taxDesc));
            if (null === $this->taxDescsScheduledForDeletion) {
                $this->taxDescsScheduledForDeletion = clone $this->collTaxDescs;
                $this->taxDescsScheduledForDeletion->clear();
            }
            $this->taxDescsScheduledForDeletion[]= $taxDesc;
            $taxDesc->setTax(null);
        }
    }

    /**
     * Clears out the collTaxRuleCountrys collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addTaxRuleCountrys()
     */
    public function clearTaxRuleCountrys()
    {
        $this->collTaxRuleCountrys = null; // important to set this to null since that means it is uninitialized
        $this->collTaxRuleCountrysPartial = null;
    }

    /**
     * reset is the collTaxRuleCountrys collection loaded partially
     *
     * @return void
     */
    public function resetPartialTaxRuleCountrys($v = true)
    {
        $this->collTaxRuleCountrysPartial = $v;
    }

    /**
     * Initializes the collTaxRuleCountrys collection.
     *
     * By default this just sets the collTaxRuleCountrys collection to an empty array (like clearcollTaxRuleCountrys());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initTaxRuleCountrys($overrideExisting = true)
    {
        if (null !== $this->collTaxRuleCountrys && !$overrideExisting) {
            return;
        }
        $this->collTaxRuleCountrys = new PropelObjectCollection();
        $this->collTaxRuleCountrys->setModel('TaxRuleCountry');
    }

    /**
     * Gets an array of TaxRuleCountry objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Tax is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|TaxRuleCountry[] List of TaxRuleCountry objects
     * @throws PropelException
     */
    public function getTaxRuleCountrys($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collTaxRuleCountrysPartial && !$this->isNew();
        if (null === $this->collTaxRuleCountrys || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collTaxRuleCountrys) {
                // return empty collection
                $this->initTaxRuleCountrys();
            } else {
                $collTaxRuleCountrys = TaxRuleCountryQuery::create(null, $criteria)
                    ->filterByTax($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collTaxRuleCountrysPartial && count($collTaxRuleCountrys)) {
                      $this->initTaxRuleCountrys(false);

                      foreach($collTaxRuleCountrys as $obj) {
                        if (false == $this->collTaxRuleCountrys->contains($obj)) {
                          $this->collTaxRuleCountrys->append($obj);
                        }
                      }

                      $this->collTaxRuleCountrysPartial = true;
                    }

                    return $collTaxRuleCountrys;
                }

                if($partial && $this->collTaxRuleCountrys) {
                    foreach($this->collTaxRuleCountrys as $obj) {
                        if($obj->isNew()) {
                            $collTaxRuleCountrys[] = $obj;
                        }
                    }
                }

                $this->collTaxRuleCountrys = $collTaxRuleCountrys;
                $this->collTaxRuleCountrysPartial = false;
            }
        }

        return $this->collTaxRuleCountrys;
    }

    /**
     * Sets a collection of TaxRuleCountry objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $taxRuleCountrys A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setTaxRuleCountrys(PropelCollection $taxRuleCountrys, PropelPDO $con = null)
    {
        $this->taxRuleCountrysScheduledForDeletion = $this->getTaxRuleCountrys(new Criteria(), $con)->diff($taxRuleCountrys);

        foreach ($this->taxRuleCountrysScheduledForDeletion as $taxRuleCountryRemoved) {
            $taxRuleCountryRemoved->setTax(null);
        }

        $this->collTaxRuleCountrys = null;
        foreach ($taxRuleCountrys as $taxRuleCountry) {
            $this->addTaxRuleCountry($taxRuleCountry);
        }

        $this->collTaxRuleCountrys = $taxRuleCountrys;
        $this->collTaxRuleCountrysPartial = false;
    }

    /**
     * Returns the number of related TaxRuleCountry objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related TaxRuleCountry objects.
     * @throws PropelException
     */
    public function countTaxRuleCountrys(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collTaxRuleCountrysPartial && !$this->isNew();
        if (null === $this->collTaxRuleCountrys || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collTaxRuleCountrys) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getTaxRuleCountrys());
                }
                $query = TaxRuleCountryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByTax($this)
                    ->count($con);
            }
        } else {
            return count($this->collTaxRuleCountrys);
        }
    }

    /**
     * Method called to associate a TaxRuleCountry object to this object
     * through the TaxRuleCountry foreign key attribute.
     *
     * @param    TaxRuleCountry $l TaxRuleCountry
     * @return Tax The current object (for fluent API support)
     */
    public function addTaxRuleCountry(TaxRuleCountry $l)
    {
        if ($this->collTaxRuleCountrys === null) {
            $this->initTaxRuleCountrys();
            $this->collTaxRuleCountrysPartial = true;
        }
        if (!$this->collTaxRuleCountrys->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddTaxRuleCountry($l);
        }

        return $this;
    }

    /**
     * @param	TaxRuleCountry $taxRuleCountry The taxRuleCountry object to add.
     */
    protected function doAddTaxRuleCountry($taxRuleCountry)
    {
        $this->collTaxRuleCountrys[]= $taxRuleCountry;
        $taxRuleCountry->setTax($this);
    }

    /**
     * @param	TaxRuleCountry $taxRuleCountry The taxRuleCountry object to remove.
     */
    public function removeTaxRuleCountry($taxRuleCountry)
    {
        if ($this->getTaxRuleCountrys()->contains($taxRuleCountry)) {
            $this->collTaxRuleCountrys->remove($this->collTaxRuleCountrys->search($taxRuleCountry));
            if (null === $this->taxRuleCountrysScheduledForDeletion) {
                $this->taxRuleCountrysScheduledForDeletion = clone $this->collTaxRuleCountrys;
                $this->taxRuleCountrysScheduledForDeletion->clear();
            }
            $this->taxRuleCountrysScheduledForDeletion[]= $taxRuleCountry;
            $taxRuleCountry->setTax(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Tax is new, it will return
     * an empty collection; or if this Tax has previously
     * been saved, it will retrieve related TaxRuleCountrys from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Tax.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|TaxRuleCountry[] List of TaxRuleCountry objects
     */
    public function getTaxRuleCountrysJoinTaxRule($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = TaxRuleCountryQuery::create(null, $criteria);
        $query->joinWith('TaxRule', $join_behavior);

        return $this->getTaxRuleCountrys($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Tax is new, it will return
     * an empty collection; or if this Tax has previously
     * been saved, it will retrieve related TaxRuleCountrys from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Tax.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|TaxRuleCountry[] List of TaxRuleCountry objects
     */
    public function getTaxRuleCountrysJoinCountry($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = TaxRuleCountryQuery::create(null, $criteria);
        $query->joinWith('Country', $join_behavior);

        return $this->getTaxRuleCountrys($query, $con);
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->rate = null;
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
            if ($this->collTaxDescs) {
                foreach ($this->collTaxDescs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collTaxRuleCountrys) {
                foreach ($this->collTaxRuleCountrys as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        if ($this->collTaxDescs instanceof PropelCollection) {
            $this->collTaxDescs->clearIterator();
        }
        $this->collTaxDescs = null;
        if ($this->collTaxRuleCountrys instanceof PropelCollection) {
            $this->collTaxRuleCountrys->clearIterator();
        }
        $this->collTaxRuleCountrys = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(TaxPeer::DEFAULT_STRING_FORMAT);
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

}
