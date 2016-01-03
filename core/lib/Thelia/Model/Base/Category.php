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
use Thelia\Model\Category as ChildCategory;
use Thelia\Model\CategoryAssociatedContent as ChildCategoryAssociatedContent;
use Thelia\Model\CategoryAssociatedContentQuery as ChildCategoryAssociatedContentQuery;
use Thelia\Model\CategoryDocument as ChildCategoryDocument;
use Thelia\Model\CategoryDocumentQuery as ChildCategoryDocumentQuery;
use Thelia\Model\CategoryI18n as ChildCategoryI18n;
use Thelia\Model\CategoryI18nQuery as ChildCategoryI18nQuery;
use Thelia\Model\CategoryImage as ChildCategoryImage;
use Thelia\Model\CategoryImageQuery as ChildCategoryImageQuery;
use Thelia\Model\CategoryQuery as ChildCategoryQuery;
use Thelia\Model\CategoryVersion as ChildCategoryVersion;
use Thelia\Model\CategoryVersionQuery as ChildCategoryVersionQuery;
use Thelia\Model\Product as ChildProduct;
use Thelia\Model\ProductCategory as ChildProductCategory;
use Thelia\Model\ProductCategoryQuery as ChildProductCategoryQuery;
use Thelia\Model\ProductQuery as ChildProductQuery;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Map\CategoryVersionTableMap;

