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
use Thelia\Model\FeatureAvI18n;
use Thelia\Model\FeatureAvI18nQuery;
use Thelia\Model\FeatureAvPeer;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureProd;
use Thelia\Model\FeatureProdQuery;
use Thelia\Model\FeatureQuery;

/**
 * Base class that represents a row from the 'feature_av' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseFeatureAv extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\FeatureAvPeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        FeatureAvPeer
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
     * The value for the feature_id field.
     * @var        int
     */
    protected $feature_id;

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
     * @var        Feature
     */
    protected $aFeature;

    /**
     * @var        PropelObjectCollection|FeatureProd[] Collection to store aggregation of FeatureProd objects.
     */
    protected $collFeatureProds;
    protected $collFeatureProdsPartial;

    /**
     * @var        PropelObjectCollection|FeatureAvI18n[] Collection to store aggregation of FeatureAvI18n objects.
     */
    protected $collFeatureAvI18ns;
    protected $collFeatureAvI18nsPartial;

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

    // i18n behavior

    /**
     * Current locale
     * @var        string
     */
    protected $currentLocale = 'en_EN';

    /**
     * Current translation objects
     * @var        array[FeatureAvI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $featureProdsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $featureAvI18nsScheduledForDeletion = null;

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
     * Get the [feature_id] column value.
     *
     * @return int
     */
    public function getFeatureId()
    {
        return $this->feature_id;
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
     * @return FeatureAv The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = FeatureAvPeer::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [feature_id] column.
     *
     * @param int $v new value
     * @return FeatureAv The current object (for fluent API support)
     */
    public function setFeatureId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->feature_id !== $v) {
            $this->feature_id = $v;
            $this->modifiedColumns[] = FeatureAvPeer::FEATURE_ID;
        }

        if ($this->aFeature !== null && $this->aFeature->getId() !== $v) {
            $this->aFeature = null;
        }


        return $this;
    } // setFeatureId()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return FeatureAv The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = FeatureAvPeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return FeatureAv The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = FeatureAvPeer::UPDATED_AT;
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
            $this->feature_id = ($row[$startcol + 1] !== null) ? (int) $row[$startcol + 1] : null;
            $this->created_at = ($row[$startcol + 2] !== null) ? (string) $row[$startcol + 2] : null;
            $this->updated_at = ($row[$startcol + 3] !== null) ? (string) $row[$startcol + 3] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 4; // 4 = FeatureAvPeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating FeatureAv object", $e);
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

        if ($this->aFeature !== null && $this->feature_id !== $this->aFeature->getId()) {
            $this->aFeature = null;
        }
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
            $con = Propel::getConnection(FeatureAvPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = FeatureAvPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aFeature = null;
            $this->collFeatureProds = null;

            $this->collFeatureAvI18ns = null;

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
            $con = Propel::getConnection(FeatureAvPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = FeatureAvQuery::create()
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
            $con = Propel::getConnection(FeatureAvPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(FeatureAvPeer::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(FeatureAvPeer::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(FeatureAvPeer::UPDATED_AT)) {
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
                FeatureAvPeer::addInstanceToPool($this);
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

            // We call the save method on the following object(s) if they
            // were passed to this object by their coresponding set
            // method.  This object relates to these object(s) by a
            // foreign key reference.

            if ($this->aFeature !== null) {
                if ($this->aFeature->isModified() || $this->aFeature->isNew()) {
                    $affectedRows += $this->aFeature->save($con);
                }
                $this->setFeature($this->aFeature);
            }

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

            if ($this->featureProdsScheduledForDeletion !== null) {
                if (!$this->featureProdsScheduledForDeletion->isEmpty()) {
                    foreach ($this->featureProdsScheduledForDeletion as $featureProd) {
                        // need to save related object because we set the relation to null
                        $featureProd->save($con);
                    }
                    $this->featureProdsScheduledForDeletion = null;
                }
            }

            if ($this->collFeatureProds !== null) {
                foreach ($this->collFeatureProds as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureAvI18nsScheduledForDeletion !== null) {
                if (!$this->featureAvI18nsScheduledForDeletion->isEmpty()) {
                    FeatureAvI18nQuery::create()
                        ->filterByPrimaryKeys($this->featureAvI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureAvI18nsScheduledForDeletion = null;
                }
            }

            if ($this->collFeatureAvI18ns !== null) {
                foreach ($this->collFeatureAvI18ns as $referrerFK) {
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

        $this->modifiedColumns[] = FeatureAvPeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . FeatureAvPeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(FeatureAvPeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(FeatureAvPeer::FEATURE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`FEATURE_ID`';
        }
        if ($this->isColumnModified(FeatureAvPeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(FeatureAvPeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `feature_av` (%s) VALUES (%s)',
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
                    case '`FEATURE_ID`':
                        $stmt->bindValue($identifier, $this->feature_id, PDO::PARAM_INT);
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


            // We call the validate method on the following object(s) if they
            // were passed to this object by their coresponding set
            // method.  This object relates to these object(s) by a
            // foreign key reference.

            if ($this->aFeature !== null) {
                if (!$this->aFeature->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aFeature->getValidationFailures());
                }
            }


            if (($retval = FeatureAvPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collFeatureProds !== null) {
                    foreach ($this->collFeatureProds as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collFeatureAvI18ns !== null) {
                    foreach ($this->collFeatureAvI18ns as $referrerFK) {
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
        $pos = FeatureAvPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
                return $this->getFeatureId();
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
        if (isset($alreadyDumpedObjects['FeatureAv'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['FeatureAv'][$this->getPrimaryKey()] = true;
        $keys = FeatureAvPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getFeatureId(),
            $keys[2] => $this->getCreatedAt(),
            $keys[3] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->aFeature) {
                $result['Feature'] = $this->aFeature->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collFeatureProds) {
                $result['FeatureProds'] = $this->collFeatureProds->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureAvI18ns) {
                $result['FeatureAvI18ns'] = $this->collFeatureAvI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = FeatureAvPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
                $this->setFeatureId($value);
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
        $keys = FeatureAvPeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setFeatureId($arr[$keys[1]]);
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
        $criteria = new Criteria(FeatureAvPeer::DATABASE_NAME);

        if ($this->isColumnModified(FeatureAvPeer::ID)) $criteria->add(FeatureAvPeer::ID, $this->id);
        if ($this->isColumnModified(FeatureAvPeer::FEATURE_ID)) $criteria->add(FeatureAvPeer::FEATURE_ID, $this->feature_id);
        if ($this->isColumnModified(FeatureAvPeer::CREATED_AT)) $criteria->add(FeatureAvPeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(FeatureAvPeer::UPDATED_AT)) $criteria->add(FeatureAvPeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(FeatureAvPeer::DATABASE_NAME);
        $criteria->add(FeatureAvPeer::ID, $this->id);

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
     * @param object $copyObj An object of FeatureAv (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setFeatureId($this->getFeatureId());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            foreach ($this->getFeatureProds() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureProd($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureAvI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureAvI18n($relObj->copy($deepCopy));
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
     * @return FeatureAv Clone of current object.
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
     * @return FeatureAvPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new FeatureAvPeer();
        }

        return self::$peer;
    }

    /**
     * Declares an association between this object and a Feature object.
     *
     * @param             Feature $v
     * @return FeatureAv The current object (for fluent API support)
     * @throws PropelException
     */
    public function setFeature(Feature $v = null)
    {
        if ($v === null) {
            $this->setFeatureId(NULL);
        } else {
            $this->setFeatureId($v->getId());
        }

        $this->aFeature = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the Feature object, it will not be re-added.
        if ($v !== null) {
            $v->addFeatureAv($this);
        }


        return $this;
    }


    /**
     * Get the associated Feature object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Feature The associated Feature object.
     * @throws PropelException
     */
    public function getFeature(PropelPDO $con = null)
    {
        if ($this->aFeature === null && ($this->feature_id !== null)) {
            $this->aFeature = FeatureQuery::create()->findPk($this->feature_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aFeature->addFeatureAvs($this);
             */
        }

        return $this->aFeature;
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
        if ('FeatureProd' == $relationName) {
            $this->initFeatureProds();
        }
        if ('FeatureAvI18n' == $relationName) {
            $this->initFeatureAvI18ns();
        }
    }

    /**
     * Clears out the collFeatureProds collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureProds()
     */
    public function clearFeatureProds()
    {
        $this->collFeatureProds = null; // important to set this to null since that means it is uninitialized
        $this->collFeatureProdsPartial = null;
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
     * If this FeatureAv is new, it will return
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
                    ->filterByFeatureAv($this)
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
     */
    public function setFeatureProds(PropelCollection $featureProds, PropelPDO $con = null)
    {
        $this->featureProdsScheduledForDeletion = $this->getFeatureProds(new Criteria(), $con)->diff($featureProds);

        foreach ($this->featureProdsScheduledForDeletion as $featureProdRemoved) {
            $featureProdRemoved->setFeatureAv(null);
        }

        $this->collFeatureProds = null;
        foreach ($featureProds as $featureProd) {
            $this->addFeatureProd($featureProd);
        }

        $this->collFeatureProds = $featureProds;
        $this->collFeatureProdsPartial = false;
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
            } else {
                if($partial && !$criteria) {
                    return count($this->getFeatureProds());
                }
                $query = FeatureProdQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByFeatureAv($this)
                    ->count($con);
            }
        } else {
            return count($this->collFeatureProds);
        }
    }

    /**
     * Method called to associate a FeatureProd object to this object
     * through the FeatureProd foreign key attribute.
     *
     * @param    FeatureProd $l FeatureProd
     * @return FeatureAv The current object (for fluent API support)
     */
    public function addFeatureProd(FeatureProd $l)
    {
        if ($this->collFeatureProds === null) {
            $this->initFeatureProds();
            $this->collFeatureProdsPartial = true;
        }
        if (!$this->collFeatureProds->contains($l)) { // only add it if the **same** object is not already associated
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
        $featureProd->setFeatureAv($this);
    }

    /**
     * @param	FeatureProd $featureProd The featureProd object to remove.
     */
    public function removeFeatureProd($featureProd)
    {
        if ($this->getFeatureProds()->contains($featureProd)) {
            $this->collFeatureProds->remove($this->collFeatureProds->search($featureProd));
            if (null === $this->featureProdsScheduledForDeletion) {
                $this->featureProdsScheduledForDeletion = clone $this->collFeatureProds;
                $this->featureProdsScheduledForDeletion->clear();
            }
            $this->featureProdsScheduledForDeletion[]= $featureProd;
            $featureProd->setFeatureAv(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this FeatureAv is new, it will return
     * an empty collection; or if this FeatureAv has previously
     * been saved, it will retrieve related FeatureProds from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in FeatureAv.
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
     * Otherwise if this FeatureAv is new, it will return
     * an empty collection; or if this FeatureAv has previously
     * been saved, it will retrieve related FeatureProds from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in FeatureAv.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|FeatureProd[] List of FeatureProd objects
     */
    public function getFeatureProdsJoinFeature($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = FeatureProdQuery::create(null, $criteria);
        $query->joinWith('Feature', $join_behavior);

        return $this->getFeatureProds($query, $con);
    }

    /**
     * Clears out the collFeatureAvI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureAvI18ns()
     */
    public function clearFeatureAvI18ns()
    {
        $this->collFeatureAvI18ns = null; // important to set this to null since that means it is uninitialized
        $this->collFeatureAvI18nsPartial = null;
    }

    /**
     * reset is the collFeatureAvI18ns collection loaded partially
     *
     * @return void
     */
    public function resetPartialFeatureAvI18ns($v = true)
    {
        $this->collFeatureAvI18nsPartial = $v;
    }

    /**
     * Initializes the collFeatureAvI18ns collection.
     *
     * By default this just sets the collFeatureAvI18ns collection to an empty array (like clearcollFeatureAvI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureAvI18ns($overrideExisting = true)
    {
        if (null !== $this->collFeatureAvI18ns && !$overrideExisting) {
            return;
        }
        $this->collFeatureAvI18ns = new PropelObjectCollection();
        $this->collFeatureAvI18ns->setModel('FeatureAvI18n');
    }

    /**
     * Gets an array of FeatureAvI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this FeatureAv is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|FeatureAvI18n[] List of FeatureAvI18n objects
     * @throws PropelException
     */
    public function getFeatureAvI18ns($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collFeatureAvI18nsPartial && !$this->isNew();
        if (null === $this->collFeatureAvI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureAvI18ns) {
                // return empty collection
                $this->initFeatureAvI18ns();
            } else {
                $collFeatureAvI18ns = FeatureAvI18nQuery::create(null, $criteria)
                    ->filterByFeatureAv($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collFeatureAvI18nsPartial && count($collFeatureAvI18ns)) {
                      $this->initFeatureAvI18ns(false);

                      foreach($collFeatureAvI18ns as $obj) {
                        if (false == $this->collFeatureAvI18ns->contains($obj)) {
                          $this->collFeatureAvI18ns->append($obj);
                        }
                      }

                      $this->collFeatureAvI18nsPartial = true;
                    }

                    return $collFeatureAvI18ns;
                }

                if($partial && $this->collFeatureAvI18ns) {
                    foreach($this->collFeatureAvI18ns as $obj) {
                        if($obj->isNew()) {
                            $collFeatureAvI18ns[] = $obj;
                        }
                    }
                }

                $this->collFeatureAvI18ns = $collFeatureAvI18ns;
                $this->collFeatureAvI18nsPartial = false;
            }
        }

        return $this->collFeatureAvI18ns;
    }

    /**
     * Sets a collection of FeatureAvI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $featureAvI18ns A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setFeatureAvI18ns(PropelCollection $featureAvI18ns, PropelPDO $con = null)
    {
        $this->featureAvI18nsScheduledForDeletion = $this->getFeatureAvI18ns(new Criteria(), $con)->diff($featureAvI18ns);

        foreach ($this->featureAvI18nsScheduledForDeletion as $featureAvI18nRemoved) {
            $featureAvI18nRemoved->setFeatureAv(null);
        }

        $this->collFeatureAvI18ns = null;
        foreach ($featureAvI18ns as $featureAvI18n) {
            $this->addFeatureAvI18n($featureAvI18n);
        }

        $this->collFeatureAvI18ns = $featureAvI18ns;
        $this->collFeatureAvI18nsPartial = false;
    }

    /**
     * Returns the number of related FeatureAvI18n objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related FeatureAvI18n objects.
     * @throws PropelException
     */
    public function countFeatureAvI18ns(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collFeatureAvI18nsPartial && !$this->isNew();
        if (null === $this->collFeatureAvI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureAvI18ns) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getFeatureAvI18ns());
                }
                $query = FeatureAvI18nQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByFeatureAv($this)
                    ->count($con);
            }
        } else {
            return count($this->collFeatureAvI18ns);
        }
    }

    /**
     * Method called to associate a FeatureAvI18n object to this object
     * through the FeatureAvI18n foreign key attribute.
     *
     * @param    FeatureAvI18n $l FeatureAvI18n
     * @return FeatureAv The current object (for fluent API support)
     */
    public function addFeatureAvI18n(FeatureAvI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collFeatureAvI18ns === null) {
            $this->initFeatureAvI18ns();
            $this->collFeatureAvI18nsPartial = true;
        }
        if (!$this->collFeatureAvI18ns->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureAvI18n($l);
        }

        return $this;
    }

    /**
     * @param	FeatureAvI18n $featureAvI18n The featureAvI18n object to add.
     */
    protected function doAddFeatureAvI18n($featureAvI18n)
    {
        $this->collFeatureAvI18ns[]= $featureAvI18n;
        $featureAvI18n->setFeatureAv($this);
    }

    /**
     * @param	FeatureAvI18n $featureAvI18n The featureAvI18n object to remove.
     */
    public function removeFeatureAvI18n($featureAvI18n)
    {
        if ($this->getFeatureAvI18ns()->contains($featureAvI18n)) {
            $this->collFeatureAvI18ns->remove($this->collFeatureAvI18ns->search($featureAvI18n));
            if (null === $this->featureAvI18nsScheduledForDeletion) {
                $this->featureAvI18nsScheduledForDeletion = clone $this->collFeatureAvI18ns;
                $this->featureAvI18nsScheduledForDeletion->clear();
            }
            $this->featureAvI18nsScheduledForDeletion[]= $featureAvI18n;
            $featureAvI18n->setFeatureAv(null);
        }
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->feature_id = null;
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
            if ($this->collFeatureProds) {
                foreach ($this->collFeatureProds as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureAvI18ns) {
                foreach ($this->collFeatureAvI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_EN';
        $this->currentTranslations = null;

        if ($this->collFeatureProds instanceof PropelCollection) {
            $this->collFeatureProds->clearIterator();
        }
        $this->collFeatureProds = null;
        if ($this->collFeatureAvI18ns instanceof PropelCollection) {
            $this->collFeatureAvI18ns->clearIterator();
        }
        $this->collFeatureAvI18ns = null;
        $this->aFeature = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(FeatureAvPeer::DEFAULT_STRING_FORMAT);
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
     * @return     FeatureAv The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = FeatureAvPeer::UPDATED_AT;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    FeatureAv The current object (for fluent API support)
     */
    public function setLocale($locale = 'en_EN')
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
     * @return FeatureAvI18n */
    public function getTranslation($locale = 'en_EN', PropelPDO $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collFeatureAvI18ns) {
                foreach ($this->collFeatureAvI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new FeatureAvI18n();
                $translation->setLocale($locale);
            } else {
                $translation = FeatureAvI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addFeatureAvI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     PropelPDO $con an optional connection object
     *
     * @return    FeatureAv The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_EN', PropelPDO $con = null)
    {
        if (!$this->isNew()) {
            FeatureAvI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collFeatureAvI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collFeatureAvI18ns[$key]);
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
     * @return FeatureAvI18n */
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
         * @return FeatureAvI18n The current object (for fluent API support)
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
         * @return FeatureAvI18n The current object (for fluent API support)
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
         * @return FeatureAvI18n The current object (for fluent API support)
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
         * @return FeatureAvI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }

}
