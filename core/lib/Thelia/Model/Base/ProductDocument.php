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
use Thelia\Model\Product as ChildProduct;
use Thelia\Model\ProductDocument as ChildProductDocument;
use Thelia\Model\ProductDocumentI18n as ChildProductDocumentI18n;
use Thelia\Model\ProductDocumentI18nQuery as ChildProductDocumentI18nQuery;
use Thelia\Model\ProductDocumentQuery as ChildProductDocumentQuery;
use Thelia\Model\ProductQuery as ChildProductQuery;
use Thelia\Model\ProductSaleElements as ChildProductSaleElements;
use Thelia\Model\ProductSaleElementsProductDocument as ChildProductSaleElementsProductDocument;
use Thelia\Model\ProductSaleElementsProductDocumentQuery as ChildProductSaleElementsProductDocumentQuery;
use Thelia\Model\ProductSaleElementsQuery as ChildProductSaleElementsQuery;
use Thelia\Model\Map\ProductDocumentTableMap;

abstract class ProductDocument implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\ProductDocumentTableMap';


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
     * The value for the product_id field.
     * @var        int
     */
    protected $product_id;

    /**
     * The value for the file field.
     * @var        string
     */
    protected $file;

    /**
     * The value for the visible field.
     * Note: this column has a database default value of: 1
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
     * @var        Product
     */
    protected $aProduct;

    /**
     * @var        ObjectCollection|ChildProductSaleElementsProductDocument[] Collection to store aggregation of ChildProductSaleElementsProductDocument objects.
     */
    protected $collProductSaleElementsProductDocuments;
    protected $collProductSaleElementsProductDocumentsPartial;

    /**
     * @var        ObjectCollection|ChildProductDocumentI18n[] Collection to store aggregation of ChildProductDocumentI18n objects.
     */
    protected $collProductDocumentI18ns;
    protected $collProductDocumentI18nsPartial;

    /**
     * @var        ChildProductSaleElements[] Collection to store aggregation of ChildProductSaleElements objects.
     */
    protected $collProductSaleElementss;

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
     * @var        array[ChildProductDocumentI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productSaleElementssScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productSaleElementsProductDocumentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productDocumentI18nsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->visible = 1;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\ProductDocument object.
     * @see applyDefaults()
     */
    public function __construct()
    {
        $this->applyDefaultValues();
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
     * Compares this with another <code>ProductDocument</code> instance.  If
     * <code>obj</code> is an instance of <code>ProductDocument</code>, delegates to
     * <code>equals(ProductDocument)</code>.  Otherwise, returns <code>false</code>.
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
     * @return ProductDocument The current object, for fluid interface
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
     * @return ProductDocument The current object, for fluid interface
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
     * Get the [product_id] column value.
     *
     * @return   int
     */
    public function getProductId()
    {

        return $this->product_id;
    }

    /**
     * Get the [file] column value.
     *
     * @return   string
     */
    public function getFile()
    {

        return $this->file;
    }

    /**
     * Get the [visible] column value.
     *
     * @return   int
     */
    public function getVisible()
    {

        return $this->visible;
    }

    /**
     * Get the [position] column value.
     *
     * @return   int
     */
    public function getPosition()
    {

        return $this->position;
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
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[ProductDocumentTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [product_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function setProductId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->product_id !== $v) {
            $this->product_id = $v;
            $this->modifiedColumns[ProductDocumentTableMap::PRODUCT_ID] = true;
        }

        if ($this->aProduct !== null && $this->aProduct->getId() !== $v) {
            $this->aProduct = null;
        }


        return $this;
    } // setProductId()

    /**
     * Set the value of [file] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function setFile($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->file !== $v) {
            $this->file = $v;
            $this->modifiedColumns[ProductDocumentTableMap::FILE] = true;
        }


        return $this;
    } // setFile()

    /**
     * Set the value of [visible] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[ProductDocumentTableMap::VISIBLE] = true;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[ProductDocumentTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[ProductDocumentTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[ProductDocumentTableMap::UPDATED_AT] = true;
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
            if ($this->visible !== 1) {
                return false;
            }

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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : ProductDocumentTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : ProductDocumentTableMap::translateFieldName('ProductId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->product_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : ProductDocumentTableMap::translateFieldName('File', TableMap::TYPE_PHPNAME, $indexType)];
            $this->file = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : ProductDocumentTableMap::translateFieldName('Visible', TableMap::TYPE_PHPNAME, $indexType)];
            $this->visible = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : ProductDocumentTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : ProductDocumentTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : ProductDocumentTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 7; // 7 = ProductDocumentTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\ProductDocument object", 0, $e);
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
        if ($this->aProduct !== null && $this->product_id !== $this->aProduct->getId()) {
            $this->aProduct = null;
        }
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
            $con = Propel::getServiceContainer()->getReadConnection(ProductDocumentTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildProductDocumentQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aProduct = null;
            $this->collProductSaleElementsProductDocuments = null;

            $this->collProductDocumentI18ns = null;

            $this->collProductSaleElementss = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see ProductDocument::setDeleted()
     * @see ProductDocument::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProductDocumentTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildProductDocumentQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProductDocumentTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(ProductDocumentTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(ProductDocumentTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(ProductDocumentTableMap::UPDATED_AT)) {
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
                ProductDocumentTableMap::addInstanceToPool($this);
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

            // We call the save method on the following object(s) if they
            // were passed to this object by their corresponding set
            // method.  This object relates to these object(s) by a
            // foreign key reference.

            if ($this->aProduct !== null) {
                if ($this->aProduct->isModified() || $this->aProduct->isNew()) {
                    $affectedRows += $this->aProduct->save($con);
                }
                $this->setProduct($this->aProduct);
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

            if ($this->productSaleElementssScheduledForDeletion !== null) {
                if (!$this->productSaleElementssScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->productSaleElementssScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    ProductSaleElementsProductDocumentQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->productSaleElementssScheduledForDeletion = null;
                }

                foreach ($this->getProductSaleElementss() as $productSaleElements) {
                    if ($productSaleElements->isModified()) {
                        $productSaleElements->save($con);
                    }
                }
            } elseif ($this->collProductSaleElementss) {
                foreach ($this->collProductSaleElementss as $productSaleElements) {
                    if ($productSaleElements->isModified()) {
                        $productSaleElements->save($con);
                    }
                }
            }

            if ($this->productSaleElementsProductDocumentsScheduledForDeletion !== null) {
                if (!$this->productSaleElementsProductDocumentsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductSaleElementsProductDocumentQuery::create()
                        ->filterByPrimaryKeys($this->productSaleElementsProductDocumentsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productSaleElementsProductDocumentsScheduledForDeletion = null;
                }
            }

                if ($this->collProductSaleElementsProductDocuments !== null) {
            foreach ($this->collProductSaleElementsProductDocuments as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productDocumentI18nsScheduledForDeletion !== null) {
                if (!$this->productDocumentI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductDocumentI18nQuery::create()
                        ->filterByPrimaryKeys($this->productDocumentI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productDocumentI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collProductDocumentI18ns !== null) {
            foreach ($this->collProductDocumentI18ns as $referrerFK) {
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

        $this->modifiedColumns[ProductDocumentTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . ProductDocumentTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(ProductDocumentTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(ProductDocumentTableMap::PRODUCT_ID)) {
            $modifiedColumns[':p' . $index++]  = '`PRODUCT_ID`';
        }
        if ($this->isColumnModified(ProductDocumentTableMap::FILE)) {
            $modifiedColumns[':p' . $index++]  = '`FILE`';
        }
        if ($this->isColumnModified(ProductDocumentTableMap::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`VISIBLE`';
        }
        if ($this->isColumnModified(ProductDocumentTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(ProductDocumentTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(ProductDocumentTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `product_document` (%s) VALUES (%s)',
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
                    case '`PRODUCT_ID`':
                        $stmt->bindValue($identifier, $this->product_id, PDO::PARAM_INT);
                        break;
                    case '`FILE`':
                        $stmt->bindValue($identifier, $this->file, PDO::PARAM_STR);
                        break;
                    case '`VISIBLE`':
                        $stmt->bindValue($identifier, $this->visible, PDO::PARAM_INT);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
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
        $pos = ProductDocumentTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getProductId();
                break;
            case 2:
                return $this->getFile();
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
        if (isset($alreadyDumpedObjects['ProductDocument'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['ProductDocument'][$this->getPrimaryKey()] = true;
        $keys = ProductDocumentTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getProductId(),
            $keys[2] => $this->getFile(),
            $keys[3] => $this->getVisible(),
            $keys[4] => $this->getPosition(),
            $keys[5] => $this->getCreatedAt(),
            $keys[6] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aProduct) {
                $result['Product'] = $this->aProduct->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collProductSaleElementsProductDocuments) {
                $result['ProductSaleElementsProductDocuments'] = $this->collProductSaleElementsProductDocuments->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductDocumentI18ns) {
                $result['ProductDocumentI18ns'] = $this->collProductDocumentI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = ProductDocumentTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setProductId($value);
                break;
            case 2:
                $this->setFile($value);
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
        $keys = ProductDocumentTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setProductId($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setFile($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setVisible($arr[$keys[3]]);
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
        $criteria = new Criteria(ProductDocumentTableMap::DATABASE_NAME);

        if ($this->isColumnModified(ProductDocumentTableMap::ID)) $criteria->add(ProductDocumentTableMap::ID, $this->id);
        if ($this->isColumnModified(ProductDocumentTableMap::PRODUCT_ID)) $criteria->add(ProductDocumentTableMap::PRODUCT_ID, $this->product_id);
        if ($this->isColumnModified(ProductDocumentTableMap::FILE)) $criteria->add(ProductDocumentTableMap::FILE, $this->file);
        if ($this->isColumnModified(ProductDocumentTableMap::VISIBLE)) $criteria->add(ProductDocumentTableMap::VISIBLE, $this->visible);
        if ($this->isColumnModified(ProductDocumentTableMap::POSITION)) $criteria->add(ProductDocumentTableMap::POSITION, $this->position);
        if ($this->isColumnModified(ProductDocumentTableMap::CREATED_AT)) $criteria->add(ProductDocumentTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(ProductDocumentTableMap::UPDATED_AT)) $criteria->add(ProductDocumentTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(ProductDocumentTableMap::DATABASE_NAME);
        $criteria->add(ProductDocumentTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\ProductDocument (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setProductId($this->getProductId());
        $copyObj->setFile($this->getFile());
        $copyObj->setVisible($this->getVisible());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getProductSaleElementsProductDocuments() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductSaleElementsProductDocument($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductDocumentI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductDocumentI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\ProductDocument Clone of current object.
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
     * Declares an association between this object and a ChildProduct object.
     *
     * @param                  ChildProduct $v
     * @return                 \Thelia\Model\ProductDocument The current object (for fluent API support)
     * @throws PropelException
     */
    public function setProduct(ChildProduct $v = null)
    {
        if ($v === null) {
            $this->setProductId(NULL);
        } else {
            $this->setProductId($v->getId());
        }

        $this->aProduct = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildProduct object, it will not be re-added.
        if ($v !== null) {
            $v->addProductDocument($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildProduct object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildProduct The associated ChildProduct object.
     * @throws PropelException
     */
    public function getProduct(ConnectionInterface $con = null)
    {
        if ($this->aProduct === null && ($this->product_id !== null)) {
            $this->aProduct = ChildProductQuery::create()->findPk($this->product_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aProduct->addProductDocuments($this);
             */
        }

        return $this->aProduct;
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
        if ('ProductSaleElementsProductDocument' == $relationName) {
            return $this->initProductSaleElementsProductDocuments();
        }
        if ('ProductDocumentI18n' == $relationName) {
            return $this->initProductDocumentI18ns();
        }
    }

    /**
     * Clears out the collProductSaleElementsProductDocuments collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductSaleElementsProductDocuments()
     */
    public function clearProductSaleElementsProductDocuments()
    {
        $this->collProductSaleElementsProductDocuments = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductSaleElementsProductDocuments collection loaded partially.
     */
    public function resetPartialProductSaleElementsProductDocuments($v = true)
    {
        $this->collProductSaleElementsProductDocumentsPartial = $v;
    }

    /**
     * Initializes the collProductSaleElementsProductDocuments collection.
     *
     * By default this just sets the collProductSaleElementsProductDocuments collection to an empty array (like clearcollProductSaleElementsProductDocuments());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductSaleElementsProductDocuments($overrideExisting = true)
    {
        if (null !== $this->collProductSaleElementsProductDocuments && !$overrideExisting) {
            return;
        }
        $this->collProductSaleElementsProductDocuments = new ObjectCollection();
        $this->collProductSaleElementsProductDocuments->setModel('\Thelia\Model\ProductSaleElementsProductDocument');
    }

    /**
     * Gets an array of ChildProductSaleElementsProductDocument objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProductDocument is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductSaleElementsProductDocument[] List of ChildProductSaleElementsProductDocument objects
     * @throws PropelException
     */
    public function getProductSaleElementsProductDocuments($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductSaleElementsProductDocumentsPartial && !$this->isNew();
        if (null === $this->collProductSaleElementsProductDocuments || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductSaleElementsProductDocuments) {
                // return empty collection
                $this->initProductSaleElementsProductDocuments();
            } else {
                $collProductSaleElementsProductDocuments = ChildProductSaleElementsProductDocumentQuery::create(null, $criteria)
                    ->filterByProductDocument($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductSaleElementsProductDocumentsPartial && count($collProductSaleElementsProductDocuments)) {
                        $this->initProductSaleElementsProductDocuments(false);

                        foreach ($collProductSaleElementsProductDocuments as $obj) {
                            if (false == $this->collProductSaleElementsProductDocuments->contains($obj)) {
                                $this->collProductSaleElementsProductDocuments->append($obj);
                            }
                        }

                        $this->collProductSaleElementsProductDocumentsPartial = true;
                    }

                    reset($collProductSaleElementsProductDocuments);

                    return $collProductSaleElementsProductDocuments;
                }

                if ($partial && $this->collProductSaleElementsProductDocuments) {
                    foreach ($this->collProductSaleElementsProductDocuments as $obj) {
                        if ($obj->isNew()) {
                            $collProductSaleElementsProductDocuments[] = $obj;
                        }
                    }
                }

                $this->collProductSaleElementsProductDocuments = $collProductSaleElementsProductDocuments;
                $this->collProductSaleElementsProductDocumentsPartial = false;
            }
        }

        return $this->collProductSaleElementsProductDocuments;
    }

    /**
     * Sets a collection of ProductSaleElementsProductDocument objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productSaleElementsProductDocuments A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProductDocument The current object (for fluent API support)
     */
    public function setProductSaleElementsProductDocuments(Collection $productSaleElementsProductDocuments, ConnectionInterface $con = null)
    {
        $productSaleElementsProductDocumentsToDelete = $this->getProductSaleElementsProductDocuments(new Criteria(), $con)->diff($productSaleElementsProductDocuments);


        $this->productSaleElementsProductDocumentsScheduledForDeletion = $productSaleElementsProductDocumentsToDelete;

        foreach ($productSaleElementsProductDocumentsToDelete as $productSaleElementsProductDocumentRemoved) {
            $productSaleElementsProductDocumentRemoved->setProductDocument(null);
        }

        $this->collProductSaleElementsProductDocuments = null;
        foreach ($productSaleElementsProductDocuments as $productSaleElementsProductDocument) {
            $this->addProductSaleElementsProductDocument($productSaleElementsProductDocument);
        }

        $this->collProductSaleElementsProductDocuments = $productSaleElementsProductDocuments;
        $this->collProductSaleElementsProductDocumentsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductSaleElementsProductDocument objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductSaleElementsProductDocument objects.
     * @throws PropelException
     */
    public function countProductSaleElementsProductDocuments(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductSaleElementsProductDocumentsPartial && !$this->isNew();
        if (null === $this->collProductSaleElementsProductDocuments || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductSaleElementsProductDocuments) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductSaleElementsProductDocuments());
            }

            $query = ChildProductSaleElementsProductDocumentQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProductDocument($this)
                ->count($con);
        }

        return count($this->collProductSaleElementsProductDocuments);
    }

    /**
     * Method called to associate a ChildProductSaleElementsProductDocument object to this object
     * through the ChildProductSaleElementsProductDocument foreign key attribute.
     *
     * @param    ChildProductSaleElementsProductDocument $l ChildProductSaleElementsProductDocument
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function addProductSaleElementsProductDocument(ChildProductSaleElementsProductDocument $l)
    {
        if ($this->collProductSaleElementsProductDocuments === null) {
            $this->initProductSaleElementsProductDocuments();
            $this->collProductSaleElementsProductDocumentsPartial = true;
        }

        if (!in_array($l, $this->collProductSaleElementsProductDocuments->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductSaleElementsProductDocument($l);
        }

        return $this;
    }

    /**
     * @param ProductSaleElementsProductDocument $productSaleElementsProductDocument The productSaleElementsProductDocument object to add.
     */
    protected function doAddProductSaleElementsProductDocument($productSaleElementsProductDocument)
    {
        $this->collProductSaleElementsProductDocuments[]= $productSaleElementsProductDocument;
        $productSaleElementsProductDocument->setProductDocument($this);
    }

    /**
     * @param  ProductSaleElementsProductDocument $productSaleElementsProductDocument The productSaleElementsProductDocument object to remove.
     * @return ChildProductDocument The current object (for fluent API support)
     */
    public function removeProductSaleElementsProductDocument($productSaleElementsProductDocument)
    {
        if ($this->getProductSaleElementsProductDocuments()->contains($productSaleElementsProductDocument)) {
            $this->collProductSaleElementsProductDocuments->remove($this->collProductSaleElementsProductDocuments->search($productSaleElementsProductDocument));
            if (null === $this->productSaleElementsProductDocumentsScheduledForDeletion) {
                $this->productSaleElementsProductDocumentsScheduledForDeletion = clone $this->collProductSaleElementsProductDocuments;
                $this->productSaleElementsProductDocumentsScheduledForDeletion->clear();
            }
            $this->productSaleElementsProductDocumentsScheduledForDeletion[]= clone $productSaleElementsProductDocument;
            $productSaleElementsProductDocument->setProductDocument(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this ProductDocument is new, it will return
     * an empty collection; or if this ProductDocument has previously
     * been saved, it will retrieve related ProductSaleElementsProductDocuments from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in ProductDocument.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProductSaleElementsProductDocument[] List of ChildProductSaleElementsProductDocument objects
     */
    public function getProductSaleElementsProductDocumentsJoinProductSaleElements($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProductSaleElementsProductDocumentQuery::create(null, $criteria);
        $query->joinWith('ProductSaleElements', $joinBehavior);

        return $this->getProductSaleElementsProductDocuments($query, $con);
    }

    /**
     * Clears out the collProductDocumentI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductDocumentI18ns()
     */
    public function clearProductDocumentI18ns()
    {
        $this->collProductDocumentI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductDocumentI18ns collection loaded partially.
     */
    public function resetPartialProductDocumentI18ns($v = true)
    {
        $this->collProductDocumentI18nsPartial = $v;
    }

    /**
     * Initializes the collProductDocumentI18ns collection.
     *
     * By default this just sets the collProductDocumentI18ns collection to an empty array (like clearcollProductDocumentI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductDocumentI18ns($overrideExisting = true)
    {
        if (null !== $this->collProductDocumentI18ns && !$overrideExisting) {
            return;
        }
        $this->collProductDocumentI18ns = new ObjectCollection();
        $this->collProductDocumentI18ns->setModel('\Thelia\Model\ProductDocumentI18n');
    }

    /**
     * Gets an array of ChildProductDocumentI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProductDocument is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductDocumentI18n[] List of ChildProductDocumentI18n objects
     * @throws PropelException
     */
    public function getProductDocumentI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductDocumentI18nsPartial && !$this->isNew();
        if (null === $this->collProductDocumentI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductDocumentI18ns) {
                // return empty collection
                $this->initProductDocumentI18ns();
            } else {
                $collProductDocumentI18ns = ChildProductDocumentI18nQuery::create(null, $criteria)
                    ->filterByProductDocument($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductDocumentI18nsPartial && count($collProductDocumentI18ns)) {
                        $this->initProductDocumentI18ns(false);

                        foreach ($collProductDocumentI18ns as $obj) {
                            if (false == $this->collProductDocumentI18ns->contains($obj)) {
                                $this->collProductDocumentI18ns->append($obj);
                            }
                        }

                        $this->collProductDocumentI18nsPartial = true;
                    }

                    reset($collProductDocumentI18ns);

                    return $collProductDocumentI18ns;
                }

                if ($partial && $this->collProductDocumentI18ns) {
                    foreach ($this->collProductDocumentI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collProductDocumentI18ns[] = $obj;
                        }
                    }
                }

                $this->collProductDocumentI18ns = $collProductDocumentI18ns;
                $this->collProductDocumentI18nsPartial = false;
            }
        }

        return $this->collProductDocumentI18ns;
    }

    /**
     * Sets a collection of ProductDocumentI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productDocumentI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProductDocument The current object (for fluent API support)
     */
    public function setProductDocumentI18ns(Collection $productDocumentI18ns, ConnectionInterface $con = null)
    {
        $productDocumentI18nsToDelete = $this->getProductDocumentI18ns(new Criteria(), $con)->diff($productDocumentI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->productDocumentI18nsScheduledForDeletion = clone $productDocumentI18nsToDelete;

        foreach ($productDocumentI18nsToDelete as $productDocumentI18nRemoved) {
            $productDocumentI18nRemoved->setProductDocument(null);
        }

        $this->collProductDocumentI18ns = null;
        foreach ($productDocumentI18ns as $productDocumentI18n) {
            $this->addProductDocumentI18n($productDocumentI18n);
        }

        $this->collProductDocumentI18ns = $productDocumentI18ns;
        $this->collProductDocumentI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductDocumentI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductDocumentI18n objects.
     * @throws PropelException
     */
    public function countProductDocumentI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductDocumentI18nsPartial && !$this->isNew();
        if (null === $this->collProductDocumentI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductDocumentI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductDocumentI18ns());
            }

            $query = ChildProductDocumentI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProductDocument($this)
                ->count($con);
        }

        return count($this->collProductDocumentI18ns);
    }

    /**
     * Method called to associate a ChildProductDocumentI18n object to this object
     * through the ChildProductDocumentI18n foreign key attribute.
     *
     * @param    ChildProductDocumentI18n $l ChildProductDocumentI18n
     * @return   \Thelia\Model\ProductDocument The current object (for fluent API support)
     */
    public function addProductDocumentI18n(ChildProductDocumentI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collProductDocumentI18ns === null) {
            $this->initProductDocumentI18ns();
            $this->collProductDocumentI18nsPartial = true;
        }

        if (!in_array($l, $this->collProductDocumentI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductDocumentI18n($l);
        }

        return $this;
    }

    /**
     * @param ProductDocumentI18n $productDocumentI18n The productDocumentI18n object to add.
     */
    protected function doAddProductDocumentI18n($productDocumentI18n)
    {
        $this->collProductDocumentI18ns[]= $productDocumentI18n;
        $productDocumentI18n->setProductDocument($this);
    }

    /**
     * @param  ProductDocumentI18n $productDocumentI18n The productDocumentI18n object to remove.
     * @return ChildProductDocument The current object (for fluent API support)
     */
    public function removeProductDocumentI18n($productDocumentI18n)
    {
        if ($this->getProductDocumentI18ns()->contains($productDocumentI18n)) {
            $this->collProductDocumentI18ns->remove($this->collProductDocumentI18ns->search($productDocumentI18n));
            if (null === $this->productDocumentI18nsScheduledForDeletion) {
                $this->productDocumentI18nsScheduledForDeletion = clone $this->collProductDocumentI18ns;
                $this->productDocumentI18nsScheduledForDeletion->clear();
            }
            $this->productDocumentI18nsScheduledForDeletion[]= clone $productDocumentI18n;
            $productDocumentI18n->setProductDocument(null);
        }

        return $this;
    }

    /**
     * Clears out the collProductSaleElementss collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductSaleElementss()
     */
    public function clearProductSaleElementss()
    {
        $this->collProductSaleElementss = null; // important to set this to NULL since that means it is uninitialized
        $this->collProductSaleElementssPartial = null;
    }

    /**
     * Initializes the collProductSaleElementss collection.
     *
     * By default this just sets the collProductSaleElementss collection to an empty collection (like clearProductSaleElementss());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initProductSaleElementss()
    {
        $this->collProductSaleElementss = new ObjectCollection();
        $this->collProductSaleElementss->setModel('\Thelia\Model\ProductSaleElements');
    }

    /**
     * Gets a collection of ChildProductSaleElements objects related by a many-to-many relationship
     * to the current object by way of the product_sale_elements_product_document cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProductDocument is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildProductSaleElements[] List of ChildProductSaleElements objects
     */
    public function getProductSaleElementss($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collProductSaleElementss || null !== $criteria) {
            if ($this->isNew() && null === $this->collProductSaleElementss) {
                // return empty collection
                $this->initProductSaleElementss();
            } else {
                $collProductSaleElementss = ChildProductSaleElementsQuery::create(null, $criteria)
                    ->filterByProductDocument($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collProductSaleElementss;
                }
                $this->collProductSaleElementss = $collProductSaleElementss;
            }
        }

        return $this->collProductSaleElementss;
    }

    /**
     * Sets a collection of ProductSaleElements objects related by a many-to-many relationship
     * to the current object by way of the product_sale_elements_product_document cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $productSaleElementss A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildProductDocument The current object (for fluent API support)
     */
    public function setProductSaleElementss(Collection $productSaleElementss, ConnectionInterface $con = null)
    {
        $this->clearProductSaleElementss();
        $currentProductSaleElementss = $this->getProductSaleElementss();

        $this->productSaleElementssScheduledForDeletion = $currentProductSaleElementss->diff($productSaleElementss);

        foreach ($productSaleElementss as $productSaleElements) {
            if (!$currentProductSaleElementss->contains($productSaleElements)) {
                $this->doAddProductSaleElements($productSaleElements);
            }
        }

        $this->collProductSaleElementss = $productSaleElementss;

        return $this;
    }

    /**
     * Gets the number of ChildProductSaleElements objects related by a many-to-many relationship
     * to the current object by way of the product_sale_elements_product_document cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildProductSaleElements objects
     */
    public function countProductSaleElementss($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collProductSaleElementss || null !== $criteria) {
            if ($this->isNew() && null === $this->collProductSaleElementss) {
                return 0;
            } else {
                $query = ChildProductSaleElementsQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProductDocument($this)
                    ->count($con);
            }
        } else {
            return count($this->collProductSaleElementss);
        }
    }

    /**
     * Associate a ChildProductSaleElements object to this object
     * through the product_sale_elements_product_document cross reference table.
     *
     * @param  ChildProductSaleElements $productSaleElements The ChildProductSaleElementsProductDocument object to relate
     * @return ChildProductDocument The current object (for fluent API support)
     */
    public function addProductSaleElements(ChildProductSaleElements $productSaleElements)
    {
        if ($this->collProductSaleElementss === null) {
            $this->initProductSaleElementss();
        }

        if (!$this->collProductSaleElementss->contains($productSaleElements)) { // only add it if the **same** object is not already associated
            $this->doAddProductSaleElements($productSaleElements);
            $this->collProductSaleElementss[] = $productSaleElements;
        }

        return $this;
    }

    /**
     * @param    ProductSaleElements $productSaleElements The productSaleElements object to add.
     */
    protected function doAddProductSaleElements($productSaleElements)
    {
        $productSaleElementsProductDocument = new ChildProductSaleElementsProductDocument();
        $productSaleElementsProductDocument->setProductSaleElements($productSaleElements);
        $this->addProductSaleElementsProductDocument($productSaleElementsProductDocument);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$productSaleElements->getProductDocuments()->contains($this)) {
            $foreignCollection   = $productSaleElements->getProductDocuments();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildProductSaleElements object to this object
     * through the product_sale_elements_product_document cross reference table.
     *
     * @param ChildProductSaleElements $productSaleElements The ChildProductSaleElementsProductDocument object to relate
     * @return ChildProductDocument The current object (for fluent API support)
     */
    public function removeProductSaleElements(ChildProductSaleElements $productSaleElements)
    {
        if ($this->getProductSaleElementss()->contains($productSaleElements)) {
            $this->collProductSaleElementss->remove($this->collProductSaleElementss->search($productSaleElements));

            if (null === $this->productSaleElementssScheduledForDeletion) {
                $this->productSaleElementssScheduledForDeletion = clone $this->collProductSaleElementss;
                $this->productSaleElementssScheduledForDeletion->clear();
            }

            $this->productSaleElementssScheduledForDeletion[] = $productSaleElements;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->product_id = null;
        $this->file = null;
        $this->visible = null;
        $this->position = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->alreadyInSave = false;
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
     * when using Propel in certain daemon or large-volume/high-memory operations.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
            if ($this->collProductSaleElementsProductDocuments) {
                foreach ($this->collProductSaleElementsProductDocuments as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductDocumentI18ns) {
                foreach ($this->collProductDocumentI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductSaleElementss) {
                foreach ($this->collProductSaleElementss as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collProductSaleElementsProductDocuments = null;
        $this->collProductDocumentI18ns = null;
        $this->collProductSaleElementss = null;
        $this->aProduct = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(ProductDocumentTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildProductDocument The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[ProductDocumentTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildProductDocument The current object (for fluent API support)
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
     * @return ChildProductDocumentI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collProductDocumentI18ns) {
                foreach ($this->collProductDocumentI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildProductDocumentI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildProductDocumentI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addProductDocumentI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildProductDocument The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildProductDocumentI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collProductDocumentI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collProductDocumentI18ns[$key]);
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
     * @return ChildProductDocumentI18n */
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
         * @return   \Thelia\Model\ProductDocumentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProductDocumentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProductDocumentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProductDocumentI18n The current object (for fluent API support)
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