abstract class Category implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\CategoryTableMap';


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
     * The value for the parent field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $parent;

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
     * The value for the default_template_id field.
     * @var        int
     */
    protected $default_template_id;

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
     * @var        ObjectCollection|ChildProductCategory[] Collection to store aggregation of ChildProductCategory objects.
     */
    protected $collProductCategories;
    protected $collProductCategoriesPartial;

    /**
     * @var        ObjectCollection|ChildCategoryImage[] Collection to store aggregation of ChildCategoryImage objects.
     */
    protected $collCategoryImages;
    protected $collCategoryImagesPartial;

    /**
     * @var        ObjectCollection|ChildCategoryDocument[] Collection to store aggregation of ChildCategoryDocument objects.
     */
    protected $collCategoryDocuments;
    protected $collCategoryDocumentsPartial;

    /**
     * @var        ObjectCollection|ChildCategoryAssociatedContent[] Collection to store aggregation of ChildCategoryAssociatedContent objects.
     */
    protected $collCategoryAssociatedContents;
    protected $collCategoryAssociatedContentsPartial;

    /**
     * @var        ObjectCollection|ChildCategoryI18n[] Collection to store aggregation of ChildCategoryI18n objects.
     */
    protected $collCategoryI18ns;
    protected $collCategoryI18nsPartial;

    /**
     * @var        ObjectCollection|ChildCategoryVersion[] Collection to store aggregation of ChildCategoryVersion objects.
     */
    protected $collCategoryVersions;
    protected $collCategoryVersionsPartial;

    /**
     * @var        ChildProduct[] Collection to store aggregation of ChildProduct objects.
     */
    protected $collProducts;

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
     * @var        array[ChildCategoryI18n]
     */
    protected $currentTranslations;

    // versionable behavior


    /**
     * @var bool
     */
    protected $enforceVersion = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productCategoriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $categoryImagesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $categoryDocumentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $categoryAssociatedContentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $categoryI18nsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $categoryVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->parent = 0;
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Category object.
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
     * Compares this with another <code>Category</code> instance.  If
     * <code>obj</code> is an instance of <code>Category</code>, delegates to
     * <code>equals(Category)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Category The current object, for fluid interface
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
     * @return Category The current object, for fluid interface
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
     * Get the [parent] column value.
     *
     * @return   int
     */
    public function getParent()
    {

        return $this->parent;
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
     * Get the [default_template_id] column value.
     *
     * @return   int
     */
    public function getDefaultTemplateId()
    {

        return $this->default_template_id;
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
     * Get the [version] column value.
     *
     * @return   int
     */
    public function getVersion()
    {

        return $this->version;
    }

    /**
     * Get the [optionally formatted] temporal [version_created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getVersionCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->version_created_at;
        } else {
            return $this->version_created_at instanceof \DateTime ? $this->version_created_at->format($format) : null;
        }
    }

    /**
     * Get the [version_created_by] column value.
     *
     * @return   string
     */
    public function getVersionCreatedBy()
    {

        return $this->version_created_by;
    }

    /**
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[CategoryTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [parent] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setParent($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->parent !== $v) {
            $this->parent = $v;
            $this->modifiedColumns[CategoryTableMap::PARENT] = true;
        }


        return $this;
    } // setParent()

    /**
     * Set the value of [visible] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[CategoryTableMap::VISIBLE] = true;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[CategoryTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Set the value of [default_template_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setDefaultTemplateId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->default_template_id !== $v) {
            $this->default_template_id = $v;
            $this->modifiedColumns[CategoryTableMap::DEFAULT_TEMPLATE_ID] = true;
        }


        return $this;
    } // setDefaultTemplateId()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[CategoryTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[CategoryTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[CategoryTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[CategoryTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[CategoryTableMap::VERSION_CREATED_BY] = true;
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
            if ($this->parent !== 0) {
                return false;
            }

            if ($this->version !== 0) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : CategoryTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : CategoryTableMap::translateFieldName('Parent', TableMap::TYPE_PHPNAME, $indexType)];
            $this->parent = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : CategoryTableMap::translateFieldName('Visible', TableMap::TYPE_PHPNAME, $indexType)];
            $this->visible = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : CategoryTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : CategoryTableMap::translateFieldName('DefaultTemplateId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->default_template_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : CategoryTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : CategoryTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : CategoryTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : CategoryTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : CategoryTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 10; // 10 = CategoryTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Category object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(CategoryTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildCategoryQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collProductCategories = null;

            $this->collCategoryImages = null;

            $this->collCategoryDocuments = null;

            $this->collCategoryAssociatedContents = null;

            $this->collCategoryI18ns = null;

            $this->collCategoryVersions = null;

            $this->collProducts = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Category::setDeleted()
     * @see Category::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CategoryTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildCategoryQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(CategoryTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(CategoryTableMap::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(CategoryTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(CategoryTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(CategoryTableMap::UPDATED_AT)) {
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
                CategoryTableMap::addInstanceToPool($this);
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

            if ($this->productsScheduledForDeletion !== null) {
                if (!$this->productsScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->productsScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    ProductCategoryQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->productsScheduledForDeletion = null;
                }

                foreach ($this->getProducts() as $product) {
                    if ($product->isModified()) {
                        $product->save($con);
                    }
                }
            } elseif ($this->collProducts) {
                foreach ($this->collProducts as $product) {
                    if ($product->isModified()) {
                        $product->save($con);
                    }
                }
            }

            if ($this->productCategoriesScheduledForDeletion !== null) {
                if (!$this->productCategoriesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductCategoryQuery::create()
                        ->filterByPrimaryKeys($this->productCategoriesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productCategoriesScheduledForDeletion = null;
                }
            }

                if ($this->collProductCategories !== null) {
            foreach ($this->collProductCategories as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->categoryImagesScheduledForDeletion !== null) {
                if (!$this->categoryImagesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CategoryImageQuery::create()
                        ->filterByPrimaryKeys($this->categoryImagesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->categoryImagesScheduledForDeletion = null;
                }
            }

                if ($this->collCategoryImages !== null) {
            foreach ($this->collCategoryImages as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->categoryDocumentsScheduledForDeletion !== null) {
                if (!$this->categoryDocumentsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CategoryDocumentQuery::create()
                        ->filterByPrimaryKeys($this->categoryDocumentsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->categoryDocumentsScheduledForDeletion = null;
                }
            }

                if ($this->collCategoryDocuments !== null) {
            foreach ($this->collCategoryDocuments as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->categoryAssociatedContentsScheduledForDeletion !== null) {
                if (!$this->categoryAssociatedContentsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CategoryAssociatedContentQuery::create()
                        ->filterByPrimaryKeys($this->categoryAssociatedContentsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->categoryAssociatedContentsScheduledForDeletion = null;
                }
            }

                if ($this->collCategoryAssociatedContents !== null) {
            foreach ($this->collCategoryAssociatedContents as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->categoryI18nsScheduledForDeletion !== null) {
                if (!$this->categoryI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CategoryI18nQuery::create()
                        ->filterByPrimaryKeys($this->categoryI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->categoryI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collCategoryI18ns !== null) {
            foreach ($this->collCategoryI18ns as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->categoryVersionsScheduledForDeletion !== null) {
                if (!$this->categoryVersionsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CategoryVersionQuery::create()
                        ->filterByPrimaryKeys($this->categoryVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->categoryVersionsScheduledForDeletion = null;
                }
            }

                if ($this->collCategoryVersions !== null) {
            foreach ($this->collCategoryVersions as $referrerFK) {
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

        $this->modifiedColumns[CategoryTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . CategoryTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CategoryTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CategoryTableMap::PARENT)) {
            $modifiedColumns[':p' . $index++]  = '`PARENT`';
        }
        if ($this->isColumnModified(CategoryTableMap::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`VISIBLE`';
        }
        if ($this->isColumnModified(CategoryTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(CategoryTableMap::DEFAULT_TEMPLATE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`DEFAULT_TEMPLATE_ID`';
        }
        if ($this->isColumnModified(CategoryTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CategoryTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(CategoryTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(CategoryTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(CategoryTableMap::VERSION_CREATED_BY)) {
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
                    case '`VISIBLE`':
                        $stmt->bindValue($identifier, $this->visible, PDO::PARAM_INT);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
                        break;
                    case '`DEFAULT_TEMPLATE_ID`':
                        $stmt->bindValue($identifier, $this->default_template_id, PDO::PARAM_INT);
                        break;
                    case '`CREATED_AT`':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`UPDATED_AT`':
                        $stmt->bindValue($identifier, $this->updated_at ? $this->updated_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`VERSION`':
                        $stmt->bindValue($identifier, $this->version, PDO::PARAM_INT);
                        break;
                    case '`VERSION_CREATED_AT`':
                        $stmt->bindValue($identifier, $this->version_created_at ? $this->version_created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`VERSION_CREATED_BY`':
                        $stmt->bindValue($identifier, $this->version_created_by, PDO::PARAM_STR);
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
        $pos = CategoryTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getParent();
                break;
            case 2:
                return $this->getVisible();
                break;
            case 3:
                return $this->getPosition();
                break;
            case 4:
                return $this->getDefaultTemplateId();
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
        if (isset($alreadyDumpedObjects['Category'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Category'][$this->getPrimaryKey()] = true;
        $keys = CategoryTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getParent(),
            $keys[2] => $this->getVisible(),
            $keys[3] => $this->getPosition(),
            $keys[4] => $this->getDefaultTemplateId(),
            $keys[5] => $this->getCreatedAt(),
            $keys[6] => $this->getUpdatedAt(),
            $keys[7] => $this->getVersion(),
            $keys[8] => $this->getVersionCreatedAt(),
            $keys[9] => $this->getVersionCreatedBy(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collProductCategories) {
                $result['ProductCategories'] = $this->collProductCategories->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCategoryImages) {
                $result['CategoryImages'] = $this->collCategoryImages->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCategoryDocuments) {
                $result['CategoryDocuments'] = $this->collCategoryDocuments->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCategoryAssociatedContents) {
                $result['CategoryAssociatedContents'] = $this->collCategoryAssociatedContents->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = CategoryTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setParent($value);
                break;
            case 2:
                $this->setVisible($value);
                break;
            case 3:
                $this->setPosition($value);
                break;
            case 4:
                $this->setDefaultTemplateId($value);
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
        $keys = CategoryTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setParent($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setVisible($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setPosition($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setDefaultTemplateId($arr[$keys[4]]);
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
        $criteria = new Criteria(CategoryTableMap::DATABASE_NAME);

        if ($this->isColumnModified(CategoryTableMap::ID)) $criteria->add(CategoryTableMap::ID, $this->id);
        if ($this->isColumnModified(CategoryTableMap::PARENT)) $criteria->add(CategoryTableMap::PARENT, $this->parent);
        if ($this->isColumnModified(CategoryTableMap::VISIBLE)) $criteria->add(CategoryTableMap::VISIBLE, $this->visible);
        if ($this->isColumnModified(CategoryTableMap::POSITION)) $criteria->add(CategoryTableMap::POSITION, $this->position);
        if ($this->isColumnModified(CategoryTableMap::DEFAULT_TEMPLATE_ID)) $criteria->add(CategoryTableMap::DEFAULT_TEMPLATE_ID, $this->default_template_id);
        if ($this->isColumnModified(CategoryTableMap::CREATED_AT)) $criteria->add(CategoryTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CategoryTableMap::UPDATED_AT)) $criteria->add(CategoryTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(CategoryTableMap::VERSION)) $criteria->add(CategoryTableMap::VERSION, $this->version);
        if ($this->isColumnModified(CategoryTableMap::VERSION_CREATED_AT)) $criteria->add(CategoryTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(CategoryTableMap::VERSION_CREATED_BY)) $criteria->add(CategoryTableMap::VERSION_CREATED_BY, $this->version_created_by);

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
        $criteria = new Criteria(CategoryTableMap::DATABASE_NAME);
        $criteria->add(CategoryTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Category (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setParent($this->getParent());
        $copyObj->setVisible($this->getVisible());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setDefaultTemplateId($this->getDefaultTemplateId());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getProductCategories() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductCategory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCategoryImages() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCategoryImage($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCategoryDocuments() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCategoryDocument($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCategoryAssociatedContents() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCategoryAssociatedContent($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Category Clone of current object.
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
        if ('ProductCategory' == $relationName) {
            return $this->initProductCategories();
        }
        if ('CategoryImage' == $relationName) {
            return $this->initCategoryImages();
        }
        if ('CategoryDocument' == $relationName) {
            return $this->initCategoryDocuments();
        }
        if ('CategoryAssociatedContent' == $relationName) {
            return $this->initCategoryAssociatedContents();
        }
        if ('CategoryI18n' == $relationName) {
            return $this->initCategoryI18ns();
        }
        if ('CategoryVersion' == $relationName) {
            return $this->initCategoryVersions();
        }
    }

    /**
     * Clears out the collProductCategories collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductCategories()
     */
    public function clearProductCategories()
    {
        $this->collProductCategories = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductCategories collection loaded partially.
     */
    public function resetPartialProductCategories($v = true)
    {
        $this->collProductCategoriesPartial = $v;
    }

    /**
     * Initializes the collProductCategories collection.
     *
     * By default this just sets the collProductCategories collection to an empty array (like clearcollProductCategories());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductCategories($overrideExisting = true)
    {
        if (null !== $this->collProductCategories && !$overrideExisting) {
            return;
        }
        $this->collProductCategories = new ObjectCollection();
        $this->collProductCategories->setModel('\Thelia\Model\ProductCategory');
    }

    /**
     * Gets an array of ChildProductCategory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCategory is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductCategory[] List of ChildProductCategory objects
     * @throws PropelException
     */
    public function getProductCategories($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductCategoriesPartial && !$this->isNew();
        if (null === $this->collProductCategories || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductCategories) {
                // return empty collection
                $this->initProductCategories();
            } else {
                $collProductCategories = ChildProductCategoryQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductCategoriesPartial && count($collProductCategories)) {
                        $this->initProductCategories(false);

                        foreach ($collProductCategories as $obj) {
                            if (false == $this->collProductCategories->contains($obj)) {
                                $this->collProductCategories->append($obj);
                            }
                        }

                        $this->collProductCategoriesPartial = true;
                    }

                    reset($collProductCategories);

                    return $collProductCategories;
                }

                if ($partial && $this->collProductCategories) {
                    foreach ($this->collProductCategories as $obj) {
                        if ($obj->isNew()) {
                            $collProductCategories[] = $obj;
                        }
                    }
                }

                $this->collProductCategories = $collProductCategories;
                $this->collProductCategoriesPartial = false;
            }
        }

        return $this->collProductCategories;
    }

    /**
     * Sets a collection of ProductCategory objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productCategories A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCategory The current object (for fluent API support)
     */
    public function setProductCategories(Collection $productCategories, ConnectionInterface $con = null)
    {
        $productCategoriesToDelete = $this->getProductCategories(new Criteria(), $con)->diff($productCategories);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->productCategoriesScheduledForDeletion = clone $productCategoriesToDelete;

        foreach ($productCategoriesToDelete as $productCategoryRemoved) {
            $productCategoryRemoved->setCategory(null);
        }

        $this->collProductCategories = null;
        foreach ($productCategories as $productCategory) {
            $this->addProductCategory($productCategory);
        }

        $this->collProductCategories = $productCategories;
        $this->collProductCategoriesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductCategory objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductCategory objects.
     * @throws PropelException
     */
    public function countProductCategories(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductCategoriesPartial && !$this->isNew();
        if (null === $this->collProductCategories || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductCategories) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductCategories());
            }

            $query = ChildProductCategoryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCategory($this)
                ->count($con);
        }

        return count($this->collProductCategories);
    }

    /**
     * Method called to associate a ChildProductCategory object to this object
     * through the ChildProductCategory foreign key attribute.
     *
     * @param    ChildProductCategory $l ChildProductCategory
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function addProductCategory(ChildProductCategory $l)
    {
        if ($this->collProductCategories === null) {
            $this->initProductCategories();
            $this->collProductCategoriesPartial = true;
        }

        if (!in_array($l, $this->collProductCategories->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductCategory($l);
        }

        return $this;
    }

    /**
     * @param ProductCategory $productCategory The productCategory object to add.
     */
    protected function doAddProductCategory($productCategory)
    {
        $this->collProductCategories[]= $productCategory;
        $productCategory->setCategory($this);
    }

    /**
     * @param  ProductCategory $productCategory The productCategory object to remove.
     * @return ChildCategory The current object (for fluent API support)
     */
    public function removeProductCategory($productCategory)
    {
        if ($this->getProductCategories()->contains($productCategory)) {
            $this->collProductCategories->remove($this->collProductCategories->search($productCategory));
            if (null === $this->productCategoriesScheduledForDeletion) {
                $this->productCategoriesScheduledForDeletion = clone $this->collProductCategories;
                $this->productCategoriesScheduledForDeletion->clear();
            }
            $this->productCategoriesScheduledForDeletion[]= clone $productCategory;
            $productCategory->setCategory(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related ProductCategories from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProductCategory[] List of ChildProductCategory objects
     */
    public function getProductCategoriesJoinProduct($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProductCategoryQuery::create(null, $criteria);
        $query->joinWith('Product', $joinBehavior);

        return $this->getProductCategories($query, $con);
    }

    /**
     * Clears out the collCategoryImages collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCategoryImages()
     */
    public function clearCategoryImages()
    {
        $this->collCategoryImages = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCategoryImages collection loaded partially.
     */
    public function resetPartialCategoryImages($v = true)
    {
        $this->collCategoryImagesPartial = $v;
    }

    /**
     * Initializes the collCategoryImages collection.
     *
     * By default this just sets the collCategoryImages collection to an empty array (like clearcollCategoryImages());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCategoryImages($overrideExisting = true)
    {
        if (null !== $this->collCategoryImages && !$overrideExisting) {
            return;
        }
        $this->collCategoryImages = new ObjectCollection();
        $this->collCategoryImages->setModel('\Thelia\Model\CategoryImage');
    }

    /**
     * Gets an array of ChildCategoryImage objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCategory is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCategoryImage[] List of ChildCategoryImage objects
     * @throws PropelException
     */
    public function getCategoryImages($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryImagesPartial && !$this->isNew();
        if (null === $this->collCategoryImages || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCategoryImages) {
                // return empty collection
                $this->initCategoryImages();
            } else {
                $collCategoryImages = ChildCategoryImageQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCategoryImagesPartial && count($collCategoryImages)) {
                        $this->initCategoryImages(false);

                        foreach ($collCategoryImages as $obj) {
                            if (false == $this->collCategoryImages->contains($obj)) {
                                $this->collCategoryImages->append($obj);
                            }
                        }

                        $this->collCategoryImagesPartial = true;
                    }

                    reset($collCategoryImages);

                    return $collCategoryImages;
                }

                if ($partial && $this->collCategoryImages) {
                    foreach ($this->collCategoryImages as $obj) {
                        if ($obj->isNew()) {
                            $collCategoryImages[] = $obj;
                        }
                    }
                }

                $this->collCategoryImages = $collCategoryImages;
                $this->collCategoryImagesPartial = false;
            }
        }

        return $this->collCategoryImages;
    }

    /**
     * Sets a collection of CategoryImage objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $categoryImages A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCategory The current object (for fluent API support)
     */
    public function setCategoryImages(Collection $categoryImages, ConnectionInterface $con = null)
    {
        $categoryImagesToDelete = $this->getCategoryImages(new Criteria(), $con)->diff($categoryImages);


        $this->categoryImagesScheduledForDeletion = $categoryImagesToDelete;

        foreach ($categoryImagesToDelete as $categoryImageRemoved) {
            $categoryImageRemoved->setCategory(null);
        }

        $this->collCategoryImages = null;
        foreach ($categoryImages as $categoryImage) {
            $this->addCategoryImage($categoryImage);
        }

        $this->collCategoryImages = $categoryImages;
        $this->collCategoryImagesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CategoryImage objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CategoryImage objects.
     * @throws PropelException
     */
    public function countCategoryImages(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryImagesPartial && !$this->isNew();
        if (null === $this->collCategoryImages || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCategoryImages) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCategoryImages());
            }

            $query = ChildCategoryImageQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCategory($this)
                ->count($con);
        }

        return count($this->collCategoryImages);
    }

    /**
     * Method called to associate a ChildCategoryImage object to this object
     * through the ChildCategoryImage foreign key attribute.
     *
     * @param    ChildCategoryImage $l ChildCategoryImage
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function addCategoryImage(ChildCategoryImage $l)
    {
        if ($this->collCategoryImages === null) {
            $this->initCategoryImages();
            $this->collCategoryImagesPartial = true;
        }

        if (!in_array($l, $this->collCategoryImages->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCategoryImage($l);
        }

        return $this;
    }

    /**
     * @param CategoryImage $categoryImage The categoryImage object to add.
     */
    protected function doAddCategoryImage($categoryImage)
    {
        $this->collCategoryImages[]= $categoryImage;
        $categoryImage->setCategory($this);
    }

    /**
     * @param  CategoryImage $categoryImage The categoryImage object to remove.
     * @return ChildCategory The current object (for fluent API support)
     */
    public function removeCategoryImage($categoryImage)
    {
        if ($this->getCategoryImages()->contains($categoryImage)) {
            $this->collCategoryImages->remove($this->collCategoryImages->search($categoryImage));
            if (null === $this->categoryImagesScheduledForDeletion) {
                $this->categoryImagesScheduledForDeletion = clone $this->collCategoryImages;
                $this->categoryImagesScheduledForDeletion->clear();
            }
            $this->categoryImagesScheduledForDeletion[]= clone $categoryImage;
            $categoryImage->setCategory(null);
        }

        return $this;
    }

    /**
     * Clears out the collCategoryDocuments collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCategoryDocuments()
     */
    public function clearCategoryDocuments()
    {
        $this->collCategoryDocuments = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCategoryDocuments collection loaded partially.
     */
    public function resetPartialCategoryDocuments($v = true)
    {
        $this->collCategoryDocumentsPartial = $v;
    }

    /**
     * Initializes the collCategoryDocuments collection.
     *
     * By default this just sets the collCategoryDocuments collection to an empty array (like clearcollCategoryDocuments());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCategoryDocuments($overrideExisting = true)
    {
        if (null !== $this->collCategoryDocuments && !$overrideExisting) {
            return;
        }
        $this->collCategoryDocuments = new ObjectCollection();
        $this->collCategoryDocuments->setModel('\Thelia\Model\CategoryDocument');
    }

    /**
     * Gets an array of ChildCategoryDocument objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCategory is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCategoryDocument[] List of ChildCategoryDocument objects
     * @throws PropelException
     */
    public function getCategoryDocuments($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryDocumentsPartial && !$this->isNew();
        if (null === $this->collCategoryDocuments || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCategoryDocuments) {
                // return empty collection
                $this->initCategoryDocuments();
            } else {
                $collCategoryDocuments = ChildCategoryDocumentQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCategoryDocumentsPartial && count($collCategoryDocuments)) {
                        $this->initCategoryDocuments(false);

                        foreach ($collCategoryDocuments as $obj) {
                            if (false == $this->collCategoryDocuments->contains($obj)) {
                                $this->collCategoryDocuments->append($obj);
                            }
                        }

                        $this->collCategoryDocumentsPartial = true;
                    }

                    reset($collCategoryDocuments);

                    return $collCategoryDocuments;
                }

                if ($partial && $this->collCategoryDocuments) {
                    foreach ($this->collCategoryDocuments as $obj) {
                        if ($obj->isNew()) {
                            $collCategoryDocuments[] = $obj;
                        }
                    }
                }

                $this->collCategoryDocuments = $collCategoryDocuments;
                $this->collCategoryDocumentsPartial = false;
            }
        }

        return $this->collCategoryDocuments;
    }

    /**
     * Sets a collection of CategoryDocument objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $categoryDocuments A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCategory The current object (for fluent API support)
     */
    public function setCategoryDocuments(Collection $categoryDocuments, ConnectionInterface $con = null)
    {
        $categoryDocumentsToDelete = $this->getCategoryDocuments(new Criteria(), $con)->diff($categoryDocuments);


        $this->categoryDocumentsScheduledForDeletion = $categoryDocumentsToDelete;

        foreach ($categoryDocumentsToDelete as $categoryDocumentRemoved) {
            $categoryDocumentRemoved->setCategory(null);
        }

        $this->collCategoryDocuments = null;
        foreach ($categoryDocuments as $categoryDocument) {
            $this->addCategoryDocument($categoryDocument);
        }

        $this->collCategoryDocuments = $categoryDocuments;
        $this->collCategoryDocumentsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CategoryDocument objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CategoryDocument objects.
     * @throws PropelException
     */
    public function countCategoryDocuments(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryDocumentsPartial && !$this->isNew();
        if (null === $this->collCategoryDocuments || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCategoryDocuments) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCategoryDocuments());
            }

            $query = ChildCategoryDocumentQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCategory($this)
                ->count($con);
        }

        return count($this->collCategoryDocuments);
    }

    /**
     * Method called to associate a ChildCategoryDocument object to this object
     * through the ChildCategoryDocument foreign key attribute.
     *
     * @param    ChildCategoryDocument $l ChildCategoryDocument
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function addCategoryDocument(ChildCategoryDocument $l)
    {
        if ($this->collCategoryDocuments === null) {
            $this->initCategoryDocuments();
            $this->collCategoryDocumentsPartial = true;
        }

        if (!in_array($l, $this->collCategoryDocuments->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCategoryDocument($l);
        }

        return $this;
    }

    /**
     * @param CategoryDocument $categoryDocument The categoryDocument object to add.
     */
    protected function doAddCategoryDocument($categoryDocument)
    {
        $this->collCategoryDocuments[]= $categoryDocument;
        $categoryDocument->setCategory($this);
    }

    /**
     * @param  CategoryDocument $categoryDocument The categoryDocument object to remove.
     * @return ChildCategory The current object (for fluent API support)
     */
    public function removeCategoryDocument($categoryDocument)
    {
        if ($this->getCategoryDocuments()->contains($categoryDocument)) {
            $this->collCategoryDocuments->remove($this->collCategoryDocuments->search($categoryDocument));
            if (null === $this->categoryDocumentsScheduledForDeletion) {
                $this->categoryDocumentsScheduledForDeletion = clone $this->collCategoryDocuments;
                $this->categoryDocumentsScheduledForDeletion->clear();
            }
            $this->categoryDocumentsScheduledForDeletion[]= clone $categoryDocument;
            $categoryDocument->setCategory(null);
        }

        return $this;
    }

    /**
     * Clears out the collCategoryAssociatedContents collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCategoryAssociatedContents()
     */
    public function clearCategoryAssociatedContents()
    {
        $this->collCategoryAssociatedContents = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCategoryAssociatedContents collection loaded partially.
     */
    public function resetPartialCategoryAssociatedContents($v = true)
    {
        $this->collCategoryAssociatedContentsPartial = $v;
    }

    /**
     * Initializes the collCategoryAssociatedContents collection.
     *
     * By default this just sets the collCategoryAssociatedContents collection to an empty array (like clearcollCategoryAssociatedContents());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCategoryAssociatedContents($overrideExisting = true)
    {
        if (null !== $this->collCategoryAssociatedContents && !$overrideExisting) {
            return;
        }
        $this->collCategoryAssociatedContents = new ObjectCollection();
        $this->collCategoryAssociatedContents->setModel('\Thelia\Model\CategoryAssociatedContent');
    }

    /**
     * Gets an array of ChildCategoryAssociatedContent objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCategory is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCategoryAssociatedContent[] List of ChildCategoryAssociatedContent objects
     * @throws PropelException
     */
    public function getCategoryAssociatedContents($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryAssociatedContentsPartial && !$this->isNew();
        if (null === $this->collCategoryAssociatedContents || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCategoryAssociatedContents) {
                // return empty collection
                $this->initCategoryAssociatedContents();
            } else {
                $collCategoryAssociatedContents = ChildCategoryAssociatedContentQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCategoryAssociatedContentsPartial && count($collCategoryAssociatedContents)) {
                        $this->initCategoryAssociatedContents(false);

                        foreach ($collCategoryAssociatedContents as $obj) {
                            if (false == $this->collCategoryAssociatedContents->contains($obj)) {
                                $this->collCategoryAssociatedContents->append($obj);
                            }
                        }

                        $this->collCategoryAssociatedContentsPartial = true;
                    }

                    reset($collCategoryAssociatedContents);

                    return $collCategoryAssociatedContents;
                }

                if ($partial && $this->collCategoryAssociatedContents) {
                    foreach ($this->collCategoryAssociatedContents as $obj) {
                        if ($obj->isNew()) {
                            $collCategoryAssociatedContents[] = $obj;
                        }
                    }
                }

                $this->collCategoryAssociatedContents = $collCategoryAssociatedContents;
                $this->collCategoryAssociatedContentsPartial = false;
            }
        }

        return $this->collCategoryAssociatedContents;
    }

    /**
     * Sets a collection of CategoryAssociatedContent objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $categoryAssociatedContents A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCategory The current object (for fluent API support)
     */
    public function setCategoryAssociatedContents(Collection $categoryAssociatedContents, ConnectionInterface $con = null)
    {
        $categoryAssociatedContentsToDelete = $this->getCategoryAssociatedContents(new Criteria(), $con)->diff($categoryAssociatedContents);


        $this->categoryAssociatedContentsScheduledForDeletion = $categoryAssociatedContentsToDelete;

        foreach ($categoryAssociatedContentsToDelete as $categoryAssociatedContentRemoved) {
            $categoryAssociatedContentRemoved->setCategory(null);
        }

        $this->collCategoryAssociatedContents = null;
        foreach ($categoryAssociatedContents as $categoryAssociatedContent) {
            $this->addCategoryAssociatedContent($categoryAssociatedContent);
        }

        $this->collCategoryAssociatedContents = $categoryAssociatedContents;
        $this->collCategoryAssociatedContentsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CategoryAssociatedContent objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CategoryAssociatedContent objects.
     * @throws PropelException
     */
    public function countCategoryAssociatedContents(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryAssociatedContentsPartial && !$this->isNew();
        if (null === $this->collCategoryAssociatedContents || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCategoryAssociatedContents) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCategoryAssociatedContents());
            }

            $query = ChildCategoryAssociatedContentQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCategory($this)
                ->count($con);
        }

        return count($this->collCategoryAssociatedContents);
    }

    /**
     * Method called to associate a ChildCategoryAssociatedContent object to this object
     * through the ChildCategoryAssociatedContent foreign key attribute.
     *
     * @param    ChildCategoryAssociatedContent $l ChildCategoryAssociatedContent
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function addCategoryAssociatedContent(ChildCategoryAssociatedContent $l)
    {
        if ($this->collCategoryAssociatedContents === null) {
            $this->initCategoryAssociatedContents();
            $this->collCategoryAssociatedContentsPartial = true;
        }

        if (!in_array($l, $this->collCategoryAssociatedContents->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCategoryAssociatedContent($l);
        }

        return $this;
    }

    /**
     * @param CategoryAssociatedContent $categoryAssociatedContent The categoryAssociatedContent object to add.
     */
    protected function doAddCategoryAssociatedContent($categoryAssociatedContent)
    {
        $this->collCategoryAssociatedContents[]= $categoryAssociatedContent;
        $categoryAssociatedContent->setCategory($this);
    }

    /**
     * @param  CategoryAssociatedContent $categoryAssociatedContent The categoryAssociatedContent object to remove.
     * @return ChildCategory The current object (for fluent API support)
     */
    public function removeCategoryAssociatedContent($categoryAssociatedContent)
    {
        if ($this->getCategoryAssociatedContents()->contains($categoryAssociatedContent)) {
            $this->collCategoryAssociatedContents->remove($this->collCategoryAssociatedContents->search($categoryAssociatedContent));
            if (null === $this->categoryAssociatedContentsScheduledForDeletion) {
                $this->categoryAssociatedContentsScheduledForDeletion = clone $this->collCategoryAssociatedContents;
                $this->categoryAssociatedContentsScheduledForDeletion->clear();
            }
            $this->categoryAssociatedContentsScheduledForDeletion[]= clone $categoryAssociatedContent;
            $categoryAssociatedContent->setCategory(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Category is new, it will return
     * an empty collection; or if this Category has previously
     * been saved, it will retrieve related CategoryAssociatedContents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Category.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCategoryAssociatedContent[] List of ChildCategoryAssociatedContent objects
     */
    public function getCategoryAssociatedContentsJoinContent($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCategoryAssociatedContentQuery::create(null, $criteria);
        $query->joinWith('Content', $joinBehavior);

        return $this->getCategoryAssociatedContents($query, $con);
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
        $this->collCategoryI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCategoryI18ns collection loaded partially.
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
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCategoryI18ns($overrideExisting = true)
    {
        if (null !== $this->collCategoryI18ns && !$overrideExisting) {
            return;
        }
        $this->collCategoryI18ns = new ObjectCollection();
        $this->collCategoryI18ns->setModel('\Thelia\Model\CategoryI18n');
    }

    /**
     * Gets an array of ChildCategoryI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCategory is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCategoryI18n[] List of ChildCategoryI18n objects
     * @throws PropelException
     */
    public function getCategoryI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryI18nsPartial && !$this->isNew();
        if (null === $this->collCategoryI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCategoryI18ns) {
                // return empty collection
                $this->initCategoryI18ns();
            } else {
                $collCategoryI18ns = ChildCategoryI18nQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCategoryI18nsPartial && count($collCategoryI18ns)) {
                        $this->initCategoryI18ns(false);

                        foreach ($collCategoryI18ns as $obj) {
                            if (false == $this->collCategoryI18ns->contains($obj)) {
                                $this->collCategoryI18ns->append($obj);
                            }
                        }

                        $this->collCategoryI18nsPartial = true;
                    }

                    reset($collCategoryI18ns);

                    return $collCategoryI18ns;
                }

                if ($partial && $this->collCategoryI18ns) {
                    foreach ($this->collCategoryI18ns as $obj) {
                        if ($obj->isNew()) {
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
     * @param      Collection $categoryI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCategory The current object (for fluent API support)
     */
    public function setCategoryI18ns(Collection $categoryI18ns, ConnectionInterface $con = null)
    {
        $categoryI18nsToDelete = $this->getCategoryI18ns(new Criteria(), $con)->diff($categoryI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->categoryI18nsScheduledForDeletion = clone $categoryI18nsToDelete;

        foreach ($categoryI18nsToDelete as $categoryI18nRemoved) {
            $categoryI18nRemoved->setCategory(null);
        }

        $this->collCategoryI18ns = null;
        foreach ($categoryI18ns as $categoryI18n) {
            $this->addCategoryI18n($categoryI18n);
        }

        $this->collCategoryI18ns = $categoryI18ns;
        $this->collCategoryI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CategoryI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CategoryI18n objects.
     * @throws PropelException
     */
    public function countCategoryI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryI18nsPartial && !$this->isNew();
        if (null === $this->collCategoryI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCategoryI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCategoryI18ns());
            }

            $query = ChildCategoryI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCategory($this)
                ->count($con);
        }

        return count($this->collCategoryI18ns);
    }

    /**
     * Method called to associate a ChildCategoryI18n object to this object
     * through the ChildCategoryI18n foreign key attribute.
     *
     * @param    ChildCategoryI18n $l ChildCategoryI18n
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function addCategoryI18n(ChildCategoryI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collCategoryI18ns === null) {
            $this->initCategoryI18ns();
            $this->collCategoryI18nsPartial = true;
        }

        if (!in_array($l, $this->collCategoryI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCategoryI18n($l);
        }

        return $this;
    }

    /**
     * @param CategoryI18n $categoryI18n The categoryI18n object to add.
     */
    protected function doAddCategoryI18n($categoryI18n)
    {
        $this->collCategoryI18ns[]= $categoryI18n;
        $categoryI18n->setCategory($this);
    }

    /**
     * @param  CategoryI18n $categoryI18n The categoryI18n object to remove.
     * @return ChildCategory The current object (for fluent API support)
     */
    public function removeCategoryI18n($categoryI18n)
    {
        if ($this->getCategoryI18ns()->contains($categoryI18n)) {
            $this->collCategoryI18ns->remove($this->collCategoryI18ns->search($categoryI18n));
            if (null === $this->categoryI18nsScheduledForDeletion) {
                $this->categoryI18nsScheduledForDeletion = clone $this->collCategoryI18ns;
                $this->categoryI18nsScheduledForDeletion->clear();
            }
            $this->categoryI18nsScheduledForDeletion[]= clone $categoryI18n;
            $categoryI18n->setCategory(null);
        }

        return $this;
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
        $this->collCategoryVersions = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCategoryVersions collection loaded partially.
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
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCategoryVersions($overrideExisting = true)
    {
        if (null !== $this->collCategoryVersions && !$overrideExisting) {
            return;
        }
        $this->collCategoryVersions = new ObjectCollection();
        $this->collCategoryVersions->setModel('\Thelia\Model\CategoryVersion');
    }

    /**
     * Gets an array of ChildCategoryVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCategory is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCategoryVersion[] List of ChildCategoryVersion objects
     * @throws PropelException
     */
    public function getCategoryVersions($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryVersionsPartial && !$this->isNew();
        if (null === $this->collCategoryVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCategoryVersions) {
                // return empty collection
                $this->initCategoryVersions();
            } else {
                $collCategoryVersions = ChildCategoryVersionQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCategoryVersionsPartial && count($collCategoryVersions)) {
                        $this->initCategoryVersions(false);

                        foreach ($collCategoryVersions as $obj) {
                            if (false == $this->collCategoryVersions->contains($obj)) {
                                $this->collCategoryVersions->append($obj);
                            }
                        }

                        $this->collCategoryVersionsPartial = true;
                    }

                    reset($collCategoryVersions);

                    return $collCategoryVersions;
                }

                if ($partial && $this->collCategoryVersions) {
                    foreach ($this->collCategoryVersions as $obj) {
                        if ($obj->isNew()) {
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
     * @param      Collection $categoryVersions A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCategory The current object (for fluent API support)
     */
    public function setCategoryVersions(Collection $categoryVersions, ConnectionInterface $con = null)
    {
        $categoryVersionsToDelete = $this->getCategoryVersions(new Criteria(), $con)->diff($categoryVersions);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->categoryVersionsScheduledForDeletion = clone $categoryVersionsToDelete;

        foreach ($categoryVersionsToDelete as $categoryVersionRemoved) {
            $categoryVersionRemoved->setCategory(null);
        }

        $this->collCategoryVersions = null;
        foreach ($categoryVersions as $categoryVersion) {
            $this->addCategoryVersion($categoryVersion);
        }

        $this->collCategoryVersions = $categoryVersions;
        $this->collCategoryVersionsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CategoryVersion objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CategoryVersion objects.
     * @throws PropelException
     */
    public function countCategoryVersions(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCategoryVersionsPartial && !$this->isNew();
        if (null === $this->collCategoryVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCategoryVersions) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCategoryVersions());
            }

            $query = ChildCategoryVersionQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCategory($this)
                ->count($con);
        }

        return count($this->collCategoryVersions);
    }

    /**
     * Method called to associate a ChildCategoryVersion object to this object
     * through the ChildCategoryVersion foreign key attribute.
     *
     * @param    ChildCategoryVersion $l ChildCategoryVersion
     * @return   \Thelia\Model\Category The current object (for fluent API support)
     */
    public function addCategoryVersion(ChildCategoryVersion $l)
    {
        if ($this->collCategoryVersions === null) {
            $this->initCategoryVersions();
            $this->collCategoryVersionsPartial = true;
        }

        if (!in_array($l, $this->collCategoryVersions->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCategoryVersion($l);
        }

        return $this;
    }

    /**
     * @param CategoryVersion $categoryVersion The categoryVersion object to add.
     */
    protected function doAddCategoryVersion($categoryVersion)
    {
        $this->collCategoryVersions[]= $categoryVersion;
        $categoryVersion->setCategory($this);
    }

    /**
     * @param  CategoryVersion $categoryVersion The categoryVersion object to remove.
     * @return ChildCategory The current object (for fluent API support)
     */
    public function removeCategoryVersion($categoryVersion)
    {
        if ($this->getCategoryVersions()->contains($categoryVersion)) {
            $this->collCategoryVersions->remove($this->collCategoryVersions->search($categoryVersion));
            if (null === $this->categoryVersionsScheduledForDeletion) {
                $this->categoryVersionsScheduledForDeletion = clone $this->collCategoryVersions;
                $this->categoryVersionsScheduledForDeletion->clear();
            }
            $this->categoryVersionsScheduledForDeletion[]= clone $categoryVersion;
            $categoryVersion->setCategory(null);
        }

        return $this;
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
        $this->collProductsPartial = null;
    }

    /**
     * Initializes the collProducts collection.
     *
     * By default this just sets the collProducts collection to an empty collection (like clearProducts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initProducts()
    {
        $this->collProducts = new ObjectCollection();
        $this->collProducts->setModel('\Thelia\Model\Product');
    }

    /**
     * Gets a collection of ChildProduct objects related by a many-to-many relationship
     * to the current object by way of the product_category cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCategory is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildProduct[] List of ChildProduct objects
     */
    public function getProducts($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collProducts || null !== $criteria) {
            if ($this->isNew() && null === $this->collProducts) {
                // return empty collection
                $this->initProducts();
            } else {
                $collProducts = ChildProductQuery::create(null, $criteria)
                    ->filterByCategory($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collProducts;
                }
                $this->collProducts = $collProducts;
            }
        }

        return $this->collProducts;
    }

    /**
     * Sets a collection of Product objects related by a many-to-many relationship
     * to the current object by way of the product_category cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $products A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildCategory The current object (for fluent API support)
     */
    public function setProducts(Collection $products, ConnectionInterface $con = null)
    {
        $this->clearProducts();
        $currentProducts = $this->getProducts();

        $this->productsScheduledForDeletion = $currentProducts->diff($products);

        foreach ($products as $product) {
            if (!$currentProducts->contains($product)) {
                $this->doAddProduct($product);
            }
        }

        $this->collProducts = $products;

        return $this;
    }

    /**
     * Gets the number of ChildProduct objects related by a many-to-many relationship
     * to the current object by way of the product_category cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildProduct objects
     */
    public function countProducts($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collProducts || null !== $criteria) {
            if ($this->isNew() && null === $this->collProducts) {
                return 0;
            } else {
                $query = ChildProductQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCategory($this)
                    ->count($con);
            }
        } else {
            return count($this->collProducts);
        }
    }

    /**
     * Associate a ChildProduct object to this object
     * through the product_category cross reference table.
     *
     * @param  ChildProduct $product The ChildProductCategory object to relate
     * @return ChildCategory The current object (for fluent API support)
     */
    public function addProduct(ChildProduct $product)
    {
        if ($this->collProducts === null) {
            $this->initProducts();
        }

        if (!$this->collProducts->contains($product)) { // only add it if the **same** object is not already associated
            $this->doAddProduct($product);
            $this->collProducts[] = $product;
        }

        return $this;
    }

    /**
     * @param    Product $product The product object to add.
     */
    protected function doAddProduct($product)
    {
        $productCategory = new ChildProductCategory();
        $productCategory->setProduct($product);
        $this->addProductCategory($productCategory);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$product->getCategories()->contains($this)) {
            $foreignCollection   = $product->getCategories();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildProduct object to this object
     * through the product_category cross reference table.
     *
     * @param ChildProduct $product The ChildProductCategory object to relate
     * @return ChildCategory The current object (for fluent API support)
     */
    public function removeProduct(ChildProduct $product)
    {
        if ($this->getProducts()->contains($product)) {
            $this->collProducts->remove($this->collProducts->search($product));

            if (null === $this->productsScheduledForDeletion) {
                $this->productsScheduledForDeletion = clone $this->collProducts;
                $this->productsScheduledForDeletion->clear();
            }

            $this->productsScheduledForDeletion[] = $product;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->parent = null;
        $this->visible = null;
        $this->position = null;
        $this->default_template_id = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->version = null;
        $this->version_created_at = null;
        $this->version_created_by = null;
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
            if ($this->collProductCategories) {
                foreach ($this->collProductCategories as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCategoryImages) {
                foreach ($this->collCategoryImages as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCategoryDocuments) {
                foreach ($this->collCategoryDocuments as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCategoryAssociatedContents) {
                foreach ($this->collCategoryAssociatedContents as $o) {
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
            if ($this->collProducts) {
                foreach ($this->collProducts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collProductCategories = null;
        $this->collCategoryImages = null;
        $this->collCategoryDocuments = null;
        $this->collCategoryAssociatedContents = null;
        $this->collCategoryI18ns = null;
        $this->collCategoryVersions = null;
        $this->collProducts = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CategoryTableMap::DEFAULT_STRING_FORMAT);
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildCategory The current object (for fluent API support)
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
     * @return ChildCategoryI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
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
                $translation = new ChildCategoryI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildCategoryI18nQuery::create()
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
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildCategory The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildCategoryI18nQuery::create()
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
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ChildCategoryI18n */
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
         * @return   \Thelia\Model\CategoryI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\CategoryI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\CategoryI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\CategoryI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }


        /**
         * Get the [meta_title] column value.
         *
         * @return   string
         */
        public function getMetaTitle()
        {
        return $this->getCurrentTranslation()->getMetaTitle();
    }


        /**
         * Set the value of [meta_title] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CategoryI18n The current object (for fluent API support)
         */
        public function setMetaTitle($v)
        {    $this->getCurrentTranslation()->setMetaTitle($v);

        return $this;
    }


        /**
         * Get the [meta_description] column value.
         *
         * @return   string
         */
        public function getMetaDescription()
        {
        return $this->getCurrentTranslation()->getMetaDescription();
    }


        /**
         * Set the value of [meta_description] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CategoryI18n The current object (for fluent API support)
         */
        public function setMetaDescription($v)
        {    $this->getCurrentTranslation()->setMetaDescription($v);

        return $this;
    }


        /**
         * Get the [meta_keywords] column value.
         *
         * @return   string
         */
        public function getMetaKeywords()
        {
        return $this->getCurrentTranslation()->getMetaKeywords();
    }


        /**
         * Set the value of [meta_keywords] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\CategoryI18n The current object (for fluent API support)
         */
        public function setMetaKeywords($v)
        {    $this->getCurrentTranslation()->setMetaKeywords($v);

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return \Thelia\Model\Category
     */
    public function enforceVersioning()
    {
        $this->enforceVersion = true;

        return $this;
    }

    /**
     * Checks whether the current state must be recorded as a version
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

        if (ChildCategoryQuery::isVersioningEnabled() && ($this->isNew() || $this->isModified()) || $this->isDeleted()) {
            return true;
        }

        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildCategoryVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new ChildCategoryVersion();
        $version->setId($this->getId());
        $version->setParent($this->getParent());
        $version->setVisible($this->getVisible());
        $version->setPosition($this->getPosition());
        $version->setDefaultTemplateId($this->getDefaultTemplateId());
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
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con The connection to use
     *
     * @return  ChildCategory The current object (for fluent API support)
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No ChildCategory object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param ChildCategoryVersion $version The version object to use
     * @param ConnectionInterface   $con the connection to use
     * @param array                 $loadedObjects objects that been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return ChildCategory The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {
        $loadedObjects['ChildCategory'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
        $this->setParent($version->getParent());
        $this->setVisible($version->getVisible());
        $this->setPosition($version->getPosition());
        $this->setDefaultTemplateId($version->getDefaultTemplateId());
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
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  integer
     */
    public function getLastVersionNumber($con = null)
    {
        $v = ChildCategoryVersionQuery::create()
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
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  Boolean
     */
    public function isLastVersion($con = null)
    {
        return $this->getLastVersionNumber($con) == $this->getVersion();
    }

    /**
     * Retrieves a version object for this entity and a version number
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildCategoryVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return ChildCategoryVersionQuery::create()
            ->filterByCategory($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ObjectCollection A list of ChildCategoryVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(CategoryVersionTableMap::VERSION);

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
     * @param   integer             $versionNumber
     * @param   string              $keys Main key used for the result diff (versions|columns)
     * @param   ConnectionInterface $con the connection to use
     * @param   array               $ignoredColumns  The columns to exclude from the diff.
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
     * @param   integer             $fromVersionNumber
     * @param   integer             $toVersionNumber
     * @param   string              $keys Main key used for the result diff (versions|columns)
     * @param   ConnectionInterface $con the connection to use
     * @param   array               $ignoredColumns  The columns to exclude from the diff.
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
     * print_r($book->computeDiff(1, 2));
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
     * @param Integer $number the number of record to return.
     * @return PropelCollection|array \Thelia\Model\CategoryVersion[] List of \Thelia\Model\CategoryVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, $con = null)
    {
        $criteria = ChildCategoryVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(CategoryVersionTableMap::VERSION);
        $criteria->limit($number);

        return $this->getCategoryVersions($criteria, $con);
    }
    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildCategory The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[CategoryTableMap::UPDATED_AT] = true;

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
