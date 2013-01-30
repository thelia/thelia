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
use Thelia\Model\AttributeCategory;
use Thelia\Model\AttributeCategoryQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryI18n;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\CategoryPeer;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CategoryVersion;
use Thelia\Model\CategoryVersionPeer;
use Thelia\Model\CategoryVersionQuery;
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
     * The value for the updated_at field.
     * @var        string
     */
    protected $updated_at;

    /**
     * The value for the version field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $version;

    /**
     * The value for the version_created_at field.
     * @var        string
     */
    protected $version_created_at;

    /**
     * The value for the version_created_by field.
     * @var        string
     */
    protected $version_created_by;

    /**
     * @var        PropelObjectCollection|ProductCategory[] Collection to store aggregation of ProductCategory objects.
     */
    protected $collProductCategorys;
    protected $collProductCategorysPartial;

    /**
     * @var        PropelObjectCollection|FeatureCategory[] Collection to store aggregation of FeatureCategory objects.
     */
    protected $collFeatureCategorys;
    protected $collFeatureCategorysPartial;

    /**
     * @var        PropelObjectCollection|AttributeCategory[] Collection to store aggregation of AttributeCategory objects.
     */
    protected $collAttributeCategorys;
    protected $collAttributeCategorysPartial;

    /**
     * @var        PropelObjectCollection|ContentAssoc[] Collection to store aggregation of ContentAssoc objects.
     */
    protected $collContentAssocs;
    protected $collContentAssocsPartial;

    /**
     * @var        PropelObjectCollection|Image[] Collection to store aggregation of Image objects.
     */
    protected $collImages;
    protected $collImagesPartial;

    /**
     * @var        PropelObjectCollection|Document[] Collection to store aggregation of Document objects.
     */
    protected $collDocuments;
    protected $collDocumentsPartial;

    /**
     * @var        PropelObjectCollection|Rewriting[] Collection to store aggregation of Rewriting objects.
     */
    protected $collRewritings;
    protected $collRewritingsPartial;

    /**
     * @var        PropelObjectCollection|CategoryI18n[] Collection to store aggregation of CategoryI18n objects.
     */
    protected $collCategoryI18ns;
    protected $collCategoryI18nsPartial;

    /**
     * @var        PropelObjectCollection|CategoryVersion[] Collection to store aggregation of CategoryVersion objects.
     */
    protected $collCategoryVersions;
    protected $collCategoryVersionsPartial;

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
     * @var        array[CategoryI18n]
     */
    protected $currentTranslations;

    // versionable behavior


    /**
     * @var bool
     */
    protected $enforceVersion = false;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $productCategorysScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $featureCategorysScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $attributeCategorysScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $contentAssocsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $imagesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $documentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $rewritingsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $categoryI18nsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $categoryVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see        __construct()
     */
    public function applyDefaultValues()
    {
        $this->version = 0;
    }

    /**
     * Initializes internal state of BaseCategory object.
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
     * Get the [version] column value.
     *
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get the [optionally formatted] temporal [version_created_at] column value.
     *
     *
     * @param string $format The date/time format string (either date()-style or strftime()-style).
     *				 If format is null, then the raw DateTime object will be returned.
     * @return mixed Formatted date/time value as string or DateTime object (if format is null), null if column is null, and 0 if column value is 0000-00-00 00:00:00
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getVersionCreatedAt($format = 'Y-m-d H:i:s')
    {
        if ($this->version_created_at === null) {
            return null;
        }

        if ($this->version_created_at === '0000-00-00 00:00:00') {
            // while technically this is not a default value of null,
            // this seems to be closest in meaning.
            return null;
        } else {
            try {
                $dt = new DateTime($this->version_created_at);
            } catch (Exception $x) {
                throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($this->version_created_at, true), $x);
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
     * Get the [version_created_by] column value.
     *
     * @return string
     */
    public function getVersionCreatedBy()
    {
        return $this->version_created_by;
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
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Category The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = CategoryPeer::UPDATED_AT;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param int $v new value
     * @return Category The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[] = CategoryPeer::VERSION;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Category The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->version_created_at !== null && $tmpDt = new DateTime($this->version_created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->version_created_at = $newDateAsString;
                $this->modifiedColumns[] = CategoryPeer::VERSION_CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param string $v new value
     * @return Category The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[] = CategoryPeer::VERSION_CREATED_BY;
        }


        return $this;
    } // setVersionCreatedBy()

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
            if ($this->version !== 0) {
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
            $this->parent = ($row[$startcol + 1] !== null) ? (int) $row[$startcol + 1] : null;
            $this->link = ($row[$startcol + 2] !== null) ? (string) $row[$startcol + 2] : null;
            $this->visible = ($row[$startcol + 3] !== null) ? (int) $row[$startcol + 3] : null;
            $this->position = ($row[$startcol + 4] !== null) ? (int) $row[$startcol + 4] : null;
            $this->created_at = ($row[$startcol + 5] !== null) ? (string) $row[$startcol + 5] : null;
            $this->updated_at = ($row[$startcol + 6] !== null) ? (string) $row[$startcol + 6] : null;
            $this->version = ($row[$startcol + 7] !== null) ? (int) $row[$startcol + 7] : null;
            $this->version_created_at = ($row[$startcol + 8] !== null) ? (string) $row[$startcol + 8] : null;
            $this->version_created_by = ($row[$startcol + 9] !== null) ? (string) $row[$startcol + 9] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 10; // 10 = CategoryPeer::NUM_HYDRATE_COLUMNS.

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

            $this->collProductCategorys = null;

            $this->collFeatureCategorys = null;

            $this->collAttributeCategorys = null;

            $this->collContentAssocs = null;

            $this->collImages = null;

            $this->collDocuments = null;

            $this->collRewritings = null;

            $this->collCategoryI18ns = null;

            $this->collCategoryVersions = null;

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
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(CategoryPeer::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(CategoryPeer::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(CategoryPeer::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(CategoryPeer::UPDATED_AT)) {
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
                // versionable behavior
                if (isset($createVersion)) {
                    $this->addVersion($con);
                }
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

            if ($this->productCategorysScheduledForDeletion !== null) {
                if (!$this->productCategorysScheduledForDeletion->isEmpty()) {
                    ProductCategoryQuery::create()
                        ->filterByPrimaryKeys($this->productCategorysScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productCategorysScheduledForDeletion = null;
                }
            }

            if ($this->collProductCategorys !== null) {
                foreach ($this->collProductCategorys as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
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
                    if (!$referrerFK->isDeleted()) {
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
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->contentAssocsScheduledForDeletion !== null) {
                if (!$this->contentAssocsScheduledForDeletion->isEmpty()) {
                    foreach ($this->contentAssocsScheduledForDeletion as $contentAssoc) {
                        // need to save related object because we set the relation to null
                        $contentAssoc->save($con);
                    }
                    $this->contentAssocsScheduledForDeletion = null;
                }
            }

            if ($this->collContentAssocs !== null) {
                foreach ($this->collContentAssocs as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->imagesScheduledForDeletion !== null) {
                if (!$this->imagesScheduledForDeletion->isEmpty()) {
                    foreach ($this->imagesScheduledForDeletion as $image) {
                        // need to save related object because we set the relation to null
                        $image->save($con);
                    }
                    $this->imagesScheduledForDeletion = null;
                }
            }

            if ($this->collImages !== null) {
                foreach ($this->collImages as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->documentsScheduledForDeletion !== null) {
                if (!$this->documentsScheduledForDeletion->isEmpty()) {
                    foreach ($this->documentsScheduledForDeletion as $document) {
                        // need to save related object because we set the relation to null
                        $document->save($con);
                    }
                    $this->documentsScheduledForDeletion = null;
                }
            }

            if ($this->collDocuments !== null) {
                foreach ($this->collDocuments as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->rewritingsScheduledForDeletion !== null) {
                if (!$this->rewritingsScheduledForDeletion->isEmpty()) {
                    foreach ($this->rewritingsScheduledForDeletion as $rewriting) {
                        // need to save related object because we set the relation to null
                        $rewriting->save($con);
                    }
                    $this->rewritingsScheduledForDeletion = null;
                }
            }

            if ($this->collRewritings !== null) {
                foreach ($this->collRewritings as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->categoryI18nsScheduledForDeletion !== null) {
                if (!$this->categoryI18nsScheduledForDeletion->isEmpty()) {
                    CategoryI18nQuery::create()
                        ->filterByPrimaryKeys($this->categoryI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->categoryI18nsScheduledForDeletion = null;
                }
            }

            if ($this->collCategoryI18ns !== null) {
                foreach ($this->collCategoryI18ns as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->categoryVersionsScheduledForDeletion !== null) {
                if (!$this->categoryVersionsScheduledForDeletion->isEmpty()) {
                    CategoryVersionQuery::create()
                        ->filterByPrimaryKeys($this->categoryVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->categoryVersionsScheduledForDeletion = null;
                }
            }

            if ($this->collCategoryVersions !== null) {
                foreach ($this->collCategoryVersions as $referrerFK) {
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
        if ($this->isColumnModified(CategoryPeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(CategoryPeer::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(CategoryPeer::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(CategoryPeer::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
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
                    case '`UPDATED_AT`':
                        $stmt->bindValue($identifier, $this->updated_at, PDO::PARAM_STR);
                        break;
                    case '`VERSION`':
                        $stmt->bindValue($identifier, $this->version, PDO::PARAM_INT);
                        break;
                    case '`VERSION_CREATED_AT`':
                        $stmt->bindValue($identifier, $this->version_created_at, PDO::PARAM_STR);
                        break;
                    case '`VERSION_CREATED_BY`':
                        $stmt->bindValue($identifier, $this->version_created_by, PDO::PARAM_STR);
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


            if (($retval = CategoryPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collProductCategorys !== null) {
                    foreach ($this->collProductCategorys as $referrerFK) {
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

                if ($this->collAttributeCategorys !== null) {
                    foreach ($this->collAttributeCategorys as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collContentAssocs !== null) {
                    foreach ($this->collContentAssocs as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collImages !== null) {
                    foreach ($this->collImages as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collDocuments !== null) {
                    foreach ($this->collDocuments as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collRewritings !== null) {
                    foreach ($this->collRewritings as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collCategoryI18ns !== null) {
                    foreach ($this->collCategoryI18ns as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collCategoryVersions !== null) {
                    foreach ($this->collCategoryVersions as $referrerFK) {
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
                return $this->getUpdatedAt();
                break;
            case 7:
                return $this->getVersion();
                break;
            case 8:
                return $this->getVersionCreatedAt();
                break;
            case 9:
                return $this->getVersionCreatedBy();
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
            $keys[6] => $this->getUpdatedAt(),
            $keys[7] => $this->getVersion(),
            $keys[8] => $this->getVersionCreatedAt(),
            $keys[9] => $this->getVersionCreatedBy(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->collProductCategorys) {
                $result['ProductCategorys'] = $this->collProductCategorys->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureCategorys) {
                $result['FeatureCategorys'] = $this->collFeatureCategorys->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAttributeCategorys) {
                $result['AttributeCategorys'] = $this->collAttributeCategorys->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collContentAssocs) {
                $result['ContentAssocs'] = $this->collContentAssocs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collImages) {
                $result['Images'] = $this->collImages->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collDocuments) {
                $result['Documents'] = $this->collDocuments->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collRewritings) {
                $result['Rewritings'] = $this->collRewritings->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCategoryI18ns) {
                $result['CategoryI18ns'] = $this->collCategoryI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCategoryVersions) {
                $result['CategoryVersions'] = $this->collCategoryVersions->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
                $this->setUpdatedAt($value);
                break;
            case 7:
                $this->setVersion($value);
                break;
            case 8:
                $this->setVersionCreatedAt($value);
                break;
            case 9:
                $this->setVersionCreatedBy($value);
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
        if (array_key_exists($keys[6], $arr)) $this->setUpdatedAt($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setVersion($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setVersionCreatedAt($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setVersionCreatedBy($arr[$keys[9]]);
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
        if ($this->isColumnModified(CategoryPeer::UPDATED_AT)) $criteria->add(CategoryPeer::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(CategoryPeer::VERSION)) $criteria->add(CategoryPeer::VERSION, $this->version);
        if ($this->isColumnModified(CategoryPeer::VERSION_CREATED_AT)) $criteria->add(CategoryPeer::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(CategoryPeer::VERSION_CREATED_BY)) $criteria->add(CategoryPeer::VERSION_CREATED_BY, $this->version_created_by);

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
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            foreach ($this->getProductCategorys() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductCategory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureCategorys() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureCategory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAttributeCategorys() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAttributeCategory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getContentAssocs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addContentAssoc($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getImages() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addImage($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getDocuments() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addDocument($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getRewritings() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addRewriting($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCategoryI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCategoryI18n($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCategoryVersions() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCategoryVersion($relObj->copy($deepCopy));
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
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('ProductCategory' == $relationName) {
            $this->initProductCategorys();
        }
        if ('FeatureCategory' == $relationName) {
            $this->initFeatureCategorys();
        }
        if ('AttributeCategory' == $relationName) {
            $this->initAttributeCategorys();
        }
        if ('ContentAssoc' == $relationName) {
            $this->initContentAssocs();
        }
        if ('Image' == $relationName) {
            $this->initImages();
        }
        if ('Document' == $relationName) {
            $this->initDocuments();
        }
        if ('Rewriting' == $relationName) {
            $this->initRewritings();
        }
        if ('CategoryI18n' == $relationName) {
            $this->initCategoryI18ns();
        }
        if ('CategoryVersion' == $relationName) {
            $this->initCategoryVersions();
        }
    }

    /**
     * Clears out the collProductCategorys collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductCategorys()
     */
    public function clearProductCategorys()
    {
        $this->collProductCategorys = null; // important to set this to null since that means it is uninitialized
        $this->collProductCategorysPartial = null;
    }

    /**
     * reset is the collProductCategorys collection loaded partially
     *
     * @return void
     */
    public function resetPartialProductCategorys($v = true)
    {
        $this->collProductCategorysPartial = $v;
    }

    /**
     * Initializes the collProductCategorys collection.
     *
     * By default this just sets the collProductCategorys collection to an empty array (like clearcollProductCategorys());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductCategorys($overrideExisting = true)
    {
        if (null !== $this->collProductCategorys && !$overrideExisting) {
            return;
        }
        $this->collProductCategorys = new PropelObjectCollection();
        $this->collProductCategorys->setModel('ProductCategory');
    }

    /**
     * Gets an array of ProductCategory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Category is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|ProductCategory[] List of ProductCategory objects
     * @throws PropelException
     */
    public function getProductCategorys($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collProductCategorysPartial && !$this->isNew();
        if (null === $this->collProductCategorys || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductCategorys) {
                // return empty collection
                $this->initProductCategorys();
            } else {
                $collProductCategorys = ProductCategoryQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collProductCategorysPartial && count($collProductCategorys)) {
                      $this->initProductCategorys(false);

                      foreach($collProductCategorys as $obj) {
                        if (false == $this->collProductCategorys->contains($obj)) {
                          $this->collProductCategorys->append($obj);
                        }
                      }

                      $this->collProductCategorysPartial = true;
                    }

                    return $collProductCategorys;
                }

                if($partial && $this->collProductCategorys) {
                    foreach($this->collProductCategorys as $obj) {
                        if($obj->isNew()) {
                            $collProductCategorys[] = $obj;
                        }
                    }
                }

                $this->collProductCategorys = $collProductCategorys;
                $this->collProductCategorysPartial = false;
            }
        }

        return $this->collProductCategorys;
    }

    /**
     * Sets a collection of ProductCategory objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $productCategorys A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setProductCategorys(PropelCollection $productCategorys, PropelPDO $con = null)
    {
        $this->productCategorysScheduledForDeletion = $this->getProductCategorys(new Criteria(), $con)->diff($productCategorys);

        foreach ($this->productCategorysScheduledForDeletion as $productCategoryRemoved) {
            $productCategoryRemoved->setCategory(null);
        }

        $this->collProductCategorys = null;
        foreach ($productCategorys as $productCategory) {
            $this->addProductCategory($productCategory);
        }

        $this->collProductCategorys = $productCategorys;
        $this->collProductCategorysPartial = false;
    }

    /**
     * Returns the number of related ProductCategory objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related ProductCategory objects.
     * @throws PropelException
     */
    public function countProductCategorys(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collProductCategorysPartial && !$this->isNew();
        if (null === $this->collProductCategorys || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductCategorys) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getProductCategorys());
                }
                $query = ProductCategoryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collProductCategorys);
        }
    }

    /**
     * Method called to associate a ProductCategory object to this object
     * through the ProductCategory foreign key attribute.
     *
     * @param    ProductCategory $l ProductCategory
     * @return Category The current object (for fluent API support)
     */
    public function addProductCategory(ProductCategory $l)
    {
        if ($this->collProductCategorys === null) {
            $this->initProductCategorys();
            $this->collProductCategorysPartial = true;
        }
        if (!$this->collProductCategorys->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddProductCategory($l);
        }

        return $this;
    }

    /**
     * @param	ProductCategory $productCategory The productCategory object to add.
     */
    protected function doAddProductCategory($productCategory)
    {
        $this->collProductCategorys[]= $productCategory;
        $productCategory->setCategory($this);
    }

    /**
     * @param	ProductCategory $productCategory The productCategory object to remove.
     */
    public function removeProductCategory($productCategory)
    {
        if ($this->getProductCategorys()->contains($productCategory)) {
            $this->collProductCategorys->remove($this->collProductCategorys->search($productCategory));
            if (null === $this->productCategorysScheduledForDeletion) {
                $this->productCategorysScheduledForDeletion = clone $this->collProductCategorys;
                $this->productCategorysScheduledForDeletion->clear();
            }
            $this->productCategorysScheduledForDeletion[]= $productCategory;
            $productCategory->setCategory(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related ProductCategorys from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|ProductCategory[] List of ProductCategory objects
     */
    public function getProductCategorysJoinProduct($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ProductCategoryQuery::create(null, $criteria);
        $query->joinWith('Product', $join_behavior);

        return $this->getProductCategorys($query, $con);
    }

    /**
     * Clears out the collFeatureCategorys collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureCategorys()
     */
    public function clearFeatureCategorys()
    {
        $this->collFeatureCategorys = null; // important to set this to null since that means it is uninitialized
        $this->collFeatureCategorysPartial = null;
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
     * If this Category is new, it will return
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
                    ->filterByCategory($this)
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
     */
    public function setFeatureCategorys(PropelCollection $featureCategorys, PropelPDO $con = null)
    {
        $this->featureCategorysScheduledForDeletion = $this->getFeatureCategorys(new Criteria(), $con)->diff($featureCategorys);

        foreach ($this->featureCategorysScheduledForDeletion as $featureCategoryRemoved) {
            $featureCategoryRemoved->setCategory(null);
        }

        $this->collFeatureCategorys = null;
        foreach ($featureCategorys as $featureCategory) {
            $this->addFeatureCategory($featureCategory);
        }

        $this->collFeatureCategorys = $featureCategorys;
        $this->collFeatureCategorysPartial = false;
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
            } else {
                if($partial && !$criteria) {
                    return count($this->getFeatureCategorys());
                }
                $query = FeatureCategoryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collFeatureCategorys);
        }
    }

    /**
     * Method called to associate a FeatureCategory object to this object
     * through the FeatureCategory foreign key attribute.
     *
     * @param    FeatureCategory $l FeatureCategory
     * @return Category The current object (for fluent API support)
     */
    public function addFeatureCategory(FeatureCategory $l)
    {
        if ($this->collFeatureCategorys === null) {
            $this->initFeatureCategorys();
            $this->collFeatureCategorysPartial = true;
        }
        if (!$this->collFeatureCategorys->contains($l)) { // only add it if the **same** object is not already associated
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
        $featureCategory->setCategory($this);
    }

    /**
     * @param	FeatureCategory $featureCategory The featureCategory object to remove.
     */
    public function removeFeatureCategory($featureCategory)
    {
        if ($this->getFeatureCategorys()->contains($featureCategory)) {
            $this->collFeatureCategorys->remove($this->collFeatureCategorys->search($featureCategory));
            if (null === $this->featureCategorysScheduledForDeletion) {
                $this->featureCategorysScheduledForDeletion = clone $this->collFeatureCategorys;
                $this->featureCategorysScheduledForDeletion->clear();
            }
            $this->featureCategorysScheduledForDeletion[]= $featureCategory;
            $featureCategory->setCategory(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related FeatureCategorys from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|FeatureCategory[] List of FeatureCategory objects
     */
    public function getFeatureCategorysJoinFeature($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = FeatureCategoryQuery::create(null, $criteria);
        $query->joinWith('Feature', $join_behavior);

        return $this->getFeatureCategorys($query, $con);
    }

    /**
     * Clears out the collAttributeCategorys collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAttributeCategorys()
     */
    public function clearAttributeCategorys()
    {
        $this->collAttributeCategorys = null; // important to set this to null since that means it is uninitialized
        $this->collAttributeCategorysPartial = null;
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
     * If this Category is new, it will return
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
                    ->filterByCategory($this)
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
     */
    public function setAttributeCategorys(PropelCollection $attributeCategorys, PropelPDO $con = null)
    {
        $this->attributeCategorysScheduledForDeletion = $this->getAttributeCategorys(new Criteria(), $con)->diff($attributeCategorys);

        foreach ($this->attributeCategorysScheduledForDeletion as $attributeCategoryRemoved) {
            $attributeCategoryRemoved->setCategory(null);
        }

        $this->collAttributeCategorys = null;
        foreach ($attributeCategorys as $attributeCategory) {
            $this->addAttributeCategory($attributeCategory);
        }

        $this->collAttributeCategorys = $attributeCategorys;
        $this->collAttributeCategorysPartial = false;
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
            } else {
                if($partial && !$criteria) {
                    return count($this->getAttributeCategorys());
                }
                $query = AttributeCategoryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collAttributeCategorys);
        }
    }

    /**
     * Method called to associate a AttributeCategory object to this object
     * through the AttributeCategory foreign key attribute.
     *
     * @param    AttributeCategory $l AttributeCategory
     * @return Category The current object (for fluent API support)
     */
    public function addAttributeCategory(AttributeCategory $l)
    {
        if ($this->collAttributeCategorys === null) {
            $this->initAttributeCategorys();
            $this->collAttributeCategorysPartial = true;
        }
        if (!$this->collAttributeCategorys->contains($l)) { // only add it if the **same** object is not already associated
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
        $attributeCategory->setCategory($this);
    }

    /**
     * @param	AttributeCategory $attributeCategory The attributeCategory object to remove.
     */
    public function removeAttributeCategory($attributeCategory)
    {
        if ($this->getAttributeCategorys()->contains($attributeCategory)) {
            $this->collAttributeCategorys->remove($this->collAttributeCategorys->search($attributeCategory));
            if (null === $this->attributeCategorysScheduledForDeletion) {
                $this->attributeCategorysScheduledForDeletion = clone $this->collAttributeCategorys;
                $this->attributeCategorysScheduledForDeletion->clear();
            }
            $this->attributeCategorysScheduledForDeletion[]= $attributeCategory;
            $attributeCategory->setCategory(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related AttributeCategorys from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|AttributeCategory[] List of AttributeCategory objects
     */
    public function getAttributeCategorysJoinAttribute($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = AttributeCategoryQuery::create(null, $criteria);
        $query->joinWith('Attribute', $join_behavior);

        return $this->getAttributeCategorys($query, $con);
    }

    /**
     * Clears out the collContentAssocs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContentAssocs()
     */
    public function clearContentAssocs()
    {
        $this->collContentAssocs = null; // important to set this to null since that means it is uninitialized
        $this->collContentAssocsPartial = null;
    }

    /**
     * reset is the collContentAssocs collection loaded partially
     *
     * @return void
     */
    public function resetPartialContentAssocs($v = true)
    {
        $this->collContentAssocsPartial = $v;
    }

    /**
     * Initializes the collContentAssocs collection.
     *
     * By default this just sets the collContentAssocs collection to an empty array (like clearcollContentAssocs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initContentAssocs($overrideExisting = true)
    {
        if (null !== $this->collContentAssocs && !$overrideExisting) {
            return;
        }
        $this->collContentAssocs = new PropelObjectCollection();
        $this->collContentAssocs->setModel('ContentAssoc');
    }

    /**
     * Gets an array of ContentAssoc objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Category is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|ContentAssoc[] List of ContentAssoc objects
     * @throws PropelException
     */
    public function getContentAssocs($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collContentAssocsPartial && !$this->isNew();
        if (null === $this->collContentAssocs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collContentAssocs) {
                // return empty collection
                $this->initContentAssocs();
            } else {
                $collContentAssocs = ContentAssocQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collContentAssocsPartial && count($collContentAssocs)) {
                      $this->initContentAssocs(false);

                      foreach($collContentAssocs as $obj) {
                        if (false == $this->collContentAssocs->contains($obj)) {
                          $this->collContentAssocs->append($obj);
                        }
                      }

                      $this->collContentAssocsPartial = true;
                    }

                    return $collContentAssocs;
                }

                if($partial && $this->collContentAssocs) {
                    foreach($this->collContentAssocs as $obj) {
                        if($obj->isNew()) {
                            $collContentAssocs[] = $obj;
                        }
                    }
                }

                $this->collContentAssocs = $collContentAssocs;
                $this->collContentAssocsPartial = false;
            }
        }

        return $this->collContentAssocs;
    }

    /**
     * Sets a collection of ContentAssoc objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $contentAssocs A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setContentAssocs(PropelCollection $contentAssocs, PropelPDO $con = null)
    {
        $this->contentAssocsScheduledForDeletion = $this->getContentAssocs(new Criteria(), $con)->diff($contentAssocs);

        foreach ($this->contentAssocsScheduledForDeletion as $contentAssocRemoved) {
            $contentAssocRemoved->setCategory(null);
        }

        $this->collContentAssocs = null;
        foreach ($contentAssocs as $contentAssoc) {
            $this->addContentAssoc($contentAssoc);
        }

        $this->collContentAssocs = $contentAssocs;
        $this->collContentAssocsPartial = false;
    }

    /**
     * Returns the number of related ContentAssoc objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related ContentAssoc objects.
     * @throws PropelException
     */
    public function countContentAssocs(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collContentAssocsPartial && !$this->isNew();
        if (null === $this->collContentAssocs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collContentAssocs) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getContentAssocs());
                }
                $query = ContentAssocQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collContentAssocs);
        }
    }

    /**
     * Method called to associate a ContentAssoc object to this object
     * through the ContentAssoc foreign key attribute.
     *
     * @param    ContentAssoc $l ContentAssoc
     * @return Category The current object (for fluent API support)
     */
    public function addContentAssoc(ContentAssoc $l)
    {
        if ($this->collContentAssocs === null) {
            $this->initContentAssocs();
            $this->collContentAssocsPartial = true;
        }
        if (!$this->collContentAssocs->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddContentAssoc($l);
        }

        return $this;
    }

    /**
     * @param	ContentAssoc $contentAssoc The contentAssoc object to add.
     */
    protected function doAddContentAssoc($contentAssoc)
    {
        $this->collContentAssocs[]= $contentAssoc;
        $contentAssoc->setCategory($this);
    }

    /**
     * @param	ContentAssoc $contentAssoc The contentAssoc object to remove.
     */
    public function removeContentAssoc($contentAssoc)
    {
        if ($this->getContentAssocs()->contains($contentAssoc)) {
            $this->collContentAssocs->remove($this->collContentAssocs->search($contentAssoc));
            if (null === $this->contentAssocsScheduledForDeletion) {
                $this->contentAssocsScheduledForDeletion = clone $this->collContentAssocs;
                $this->contentAssocsScheduledForDeletion->clear();
            }
            $this->contentAssocsScheduledForDeletion[]= $contentAssoc;
            $contentAssoc->setCategory(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related ContentAssocs from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|ContentAssoc[] List of ContentAssoc objects
     */
    public function getContentAssocsJoinProduct($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ContentAssocQuery::create(null, $criteria);
        $query->joinWith('Product', $join_behavior);

        return $this->getContentAssocs($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related ContentAssocs from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|ContentAssoc[] List of ContentAssoc objects
     */
    public function getContentAssocsJoinContent($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ContentAssocQuery::create(null, $criteria);
        $query->joinWith('Content', $join_behavior);

        return $this->getContentAssocs($query, $con);
    }

    /**
     * Clears out the collImages collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addImages()
     */
    public function clearImages()
    {
        $this->collImages = null; // important to set this to null since that means it is uninitialized
        $this->collImagesPartial = null;
    }

    /**
     * reset is the collImages collection loaded partially
     *
     * @return void
     */
    public function resetPartialImages($v = true)
    {
        $this->collImagesPartial = $v;
    }

    /**
     * Initializes the collImages collection.
     *
     * By default this just sets the collImages collection to an empty array (like clearcollImages());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initImages($overrideExisting = true)
    {
        if (null !== $this->collImages && !$overrideExisting) {
            return;
        }
        $this->collImages = new PropelObjectCollection();
        $this->collImages->setModel('Image');
    }

    /**
     * Gets an array of Image objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Category is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Image[] List of Image objects
     * @throws PropelException
     */
    public function getImages($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collImagesPartial && !$this->isNew();
        if (null === $this->collImages || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collImages) {
                // return empty collection
                $this->initImages();
            } else {
                $collImages = ImageQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collImagesPartial && count($collImages)) {
                      $this->initImages(false);

                      foreach($collImages as $obj) {
                        if (false == $this->collImages->contains($obj)) {
                          $this->collImages->append($obj);
                        }
                      }

                      $this->collImagesPartial = true;
                    }

                    return $collImages;
                }

                if($partial && $this->collImages) {
                    foreach($this->collImages as $obj) {
                        if($obj->isNew()) {
                            $collImages[] = $obj;
                        }
                    }
                }

                $this->collImages = $collImages;
                $this->collImagesPartial = false;
            }
        }

        return $this->collImages;
    }

    /**
     * Sets a collection of Image objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $images A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setImages(PropelCollection $images, PropelPDO $con = null)
    {
        $this->imagesScheduledForDeletion = $this->getImages(new Criteria(), $con)->diff($images);

        foreach ($this->imagesScheduledForDeletion as $imageRemoved) {
            $imageRemoved->setCategory(null);
        }

        $this->collImages = null;
        foreach ($images as $image) {
            $this->addImage($image);
        }

        $this->collImages = $images;
        $this->collImagesPartial = false;
    }

    /**
     * Returns the number of related Image objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Image objects.
     * @throws PropelException
     */
    public function countImages(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collImagesPartial && !$this->isNew();
        if (null === $this->collImages || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collImages) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getImages());
                }
                $query = ImageQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collImages);
        }
    }

    /**
     * Method called to associate a Image object to this object
     * through the Image foreign key attribute.
     *
     * @param    Image $l Image
     * @return Category The current object (for fluent API support)
     */
    public function addImage(Image $l)
    {
        if ($this->collImages === null) {
            $this->initImages();
            $this->collImagesPartial = true;
        }
        if (!$this->collImages->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddImage($l);
        }

        return $this;
    }

    /**
     * @param	Image $image The image object to add.
     */
    protected function doAddImage($image)
    {
        $this->collImages[]= $image;
        $image->setCategory($this);
    }

    /**
     * @param	Image $image The image object to remove.
     */
    public function removeImage($image)
    {
        if ($this->getImages()->contains($image)) {
            $this->collImages->remove($this->collImages->search($image));
            if (null === $this->imagesScheduledForDeletion) {
                $this->imagesScheduledForDeletion = clone $this->collImages;
                $this->imagesScheduledForDeletion->clear();
            }
            $this->imagesScheduledForDeletion[]= $image;
            $image->setCategory(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Image[] List of Image objects
     */
    public function getImagesJoinProduct($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ImageQuery::create(null, $criteria);
        $query->joinWith('Product', $join_behavior);

        return $this->getImages($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Image[] List of Image objects
     */
    public function getImagesJoinContent($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ImageQuery::create(null, $criteria);
        $query->joinWith('Content', $join_behavior);

        return $this->getImages($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Image[] List of Image objects
     */
    public function getImagesJoinFolder($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ImageQuery::create(null, $criteria);
        $query->joinWith('Folder', $join_behavior);

        return $this->getImages($query, $con);
    }

    /**
     * Clears out the collDocuments collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addDocuments()
     */
    public function clearDocuments()
    {
        $this->collDocuments = null; // important to set this to null since that means it is uninitialized
        $this->collDocumentsPartial = null;
    }

    /**
     * reset is the collDocuments collection loaded partially
     *
     * @return void
     */
    public function resetPartialDocuments($v = true)
    {
        $this->collDocumentsPartial = $v;
    }

    /**
     * Initializes the collDocuments collection.
     *
     * By default this just sets the collDocuments collection to an empty array (like clearcollDocuments());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initDocuments($overrideExisting = true)
    {
        if (null !== $this->collDocuments && !$overrideExisting) {
            return;
        }
        $this->collDocuments = new PropelObjectCollection();
        $this->collDocuments->setModel('Document');
    }

    /**
     * Gets an array of Document objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Category is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Document[] List of Document objects
     * @throws PropelException
     */
    public function getDocuments($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collDocumentsPartial && !$this->isNew();
        if (null === $this->collDocuments || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collDocuments) {
                // return empty collection
                $this->initDocuments();
            } else {
                $collDocuments = DocumentQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collDocumentsPartial && count($collDocuments)) {
                      $this->initDocuments(false);

                      foreach($collDocuments as $obj) {
                        if (false == $this->collDocuments->contains($obj)) {
                          $this->collDocuments->append($obj);
                        }
                      }

                      $this->collDocumentsPartial = true;
                    }

                    return $collDocuments;
                }

                if($partial && $this->collDocuments) {
                    foreach($this->collDocuments as $obj) {
                        if($obj->isNew()) {
                            $collDocuments[] = $obj;
                        }
                    }
                }

                $this->collDocuments = $collDocuments;
                $this->collDocumentsPartial = false;
            }
        }

        return $this->collDocuments;
    }

    /**
     * Sets a collection of Document objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $documents A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setDocuments(PropelCollection $documents, PropelPDO $con = null)
    {
        $this->documentsScheduledForDeletion = $this->getDocuments(new Criteria(), $con)->diff($documents);

        foreach ($this->documentsScheduledForDeletion as $documentRemoved) {
            $documentRemoved->setCategory(null);
        }

        $this->collDocuments = null;
        foreach ($documents as $document) {
            $this->addDocument($document);
        }

        $this->collDocuments = $documents;
        $this->collDocumentsPartial = false;
    }

    /**
     * Returns the number of related Document objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Document objects.
     * @throws PropelException
     */
    public function countDocuments(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collDocumentsPartial && !$this->isNew();
        if (null === $this->collDocuments || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collDocuments) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getDocuments());
                }
                $query = DocumentQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collDocuments);
        }
    }

    /**
     * Method called to associate a Document object to this object
     * through the Document foreign key attribute.
     *
     * @param    Document $l Document
     * @return Category The current object (for fluent API support)
     */
    public function addDocument(Document $l)
    {
        if ($this->collDocuments === null) {
            $this->initDocuments();
            $this->collDocumentsPartial = true;
        }
        if (!$this->collDocuments->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddDocument($l);
        }

        return $this;
    }

    /**
     * @param	Document $document The document object to add.
     */
    protected function doAddDocument($document)
    {
        $this->collDocuments[]= $document;
        $document->setCategory($this);
    }

    /**
     * @param	Document $document The document object to remove.
     */
    public function removeDocument($document)
    {
        if ($this->getDocuments()->contains($document)) {
            $this->collDocuments->remove($this->collDocuments->search($document));
            if (null === $this->documentsScheduledForDeletion) {
                $this->documentsScheduledForDeletion = clone $this->collDocuments;
                $this->documentsScheduledForDeletion->clear();
            }
            $this->documentsScheduledForDeletion[]= $document;
            $document->setCategory(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Document[] List of Document objects
     */
    public function getDocumentsJoinProduct($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = DocumentQuery::create(null, $criteria);
        $query->joinWith('Product', $join_behavior);

        return $this->getDocuments($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Document[] List of Document objects
     */
    public function getDocumentsJoinContent($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = DocumentQuery::create(null, $criteria);
        $query->joinWith('Content', $join_behavior);

        return $this->getDocuments($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Document[] List of Document objects
     */
    public function getDocumentsJoinFolder($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = DocumentQuery::create(null, $criteria);
        $query->joinWith('Folder', $join_behavior);

        return $this->getDocuments($query, $con);
    }

    /**
     * Clears out the collRewritings collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addRewritings()
     */
    public function clearRewritings()
    {
        $this->collRewritings = null; // important to set this to null since that means it is uninitialized
        $this->collRewritingsPartial = null;
    }

    /**
     * reset is the collRewritings collection loaded partially
     *
     * @return void
     */
    public function resetPartialRewritings($v = true)
    {
        $this->collRewritingsPartial = $v;
    }

    /**
     * Initializes the collRewritings collection.
     *
     * By default this just sets the collRewritings collection to an empty array (like clearcollRewritings());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initRewritings($overrideExisting = true)
    {
        if (null !== $this->collRewritings && !$overrideExisting) {
            return;
        }
        $this->collRewritings = new PropelObjectCollection();
        $this->collRewritings->setModel('Rewriting');
    }

    /**
     * Gets an array of Rewriting objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Category is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Rewriting[] List of Rewriting objects
     * @throws PropelException
     */
    public function getRewritings($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collRewritingsPartial && !$this->isNew();
        if (null === $this->collRewritings || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collRewritings) {
                // return empty collection
                $this->initRewritings();
            } else {
                $collRewritings = RewritingQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collRewritingsPartial && count($collRewritings)) {
                      $this->initRewritings(false);

                      foreach($collRewritings as $obj) {
                        if (false == $this->collRewritings->contains($obj)) {
                          $this->collRewritings->append($obj);
                        }
                      }

                      $this->collRewritingsPartial = true;
                    }

                    return $collRewritings;
                }

                if($partial && $this->collRewritings) {
                    foreach($this->collRewritings as $obj) {
                        if($obj->isNew()) {
                            $collRewritings[] = $obj;
                        }
                    }
                }

                $this->collRewritings = $collRewritings;
                $this->collRewritingsPartial = false;
            }
        }

        return $this->collRewritings;
    }

    /**
     * Sets a collection of Rewriting objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $rewritings A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setRewritings(PropelCollection $rewritings, PropelPDO $con = null)
    {
        $this->rewritingsScheduledForDeletion = $this->getRewritings(new Criteria(), $con)->diff($rewritings);

        foreach ($this->rewritingsScheduledForDeletion as $rewritingRemoved) {
            $rewritingRemoved->setCategory(null);
        }

        $this->collRewritings = null;
        foreach ($rewritings as $rewriting) {
            $this->addRewriting($rewriting);
        }

        $this->collRewritings = $rewritings;
        $this->collRewritingsPartial = false;
    }

    /**
     * Returns the number of related Rewriting objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Rewriting objects.
     * @throws PropelException
     */
    public function countRewritings(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collRewritingsPartial && !$this->isNew();
        if (null === $this->collRewritings || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collRewritings) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getRewritings());
                }
                $query = RewritingQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collRewritings);
        }
    }

    /**
     * Method called to associate a Rewriting object to this object
     * through the Rewriting foreign key attribute.
     *
     * @param    Rewriting $l Rewriting
     * @return Category The current object (for fluent API support)
     */
    public function addRewriting(Rewriting $l)
    {
        if ($this->collRewritings === null) {
            $this->initRewritings();
            $this->collRewritingsPartial = true;
        }
        if (!$this->collRewritings->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddRewriting($l);
        }

        return $this;
    }

    /**
     * @param	Rewriting $rewriting The rewriting object to add.
     */
    protected function doAddRewriting($rewriting)
    {
        $this->collRewritings[]= $rewriting;
        $rewriting->setCategory($this);
    }

    /**
     * @param	Rewriting $rewriting The rewriting object to remove.
     */
    public function removeRewriting($rewriting)
    {
        if ($this->getRewritings()->contains($rewriting)) {
            $this->collRewritings->remove($this->collRewritings->search($rewriting));
            if (null === $this->rewritingsScheduledForDeletion) {
                $this->rewritingsScheduledForDeletion = clone $this->collRewritings;
                $this->rewritingsScheduledForDeletion->clear();
            }
            $this->rewritingsScheduledForDeletion[]= $rewriting;
            $rewriting->setCategory(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Rewriting[] List of Rewriting objects
     */
    public function getRewritingsJoinProduct($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = RewritingQuery::create(null, $criteria);
        $query->joinWith('Product', $join_behavior);

        return $this->getRewritings($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Rewriting[] List of Rewriting objects
     */
    public function getRewritingsJoinFolder($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = RewritingQuery::create(null, $criteria);
        $query->joinWith('Folder', $join_behavior);

        return $this->getRewritings($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Rewriting[] List of Rewriting objects
     */
    public function getRewritingsJoinContent($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = RewritingQuery::create(null, $criteria);
        $query->joinWith('Content', $join_behavior);

        return $this->getRewritings($query, $con);
    }

    /**
     * Clears out the collCategoryI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCategoryI18ns()
     */
    public function clearCategoryI18ns()
    {
        $this->collCategoryI18ns = null; // important to set this to null since that means it is uninitialized
        $this->collCategoryI18nsPartial = null;
    }

    /**
     * reset is the collCategoryI18ns collection loaded partially
     *
     * @return void
     */
    public function resetPartialCategoryI18ns($v = true)
    {
        $this->collCategoryI18nsPartial = $v;
    }

    /**
     * Initializes the collCategoryI18ns collection.
     *
     * By default this just sets the collCategoryI18ns collection to an empty array (like clearcollCategoryI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCategoryI18ns($overrideExisting = true)
    {
        if (null !== $this->collCategoryI18ns && !$overrideExisting) {
            return;
        }
        $this->collCategoryI18ns = new PropelObjectCollection();
        $this->collCategoryI18ns->setModel('CategoryI18n');
    }

    /**
     * Gets an array of CategoryI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Category is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|CategoryI18n[] List of CategoryI18n objects
     * @throws PropelException
     */
    public function getCategoryI18ns($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collCategoryI18nsPartial && !$this->isNew();
        if (null === $this->collCategoryI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCategoryI18ns) {
                // return empty collection
                $this->initCategoryI18ns();
            } else {
                $collCategoryI18ns = CategoryI18nQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collCategoryI18nsPartial && count($collCategoryI18ns)) {
                      $this->initCategoryI18ns(false);

                      foreach($collCategoryI18ns as $obj) {
                        if (false == $this->collCategoryI18ns->contains($obj)) {
                          $this->collCategoryI18ns->append($obj);
                        }
                      }

                      $this->collCategoryI18nsPartial = true;
                    }

                    return $collCategoryI18ns;
                }

                if($partial && $this->collCategoryI18ns) {
                    foreach($this->collCategoryI18ns as $obj) {
                        if($obj->isNew()) {
                            $collCategoryI18ns[] = $obj;
                        }
                    }
                }

                $this->collCategoryI18ns = $collCategoryI18ns;
                $this->collCategoryI18nsPartial = false;
            }
        }

        return $this->collCategoryI18ns;
    }

    /**
     * Sets a collection of CategoryI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $categoryI18ns A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setCategoryI18ns(PropelCollection $categoryI18ns, PropelPDO $con = null)
    {
        $this->categoryI18nsScheduledForDeletion = $this->getCategoryI18ns(new Criteria(), $con)->diff($categoryI18ns);

        foreach ($this->categoryI18nsScheduledForDeletion as $categoryI18nRemoved) {
            $categoryI18nRemoved->setCategory(null);
        }

        $this->collCategoryI18ns = null;
        foreach ($categoryI18ns as $categoryI18n) {
            $this->addCategoryI18n($categoryI18n);
        }

        $this->collCategoryI18ns = $categoryI18ns;
        $this->collCategoryI18nsPartial = false;
    }

    /**
     * Returns the number of related CategoryI18n objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related CategoryI18n objects.
     * @throws PropelException
     */
    public function countCategoryI18ns(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collCategoryI18nsPartial && !$this->isNew();
        if (null === $this->collCategoryI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCategoryI18ns) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getCategoryI18ns());
                }
                $query = CategoryI18nQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collCategoryI18ns);
        }
    }

    /**
     * Method called to associate a CategoryI18n object to this object
     * through the CategoryI18n foreign key attribute.
     *
     * @param    CategoryI18n $l CategoryI18n
     * @return Category The current object (for fluent API support)
     */
    public function addCategoryI18n(CategoryI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collCategoryI18ns === null) {
            $this->initCategoryI18ns();
            $this->collCategoryI18nsPartial = true;
        }
        if (!$this->collCategoryI18ns->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddCategoryI18n($l);
        }

        return $this;
    }

    /**
     * @param	CategoryI18n $categoryI18n The categoryI18n object to add.
     */
    protected function doAddCategoryI18n($categoryI18n)
    {
        $this->collCategoryI18ns[]= $categoryI18n;
        $categoryI18n->setCategory($this);
    }

    /**
     * @param	CategoryI18n $categoryI18n The categoryI18n object to remove.
     */
    public function removeCategoryI18n($categoryI18n)
    {
        if ($this->getCategoryI18ns()->contains($categoryI18n)) {
            $this->collCategoryI18ns->remove($this->collCategoryI18ns->search($categoryI18n));
            if (null === $this->categoryI18nsScheduledForDeletion) {
                $this->categoryI18nsScheduledForDeletion = clone $this->collCategoryI18ns;
                $this->categoryI18nsScheduledForDeletion->clear();
            }
            $this->categoryI18nsScheduledForDeletion[]= $categoryI18n;
            $categoryI18n->setCategory(null);
        }
    }

    /**
     * Clears out the collCategoryVersions collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCategoryVersions()
     */
    public function clearCategoryVersions()
    {
        $this->collCategoryVersions = null; // important to set this to null since that means it is uninitialized
        $this->collCategoryVersionsPartial = null;
    }

    /**
     * reset is the collCategoryVersions collection loaded partially
     *
     * @return void
     */
    public function resetPartialCategoryVersions($v = true)
    {
        $this->collCategoryVersionsPartial = $v;
    }

    /**
     * Initializes the collCategoryVersions collection.
     *
     * By default this just sets the collCategoryVersions collection to an empty array (like clearcollCategoryVersions());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCategoryVersions($overrideExisting = true)
    {
        if (null !== $this->collCategoryVersions && !$overrideExisting) {
            return;
        }
        $this->collCategoryVersions = new PropelObjectCollection();
        $this->collCategoryVersions->setModel('CategoryVersion');
    }

    /**
     * Gets an array of CategoryVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Category is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|CategoryVersion[] List of CategoryVersion objects
     * @throws PropelException
     */
    public function getCategoryVersions($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collCategoryVersionsPartial && !$this->isNew();
        if (null === $this->collCategoryVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCategoryVersions) {
                // return empty collection
                $this->initCategoryVersions();
            } else {
                $collCategoryVersions = CategoryVersionQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collCategoryVersionsPartial && count($collCategoryVersions)) {
                      $this->initCategoryVersions(false);

                      foreach($collCategoryVersions as $obj) {
                        if (false == $this->collCategoryVersions->contains($obj)) {
                          $this->collCategoryVersions->append($obj);
                        }
                      }

                      $this->collCategoryVersionsPartial = true;
                    }

                    return $collCategoryVersions;
                }

                if($partial && $this->collCategoryVersions) {
                    foreach($this->collCategoryVersions as $obj) {
                        if($obj->isNew()) {
                            $collCategoryVersions[] = $obj;
                        }
                    }
                }

                $this->collCategoryVersions = $collCategoryVersions;
                $this->collCategoryVersionsPartial = false;
            }
        }

        return $this->collCategoryVersions;
    }

    /**
     * Sets a collection of CategoryVersion objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $categoryVersions A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setCategoryVersions(PropelCollection $categoryVersions, PropelPDO $con = null)
    {
        $this->categoryVersionsScheduledForDeletion = $this->getCategoryVersions(new Criteria(), $con)->diff($categoryVersions);

        foreach ($this->categoryVersionsScheduledForDeletion as $categoryVersionRemoved) {
            $categoryVersionRemoved->setCategory(null);
        }

        $this->collCategoryVersions = null;
        foreach ($categoryVersions as $categoryVersion) {
            $this->addCategoryVersion($categoryVersion);
        }

        $this->collCategoryVersions = $categoryVersions;
        $this->collCategoryVersionsPartial = false;
    }

    /**
     * Returns the number of related CategoryVersion objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related CategoryVersion objects.
     * @throws PropelException
     */
    public function countCategoryVersions(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collCategoryVersionsPartial && !$this->isNew();
        if (null === $this->collCategoryVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCategoryVersions) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getCategoryVersions());
                }
                $query = CategoryVersionQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collCategoryVersions);
        }
    }

    /**
     * Method called to associate a CategoryVersion object to this object
     * through the CategoryVersion foreign key attribute.
     *
     * @param    CategoryVersion $l CategoryVersion
     * @return Category The current object (for fluent API support)
     */
    public function addCategoryVersion(CategoryVersion $l)
    {
        if ($this->collCategoryVersions === null) {
            $this->initCategoryVersions();
            $this->collCategoryVersionsPartial = true;
        }
        if (!$this->collCategoryVersions->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddCategoryVersion($l);
        }

        return $this;
    }

    /**
     * @param	CategoryVersion $categoryVersion The categoryVersion object to add.
     */
    protected function doAddCategoryVersion($categoryVersion)
    {
        $this->collCategoryVersions[]= $categoryVersion;
        $categoryVersion->setCategory($this);
    }

    /**
     * @param	CategoryVersion $categoryVersion The categoryVersion object to remove.
     */
    public function removeCategoryVersion($categoryVersion)
    {
        if ($this->getCategoryVersions()->contains($categoryVersion)) {
            $this->collCategoryVersions->remove($this->collCategoryVersions->search($categoryVersion));
            if (null === $this->categoryVersionsScheduledForDeletion) {
                $this->categoryVersionsScheduledForDeletion = clone $this->collCategoryVersions;
                $this->categoryVersionsScheduledForDeletion->clear();
            }
            $this->categoryVersionsScheduledForDeletion[]= $categoryVersion;
            $categoryVersion->setCategory(null);
        }
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
        $this->updated_at = null;
        $this->version = null;
        $this->version_created_at = null;
        $this->version_created_by = null;
        $this->alreadyInSave = false;
        $this->alreadyInValidation = false;
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
        if ($deep) {
            if ($this->collProductCategorys) {
                foreach ($this->collProductCategorys as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureCategorys) {
                foreach ($this->collFeatureCategorys as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAttributeCategorys) {
                foreach ($this->collAttributeCategorys as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collContentAssocs) {
                foreach ($this->collContentAssocs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collImages) {
                foreach ($this->collImages as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collDocuments) {
                foreach ($this->collDocuments as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collRewritings) {
                foreach ($this->collRewritings as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCategoryI18ns) {
                foreach ($this->collCategoryI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCategoryVersions) {
                foreach ($this->collCategoryVersions as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_EN';
        $this->currentTranslations = null;

        if ($this->collProductCategorys instanceof PropelCollection) {
            $this->collProductCategorys->clearIterator();
        }
        $this->collProductCategorys = null;
        if ($this->collFeatureCategorys instanceof PropelCollection) {
            $this->collFeatureCategorys->clearIterator();
        }
        $this->collFeatureCategorys = null;
        if ($this->collAttributeCategorys instanceof PropelCollection) {
            $this->collAttributeCategorys->clearIterator();
        }
        $this->collAttributeCategorys = null;
        if ($this->collContentAssocs instanceof PropelCollection) {
            $this->collContentAssocs->clearIterator();
        }
        $this->collContentAssocs = null;
        if ($this->collImages instanceof PropelCollection) {
            $this->collImages->clearIterator();
        }
        $this->collImages = null;
        if ($this->collDocuments instanceof PropelCollection) {
            $this->collDocuments->clearIterator();
        }
        $this->collDocuments = null;
        if ($this->collRewritings instanceof PropelCollection) {
            $this->collRewritings->clearIterator();
        }
        $this->collRewritings = null;
        if ($this->collCategoryI18ns instanceof PropelCollection) {
            $this->collCategoryI18ns->clearIterator();
        }
        $this->collCategoryI18ns = null;
        if ($this->collCategoryVersions instanceof PropelCollection) {
            $this->collCategoryVersions->clearIterator();
        }
        $this->collCategoryVersions = null;
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

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     Category The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = CategoryPeer::UPDATED_AT;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    Category The current object (for fluent API support)
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
     * @return CategoryI18n */
    public function getTranslation($locale = 'en_EN', PropelPDO $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collCategoryI18ns) {
                foreach ($this->collCategoryI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new CategoryI18n();
                $translation->setLocale($locale);
            } else {
                $translation = CategoryI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addCategoryI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     PropelPDO $con an optional connection object
     *
     * @return    Category The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_EN', PropelPDO $con = null)
    {
        if (!$this->isNew()) {
            CategoryI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collCategoryI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collCategoryI18ns[$key]);
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
     * @return CategoryI18n */
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
         * @return CategoryI18n The current object (for fluent API support)
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
         * @return CategoryI18n The current object (for fluent API support)
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
         * @return CategoryI18n The current object (for fluent API support)
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
         * @return CategoryI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return Category
     */
    public function enforceVersioning()
    {
        $this->enforceVersion = true;

        return $this;
    }

    /**
     * Checks whether the current state must be recorded as a version
     *
     * @param PropelPDO $con An optional PropelPDO connection to use.
     *
     * @return  boolean
     */
    public function isVersioningNecessary($con = null)
    {
        if ($this->alreadyInSave) {
            return false;
        }

        if ($this->enforceVersion) {
            return true;
        }

        if (CategoryPeer::isVersioningEnabled() && ($this->isNew() || $this->isModified() || $this->isDeleted())) {
            return true;
        }

        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   PropelPDO $con the connection to use
     *
     * @return  CategoryVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new CategoryVersion();
        $version->setId($this->getId());
        $version->setParent($this->getParent());
        $version->setLink($this->getLink());
        $version->setVisible($this->getVisible());
        $version->setPosition($this->getPosition());
        $version->setCreatedAt($this->getCreatedAt());
        $version->setUpdatedAt($this->getUpdatedAt());
        $version->setVersion($this->getVersion());
        $version->setVersionCreatedAt($this->getVersionCreatedAt());
        $version->setVersionCreatedBy($this->getVersionCreatedBy());
        $version->setCategory($this);
        $version->save($con);

        return $version;
    }

    /**
     * Sets the properties of the curent object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   PropelPDO $con the connection to use
     *
     * @return  Category The current object (for fluent API support)
     * @throws  PropelException - if no object with the given version can be found.
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No Category object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the curent object to the value they had at a specific version
     *
     * @param   CategoryVersion $version The version object to use
     * @param   PropelPDO $con the connection to use
     * @param   array $loadedObjects objects thats been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return  Category The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {

        $loadedObjects['Category'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
        $this->setParent($version->getParent());
        $this->setLink($version->getLink());
        $this->setVisible($version->getVisible());
        $this->setPosition($version->getPosition());
        $this->setCreatedAt($version->getCreatedAt());
        $this->setUpdatedAt($version->getUpdatedAt());
        $this->setVersion($version->getVersion());
        $this->setVersionCreatedAt($version->getVersionCreatedAt());
        $this->setVersionCreatedBy($version->getVersionCreatedBy());

        return $this;
    }

    /**
     * Gets the latest persisted version number for the current object
     *
     * @param   PropelPDO $con the connection to use
     *
     * @return  integer
     */
    public function getLastVersionNumber($con = null)
    {
        $v = CategoryVersionQuery::create()
            ->filterByCategory($this)
            ->orderByVersion('desc')
            ->findOne($con);
        if (!$v) {
            return 0;
        }

        return $v->getVersion();
    }

    /**
     * Checks whether the current object is the latest one
     *
     * @param   PropelPDO $con the connection to use
     *
     * @return  boolean
     */
    public function isLastVersion($con = null)
    {
        return $this->getLastVersionNumber($con) == $this->getVersion();
    }

    /**
     * Retrieves a version object for this entity and a version number
     *
     * @param   integer $versionNumber The version number to read
     * @param   PropelPDO $con the connection to use
     *
     * @return  CategoryVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return CategoryVersionQuery::create()
            ->filterByCategory($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   PropelPDO $con the connection to use
     *
     * @return  PropelObjectCollection A list of CategoryVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(CategoryVersionPeer::VERSION);

        return $this->getCategoryVersions($criteria, $con);
    }

    /**
     * Compares the current object with another of its version.
     * <code>
     * print_r($book->compareVersion(1));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   integer   $versionNumber
     * @param   string    $keys Main key used for the result diff (versions|columns)
     * @param   PropelPDO $con the connection to use
     * @param   array     $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    public function compareVersion($versionNumber, $keys = 'columns', $con = null, $ignoredColumns = array())
    {
        $fromVersion = $this->toArray();
        $toVersion = $this->getOneVersion($versionNumber, $con)->toArray();

        return $this->computeDiff($fromVersion, $toVersion, $keys, $ignoredColumns);
    }

    /**
     * Compares two versions of the current object.
     * <code>
     * print_r($book->compareVersions(1, 2));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   integer   $fromVersionNumber
     * @param   integer   $toVersionNumber
     * @param   string    $keys Main key used for the result diff (versions|columns)
     * @param   PropelPDO $con the connection to use
     * @param   array     $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    public function compareVersions($fromVersionNumber, $toVersionNumber, $keys = 'columns', $con = null, $ignoredColumns = array())
    {
        $fromVersion = $this->getOneVersion($fromVersionNumber, $con)->toArray();
        $toVersion = $this->getOneVersion($toVersionNumber, $con)->toArray();

        return $this->computeDiff($fromVersion, $toVersion, $keys, $ignoredColumns);
    }

    /**
     * Computes the diff between two versions.
     * <code>
     * print_r($this->computeDiff(1, 2));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   array     $fromVersion     An array representing the original version.
     * @param   array     $toVersion       An array representing the destination version.
     * @param   string    $keys            Main key used for the result diff (versions|columns).
     * @param   array     $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    protected function computeDiff($fromVersion, $toVersion, $keys = 'columns', $ignoredColumns = array())
    {
        $fromVersionNumber = $fromVersion['Version'];
        $toVersionNumber = $toVersion['Version'];
        $ignoredColumns = array_merge(array(
            'Version',
            'VersionCreatedAt',
            'VersionCreatedBy',
        ), $ignoredColumns);
        $diff = array();
        foreach ($fromVersion as $key => $value) {
            if (in_array($key, $ignoredColumns)) {
                continue;
            }
            if ($toVersion[$key] != $value) {
                switch ($keys) {
                    case 'versions':
                        $diff[$fromVersionNumber][$key] = $value;
                        $diff[$toVersionNumber][$key] = $toVersion[$key];
                        break;
                    default:
                        $diff[$key] = array(
                            $fromVersionNumber => $value,
                            $toVersionNumber => $toVersion[$key],
                        );
                        break;
                }
            }
        }

        return $diff;
    }
    /**
     * retrieve the last $number versions.
     *
     * @param integer $number the number of record to return.
     * @param CategoryVersionQuery|Criteria $criteria Additional criteria to filter.
     * @param PropelPDO $con An optional connection to use.
     *
     * @return PropelCollection|CategoryVersion[] List of CategoryVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, PropelPDO $con = null)
    {
        $criteria = CategoryVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(CategoryVersionPeer::VERSION);
        $criteria->limit($number);

        return $this->getCategoryVersions($criteria, $con);
    }
}
