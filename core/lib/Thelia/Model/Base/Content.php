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
use Thelia\Model\CategoryAssociatedContent as ChildCategoryAssociatedContent;
use Thelia\Model\CategoryAssociatedContentQuery as ChildCategoryAssociatedContentQuery;
use Thelia\Model\Content as ChildContent;
use Thelia\Model\ContentDocument as ChildContentDocument;
use Thelia\Model\ContentDocumentQuery as ChildContentDocumentQuery;
use Thelia\Model\ContentFolder as ChildContentFolder;
use Thelia\Model\ContentFolderQuery as ChildContentFolderQuery;
use Thelia\Model\ContentI18n as ChildContentI18n;
use Thelia\Model\ContentI18nQuery as ChildContentI18nQuery;
use Thelia\Model\ContentImage as ChildContentImage;
use Thelia\Model\ContentImageQuery as ChildContentImageQuery;
use Thelia\Model\ContentQuery as ChildContentQuery;
use Thelia\Model\ContentVersion as ChildContentVersion;
use Thelia\Model\ContentVersionQuery as ChildContentVersionQuery;
use Thelia\Model\Folder as ChildFolder;
use Thelia\Model\FolderQuery as ChildFolderQuery;
use Thelia\Model\ProductAssociatedContent as ChildProductAssociatedContent;
use Thelia\Model\ProductAssociatedContentQuery as ChildProductAssociatedContentQuery;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\ContentVersionTableMap;

