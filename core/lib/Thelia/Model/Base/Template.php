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
use Thelia\Model\AttributeQuery as ChildAttributeQuery;
use Thelia\Model\AttributeTemplate as ChildAttributeTemplate;
use Thelia\Model\AttributeTemplateQuery as ChildAttributeTemplateQuery;
use Thelia\Model\Feature as ChildFeature;
use Thelia\Model\FeatureQuery as ChildFeatureQuery;
use Thelia\Model\FeatureTemplate as ChildFeatureTemplate;
use Thelia\Model\FeatureTemplateQuery as ChildFeatureTemplateQuery;
use Thelia\Model\Product as ChildProduct;
use Thelia\Model\ProductQuery as ChildProductQuery;
use Thelia\Model\Template as ChildTemplate;
use Thelia\Model\TemplateI18n as ChildTemplateI18n;
use Thelia\Model\TemplateI18nQuery as ChildTemplateI18nQuery;
use Thelia\Model\TemplateQuery as ChildTemplateQuery;
use Thelia\Model\Map\TemplateTableMap;

abstract class Template implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\TemplateTableMap';


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
     * @var        ObjectCollection|ChildProduct[] Collection to store aggregation of ChildProduct objects.
     */
    protected $collProducts;
    protected $collProductsPartial;

    /**
     * @var        ObjectCollection|ChildFeatureTemplate[] Collection to store aggregation of ChildFeatureTemplate objects.
     */
    protected $collFeatureTemplates;
    protected $collFeatureTemplatesPartial;

    /**
     * @var        ObjectCollection|ChildAttributeTemplate[] Collection to store aggregation of ChildAttributeTemplate objects.
     */
    protected $collAttributeTemplates;
    protected $collAttributeTemplatesPartial;

    /**
     * @var        ObjectCollection|ChildTemplateI18n[] Collection to store aggregation of ChildTemplateI18n objects.
     */
    protected $collTemplateI18ns;
    protected $collTemplateI18nsPartial;

    /**
     * @var        ChildFeature[] Collection to store aggregation of ChildFeature objects.
     */
    protected $collFeatures;

    /**
     * @var        ChildAttribute[] Collection to store aggregation of ChildAttribute objects.
     */
    protected $collAttributes;

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
     * @var        array[ChildTemplateI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $featuresScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $attributesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $featureTemplatesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $attributeTemplatesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $templateI18nsScheduledForDeletion = null;

    /**
     * Initializes internal state of Thelia\Model\Base\Template object.
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
     * Compares this with another <code>Template</code> instance.  If
     * <code>obj</code> is an instance of <code>Template</code>, delegates to
     * <code>equals(Template)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Template The current object, for fluid interface
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
     * @return Template The current object, for fluid interface
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
     * @return   \Thelia\Model\Template The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[TemplateTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Template The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[TemplateTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Template The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[TemplateTableMap::UPDATED_AT] = true;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : TemplateTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : TemplateTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : TemplateTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 3; // 3 = TemplateTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Template object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(TemplateTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildTemplateQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collProducts = null;

            $this->collFeatureTemplates = null;

            $this->collAttributeTemplates = null;

            $this->collTemplateI18ns = null;

            $this->collFeatures = null;
            $this->collAttributes = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Template::setDeleted()
     * @see Template::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(TemplateTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildTemplateQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(TemplateTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(TemplateTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(TemplateTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(TemplateTableMap::UPDATED_AT)) {
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
                TemplateTableMap::addInstanceToPool($this);
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

            if ($this->featuresScheduledForDeletion !== null) {
                if (!$this->featuresScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->featuresScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    FeatureTemplateQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->featuresScheduledForDeletion = null;
                }

                foreach ($this->getFeatures() as $feature) {
                    if ($feature->isModified()) {
                        $feature->save($con);
                    }
                }
            } elseif ($this->collFeatures) {
                foreach ($this->collFeatures as $feature) {
                    if ($feature->isModified()) {
                        $feature->save($con);
                    }
                }
            }

            if ($this->attributesScheduledForDeletion !== null) {
                if (!$this->attributesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->attributesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    AttributeTemplateQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->attributesScheduledForDeletion = null;
                }

                foreach ($this->getAttributes() as $attribute) {
                    if ($attribute->isModified()) {
                        $attribute->save($con);
                    }
                }
            } elseif ($this->collAttributes) {
                foreach ($this->collAttributes as $attribute) {
                    if ($attribute->isModified()) {
                        $attribute->save($con);
                    }
                }
            }

            if ($this->productsScheduledForDeletion !== null) {
                if (!$this->productsScheduledForDeletion->isEmpty()) {
                    foreach ($this->productsScheduledForDeletion as $product) {
                        // need to save related object because we set the relation to null
                        $product->save($con);
                    }
                    $this->productsScheduledForDeletion = null;
                }
            }

                if ($this->collProducts !== null) {
            foreach ($this->collProducts as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureTemplatesScheduledForDeletion !== null) {
                if (!$this->featureTemplatesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\FeatureTemplateQuery::create()
                        ->filterByPrimaryKeys($this->featureTemplatesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureTemplatesScheduledForDeletion = null;
                }
            }

                if ($this->collFeatureTemplates !== null) {
            foreach ($this->collFeatureTemplates as $referrerFK) {
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

            if ($this->templateI18nsScheduledForDeletion !== null) {
                if (!$this->templateI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\TemplateI18nQuery::create()
                        ->filterByPrimaryKeys($this->templateI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->templateI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collTemplateI18ns !== null) {
            foreach ($this->collTemplateI18ns as $referrerFK) {
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

        $this->modifiedColumns[TemplateTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . TemplateTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(TemplateTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(TemplateTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(TemplateTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `template` (%s) VALUES (%s)',
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
        $pos = TemplateTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getCreatedAt();
                break;
            case 2:
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
        if (isset($alreadyDumpedObjects['Template'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Template'][$this->getPrimaryKey()] = true;
        $keys = TemplateTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCreatedAt(),
            $keys[2] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collProducts) {
                $result['Products'] = $this->collProducts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureTemplates) {
                $result['FeatureTemplates'] = $this->collFeatureTemplates->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAttributeTemplates) {
                $result['AttributeTemplates'] = $this->collAttributeTemplates->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collTemplateI18ns) {
                $result['TemplateI18ns'] = $this->collTemplateI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = TemplateTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setCreatedAt($value);
                break;
            case 2:
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
        $keys = TemplateTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCreatedAt($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setUpdatedAt($arr[$keys[2]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(TemplateTableMap::DATABASE_NAME);

        if ($this->isColumnModified(TemplateTableMap::ID)) $criteria->add(TemplateTableMap::ID, $this->id);
        if ($this->isColumnModified(TemplateTableMap::CREATED_AT)) $criteria->add(TemplateTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(TemplateTableMap::UPDATED_AT)) $criteria->add(TemplateTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(TemplateTableMap::DATABASE_NAME);
        $criteria->add(TemplateTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Template (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getProducts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProduct($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureTemplates() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureTemplate($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAttributeTemplates() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAttributeTemplate($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getTemplateI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addTemplateI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Template Clone of current object.
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
        if ('Product' == $relationName) {
            return $this->initProducts();
        }
        if ('FeatureTemplate' == $relationName) {
            return $this->initFeatureTemplates();
        }
        if ('AttributeTemplate' == $relationName) {
            return $this->initAttributeTemplates();
        }
        if ('TemplateI18n' == $relationName) {
            return $this->initTemplateI18ns();
        }
    }

    /**
     * Clears out the collProducts collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProducts()
     */
    public function clearProducts()
    {
        $this->collProducts = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProducts collection loaded partially.
     */
    public function resetPartialProducts($v = true)
    {
        $this->collProductsPartial = $v;
    }

    /**
     * Initializes the collProducts collection.
     *
     * By default this just sets the collProducts collection to an empty array (like clearcollProducts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProducts($overrideExisting = true)
    {
        if (null !== $this->collProducts && !$overrideExisting) {
            return;
        }
        $this->collProducts = new ObjectCollection();
        $this->collProducts->setModel('\Thelia\Model\Product');
    }

    /**
     * Gets an array of ChildProduct objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildTemplate is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProduct[] List of ChildProduct objects
     * @throws PropelException
     */
    public function getProducts($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductsPartial && !$this->isNew();
        if (null === $this->collProducts || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProducts) {
                // return empty collection
                $this->initProducts();
            } else {
                $collProducts = ChildProductQuery::create(null, $criteria)
                    ->filterByTemplate($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductsPartial && count($collProducts)) {
                        $this->initProducts(false);

                        foreach ($collProducts as $obj) {
                            if (false == $this->collProducts->contains($obj)) {
                                $this->collProducts->append($obj);
                            }
                        }

                        $this->collProductsPartial = true;
                    }

                    reset($collProducts);

                    return $collProducts;
                }

                if ($partial && $this->collProducts) {
                    foreach ($this->collProducts as $obj) {
                        if ($obj->isNew()) {
                            $collProducts[] = $obj;
                        }
                    }
                }

                $this->collProducts = $collProducts;
                $this->collProductsPartial = false;
            }
        }

        return $this->collProducts;
    }

    /**
     * Sets a collection of Product objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $products A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildTemplate The current object (for fluent API support)
     */
    public function setProducts(Collection $products, ConnectionInterface $con = null)
    {
        $productsToDelete = $this->getProducts(new Criteria(), $con)->diff($products);


        $this->productsScheduledForDeletion = $productsToDelete;

        foreach ($productsToDelete as $productRemoved) {
            $productRemoved->setTemplate(null);
        }

        $this->collProducts = null;
        foreach ($products as $product) {
            $this->addProduct($product);
        }

        $this->collProducts = $products;
        $this->collProductsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Product objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Product objects.
     * @throws PropelException
     */
    public function countProducts(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductsPartial && !$this->isNew();
        if (null === $this->collProducts || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProducts) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProducts());
            }

            $query = ChildProductQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByTemplate($this)
                ->count($con);
        }

        return count($this->collProducts);
    }

    /**
     * Method called to associate a ChildProduct object to this object
     * through the ChildProduct foreign key attribute.
     *
     * @param    ChildProduct $l ChildProduct
     * @return   \Thelia\Model\Template The current object (for fluent API support)
     */
    public function addProduct(ChildProduct $l)
    {
        if ($this->collProducts === null) {
            $this->initProducts();
            $this->collProductsPartial = true;
        }

        if (!in_array($l, $this->collProducts->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProduct($l);
        }

        return $this;
    }

    /**
     * @param Product $product The product object to add.
     */
    protected function doAddProduct($product)
    {
        $this->collProducts[]= $product;
        $product->setTemplate($this);
    }

    /**
     * @param  Product $product The product object to remove.
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function removeProduct($product)
    {
        if ($this->getProducts()->contains($product)) {
            $this->collProducts->remove($this->collProducts->search($product));
            if (null === $this->productsScheduledForDeletion) {
                $this->productsScheduledForDeletion = clone $this->collProducts;
                $this->productsScheduledForDeletion->clear();
            }
            $this->productsScheduledForDeletion[]= $product;
            $product->setTemplate(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Template is new, it will return
     * an empty collection; or if this Template has previously
     * been saved, it will retrieve related Products from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Template.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProduct[] List of ChildProduct objects
     */
    public function getProductsJoinTaxRule($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProductQuery::create(null, $criteria);
        $query->joinWith('TaxRule', $joinBehavior);

        return $this->getProducts($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Template is new, it will return
     * an empty collection; or if this Template has previously
     * been saved, it will retrieve related Products from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Template.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProduct[] List of ChildProduct objects
     */
    public function getProductsJoinBrand($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProductQuery::create(null, $criteria);
        $query->joinWith('Brand', $joinBehavior);

        return $this->getProducts($query, $con);
    }

    /**
     * Clears out the collFeatureTemplates collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureTemplates()
     */
    public function clearFeatureTemplates()
    {
        $this->collFeatureTemplates = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collFeatureTemplates collection loaded partially.
     */
    public function resetPartialFeatureTemplates($v = true)
    {
        $this->collFeatureTemplatesPartial = $v;
    }

    /**
     * Initializes the collFeatureTemplates collection.
     *
     * By default this just sets the collFeatureTemplates collection to an empty array (like clearcollFeatureTemplates());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureTemplates($overrideExisting = true)
    {
        if (null !== $this->collFeatureTemplates && !$overrideExisting) {
            return;
        }
        $this->collFeatureTemplates = new ObjectCollection();
        $this->collFeatureTemplates->setModel('\Thelia\Model\FeatureTemplate');
    }

    /**
     * Gets an array of ChildFeatureTemplate objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildTemplate is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildFeatureTemplate[] List of ChildFeatureTemplate objects
     * @throws PropelException
     */
    public function getFeatureTemplates($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureTemplatesPartial && !$this->isNew();
        if (null === $this->collFeatureTemplates || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureTemplates) {
                // return empty collection
                $this->initFeatureTemplates();
            } else {
                $collFeatureTemplates = ChildFeatureTemplateQuery::create(null, $criteria)
                    ->filterByTemplate($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collFeatureTemplatesPartial && count($collFeatureTemplates)) {
                        $this->initFeatureTemplates(false);

                        foreach ($collFeatureTemplates as $obj) {
                            if (false == $this->collFeatureTemplates->contains($obj)) {
                                $this->collFeatureTemplates->append($obj);
                            }
                        }

                        $this->collFeatureTemplatesPartial = true;
                    }

                    reset($collFeatureTemplates);

                    return $collFeatureTemplates;
                }

                if ($partial && $this->collFeatureTemplates) {
                    foreach ($this->collFeatureTemplates as $obj) {
                        if ($obj->isNew()) {
                            $collFeatureTemplates[] = $obj;
                        }
                    }
                }

                $this->collFeatureTemplates = $collFeatureTemplates;
                $this->collFeatureTemplatesPartial = false;
            }
        }

        return $this->collFeatureTemplates;
    }

    /**
     * Sets a collection of FeatureTemplate objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $featureTemplates A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildTemplate The current object (for fluent API support)
     */
    public function setFeatureTemplates(Collection $featureTemplates, ConnectionInterface $con = null)
    {
        $featureTemplatesToDelete = $this->getFeatureTemplates(new Criteria(), $con)->diff($featureTemplates);


        $this->featureTemplatesScheduledForDeletion = $featureTemplatesToDelete;

        foreach ($featureTemplatesToDelete as $featureTemplateRemoved) {
            $featureTemplateRemoved->setTemplate(null);
        }

        $this->collFeatureTemplates = null;
        foreach ($featureTemplates as $featureTemplate) {
            $this->addFeatureTemplate($featureTemplate);
        }

        $this->collFeatureTemplates = $featureTemplates;
        $this->collFeatureTemplatesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FeatureTemplate objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related FeatureTemplate objects.
     * @throws PropelException
     */
    public function countFeatureTemplates(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureTemplatesPartial && !$this->isNew();
        if (null === $this->collFeatureTemplates || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureTemplates) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getFeatureTemplates());
            }

            $query = ChildFeatureTemplateQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByTemplate($this)
                ->count($con);
        }

        return count($this->collFeatureTemplates);
    }

    /**
     * Method called to associate a ChildFeatureTemplate object to this object
     * through the ChildFeatureTemplate foreign key attribute.
     *
     * @param    ChildFeatureTemplate $l ChildFeatureTemplate
     * @return   \Thelia\Model\Template The current object (for fluent API support)
     */
    public function addFeatureTemplate(ChildFeatureTemplate $l)
    {
        if ($this->collFeatureTemplates === null) {
            $this->initFeatureTemplates();
            $this->collFeatureTemplatesPartial = true;
        }

        if (!in_array($l, $this->collFeatureTemplates->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureTemplate($l);
        }

        return $this;
    }

    /**
     * @param FeatureTemplate $featureTemplate The featureTemplate object to add.
     */
    protected function doAddFeatureTemplate($featureTemplate)
    {
        $this->collFeatureTemplates[]= $featureTemplate;
        $featureTemplate->setTemplate($this);
    }

    /**
     * @param  FeatureTemplate $featureTemplate The featureTemplate object to remove.
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function removeFeatureTemplate($featureTemplate)
    {
        if ($this->getFeatureTemplates()->contains($featureTemplate)) {
            $this->collFeatureTemplates->remove($this->collFeatureTemplates->search($featureTemplate));
            if (null === $this->featureTemplatesScheduledForDeletion) {
                $this->featureTemplatesScheduledForDeletion = clone $this->collFeatureTemplates;
                $this->featureTemplatesScheduledForDeletion->clear();
            }
            $this->featureTemplatesScheduledForDeletion[]= clone $featureTemplate;
            $featureTemplate->setTemplate(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Template is new, it will return
     * an empty collection; or if this Template has previously
     * been saved, it will retrieve related FeatureTemplates from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Template.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildFeatureTemplate[] List of ChildFeatureTemplate objects
     */
    public function getFeatureTemplatesJoinFeature($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildFeatureTemplateQuery::create(null, $criteria);
        $query->joinWith('Feature', $joinBehavior);

        return $this->getFeatureTemplates($query, $con);
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
     * If this ChildTemplate is new, it will return
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
                    ->filterByTemplate($this)
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
     * @return   ChildTemplate The current object (for fluent API support)
     */
    public function setAttributeTemplates(Collection $attributeTemplates, ConnectionInterface $con = null)
    {
        $attributeTemplatesToDelete = $this->getAttributeTemplates(new Criteria(), $con)->diff($attributeTemplates);


        $this->attributeTemplatesScheduledForDeletion = $attributeTemplatesToDelete;

        foreach ($attributeTemplatesToDelete as $attributeTemplateRemoved) {
            $attributeTemplateRemoved->setTemplate(null);
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
                ->filterByTemplate($this)
                ->count($con);
        }

        return count($this->collAttributeTemplates);
    }

    /**
     * Method called to associate a ChildAttributeTemplate object to this object
     * through the ChildAttributeTemplate foreign key attribute.
     *
     * @param    ChildAttributeTemplate $l ChildAttributeTemplate
     * @return   \Thelia\Model\Template The current object (for fluent API support)
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
        $attributeTemplate->setTemplate($this);
    }

    /**
     * @param  AttributeTemplate $attributeTemplate The attributeTemplate object to remove.
     * @return ChildTemplate The current object (for fluent API support)
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
            $attributeTemplate->setTemplate(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Template is new, it will return
     * an empty collection; or if this Template has previously
     * been saved, it will retrieve related AttributeTemplates from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Template.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAttributeTemplate[] List of ChildAttributeTemplate objects
     */
    public function getAttributeTemplatesJoinAttribute($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAttributeTemplateQuery::create(null, $criteria);
        $query->joinWith('Attribute', $joinBehavior);

        return $this->getAttributeTemplates($query, $con);
    }

    /**
     * Clears out the collTemplateI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addTemplateI18ns()
     */
    public function clearTemplateI18ns()
    {
        $this->collTemplateI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collTemplateI18ns collection loaded partially.
     */
    public function resetPartialTemplateI18ns($v = true)
    {
        $this->collTemplateI18nsPartial = $v;
    }

    /**
     * Initializes the collTemplateI18ns collection.
     *
     * By default this just sets the collTemplateI18ns collection to an empty array (like clearcollTemplateI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initTemplateI18ns($overrideExisting = true)
    {
        if (null !== $this->collTemplateI18ns && !$overrideExisting) {
            return;
        }
        $this->collTemplateI18ns = new ObjectCollection();
        $this->collTemplateI18ns->setModel('\Thelia\Model\TemplateI18n');
    }

    /**
     * Gets an array of ChildTemplateI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildTemplate is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildTemplateI18n[] List of ChildTemplateI18n objects
     * @throws PropelException
     */
    public function getTemplateI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collTemplateI18nsPartial && !$this->isNew();
        if (null === $this->collTemplateI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collTemplateI18ns) {
                // return empty collection
                $this->initTemplateI18ns();
            } else {
                $collTemplateI18ns = ChildTemplateI18nQuery::create(null, $criteria)
                    ->filterByTemplate($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collTemplateI18nsPartial && count($collTemplateI18ns)) {
                        $this->initTemplateI18ns(false);

                        foreach ($collTemplateI18ns as $obj) {
                            if (false == $this->collTemplateI18ns->contains($obj)) {
                                $this->collTemplateI18ns->append($obj);
                            }
                        }

                        $this->collTemplateI18nsPartial = true;
                    }

                    reset($collTemplateI18ns);

                    return $collTemplateI18ns;
                }

                if ($partial && $this->collTemplateI18ns) {
                    foreach ($this->collTemplateI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collTemplateI18ns[] = $obj;
                        }
                    }
                }

                $this->collTemplateI18ns = $collTemplateI18ns;
                $this->collTemplateI18nsPartial = false;
            }
        }

        return $this->collTemplateI18ns;
    }

    /**
     * Sets a collection of TemplateI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $templateI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildTemplate The current object (for fluent API support)
     */
    public function setTemplateI18ns(Collection $templateI18ns, ConnectionInterface $con = null)
    {
        $templateI18nsToDelete = $this->getTemplateI18ns(new Criteria(), $con)->diff($templateI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->templateI18nsScheduledForDeletion = clone $templateI18nsToDelete;

        foreach ($templateI18nsToDelete as $templateI18nRemoved) {
            $templateI18nRemoved->setTemplate(null);
        }

        $this->collTemplateI18ns = null;
        foreach ($templateI18ns as $templateI18n) {
            $this->addTemplateI18n($templateI18n);
        }

        $this->collTemplateI18ns = $templateI18ns;
        $this->collTemplateI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related TemplateI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related TemplateI18n objects.
     * @throws PropelException
     */
    public function countTemplateI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collTemplateI18nsPartial && !$this->isNew();
        if (null === $this->collTemplateI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collTemplateI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getTemplateI18ns());
            }

            $query = ChildTemplateI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByTemplate($this)
                ->count($con);
        }

        return count($this->collTemplateI18ns);
    }

    /**
     * Method called to associate a ChildTemplateI18n object to this object
     * through the ChildTemplateI18n foreign key attribute.
     *
     * @param    ChildTemplateI18n $l ChildTemplateI18n
     * @return   \Thelia\Model\Template The current object (for fluent API support)
     */
    public function addTemplateI18n(ChildTemplateI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collTemplateI18ns === null) {
            $this->initTemplateI18ns();
            $this->collTemplateI18nsPartial = true;
        }

        if (!in_array($l, $this->collTemplateI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddTemplateI18n($l);
        }

        return $this;
    }

    /**
     * @param TemplateI18n $templateI18n The templateI18n object to add.
     */
    protected function doAddTemplateI18n($templateI18n)
    {
        $this->collTemplateI18ns[]= $templateI18n;
        $templateI18n->setTemplate($this);
    }

    /**
     * @param  TemplateI18n $templateI18n The templateI18n object to remove.
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function removeTemplateI18n($templateI18n)
    {
        if ($this->getTemplateI18ns()->contains($templateI18n)) {
            $this->collTemplateI18ns->remove($this->collTemplateI18ns->search($templateI18n));
            if (null === $this->templateI18nsScheduledForDeletion) {
                $this->templateI18nsScheduledForDeletion = clone $this->collTemplateI18ns;
                $this->templateI18nsScheduledForDeletion->clear();
            }
            $this->templateI18nsScheduledForDeletion[]= clone $templateI18n;
            $templateI18n->setTemplate(null);
        }

        return $this;
    }

    /**
     * Clears out the collFeatures collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatures()
     */
    public function clearFeatures()
    {
        $this->collFeatures = null; // important to set this to NULL since that means it is uninitialized
        $this->collFeaturesPartial = null;
    }

    /**
     * Initializes the collFeatures collection.
     *
     * By default this just sets the collFeatures collection to an empty collection (like clearFeatures());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initFeatures()
    {
        $this->collFeatures = new ObjectCollection();
        $this->collFeatures->setModel('\Thelia\Model\Feature');
    }

    /**
     * Gets a collection of ChildFeature objects related by a many-to-many relationship
     * to the current object by way of the feature_template cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildTemplate is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildFeature[] List of ChildFeature objects
     */
    public function getFeatures($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collFeatures || null !== $criteria) {
            if ($this->isNew() && null === $this->collFeatures) {
                // return empty collection
                $this->initFeatures();
            } else {
                $collFeatures = ChildFeatureQuery::create(null, $criteria)
                    ->filterByTemplate($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collFeatures;
                }
                $this->collFeatures = $collFeatures;
            }
        }

        return $this->collFeatures;
    }

    /**
     * Sets a collection of Feature objects related by a many-to-many relationship
     * to the current object by way of the feature_template cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $features A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function setFeatures(Collection $features, ConnectionInterface $con = null)
    {
        $this->clearFeatures();
        $currentFeatures = $this->getFeatures();

        $this->featuresScheduledForDeletion = $currentFeatures->diff($features);

        foreach ($features as $feature) {
            if (!$currentFeatures->contains($feature)) {
                $this->doAddFeature($feature);
            }
        }

        $this->collFeatures = $features;

        return $this;
    }

    /**
     * Gets the number of ChildFeature objects related by a many-to-many relationship
     * to the current object by way of the feature_template cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildFeature objects
     */
    public function countFeatures($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collFeatures || null !== $criteria) {
            if ($this->isNew() && null === $this->collFeatures) {
                return 0;
            } else {
                $query = ChildFeatureQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByTemplate($this)
                    ->count($con);
            }
        } else {
            return count($this->collFeatures);
        }
    }

    /**
     * Associate a ChildFeature object to this object
     * through the feature_template cross reference table.
     *
     * @param  ChildFeature $feature The ChildFeatureTemplate object to relate
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function addFeature(ChildFeature $feature)
    {
        if ($this->collFeatures === null) {
            $this->initFeatures();
        }

        if (!$this->collFeatures->contains($feature)) { // only add it if the **same** object is not already associated
            $this->doAddFeature($feature);
            $this->collFeatures[] = $feature;
        }

        return $this;
    }

    /**
     * @param    Feature $feature The feature object to add.
     */
    protected function doAddFeature($feature)
    {
        $featureTemplate = new ChildFeatureTemplate();
        $featureTemplate->setFeature($feature);
        $this->addFeatureTemplate($featureTemplate);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$feature->getTemplates()->contains($this)) {
            $foreignCollection   = $feature->getTemplates();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildFeature object to this object
     * through the feature_template cross reference table.
     *
     * @param ChildFeature $feature The ChildFeatureTemplate object to relate
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function removeFeature(ChildFeature $feature)
    {
        if ($this->getFeatures()->contains($feature)) {
            $this->collFeatures->remove($this->collFeatures->search($feature));

            if (null === $this->featuresScheduledForDeletion) {
                $this->featuresScheduledForDeletion = clone $this->collFeatures;
                $this->featuresScheduledForDeletion->clear();
            }

            $this->featuresScheduledForDeletion[] = $feature;
        }

        return $this;
    }

    /**
     * Clears out the collAttributes collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAttributes()
     */
    public function clearAttributes()
    {
        $this->collAttributes = null; // important to set this to NULL since that means it is uninitialized
        $this->collAttributesPartial = null;
    }

    /**
     * Initializes the collAttributes collection.
     *
     * By default this just sets the collAttributes collection to an empty collection (like clearAttributes());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initAttributes()
    {
        $this->collAttributes = new ObjectCollection();
        $this->collAttributes->setModel('\Thelia\Model\Attribute');
    }

    /**
     * Gets a collection of ChildAttribute objects related by a many-to-many relationship
     * to the current object by way of the attribute_template cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildTemplate is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildAttribute[] List of ChildAttribute objects
     */
    public function getAttributes($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collAttributes || null !== $criteria) {
            if ($this->isNew() && null === $this->collAttributes) {
                // return empty collection
                $this->initAttributes();
            } else {
                $collAttributes = ChildAttributeQuery::create(null, $criteria)
                    ->filterByTemplate($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collAttributes;
                }
                $this->collAttributes = $collAttributes;
            }
        }

        return $this->collAttributes;
    }

    /**
     * Sets a collection of Attribute objects related by a many-to-many relationship
     * to the current object by way of the attribute_template cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $attributes A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function setAttributes(Collection $attributes, ConnectionInterface $con = null)
    {
        $this->clearAttributes();
        $currentAttributes = $this->getAttributes();

        $this->attributesScheduledForDeletion = $currentAttributes->diff($attributes);

        foreach ($attributes as $attribute) {
            if (!$currentAttributes->contains($attribute)) {
                $this->doAddAttribute($attribute);
            }
        }

        $this->collAttributes = $attributes;

        return $this;
    }

    /**
     * Gets the number of ChildAttribute objects related by a many-to-many relationship
     * to the current object by way of the attribute_template cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildAttribute objects
     */
    public function countAttributes($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collAttributes || null !== $criteria) {
            if ($this->isNew() && null === $this->collAttributes) {
                return 0;
            } else {
                $query = ChildAttributeQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByTemplate($this)
                    ->count($con);
            }
        } else {
            return count($this->collAttributes);
        }
    }

    /**
     * Associate a ChildAttribute object to this object
     * through the attribute_template cross reference table.
     *
     * @param  ChildAttribute $attribute The ChildAttributeTemplate object to relate
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function addAttribute(ChildAttribute $attribute)
    {
        if ($this->collAttributes === null) {
            $this->initAttributes();
        }

        if (!$this->collAttributes->contains($attribute)) { // only add it if the **same** object is not already associated
            $this->doAddAttribute($attribute);
            $this->collAttributes[] = $attribute;
        }

        return $this;
    }

    /**
     * @param    Attribute $attribute The attribute object to add.
     */
    protected function doAddAttribute($attribute)
    {
        $attributeTemplate = new ChildAttributeTemplate();
        $attributeTemplate->setAttribute($attribute);
        $this->addAttributeTemplate($attributeTemplate);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$attribute->getTemplates()->contains($this)) {
            $foreignCollection   = $attribute->getTemplates();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildAttribute object to this object
     * through the attribute_template cross reference table.
     *
     * @param ChildAttribute $attribute The ChildAttributeTemplate object to relate
     * @return ChildTemplate The current object (for fluent API support)
     */
    public function removeAttribute(ChildAttribute $attribute)
    {
        if ($this->getAttributes()->contains($attribute)) {
            $this->collAttributes->remove($this->collAttributes->search($attribute));

            if (null === $this->attributesScheduledForDeletion) {
                $this->attributesScheduledForDeletion = clone $this->collAttributes;
                $this->attributesScheduledForDeletion->clear();
            }

            $this->attributesScheduledForDeletion[] = $attribute;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
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
            if ($this->collProducts) {
                foreach ($this->collProducts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureTemplates) {
                foreach ($this->collFeatureTemplates as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAttributeTemplates) {
                foreach ($this->collAttributeTemplates as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collTemplateI18ns) {
                foreach ($this->collTemplateI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatures) {
                foreach ($this->collFeatures as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAttributes) {
                foreach ($this->collAttributes as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collProducts = null;
        $this->collFeatureTemplates = null;
        $this->collAttributeTemplates = null;
        $this->collTemplateI18ns = null;
        $this->collFeatures = null;
        $this->collAttributes = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(TemplateTableMap::DEFAULT_STRING_FORMAT);
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildTemplate The current object (for fluent API support)
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
     * @return ChildTemplateI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collTemplateI18ns) {
                foreach ($this->collTemplateI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildTemplateI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildTemplateI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addTemplateI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildTemplate The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildTemplateI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collTemplateI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collTemplateI18ns[$key]);
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
     * @return ChildTemplateI18n */
    public function getCurrentTranslation(ConnectionInterface $con = null)
    {
        return $this->getTranslation($this->getLocale(), $con);
    }


        /**
         * Get the [name] column value.
         *
         * @return   string
         */
        public function getName()
        {
        return $this->getCurrentTranslation()->getName();
    }


        /**
         * Set the value of [name] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\TemplateI18n The current object (for fluent API support)
         */
        public function setName($v)
        {    $this->getCurrentTranslation()->setName($v);

        return $this;
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildTemplate The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[TemplateTableMap::UPDATED_AT] = true;

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
