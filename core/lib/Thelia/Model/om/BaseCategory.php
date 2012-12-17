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
use \PropelDateTime;
use \PropelException;
use \PropelPDO;
use Thelia\Model\AttributeCategory;
use Thelia\Model\AttributeCategoryQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryDesc;
use Thelia\Model\CategoryDescQuery;
use Thelia\Model\CategoryPeer;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentAssoc;
use Thelia\Model\ContentAssocQuery;
use Thelia\Model\Document;
use Thelia\Model\DocumentQuery;
use Thelia\Model\FeatureCategory;
use Thelia\Model\FeatureCategoryQuery;
use Thelia\Model\Image;
use Thelia\Model\ImageQuery;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\Rewriting;
use Thelia\Model\RewritingQuery;

/**
 * Base class that represents a row from the 'category' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseCategory extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\CategoryPeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        CategoryPeer
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
     * The value for the parent field.
     * @var        int
     */
    protected $parent;

    /**
     * The value for the link field.
     * @var        string
     */
    protected $link;

    /**
     * The value for the visible field.
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
     * The value for the update_at field.
     * @var        string
     */
    protected $update_at;

    /**
     * @var        AttributeCategory
     */
    protected $aAttributeCategory;

    /**
     * @var        CategoryDesc
     */
    protected $aCategoryDesc;

    /**
     * @var        ContentAssoc
     */
    protected $aContentAssoc;

    /**
     * @var        Document
     */
    protected $aDocument;

    /**
     * @var        FeatureCategory
     */
    protected $aFeatureCategory;

    /**
     * @var        Image
     */
    protected $aImage;

    /**
     * @var        ProductCategory
     */
    protected $aProductCategory;

    /**
     * @var        Rewriting
     */
    protected $aRewriting;

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
     * Get the [id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the [parent] column value.
     *
     * @return int
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get the [link] column value.
     *
     * @return string
     */
    public function getLink()
    {
        return $this->link;
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
     * Get the [optionally formatted] temporal [update_at] column value.
     *
     *
     * @param string $format The date/time format string (either date()-style or strftime()-style).
     *				 If format is null, then the raw DateTime object will be returned.
     * @return mixed Formatted date/time value as string or DateTime object (if format is null), null if column is null, and 0 if column value is 0000-00-00 00:00:00
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getUpdateAt($format = 'Y-m-d H:i:s')
    {
        if ($this->update_at === null) {
            return null;
        }

        if ($this->update_at === '0000-00-00 00:00:00') {
            // while technically this is not a default value of null,
            // this seems to be closest in meaning.
            return null;
        } else {
            try {
                $dt = new DateTime($this->update_at);
            } catch (Exception $x) {
                throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($this->update_at, true), $x);
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
     * @return Category The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = CategoryPeer::ID;
        }

        if ($this->aAttributeCategory !== null && $this->aAttributeCategory->getCategoryId() !== $v) {
            $this->aAttributeCategory = null;
        }

        if ($this->aCategoryDesc !== null && $this->aCategoryDesc->getCategoryId() !== $v) {
            $this->aCategoryDesc = null;
        }

        if ($this->aContentAssoc !== null && $this->aContentAssoc->getCategoryId() !== $v) {
            $this->aContentAssoc = null;
        }

        if ($this->aDocument !== null && $this->aDocument->getCategoryId() !== $v) {
            $this->aDocument = null;
        }

        if ($this->aFeatureCategory !== null && $this->aFeatureCategory->getCategoryId() !== $v) {
            $this->aFeatureCategory = null;
        }

        if ($this->aImage !== null && $this->aImage->getCategoryId() !== $v) {
            $this->aImage = null;
        }

        if ($this->aProductCategory !== null && $this->aProductCategory->getCategoryId() !== $v) {
            $this->aProductCategory = null;
        }

        if ($this->aRewriting !== null && $this->aRewriting->getCategoryId() !== $v) {
            $this->aRewriting = null;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [parent] column.
     *
     * @param int $v new value
     * @return Category The current object (for fluent API support)
     */
    public function setParent($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->parent !== $v) {
            $this->parent = $v;
            $this->modifiedColumns[] = CategoryPeer::PARENT;
        }


        return $this;
    } // setParent()

    /**
     * Set the value of [link] column.
     *
     * @param string $v new value
     * @return Category The current object (for fluent API support)
     */
    public function setLink($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->link !== $v) {
            $this->link = $v;
            $this->modifiedColumns[] = CategoryPeer::LINK;
        }


        return $this;
    } // setLink()

    /**
     * Set the value of [visible] column.
     *
     * @param int $v new value
     * @return Category The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[] = CategoryPeer::VISIBLE;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [position] column.
     *
     * @param int $v new value
     * @return Category The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[] = CategoryPeer::POSITION;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Category The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = CategoryPeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [update_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Category The current object (for fluent API support)
     */
    public function setUpdateAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->update_at !== null || $dt !== null) {
            $currentDateAsString = ($this->update_at !== null && $tmpDt = new DateTime($this->update_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->update_at = $newDateAsString;
                $this->modifiedColumns[] = CategoryPeer::UPDATE_AT;
            }
        } // if either are not null


        return $this;
    } // setUpdateAt()

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
            $this->parent = ($row[$startcol + 1] !== null) ? (int) $row[$startcol + 1] : null;
            $this->link = ($row[$startcol + 2] !== null) ? (string) $row[$startcol + 2] : null;
            $this->visible = ($row[$startcol + 3] !== null) ? (int) $row[$startcol + 3] : null;
            $this->position = ($row[$startcol + 4] !== null) ? (int) $row[$startcol + 4] : null;
            $this->created_at = ($row[$startcol + 5] !== null) ? (string) $row[$startcol + 5] : null;
            $this->update_at = ($row[$startcol + 6] !== null) ? (string) $row[$startcol + 6] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 7; // 7 = CategoryPeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Category object", $e);
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

        if ($this->aAttributeCategory !== null && $this->id !== $this->aAttributeCategory->getCategoryId()) {
            $this->aAttributeCategory = null;
        }
        if ($this->aCategoryDesc !== null && $this->id !== $this->aCategoryDesc->getCategoryId()) {
            $this->aCategoryDesc = null;
        }
        if ($this->aContentAssoc !== null && $this->id !== $this->aContentAssoc->getCategoryId()) {
            $this->aContentAssoc = null;
        }
        if ($this->aDocument !== null && $this->id !== $this->aDocument->getCategoryId()) {
            $this->aDocument = null;
        }
        if ($this->aFeatureCategory !== null && $this->id !== $this->aFeatureCategory->getCategoryId()) {
            $this->aFeatureCategory = null;
        }
        if ($this->aImage !== null && $this->id !== $this->aImage->getCategoryId()) {
            $this->aImage = null;
        }
        if ($this->aProductCategory !== null && $this->id !== $this->aProductCategory->getCategoryId()) {
            $this->aProductCategory = null;
        }
        if ($this->aRewriting !== null && $this->id !== $this->aRewriting->getCategoryId()) {
            $this->aRewriting = null;
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
            $con = Propel::getConnection(CategoryPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = CategoryPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aAttributeCategory = null;
            $this->aCategoryDesc = null;
            $this->aContentAssoc = null;
            $this->aDocument = null;
            $this->aFeatureCategory = null;
            $this->aImage = null;
            $this->aProductCategory = null;
            $this->aRewriting = null;
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
            $con = Propel::getConnection(CategoryPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = CategoryQuery::create()
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
            $con = Propel::getConnection(CategoryPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
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
                CategoryPeer::addInstanceToPool($this);
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

            if ($this->aAttributeCategory !== null) {
                if ($this->aAttributeCategory->isModified() || $this->aAttributeCategory->isNew()) {
                    $affectedRows += $this->aAttributeCategory->save($con);
                }
                $this->setAttributeCategory($this->aAttributeCategory);
            }

            if ($this->aCategoryDesc !== null) {
                if ($this->aCategoryDesc->isModified() || $this->aCategoryDesc->isNew()) {
                    $affectedRows += $this->aCategoryDesc->save($con);
                }
                $this->setCategoryDesc($this->aCategoryDesc);
            }

            if ($this->aContentAssoc !== null) {
                if ($this->aContentAssoc->isModified() || $this->aContentAssoc->isNew()) {
                    $affectedRows += $this->aContentAssoc->save($con);
                }
                $this->setContentAssoc($this->aContentAssoc);
            }

            if ($this->aDocument !== null) {
                if ($this->aDocument->isModified() || $this->aDocument->isNew()) {
                    $affectedRows += $this->aDocument->save($con);
                }
                $this->setDocument($this->aDocument);
            }

            if ($this->aFeatureCategory !== null) {
                if ($this->aFeatureCategory->isModified() || $this->aFeatureCategory->isNew()) {
                    $affectedRows += $this->aFeatureCategory->save($con);
                }
                $this->setFeatureCategory($this->aFeatureCategory);
            }

            if ($this->aImage !== null) {
                if ($this->aImage->isModified() || $this->aImage->isNew()) {
                    $affectedRows += $this->aImage->save($con);
                }
                $this->setImage($this->aImage);
            }

            if ($this->aProductCategory !== null) {
                if ($this->aProductCategory->isModified() || $this->aProductCategory->isNew()) {
                    $affectedRows += $this->aProductCategory->save($con);
                }
                $this->setProductCategory($this->aProductCategory);
            }

            if ($this->aRewriting !== null) {
                if ($this->aRewriting->isModified() || $this->aRewriting->isNew()) {
                    $affectedRows += $this->aRewriting->save($con);
                }
                $this->setRewriting($this->aRewriting);
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

        $this->modifiedColumns[] = CategoryPeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . CategoryPeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CategoryPeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CategoryPeer::PARENT)) {
            $modifiedColumns[':p' . $index++]  = '`PARENT`';
        }
        if ($this->isColumnModified(CategoryPeer::LINK)) {
            $modifiedColumns[':p' . $index++]  = '`LINK`';
        }
        if ($this->isColumnModified(CategoryPeer::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`VISIBLE`';
        }
        if ($this->isColumnModified(CategoryPeer::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(CategoryPeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CategoryPeer::UPDATE_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATE_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `category` (%s) VALUES (%s)',
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
                    case '`PARENT`':
                        $stmt->bindValue($identifier, $this->parent, PDO::PARAM_INT);
                        break;
                    case '`LINK`':
                        $stmt->bindValue($identifier, $this->link, PDO::PARAM_STR);
                        break;
                    case '`VISIBLE`':
                        $stmt->bindValue($identifier, $this->visible, PDO::PARAM_INT);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
                        break;
                    case '`CREATED_AT`':
                        $stmt->bindValue($identifier, $this->created_at, PDO::PARAM_STR);
                        break;
                    case '`UPDATE_AT`':
                        $stmt->bindValue($identifier, $this->update_at, PDO::PARAM_STR);
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

            if ($this->aAttributeCategory !== null) {
                if (!$this->aAttributeCategory->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aAttributeCategory->getValidationFailures());
                }
            }

            if ($this->aCategoryDesc !== null) {
                if (!$this->aCategoryDesc->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aCategoryDesc->getValidationFailures());
                }
            }

            if ($this->aContentAssoc !== null) {
                if (!$this->aContentAssoc->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aContentAssoc->getValidationFailures());
                }
            }

            if ($this->aDocument !== null) {
                if (!$this->aDocument->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aDocument->getValidationFailures());
                }
            }

            if ($this->aFeatureCategory !== null) {
                if (!$this->aFeatureCategory->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aFeatureCategory->getValidationFailures());
                }
            }

            if ($this->aImage !== null) {
                if (!$this->aImage->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aImage->getValidationFailures());
                }
            }

            if ($this->aProductCategory !== null) {
                if (!$this->aProductCategory->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aProductCategory->getValidationFailures());
                }
            }

            if ($this->aRewriting !== null) {
                if (!$this->aRewriting->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aRewriting->getValidationFailures());
                }
            }


            if (($retval = CategoryPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
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
        $pos = CategoryPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
                return $this->getParent();
                break;
            case 2:
                return $this->getLink();
                break;
            case 3:
                return $this->getVisible();
                break;
            case 4:
                return $this->getPosition();
                break;
            case 5:
                return $this->getCreatedAt();
                break;
            case 6:
                return $this->getUpdateAt();
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
        if (isset($alreadyDumpedObjects['Category'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Category'][$this->getPrimaryKey()] = true;
        $keys = CategoryPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getParent(),
            $keys[2] => $this->getLink(),
            $keys[3] => $this->getVisible(),
            $keys[4] => $this->getPosition(),
            $keys[5] => $this->getCreatedAt(),
            $keys[6] => $this->getUpdateAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->aAttributeCategory) {
                $result['AttributeCategory'] = $this->aAttributeCategory->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aCategoryDesc) {
                $result['CategoryDesc'] = $this->aCategoryDesc->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aContentAssoc) {
                $result['ContentAssoc'] = $this->aContentAssoc->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aDocument) {
                $result['Document'] = $this->aDocument->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aFeatureCategory) {
                $result['FeatureCategory'] = $this->aFeatureCategory->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aImage) {
                $result['Image'] = $this->aImage->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aProductCategory) {
                $result['ProductCategory'] = $this->aProductCategory->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aRewriting) {
                $result['Rewriting'] = $this->aRewriting->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
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
        $pos = CategoryPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
                $this->setParent($value);
                break;
            case 2:
                $this->setLink($value);
                break;
            case 3:
                $this->setVisible($value);
                break;
            case 4:
                $this->setPosition($value);
                break;
            case 5:
                $this->setCreatedAt($value);
                break;
            case 6:
                $this->setUpdateAt($value);
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
        $keys = CategoryPeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setParent($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setLink($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setVisible($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setPosition($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setCreatedAt($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setUpdateAt($arr[$keys[6]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(CategoryPeer::DATABASE_NAME);

        if ($this->isColumnModified(CategoryPeer::ID)) $criteria->add(CategoryPeer::ID, $this->id);
        if ($this->isColumnModified(CategoryPeer::PARENT)) $criteria->add(CategoryPeer::PARENT, $this->parent);
        if ($this->isColumnModified(CategoryPeer::LINK)) $criteria->add(CategoryPeer::LINK, $this->link);
        if ($this->isColumnModified(CategoryPeer::VISIBLE)) $criteria->add(CategoryPeer::VISIBLE, $this->visible);
        if ($this->isColumnModified(CategoryPeer::POSITION)) $criteria->add(CategoryPeer::POSITION, $this->position);
        if ($this->isColumnModified(CategoryPeer::CREATED_AT)) $criteria->add(CategoryPeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CategoryPeer::UPDATE_AT)) $criteria->add(CategoryPeer::UPDATE_AT, $this->update_at);

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
        $criteria = new Criteria(CategoryPeer::DATABASE_NAME);
        $criteria->add(CategoryPeer::ID, $this->id);

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
     * @param object $copyObj An object of Category (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setParent($this->getParent());
        $copyObj->setLink($this->getLink());
        $copyObj->setVisible($this->getVisible());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdateAt($this->getUpdateAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            $relObj = $this->getAttributeCategory();
            if ($relObj) {
                $copyObj->setAttributeCategory($relObj->copy($deepCopy));
            }

            $relObj = $this->getCategoryDesc();
            if ($relObj) {
                $copyObj->setCategoryDesc($relObj->copy($deepCopy));
            }

            $relObj = $this->getContentAssoc();
            if ($relObj) {
                $copyObj->setContentAssoc($relObj->copy($deepCopy));
            }

            $relObj = $this->getDocument();
            if ($relObj) {
                $copyObj->setDocument($relObj->copy($deepCopy));
            }

            $relObj = $this->getFeatureCategory();
            if ($relObj) {
                $copyObj->setFeatureCategory($relObj->copy($deepCopy));
            }

            $relObj = $this->getImage();
            if ($relObj) {
                $copyObj->setImage($relObj->copy($deepCopy));
            }

            $relObj = $this->getProductCategory();
            if ($relObj) {
                $copyObj->setProductCategory($relObj->copy($deepCopy));
            }

            $relObj = $this->getRewriting();
            if ($relObj) {
                $copyObj->setRewriting($relObj->copy($deepCopy));
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
     * @return Category Clone of current object.
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
     * @return CategoryPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new CategoryPeer();
        }

        return self::$peer;
    }

    /**
     * Declares an association between this object and a AttributeCategory object.
     *
     * @param             AttributeCategory $v
     * @return Category The current object (for fluent API support)
     * @throws PropelException
     */
    public function setAttributeCategory(AttributeCategory $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getCategoryId());
        }

        $this->aAttributeCategory = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setCategory($this);
        }


        return $this;
    }


    /**
     * Get the associated AttributeCategory object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return AttributeCategory The associated AttributeCategory object.
     * @throws PropelException
     */
    public function getAttributeCategory(PropelPDO $con = null)
    {
        if ($this->aAttributeCategory === null && ($this->id !== null)) {
            $this->aAttributeCategory = AttributeCategoryQuery::create()
                ->filterByCategory($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aAttributeCategory->setCategory($this);
        }

        return $this->aAttributeCategory;
    }

    /**
     * Declares an association between this object and a CategoryDesc object.
     *
     * @param             CategoryDesc $v
     * @return Category The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCategoryDesc(CategoryDesc $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getCategoryId());
        }

        $this->aCategoryDesc = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setCategory($this);
        }


        return $this;
    }


    /**
     * Get the associated CategoryDesc object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return CategoryDesc The associated CategoryDesc object.
     * @throws PropelException
     */
    public function getCategoryDesc(PropelPDO $con = null)
    {
        if ($this->aCategoryDesc === null && ($this->id !== null)) {
            $this->aCategoryDesc = CategoryDescQuery::create()
                ->filterByCategory($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aCategoryDesc->setCategory($this);
        }

        return $this->aCategoryDesc;
    }

    /**
     * Declares an association between this object and a ContentAssoc object.
     *
     * @param             ContentAssoc $v
     * @return Category The current object (for fluent API support)
     * @throws PropelException
     */
    public function setContentAssoc(ContentAssoc $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getCategoryId());
        }

        $this->aContentAssoc = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setCategory($this);
        }


        return $this;
    }


    /**
     * Get the associated ContentAssoc object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return ContentAssoc The associated ContentAssoc object.
     * @throws PropelException
     */
    public function getContentAssoc(PropelPDO $con = null)
    {
        if ($this->aContentAssoc === null && ($this->id !== null)) {
            $this->aContentAssoc = ContentAssocQuery::create()
                ->filterByCategory($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aContentAssoc->setCategory($this);
        }

        return $this->aContentAssoc;
    }

    /**
     * Declares an association between this object and a Document object.
     *
     * @param             Document $v
     * @return Category The current object (for fluent API support)
     * @throws PropelException
     */
    public function setDocument(Document $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getCategoryId());
        }

        $this->aDocument = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setCategory($this);
        }


        return $this;
    }


    /**
     * Get the associated Document object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Document The associated Document object.
     * @throws PropelException
     */
    public function getDocument(PropelPDO $con = null)
    {
        if ($this->aDocument === null && ($this->id !== null)) {
            $this->aDocument = DocumentQuery::create()
                ->filterByCategory($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aDocument->setCategory($this);
        }

        return $this->aDocument;
    }

    /**
     * Declares an association between this object and a FeatureCategory object.
     *
     * @param             FeatureCategory $v
     * @return Category The current object (for fluent API support)
     * @throws PropelException
     */
    public function setFeatureCategory(FeatureCategory $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getCategoryId());
        }

        $this->aFeatureCategory = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setCategory($this);
        }


        return $this;
    }


    /**
     * Get the associated FeatureCategory object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return FeatureCategory The associated FeatureCategory object.
     * @throws PropelException
     */
    public function getFeatureCategory(PropelPDO $con = null)
    {
        if ($this->aFeatureCategory === null && ($this->id !== null)) {
            $this->aFeatureCategory = FeatureCategoryQuery::create()
                ->filterByCategory($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aFeatureCategory->setCategory($this);
        }

        return $this->aFeatureCategory;
    }

    /**
     * Declares an association between this object and a Image object.
     *
     * @param             Image $v
     * @return Category The current object (for fluent API support)
     * @throws PropelException
     */
    public function setImage(Image $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getCategoryId());
        }

        $this->aImage = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setCategory($this);
        }


        return $this;
    }


    /**
     * Get the associated Image object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Image The associated Image object.
     * @throws PropelException
     */
    public function getImage(PropelPDO $con = null)
    {
        if ($this->aImage === null && ($this->id !== null)) {
            $this->aImage = ImageQuery::create()
                ->filterByCategory($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aImage->setCategory($this);
        }

        return $this->aImage;
    }

    /**
     * Declares an association between this object and a ProductCategory object.
     *
     * @param             ProductCategory $v
     * @return Category The current object (for fluent API support)
     * @throws PropelException
     */
    public function setProductCategory(ProductCategory $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getCategoryId());
        }

        $this->aProductCategory = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setCategory($this);
        }


        return $this;
    }


    /**
     * Get the associated ProductCategory object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return ProductCategory The associated ProductCategory object.
     * @throws PropelException
     */
    public function getProductCategory(PropelPDO $con = null)
    {
        if ($this->aProductCategory === null && ($this->id !== null)) {
            $this->aProductCategory = ProductCategoryQuery::create()
                ->filterByCategory($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aProductCategory->setCategory($this);
        }

        return $this->aProductCategory;
    }

    /**
     * Declares an association between this object and a Rewriting object.
     *
     * @param             Rewriting $v
     * @return Category The current object (for fluent API support)
     * @throws PropelException
     */
    public function setRewriting(Rewriting $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getCategoryId());
        }

        $this->aRewriting = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setCategory($this);
        }


        return $this;
    }


    /**
     * Get the associated Rewriting object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Rewriting The associated Rewriting object.
     * @throws PropelException
     */
    public function getRewriting(PropelPDO $con = null)
    {
        if ($this->aRewriting === null && ($this->id !== null)) {
            $this->aRewriting = RewritingQuery::create()
                ->filterByCategory($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aRewriting->setCategory($this);
        }

        return $this->aRewriting;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->parent = null;
        $this->link = null;
        $this->visible = null;
        $this->position = null;
        $this->created_at = null;
        $this->update_at = null;
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
        } // if ($deep)

        $this->aAttributeCategory = null;
        $this->aCategoryDesc = null;
        $this->aContentAssoc = null;
        $this->aDocument = null;
        $this->aFeatureCategory = null;
        $this->aImage = null;
        $this->aProductCategory = null;
        $this->aRewriting = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CategoryPeer::DEFAULT_STRING_FORMAT);
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