abstract class Content implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\ContentTableMap';


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
     * @var        ObjectCollection|ChildContentFolder[] Collection to store aggregation of ChildContentFolder objects.
     */
    protected $collContentFolders;
    protected $collContentFoldersPartial;

    /**
     * @var        ObjectCollection|ChildContentImage[] Collection to store aggregation of ChildContentImage objects.
     */
    protected $collContentImages;
    protected $collContentImagesPartial;

    /**
     * @var        ObjectCollection|ChildContentDocument[] Collection to store aggregation of ChildContentDocument objects.
     */
    protected $collContentDocuments;
    protected $collContentDocumentsPartial;

    /**
     * @var        ObjectCollection|ChildProductAssociatedContent[] Collection to store aggregation of ChildProductAssociatedContent objects.
     */
    protected $collProductAssociatedContents;
    protected $collProductAssociatedContentsPartial;

    /**
     * @var        ObjectCollection|ChildCategoryAssociatedContent[] Collection to store aggregation of ChildCategoryAssociatedContent objects.
     */
    protected $collCategoryAssociatedContents;
    protected $collCategoryAssociatedContentsPartial;

    /**
     * @var        ObjectCollection|ChildContentI18n[] Collection to store aggregation of ChildContentI18n objects.
     */
    protected $collContentI18ns;
    protected $collContentI18nsPartial;

    /**
     * @var        ObjectCollection|ChildContentVersion[] Collection to store aggregation of ChildContentVersion objects.
     */
    protected $collContentVersions;
    protected $collContentVersionsPartial;

    /**
     * @var        ChildFolder[] Collection to store aggregation of ChildFolder objects.
     */
    protected $collFolders;

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
     * @var        array[ChildContentI18n]
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
    protected $foldersScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $contentFoldersScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $contentImagesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $contentDocumentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productAssociatedContentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $categoryAssociatedContentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $contentI18nsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $contentVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Content object.
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
     * Compares this with another <code>Content</code> instance.  If
     * <code>obj</code> is an instance of <code>Content</code>, delegates to
     * <code>equals(Content)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Content The current object, for fluid interface
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
     * @return Content The current object, for fluid interface
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
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[ContentTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [visible] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[ContentTableMap::VISIBLE] = true;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[ContentTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[ContentTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[ContentTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[ContentTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[ContentTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[ContentTableMap::VERSION_CREATED_BY] = true;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : ContentTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : ContentTableMap::translateFieldName('Visible', TableMap::TYPE_PHPNAME, $indexType)];
            $this->visible = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : ContentTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : ContentTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : ContentTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : ContentTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : ContentTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : ContentTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 8; // 8 = ContentTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Content object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(ContentTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildContentQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collContentFolders = null;

            $this->collContentImages = null;

            $this->collContentDocuments = null;

            $this->collProductAssociatedContents = null;

            $this->collCategoryAssociatedContents = null;

            $this->collContentI18ns = null;

            $this->collContentVersions = null;

            $this->collFolders = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Content::setDeleted()
     * @see Content::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(ContentTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildContentQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(ContentTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(ContentTableMap::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(ContentTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(ContentTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(ContentTableMap::UPDATED_AT)) {
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
                ContentTableMap::addInstanceToPool($this);
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

            if ($this->foldersScheduledForDeletion !== null) {
                if (!$this->foldersScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->foldersScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    ContentFolderQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->foldersScheduledForDeletion = null;
                }

                foreach ($this->getFolders() as $folder) {
                    if ($folder->isModified()) {
                        $folder->save($con);
                    }
                }
            } elseif ($this->collFolders) {
                foreach ($this->collFolders as $folder) {
                    if ($folder->isModified()) {
                        $folder->save($con);
                    }
                }
            }

            if ($this->contentFoldersScheduledForDeletion !== null) {
                if (!$this->contentFoldersScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ContentFolderQuery::create()
                        ->filterByPrimaryKeys($this->contentFoldersScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->contentFoldersScheduledForDeletion = null;
                }
            }

                if ($this->collContentFolders !== null) {
            foreach ($this->collContentFolders as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->contentImagesScheduledForDeletion !== null) {
                if (!$this->contentImagesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ContentImageQuery::create()
                        ->filterByPrimaryKeys($this->contentImagesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->contentImagesScheduledForDeletion = null;
                }
            }

                if ($this->collContentImages !== null) {
            foreach ($this->collContentImages as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->contentDocumentsScheduledForDeletion !== null) {
                if (!$this->contentDocumentsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ContentDocumentQuery::create()
                        ->filterByPrimaryKeys($this->contentDocumentsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->contentDocumentsScheduledForDeletion = null;
                }
            }

                if ($this->collContentDocuments !== null) {
            foreach ($this->collContentDocuments as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productAssociatedContentsScheduledForDeletion !== null) {
                if (!$this->productAssociatedContentsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductAssociatedContentQuery::create()
                        ->filterByPrimaryKeys($this->productAssociatedContentsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productAssociatedContentsScheduledForDeletion = null;
                }
            }

                if ($this->collProductAssociatedContents !== null) {
            foreach ($this->collProductAssociatedContents as $referrerFK) {
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

            if ($this->contentI18nsScheduledForDeletion !== null) {
                if (!$this->contentI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ContentI18nQuery::create()
                        ->filterByPrimaryKeys($this->contentI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->contentI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collContentI18ns !== null) {
            foreach ($this->collContentI18ns as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->contentVersionsScheduledForDeletion !== null) {
                if (!$this->contentVersionsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ContentVersionQuery::create()
                        ->filterByPrimaryKeys($this->contentVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->contentVersionsScheduledForDeletion = null;
                }
            }

                if ($this->collContentVersions !== null) {
            foreach ($this->collContentVersions as $referrerFK) {
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

        $this->modifiedColumns[ContentTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . ContentTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(ContentTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(ContentTableMap::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`VISIBLE`';
        }
        if ($this->isColumnModified(ContentTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(ContentTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(ContentTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(ContentTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(ContentTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(ContentTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
        }

        $sql = sprintf(
            'INSERT INTO `content` (%s) VALUES (%s)',
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
        $pos = ContentTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getVisible();
                break;
            case 2:
                return $this->getPosition();
                break;
            case 3:
                return $this->getCreatedAt();
                break;
            case 4:
                return $this->getUpdatedAt();
                break;
            case 5:
                return $this->getVersion();
                break;
            case 6:
                return $this->getVersionCreatedAt();
                break;
            case 7:
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
        if (isset($alreadyDumpedObjects['Content'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Content'][$this->getPrimaryKey()] = true;
        $keys = ContentTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getVisible(),
            $keys[2] => $this->getPosition(),
            $keys[3] => $this->getCreatedAt(),
            $keys[4] => $this->getUpdatedAt(),
            $keys[5] => $this->getVersion(),
            $keys[6] => $this->getVersionCreatedAt(),
            $keys[7] => $this->getVersionCreatedBy(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collContentFolders) {
                $result['ContentFolders'] = $this->collContentFolders->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collContentImages) {
                $result['ContentImages'] = $this->collContentImages->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collContentDocuments) {
                $result['ContentDocuments'] = $this->collContentDocuments->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductAssociatedContents) {
                $result['ProductAssociatedContents'] = $this->collProductAssociatedContents->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCategoryAssociatedContents) {
                $result['CategoryAssociatedContents'] = $this->collCategoryAssociatedContents->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collContentI18ns) {
                $result['ContentI18ns'] = $this->collContentI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collContentVersions) {
                $result['ContentVersions'] = $this->collContentVersions->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = ContentTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setVisible($value);
                break;
            case 2:
                $this->setPosition($value);
                break;
            case 3:
                $this->setCreatedAt($value);
                break;
            case 4:
                $this->setUpdatedAt($value);
                break;
            case 5:
                $this->setVersion($value);
                break;
            case 6:
                $this->setVersionCreatedAt($value);
                break;
            case 7:
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
        $keys = ContentTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setVisible($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setPosition($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setCreatedAt($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setUpdatedAt($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setVersion($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setVersionCreatedAt($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setVersionCreatedBy($arr[$keys[7]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(ContentTableMap::DATABASE_NAME);

        if ($this->isColumnModified(ContentTableMap::ID)) $criteria->add(ContentTableMap::ID, $this->id);
        if ($this->isColumnModified(ContentTableMap::VISIBLE)) $criteria->add(ContentTableMap::VISIBLE, $this->visible);
        if ($this->isColumnModified(ContentTableMap::POSITION)) $criteria->add(ContentTableMap::POSITION, $this->position);
        if ($this->isColumnModified(ContentTableMap::CREATED_AT)) $criteria->add(ContentTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(ContentTableMap::UPDATED_AT)) $criteria->add(ContentTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(ContentTableMap::VERSION)) $criteria->add(ContentTableMap::VERSION, $this->version);
        if ($this->isColumnModified(ContentTableMap::VERSION_CREATED_AT)) $criteria->add(ContentTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(ContentTableMap::VERSION_CREATED_BY)) $criteria->add(ContentTableMap::VERSION_CREATED_BY, $this->version_created_by);

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
        $criteria = new Criteria(ContentTableMap::DATABASE_NAME);
        $criteria->add(ContentTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Content (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setVisible($this->getVisible());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getContentFolders() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addContentFolder($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getContentImages() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addContentImage($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getContentDocuments() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addContentDocument($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductAssociatedContents() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductAssociatedContent($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCategoryAssociatedContents() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCategoryAssociatedContent($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getContentI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addContentI18n($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getContentVersions() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addContentVersion($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Content Clone of current object.
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
        if ('ContentFolder' == $relationName) {
            return $this->initContentFolders();
        }
        if ('ContentImage' == $relationName) {
            return $this->initContentImages();
        }
        if ('ContentDocument' == $relationName) {
            return $this->initContentDocuments();
        }
        if ('ProductAssociatedContent' == $relationName) {
            return $this->initProductAssociatedContents();
        }
        if ('CategoryAssociatedContent' == $relationName) {
            return $this->initCategoryAssociatedContents();
        }
        if ('ContentI18n' == $relationName) {
            return $this->initContentI18ns();
        }
        if ('ContentVersion' == $relationName) {
            return $this->initContentVersions();
        }
    }

    /**
     * Clears out the collContentFolders collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContentFolders()
     */
    public function clearContentFolders()
    {
        $this->collContentFolders = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collContentFolders collection loaded partially.
     */
    public function resetPartialContentFolders($v = true)
    {
        $this->collContentFoldersPartial = $v;
    }

    /**
     * Initializes the collContentFolders collection.
     *
     * By default this just sets the collContentFolders collection to an empty array (like clearcollContentFolders());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initContentFolders($overrideExisting = true)
    {
        if (null !== $this->collContentFolders && !$overrideExisting) {
            return;
        }
        $this->collContentFolders = new ObjectCollection();
        $this->collContentFolders->setModel('\Thelia\Model\ContentFolder');
    }

    /**
     * Gets an array of ChildContentFolder objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildContent is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildContentFolder[] List of ChildContentFolder objects
     * @throws PropelException
     */
    public function getContentFolders($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collContentFoldersPartial && !$this->isNew();
        if (null === $this->collContentFolders || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collContentFolders) {
                // return empty collection
                $this->initContentFolders();
            } else {
                $collContentFolders = ChildContentFolderQuery::create(null, $criteria)
                    ->filterByContent($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collContentFoldersPartial && count($collContentFolders)) {
                        $this->initContentFolders(false);

                        foreach ($collContentFolders as $obj) {
                            if (false == $this->collContentFolders->contains($obj)) {
                                $this->collContentFolders->append($obj);
                            }
                        }

                        $this->collContentFoldersPartial = true;
                    }

                    reset($collContentFolders);

                    return $collContentFolders;
                }

                if ($partial && $this->collContentFolders) {
                    foreach ($this->collContentFolders as $obj) {
                        if ($obj->isNew()) {
                            $collContentFolders[] = $obj;
                        }
                    }
                }

                $this->collContentFolders = $collContentFolders;
                $this->collContentFoldersPartial = false;
            }
        }

        return $this->collContentFolders;
    }

    /**
     * Sets a collection of ContentFolder objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $contentFolders A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildContent The current object (for fluent API support)
     */
    public function setContentFolders(Collection $contentFolders, ConnectionInterface $con = null)
    {
        $contentFoldersToDelete = $this->getContentFolders(new Criteria(), $con)->diff($contentFolders);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->contentFoldersScheduledForDeletion = clone $contentFoldersToDelete;

        foreach ($contentFoldersToDelete as $contentFolderRemoved) {
            $contentFolderRemoved->setContent(null);
        }

        $this->collContentFolders = null;
        foreach ($contentFolders as $contentFolder) {
            $this->addContentFolder($contentFolder);
        }

        $this->collContentFolders = $contentFolders;
        $this->collContentFoldersPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ContentFolder objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ContentFolder objects.
     * @throws PropelException
     */
    public function countContentFolders(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collContentFoldersPartial && !$this->isNew();
        if (null === $this->collContentFolders || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collContentFolders) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getContentFolders());
            }

            $query = ChildContentFolderQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByContent($this)
                ->count($con);
        }

        return count($this->collContentFolders);
    }

    /**
     * Method called to associate a ChildContentFolder object to this object
     * through the ChildContentFolder foreign key attribute.
     *
     * @param    ChildContentFolder $l ChildContentFolder
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function addContentFolder(ChildContentFolder $l)
    {
        if ($this->collContentFolders === null) {
            $this->initContentFolders();
            $this->collContentFoldersPartial = true;
        }

        if (!in_array($l, $this->collContentFolders->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddContentFolder($l);
        }

        return $this;
    }

    /**
     * @param ContentFolder $contentFolder The contentFolder object to add.
     */
    protected function doAddContentFolder($contentFolder)
    {
        $this->collContentFolders[]= $contentFolder;
        $contentFolder->setContent($this);
    }

    /**
     * @param  ContentFolder $contentFolder The contentFolder object to remove.
     * @return ChildContent The current object (for fluent API support)
     */
    public function removeContentFolder($contentFolder)
    {
        if ($this->getContentFolders()->contains($contentFolder)) {
            $this->collContentFolders->remove($this->collContentFolders->search($contentFolder));
            if (null === $this->contentFoldersScheduledForDeletion) {
                $this->contentFoldersScheduledForDeletion = clone $this->collContentFolders;
                $this->contentFoldersScheduledForDeletion->clear();
            }
            $this->contentFoldersScheduledForDeletion[]= clone $contentFolder;
            $contentFolder->setContent(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Content is new, it will return
     * an empty collection; or if this Content has previously
     * been saved, it will retrieve related ContentFolders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Content.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildContentFolder[] List of ChildContentFolder objects
     */
    public function getContentFoldersJoinFolder($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildContentFolderQuery::create(null, $criteria);
        $query->joinWith('Folder', $joinBehavior);

        return $this->getContentFolders($query, $con);
    }

    /**
     * Clears out the collContentImages collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContentImages()
     */
    public function clearContentImages()
    {
        $this->collContentImages = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collContentImages collection loaded partially.
     */
    public function resetPartialContentImages($v = true)
    {
        $this->collContentImagesPartial = $v;
    }

    /**
     * Initializes the collContentImages collection.
     *
     * By default this just sets the collContentImages collection to an empty array (like clearcollContentImages());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initContentImages($overrideExisting = true)
    {
        if (null !== $this->collContentImages && !$overrideExisting) {
            return;
        }
        $this->collContentImages = new ObjectCollection();
        $this->collContentImages->setModel('\Thelia\Model\ContentImage');
    }

    /**
     * Gets an array of ChildContentImage objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildContent is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildContentImage[] List of ChildContentImage objects
     * @throws PropelException
     */
    public function getContentImages($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collContentImagesPartial && !$this->isNew();
        if (null === $this->collContentImages || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collContentImages) {
                // return empty collection
                $this->initContentImages();
            } else {
                $collContentImages = ChildContentImageQuery::create(null, $criteria)
                    ->filterByContent($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collContentImagesPartial && count($collContentImages)) {
                        $this->initContentImages(false);

                        foreach ($collContentImages as $obj) {
                            if (false == $this->collContentImages->contains($obj)) {
                                $this->collContentImages->append($obj);
                            }
                        }

                        $this->collContentImagesPartial = true;
                    }

                    reset($collContentImages);

                    return $collContentImages;
                }

                if ($partial && $this->collContentImages) {
                    foreach ($this->collContentImages as $obj) {
                        if ($obj->isNew()) {
                            $collContentImages[] = $obj;
                        }
                    }
                }

                $this->collContentImages = $collContentImages;
                $this->collContentImagesPartial = false;
            }
        }

        return $this->collContentImages;
    }

    /**
     * Sets a collection of ContentImage objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $contentImages A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildContent The current object (for fluent API support)
     */
    public function setContentImages(Collection $contentImages, ConnectionInterface $con = null)
    {
        $contentImagesToDelete = $this->getContentImages(new Criteria(), $con)->diff($contentImages);


        $this->contentImagesScheduledForDeletion = $contentImagesToDelete;

        foreach ($contentImagesToDelete as $contentImageRemoved) {
            $contentImageRemoved->setContent(null);
        }

        $this->collContentImages = null;
        foreach ($contentImages as $contentImage) {
            $this->addContentImage($contentImage);
        }

        $this->collContentImages = $contentImages;
        $this->collContentImagesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ContentImage objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ContentImage objects.
     * @throws PropelException
     */
    public function countContentImages(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collContentImagesPartial && !$this->isNew();
        if (null === $this->collContentImages || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collContentImages) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getContentImages());
            }

            $query = ChildContentImageQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByContent($this)
                ->count($con);
        }

        return count($this->collContentImages);
    }

    /**
     * Method called to associate a ChildContentImage object to this object
     * through the ChildContentImage foreign key attribute.
     *
     * @param    ChildContentImage $l ChildContentImage
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function addContentImage(ChildContentImage $l)
    {
        if ($this->collContentImages === null) {
            $this->initContentImages();
            $this->collContentImagesPartial = true;
        }

        if (!in_array($l, $this->collContentImages->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddContentImage($l);
        }

        return $this;
    }

    /**
     * @param ContentImage $contentImage The contentImage object to add.
     */
    protected function doAddContentImage($contentImage)
    {
        $this->collContentImages[]= $contentImage;
        $contentImage->setContent($this);
    }

    /**
     * @param  ContentImage $contentImage The contentImage object to remove.
     * @return ChildContent The current object (for fluent API support)
     */
    public function removeContentImage($contentImage)
    {
        if ($this->getContentImages()->contains($contentImage)) {
            $this->collContentImages->remove($this->collContentImages->search($contentImage));
            if (null === $this->contentImagesScheduledForDeletion) {
                $this->contentImagesScheduledForDeletion = clone $this->collContentImages;
                $this->contentImagesScheduledForDeletion->clear();
            }
            $this->contentImagesScheduledForDeletion[]= clone $contentImage;
            $contentImage->setContent(null);
        }

        return $this;
    }

    /**
     * Clears out the collContentDocuments collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContentDocuments()
     */
    public function clearContentDocuments()
    {
        $this->collContentDocuments = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collContentDocuments collection loaded partially.
     */
    public function resetPartialContentDocuments($v = true)
    {
        $this->collContentDocumentsPartial = $v;
    }

    /**
     * Initializes the collContentDocuments collection.
     *
     * By default this just sets the collContentDocuments collection to an empty array (like clearcollContentDocuments());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initContentDocuments($overrideExisting = true)
    {
        if (null !== $this->collContentDocuments && !$overrideExisting) {
            return;
        }
        $this->collContentDocuments = new ObjectCollection();
        $this->collContentDocuments->setModel('\Thelia\Model\ContentDocument');
    }

    /**
     * Gets an array of ChildContentDocument objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildContent is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildContentDocument[] List of ChildContentDocument objects
     * @throws PropelException
     */
    public function getContentDocuments($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collContentDocumentsPartial && !$this->isNew();
        if (null === $this->collContentDocuments || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collContentDocuments) {
                // return empty collection
                $this->initContentDocuments();
            } else {
                $collContentDocuments = ChildContentDocumentQuery::create(null, $criteria)
                    ->filterByContent($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collContentDocumentsPartial && count($collContentDocuments)) {
                        $this->initContentDocuments(false);

                        foreach ($collContentDocuments as $obj) {
                            if (false == $this->collContentDocuments->contains($obj)) {
                                $this->collContentDocuments->append($obj);
                            }
                        }

                        $this->collContentDocumentsPartial = true;
                    }

                    reset($collContentDocuments);

                    return $collContentDocuments;
                }

                if ($partial && $this->collContentDocuments) {
                    foreach ($this->collContentDocuments as $obj) {
                        if ($obj->isNew()) {
                            $collContentDocuments[] = $obj;
                        }
                    }
                }

                $this->collContentDocuments = $collContentDocuments;
                $this->collContentDocumentsPartial = false;
            }
        }

        return $this->collContentDocuments;
    }

    /**
     * Sets a collection of ContentDocument objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $contentDocuments A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildContent The current object (for fluent API support)
     */
    public function setContentDocuments(Collection $contentDocuments, ConnectionInterface $con = null)
    {
        $contentDocumentsToDelete = $this->getContentDocuments(new Criteria(), $con)->diff($contentDocuments);


        $this->contentDocumentsScheduledForDeletion = $contentDocumentsToDelete;

        foreach ($contentDocumentsToDelete as $contentDocumentRemoved) {
            $contentDocumentRemoved->setContent(null);
        }

        $this->collContentDocuments = null;
        foreach ($contentDocuments as $contentDocument) {
            $this->addContentDocument($contentDocument);
        }

        $this->collContentDocuments = $contentDocuments;
        $this->collContentDocumentsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ContentDocument objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ContentDocument objects.
     * @throws PropelException
     */
    public function countContentDocuments(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collContentDocumentsPartial && !$this->isNew();
        if (null === $this->collContentDocuments || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collContentDocuments) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getContentDocuments());
            }

            $query = ChildContentDocumentQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByContent($this)
                ->count($con);
        }

        return count($this->collContentDocuments);
    }

    /**
     * Method called to associate a ChildContentDocument object to this object
     * through the ChildContentDocument foreign key attribute.
     *
     * @param    ChildContentDocument $l ChildContentDocument
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function addContentDocument(ChildContentDocument $l)
    {
        if ($this->collContentDocuments === null) {
            $this->initContentDocuments();
            $this->collContentDocumentsPartial = true;
        }

        if (!in_array($l, $this->collContentDocuments->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddContentDocument($l);
        }

        return $this;
    }

    /**
     * @param ContentDocument $contentDocument The contentDocument object to add.
     */
    protected function doAddContentDocument($contentDocument)
    {
        $this->collContentDocuments[]= $contentDocument;
        $contentDocument->setContent($this);
    }

    /**
     * @param  ContentDocument $contentDocument The contentDocument object to remove.
     * @return ChildContent The current object (for fluent API support)
     */
    public function removeContentDocument($contentDocument)
    {
        if ($this->getContentDocuments()->contains($contentDocument)) {
            $this->collContentDocuments->remove($this->collContentDocuments->search($contentDocument));
            if (null === $this->contentDocumentsScheduledForDeletion) {
                $this->contentDocumentsScheduledForDeletion = clone $this->collContentDocuments;
                $this->contentDocumentsScheduledForDeletion->clear();
            }
            $this->contentDocumentsScheduledForDeletion[]= clone $contentDocument;
            $contentDocument->setContent(null);
        }

        return $this;
    }

    /**
     * Clears out the collProductAssociatedContents collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductAssociatedContents()
     */
    public function clearProductAssociatedContents()
    {
        $this->collProductAssociatedContents = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductAssociatedContents collection loaded partially.
     */
    public function resetPartialProductAssociatedContents($v = true)
    {
        $this->collProductAssociatedContentsPartial = $v;
    }

    /**
     * Initializes the collProductAssociatedContents collection.
     *
     * By default this just sets the collProductAssociatedContents collection to an empty array (like clearcollProductAssociatedContents());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductAssociatedContents($overrideExisting = true)
    {
        if (null !== $this->collProductAssociatedContents && !$overrideExisting) {
            return;
        }
        $this->collProductAssociatedContents = new ObjectCollection();
        $this->collProductAssociatedContents->setModel('\Thelia\Model\ProductAssociatedContent');
    }

    /**
     * Gets an array of ChildProductAssociatedContent objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildContent is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductAssociatedContent[] List of ChildProductAssociatedContent objects
     * @throws PropelException
     */
    public function getProductAssociatedContents($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductAssociatedContentsPartial && !$this->isNew();
        if (null === $this->collProductAssociatedContents || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductAssociatedContents) {
                // return empty collection
                $this->initProductAssociatedContents();
            } else {
                $collProductAssociatedContents = ChildProductAssociatedContentQuery::create(null, $criteria)
                    ->filterByContent($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductAssociatedContentsPartial && count($collProductAssociatedContents)) {
                        $this->initProductAssociatedContents(false);

                        foreach ($collProductAssociatedContents as $obj) {
                            if (false == $this->collProductAssociatedContents->contains($obj)) {
                                $this->collProductAssociatedContents->append($obj);
                            }
                        }

                        $this->collProductAssociatedContentsPartial = true;
                    }

                    reset($collProductAssociatedContents);

                    return $collProductAssociatedContents;
                }

                if ($partial && $this->collProductAssociatedContents) {
                    foreach ($this->collProductAssociatedContents as $obj) {
                        if ($obj->isNew()) {
                            $collProductAssociatedContents[] = $obj;
                        }
                    }
                }

                $this->collProductAssociatedContents = $collProductAssociatedContents;
                $this->collProductAssociatedContentsPartial = false;
            }
        }

        return $this->collProductAssociatedContents;
    }

    /**
     * Sets a collection of ProductAssociatedContent objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productAssociatedContents A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildContent The current object (for fluent API support)
     */
    public function setProductAssociatedContents(Collection $productAssociatedContents, ConnectionInterface $con = null)
    {
        $productAssociatedContentsToDelete = $this->getProductAssociatedContents(new Criteria(), $con)->diff($productAssociatedContents);


        $this->productAssociatedContentsScheduledForDeletion = $productAssociatedContentsToDelete;

        foreach ($productAssociatedContentsToDelete as $productAssociatedContentRemoved) {
            $productAssociatedContentRemoved->setContent(null);
        }

        $this->collProductAssociatedContents = null;
        foreach ($productAssociatedContents as $productAssociatedContent) {
            $this->addProductAssociatedContent($productAssociatedContent);
        }

        $this->collProductAssociatedContents = $productAssociatedContents;
        $this->collProductAssociatedContentsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductAssociatedContent objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductAssociatedContent objects.
     * @throws PropelException
     */
    public function countProductAssociatedContents(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductAssociatedContentsPartial && !$this->isNew();
        if (null === $this->collProductAssociatedContents || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductAssociatedContents) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductAssociatedContents());
            }

            $query = ChildProductAssociatedContentQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByContent($this)
                ->count($con);
        }

        return count($this->collProductAssociatedContents);
    }

    /**
     * Method called to associate a ChildProductAssociatedContent object to this object
     * through the ChildProductAssociatedContent foreign key attribute.
     *
     * @param    ChildProductAssociatedContent $l ChildProductAssociatedContent
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function addProductAssociatedContent(ChildProductAssociatedContent $l)
    {
        if ($this->collProductAssociatedContents === null) {
            $this->initProductAssociatedContents();
            $this->collProductAssociatedContentsPartial = true;
        }

        if (!in_array($l, $this->collProductAssociatedContents->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductAssociatedContent($l);
        }

        return $this;
    }

    /**
     * @param ProductAssociatedContent $productAssociatedContent The productAssociatedContent object to add.
     */
    protected function doAddProductAssociatedContent($productAssociatedContent)
    {
        $this->collProductAssociatedContents[]= $productAssociatedContent;
        $productAssociatedContent->setContent($this);
    }

    /**
     * @param  ProductAssociatedContent $productAssociatedContent The productAssociatedContent object to remove.
     * @return ChildContent The current object (for fluent API support)
     */
    public function removeProductAssociatedContent($productAssociatedContent)
    {
        if ($this->getProductAssociatedContents()->contains($productAssociatedContent)) {
            $this->collProductAssociatedContents->remove($this->collProductAssociatedContents->search($productAssociatedContent));
            if (null === $this->productAssociatedContentsScheduledForDeletion) {
                $this->productAssociatedContentsScheduledForDeletion = clone $this->collProductAssociatedContents;
                $this->productAssociatedContentsScheduledForDeletion->clear();
            }
            $this->productAssociatedContentsScheduledForDeletion[]= clone $productAssociatedContent;
            $productAssociatedContent->setContent(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Content is new, it will return
     * an empty collection; or if this Content has previously
     * been saved, it will retrieve related ProductAssociatedContents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Content.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProductAssociatedContent[] List of ChildProductAssociatedContent objects
     */
    public function getProductAssociatedContentsJoinProduct($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProductAssociatedContentQuery::create(null, $criteria);
        $query->joinWith('Product', $joinBehavior);

        return $this->getProductAssociatedContents($query, $con);
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
     * If this ChildContent is new, it will return
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
                    ->filterByContent($this)
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
     * @return   ChildContent The current object (for fluent API support)
     */
    public function setCategoryAssociatedContents(Collection $categoryAssociatedContents, ConnectionInterface $con = null)
    {
        $categoryAssociatedContentsToDelete = $this->getCategoryAssociatedContents(new Criteria(), $con)->diff($categoryAssociatedContents);


        $this->categoryAssociatedContentsScheduledForDeletion = $categoryAssociatedContentsToDelete;

        foreach ($categoryAssociatedContentsToDelete as $categoryAssociatedContentRemoved) {
            $categoryAssociatedContentRemoved->setContent(null);
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
                ->filterByContent($this)
                ->count($con);
        }

        return count($this->collCategoryAssociatedContents);
    }

    /**
     * Method called to associate a ChildCategoryAssociatedContent object to this object
     * through the ChildCategoryAssociatedContent foreign key attribute.
     *
     * @param    ChildCategoryAssociatedContent $l ChildCategoryAssociatedContent
     * @return   \Thelia\Model\Content The current object (for fluent API support)
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
        $categoryAssociatedContent->setContent($this);
    }

    /**
     * @param  CategoryAssociatedContent $categoryAssociatedContent The categoryAssociatedContent object to remove.
     * @return ChildContent The current object (for fluent API support)
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
            $categoryAssociatedContent->setContent(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Content is new, it will return
     * an empty collection; or if this Content has previously
     * been saved, it will retrieve related CategoryAssociatedContents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Content.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCategoryAssociatedContent[] List of ChildCategoryAssociatedContent objects
     */
    public function getCategoryAssociatedContentsJoinCategory($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCategoryAssociatedContentQuery::create(null, $criteria);
        $query->joinWith('Category', $joinBehavior);

        return $this->getCategoryAssociatedContents($query, $con);
    }

    /**
     * Clears out the collContentI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContentI18ns()
     */
    public function clearContentI18ns()
    {
        $this->collContentI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collContentI18ns collection loaded partially.
     */
    public function resetPartialContentI18ns($v = true)
    {
        $this->collContentI18nsPartial = $v;
    }

    /**
     * Initializes the collContentI18ns collection.
     *
     * By default this just sets the collContentI18ns collection to an empty array (like clearcollContentI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initContentI18ns($overrideExisting = true)
    {
        if (null !== $this->collContentI18ns && !$overrideExisting) {
            return;
        }
        $this->collContentI18ns = new ObjectCollection();
        $this->collContentI18ns->setModel('\Thelia\Model\ContentI18n');
    }

    /**
     * Gets an array of ChildContentI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildContent is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildContentI18n[] List of ChildContentI18n objects
     * @throws PropelException
     */
    public function getContentI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collContentI18nsPartial && !$this->isNew();
        if (null === $this->collContentI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collContentI18ns) {
                // return empty collection
                $this->initContentI18ns();
            } else {
                $collContentI18ns = ChildContentI18nQuery::create(null, $criteria)
                    ->filterByContent($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collContentI18nsPartial && count($collContentI18ns)) {
                        $this->initContentI18ns(false);

                        foreach ($collContentI18ns as $obj) {
                            if (false == $this->collContentI18ns->contains($obj)) {
                                $this->collContentI18ns->append($obj);
                            }
                        }

                        $this->collContentI18nsPartial = true;
                    }

                    reset($collContentI18ns);

                    return $collContentI18ns;
                }

                if ($partial && $this->collContentI18ns) {
                    foreach ($this->collContentI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collContentI18ns[] = $obj;
                        }
                    }
                }

                $this->collContentI18ns = $collContentI18ns;
                $this->collContentI18nsPartial = false;
            }
        }

        return $this->collContentI18ns;
    }

    /**
     * Sets a collection of ContentI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $contentI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildContent The current object (for fluent API support)
     */
    public function setContentI18ns(Collection $contentI18ns, ConnectionInterface $con = null)
    {
        $contentI18nsToDelete = $this->getContentI18ns(new Criteria(), $con)->diff($contentI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->contentI18nsScheduledForDeletion = clone $contentI18nsToDelete;

        foreach ($contentI18nsToDelete as $contentI18nRemoved) {
            $contentI18nRemoved->setContent(null);
        }

        $this->collContentI18ns = null;
        foreach ($contentI18ns as $contentI18n) {
            $this->addContentI18n($contentI18n);
        }

        $this->collContentI18ns = $contentI18ns;
        $this->collContentI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ContentI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ContentI18n objects.
     * @throws PropelException
     */
    public function countContentI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collContentI18nsPartial && !$this->isNew();
        if (null === $this->collContentI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collContentI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getContentI18ns());
            }

            $query = ChildContentI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByContent($this)
                ->count($con);
        }

        return count($this->collContentI18ns);
    }

    /**
     * Method called to associate a ChildContentI18n object to this object
     * through the ChildContentI18n foreign key attribute.
     *
     * @param    ChildContentI18n $l ChildContentI18n
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function addContentI18n(ChildContentI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collContentI18ns === null) {
            $this->initContentI18ns();
            $this->collContentI18nsPartial = true;
        }

        if (!in_array($l, $this->collContentI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddContentI18n($l);
        }

        return $this;
    }

    /**
     * @param ContentI18n $contentI18n The contentI18n object to add.
     */
    protected function doAddContentI18n($contentI18n)
    {
        $this->collContentI18ns[]= $contentI18n;
        $contentI18n->setContent($this);
    }

    /**
     * @param  ContentI18n $contentI18n The contentI18n object to remove.
     * @return ChildContent The current object (for fluent API support)
     */
    public function removeContentI18n($contentI18n)
    {
        if ($this->getContentI18ns()->contains($contentI18n)) {
            $this->collContentI18ns->remove($this->collContentI18ns->search($contentI18n));
            if (null === $this->contentI18nsScheduledForDeletion) {
                $this->contentI18nsScheduledForDeletion = clone $this->collContentI18ns;
                $this->contentI18nsScheduledForDeletion->clear();
            }
            $this->contentI18nsScheduledForDeletion[]= clone $contentI18n;
            $contentI18n->setContent(null);
        }

        return $this;
    }

    /**
     * Clears out the collContentVersions collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContentVersions()
     */
    public function clearContentVersions()
    {
        $this->collContentVersions = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collContentVersions collection loaded partially.
     */
    public function resetPartialContentVersions($v = true)
    {
        $this->collContentVersionsPartial = $v;
    }

    /**
     * Initializes the collContentVersions collection.
     *
     * By default this just sets the collContentVersions collection to an empty array (like clearcollContentVersions());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initContentVersions($overrideExisting = true)
    {
        if (null !== $this->collContentVersions && !$overrideExisting) {
            return;
        }
        $this->collContentVersions = new ObjectCollection();
        $this->collContentVersions->setModel('\Thelia\Model\ContentVersion');
    }

    /**
     * Gets an array of ChildContentVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildContent is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildContentVersion[] List of ChildContentVersion objects
     * @throws PropelException
     */
    public function getContentVersions($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collContentVersionsPartial && !$this->isNew();
        if (null === $this->collContentVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collContentVersions) {
                // return empty collection
                $this->initContentVersions();
            } else {
                $collContentVersions = ChildContentVersionQuery::create(null, $criteria)
                    ->filterByContent($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collContentVersionsPartial && count($collContentVersions)) {
                        $this->initContentVersions(false);

                        foreach ($collContentVersions as $obj) {
                            if (false == $this->collContentVersions->contains($obj)) {
                                $this->collContentVersions->append($obj);
                            }
                        }

                        $this->collContentVersionsPartial = true;
                    }

                    reset($collContentVersions);

                    return $collContentVersions;
                }

                if ($partial && $this->collContentVersions) {
                    foreach ($this->collContentVersions as $obj) {
                        if ($obj->isNew()) {
                            $collContentVersions[] = $obj;
                        }
                    }
                }

                $this->collContentVersions = $collContentVersions;
                $this->collContentVersionsPartial = false;
            }
        }

        return $this->collContentVersions;
    }

    /**
     * Sets a collection of ContentVersion objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $contentVersions A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildContent The current object (for fluent API support)
     */
    public function setContentVersions(Collection $contentVersions, ConnectionInterface $con = null)
    {
        $contentVersionsToDelete = $this->getContentVersions(new Criteria(), $con)->diff($contentVersions);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->contentVersionsScheduledForDeletion = clone $contentVersionsToDelete;

        foreach ($contentVersionsToDelete as $contentVersionRemoved) {
            $contentVersionRemoved->setContent(null);
        }

        $this->collContentVersions = null;
        foreach ($contentVersions as $contentVersion) {
            $this->addContentVersion($contentVersion);
        }

        $this->collContentVersions = $contentVersions;
        $this->collContentVersionsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ContentVersion objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ContentVersion objects.
     * @throws PropelException
     */
    public function countContentVersions(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collContentVersionsPartial && !$this->isNew();
        if (null === $this->collContentVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collContentVersions) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getContentVersions());
            }

            $query = ChildContentVersionQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByContent($this)
                ->count($con);
        }

        return count($this->collContentVersions);
    }

    /**
     * Method called to associate a ChildContentVersion object to this object
     * through the ChildContentVersion foreign key attribute.
     *
     * @param    ChildContentVersion $l ChildContentVersion
     * @return   \Thelia\Model\Content The current object (for fluent API support)
     */
    public function addContentVersion(ChildContentVersion $l)
    {
        if ($this->collContentVersions === null) {
            $this->initContentVersions();
            $this->collContentVersionsPartial = true;
        }

        if (!in_array($l, $this->collContentVersions->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddContentVersion($l);
        }

        return $this;
    }

    /**
     * @param ContentVersion $contentVersion The contentVersion object to add.
     */
    protected function doAddContentVersion($contentVersion)
    {
        $this->collContentVersions[]= $contentVersion;
        $contentVersion->setContent($this);
    }

    /**
     * @param  ContentVersion $contentVersion The contentVersion object to remove.
     * @return ChildContent The current object (for fluent API support)
     */
    public function removeContentVersion($contentVersion)
    {
        if ($this->getContentVersions()->contains($contentVersion)) {
            $this->collContentVersions->remove($this->collContentVersions->search($contentVersion));
            if (null === $this->contentVersionsScheduledForDeletion) {
                $this->contentVersionsScheduledForDeletion = clone $this->collContentVersions;
                $this->contentVersionsScheduledForDeletion->clear();
            }
            $this->contentVersionsScheduledForDeletion[]= clone $contentVersion;
            $contentVersion->setContent(null);
        }

        return $this;
    }

    /**
     * Clears out the collFolders collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFolders()
     */
    public function clearFolders()
    {
        $this->collFolders = null; // important to set this to NULL since that means it is uninitialized
        $this->collFoldersPartial = null;
    }

    /**
     * Initializes the collFolders collection.
     *
     * By default this just sets the collFolders collection to an empty collection (like clearFolders());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initFolders()
    {
        $this->collFolders = new ObjectCollection();
        $this->collFolders->setModel('\Thelia\Model\Folder');
    }

    /**
     * Gets a collection of ChildFolder objects related by a many-to-many relationship
     * to the current object by way of the content_folder cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildContent is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildFolder[] List of ChildFolder objects
     */
    public function getFolders($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collFolders || null !== $criteria) {
            if ($this->isNew() && null === $this->collFolders) {
                // return empty collection
                $this->initFolders();
            } else {
                $collFolders = ChildFolderQuery::create(null, $criteria)
                    ->filterByContent($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collFolders;
                }
                $this->collFolders = $collFolders;
            }
        }

        return $this->collFolders;
    }

    /**
     * Sets a collection of Folder objects related by a many-to-many relationship
     * to the current object by way of the content_folder cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $folders A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildContent The current object (for fluent API support)
     */
    public function setFolders(Collection $folders, ConnectionInterface $con = null)
    {
        $this->clearFolders();
        $currentFolders = $this->getFolders();

        $this->foldersScheduledForDeletion = $currentFolders->diff($folders);

        foreach ($folders as $folder) {
            if (!$currentFolders->contains($folder)) {
                $this->doAddFolder($folder);
            }
        }

        $this->collFolders = $folders;

        return $this;
    }

    /**
     * Gets the number of ChildFolder objects related by a many-to-many relationship
     * to the current object by way of the content_folder cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildFolder objects
     */
    public function countFolders($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collFolders || null !== $criteria) {
            if ($this->isNew() && null === $this->collFolders) {
                return 0;
            } else {
                $query = ChildFolderQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByContent($this)
                    ->count($con);
            }
        } else {
            return count($this->collFolders);
        }
    }

    /**
     * Associate a ChildFolder object to this object
     * through the content_folder cross reference table.
     *
     * @param  ChildFolder $folder The ChildContentFolder object to relate
     * @return ChildContent The current object (for fluent API support)
     */
    public function addFolder(ChildFolder $folder)
    {
        if ($this->collFolders === null) {
            $this->initFolders();
        }

        if (!$this->collFolders->contains($folder)) { // only add it if the **same** object is not already associated
            $this->doAddFolder($folder);
            $this->collFolders[] = $folder;
        }

        return $this;
    }

    /**
     * @param    Folder $folder The folder object to add.
     */
    protected function doAddFolder($folder)
    {
        $contentFolder = new ChildContentFolder();
        $contentFolder->setFolder($folder);
        $this->addContentFolder($contentFolder);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$folder->getContents()->contains($this)) {
            $foreignCollection   = $folder->getContents();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildFolder object to this object
     * through the content_folder cross reference table.
     *
     * @param ChildFolder $folder The ChildContentFolder object to relate
     * @return ChildContent The current object (for fluent API support)
     */
    public function removeFolder(ChildFolder $folder)
    {
        if ($this->getFolders()->contains($folder)) {
            $this->collFolders->remove($this->collFolders->search($folder));

            if (null === $this->foldersScheduledForDeletion) {
                $this->foldersScheduledForDeletion = clone $this->collFolders;
                $this->foldersScheduledForDeletion->clear();
            }

            $this->foldersScheduledForDeletion[] = $folder;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->visible = null;
        $this->position = null;
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
            if ($this->collContentFolders) {
                foreach ($this->collContentFolders as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collContentImages) {
                foreach ($this->collContentImages as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collContentDocuments) {
                foreach ($this->collContentDocuments as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductAssociatedContents) {
                foreach ($this->collProductAssociatedContents as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCategoryAssociatedContents) {
                foreach ($this->collCategoryAssociatedContents as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collContentI18ns) {
                foreach ($this->collContentI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collContentVersions) {
                foreach ($this->collContentVersions as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFolders) {
                foreach ($this->collFolders as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collContentFolders = null;
        $this->collContentImages = null;
        $this->collContentDocuments = null;
        $this->collProductAssociatedContents = null;
        $this->collCategoryAssociatedContents = null;
        $this->collContentI18ns = null;
        $this->collContentVersions = null;
        $this->collFolders = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(ContentTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildContent The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[ContentTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildContent The current object (for fluent API support)
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
     * @return ChildContentI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collContentI18ns) {
                foreach ($this->collContentI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildContentI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildContentI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addContentI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildContent The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildContentI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collContentI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collContentI18ns[$key]);
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
     * @return ChildContentI18n */
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
         * @return   \Thelia\Model\ContentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ContentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ContentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ContentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ContentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ContentI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ContentI18n The current object (for fluent API support)
         */
        public function setMetaKeywords($v)
        {    $this->getCurrentTranslation()->setMetaKeywords($v);

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return \Thelia\Model\Content
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

        if (ChildContentQuery::isVersioningEnabled() && ($this->isNew() || $this->isModified()) || $this->isDeleted()) {
            return true;
        }

        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildContentVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new ChildContentVersion();
        $version->setId($this->getId());
        $version->setVisible($this->getVisible());
        $version->setPosition($this->getPosition());
        $version->setCreatedAt($this->getCreatedAt());
        $version->setUpdatedAt($this->getUpdatedAt());
        $version->setVersion($this->getVersion());
        $version->setVersionCreatedAt($this->getVersionCreatedAt());
        $version->setVersionCreatedBy($this->getVersionCreatedBy());
        $version->setContent($this);
        $version->save($con);

        return $version;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con The connection to use
     *
     * @return  ChildContent The current object (for fluent API support)
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No ChildContent object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param ChildContentVersion $version The version object to use
     * @param ConnectionInterface   $con the connection to use
     * @param array                 $loadedObjects objects that been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return ChildContent The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {
        $loadedObjects['ChildContent'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
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
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  integer
     */
    public function getLastVersionNumber($con = null)
    {
        $v = ChildContentVersionQuery::create()
            ->filterByContent($this)
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
     * @return  ChildContentVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return ChildContentVersionQuery::create()
            ->filterByContent($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ObjectCollection A list of ChildContentVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(ContentVersionTableMap::VERSION);

        return $this->getContentVersions($criteria, $con);
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
     * @return PropelCollection|array \Thelia\Model\ContentVersion[] List of \Thelia\Model\ContentVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, $con = null)
    {
        $criteria = ChildContentVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(ContentVersionTableMap::VERSION);
        $criteria->limit($number);

        return $this->getContentVersions($criteria, $con);
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
