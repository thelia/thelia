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
use Thelia\Model\AreaDeliveryModule as ChildAreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery as ChildAreaDeliveryModuleQuery;
use Thelia\Model\Coupon as ChildCoupon;
use Thelia\Model\CouponModule as ChildCouponModule;
use Thelia\Model\CouponModuleQuery as ChildCouponModuleQuery;
use Thelia\Model\CouponQuery as ChildCouponQuery;
use Thelia\Model\Hook as ChildHook;
use Thelia\Model\HookQuery as ChildHookQuery;
use Thelia\Model\IgnoredModuleHook as ChildIgnoredModuleHook;
use Thelia\Model\IgnoredModuleHookQuery as ChildIgnoredModuleHookQuery;
use Thelia\Model\Module as ChildModule;
use Thelia\Model\ModuleConfig as ChildModuleConfig;
use Thelia\Model\ModuleConfigQuery as ChildModuleConfigQuery;
use Thelia\Model\ModuleHook as ChildModuleHook;
use Thelia\Model\ModuleHookQuery as ChildModuleHookQuery;
use Thelia\Model\ModuleI18n as ChildModuleI18n;
use Thelia\Model\ModuleI18nQuery as ChildModuleI18nQuery;
use Thelia\Model\ModuleImage as ChildModuleImage;
use Thelia\Model\ModuleImageQuery as ChildModuleImageQuery;
use Thelia\Model\ModuleQuery as ChildModuleQuery;
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderCoupon as ChildOrderCoupon;
use Thelia\Model\OrderCouponModule as ChildOrderCouponModule;
use Thelia\Model\OrderCouponModuleQuery as ChildOrderCouponModuleQuery;
use Thelia\Model\OrderCouponQuery as ChildOrderCouponQuery;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\ProfileModule as ChildProfileModule;
use Thelia\Model\ProfileModuleQuery as ChildProfileModuleQuery;
use Thelia\Model\Map\ModuleTableMap;

abstract class Module implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\ModuleTableMap';


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
     * The value for the code field.
     * @var        string
     */
    protected $code;

    /**
     * The value for the version field.
     * Note: this column has a database default value of: ''
     * @var        string
     */
    protected $version;

    /**
     * The value for the type field.
     * @var        int
     */
    protected $type;

    /**
     * The value for the category field.
     * Note: this column has a database default value of: 'classic'
     * @var        string
     */
    protected $category;

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
     * The value for the full_namespace field.
     * @var        string
     */
    protected $full_namespace;

    /**
     * The value for the mandatory field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $mandatory;

    /**
     * The value for the hidden field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $hidden;

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
     * @var        ObjectCollection|ChildOrder[] Collection to store aggregation of ChildOrder objects.
     */
    protected $collOrdersRelatedByPaymentModuleId;
    protected $collOrdersRelatedByPaymentModuleIdPartial;

    /**
     * @var        ObjectCollection|ChildOrder[] Collection to store aggregation of ChildOrder objects.
     */
    protected $collOrdersRelatedByDeliveryModuleId;
    protected $collOrdersRelatedByDeliveryModuleIdPartial;

    /**
     * @var        ObjectCollection|ChildAreaDeliveryModule[] Collection to store aggregation of ChildAreaDeliveryModule objects.
     */
    protected $collAreaDeliveryModules;
    protected $collAreaDeliveryModulesPartial;

    /**
     * @var        ObjectCollection|ChildProfileModule[] Collection to store aggregation of ChildProfileModule objects.
     */
    protected $collProfileModules;
    protected $collProfileModulesPartial;

    /**
     * @var        ObjectCollection|ChildModuleImage[] Collection to store aggregation of ChildModuleImage objects.
     */
    protected $collModuleImages;
    protected $collModuleImagesPartial;

    /**
     * @var        ObjectCollection|ChildCouponModule[] Collection to store aggregation of ChildCouponModule objects.
     */
    protected $collCouponModules;
    protected $collCouponModulesPartial;

    /**
     * @var        ObjectCollection|ChildOrderCouponModule[] Collection to store aggregation of ChildOrderCouponModule objects.
     */
    protected $collOrderCouponModules;
    protected $collOrderCouponModulesPartial;

    /**
     * @var        ObjectCollection|ChildModuleHook[] Collection to store aggregation of ChildModuleHook objects.
     */
    protected $collModuleHooks;
    protected $collModuleHooksPartial;

    /**
     * @var        ObjectCollection|ChildModuleConfig[] Collection to store aggregation of ChildModuleConfig objects.
     */
    protected $collModuleConfigs;
    protected $collModuleConfigsPartial;

    /**
     * @var        ObjectCollection|ChildIgnoredModuleHook[] Collection to store aggregation of ChildIgnoredModuleHook objects.
     */
    protected $collIgnoredModuleHooks;
    protected $collIgnoredModuleHooksPartial;

    /**
     * @var        ObjectCollection|ChildModuleI18n[] Collection to store aggregation of ChildModuleI18n objects.
     */
    protected $collModuleI18ns;
    protected $collModuleI18nsPartial;

    /**
     * @var        ChildCoupon[] Collection to store aggregation of ChildCoupon objects.
     */
    protected $collCoupons;

    /**
     * @var        ChildOrderCoupon[] Collection to store aggregation of ChildOrderCoupon objects.
     */
    protected $collOrderCoupons;

    /**
     * @var        ChildHook[] Collection to store aggregation of ChildHook objects.
     */
    protected $collHooks;

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
     * @var        array[ChildModuleI18n]
     */
    protected $currentTranslations;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderCouponsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $hooksScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $ordersRelatedByPaymentModuleIdScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $ordersRelatedByDeliveryModuleIdScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $areaDeliveryModulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $profileModulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $moduleImagesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponModulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderCouponModulesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $moduleHooksScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $moduleConfigsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $ignoredModuleHooksScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $moduleI18nsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->version = '';
        $this->category = 'classic';
        $this->mandatory = 0;
        $this->hidden = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Module object.
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
     * Compares this with another <code>Module</code> instance.  If
     * <code>obj</code> is an instance of <code>Module</code>, delegates to
     * <code>equals(Module)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Module The current object, for fluid interface
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
     * @return Module The current object, for fluid interface
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
     * Get the [code] column value.
     *
     * @return   string
     */
    public function getCode()
    {

        return $this->code;
    }

    /**
     * Get the [version] column value.
     *
     * @return   string
     */
    public function getVersion()
    {

        return $this->version;
    }

    /**
     * Get the [type] column value.
     *
     * @return   int
     */
    public function getType()
    {

        return $this->type;
    }

    /**
     * Get the [category] column value.
     *
     * @return   string
     */
    public function getCategory()
    {

        return $this->category;
    }

    /**
     * Get the [activate] column value.
     *
     * @return   int
     */
    public function getActivate()
    {

        return $this->activate;
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
     * Get the [full_namespace] column value.
     *
     * @return   string
     */
    public function getFullNamespace()
    {

        return $this->full_namespace;
    }

    /**
     * Get the [mandatory] column value.
     *
     * @return   int
     */
    public function getMandatory()
    {

        return $this->mandatory;
    }

    /**
     * Get the [hidden] column value.
     *
     * @return   int
     */
    public function getHidden()
    {

        return $this->hidden;
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
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[ModuleTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [code] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setCode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->code !== $v) {
            $this->code = $v;
            $this->modifiedColumns[ModuleTableMap::CODE] = true;
        }


        return $this;
    } // setCode()

    /**
     * Set the value of [version] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[ModuleTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Set the value of [type] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->type !== $v) {
            $this->type = $v;
            $this->modifiedColumns[ModuleTableMap::TYPE] = true;
        }


        return $this;
    } // setType()

    /**
     * Set the value of [category] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setCategory($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->category !== $v) {
            $this->category = $v;
            $this->modifiedColumns[ModuleTableMap::CATEGORY] = true;
        }


        return $this;
    } // setCategory()

    /**
     * Set the value of [activate] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setActivate($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->activate !== $v) {
            $this->activate = $v;
            $this->modifiedColumns[ModuleTableMap::ACTIVATE] = true;
        }


        return $this;
    } // setActivate()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[ModuleTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Set the value of [full_namespace] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setFullNamespace($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->full_namespace !== $v) {
            $this->full_namespace = $v;
            $this->modifiedColumns[ModuleTableMap::FULL_NAMESPACE] = true;
        }


        return $this;
    } // setFullNamespace()

    /**
     * Set the value of [mandatory] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setMandatory($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->mandatory !== $v) {
            $this->mandatory = $v;
            $this->modifiedColumns[ModuleTableMap::MANDATORY] = true;
        }


        return $this;
    } // setMandatory()

    /**
     * Set the value of [hidden] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setHidden($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->hidden !== $v) {
            $this->hidden = $v;
            $this->modifiedColumns[ModuleTableMap::HIDDEN] = true;
        }


        return $this;
    } // setHidden()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[ModuleTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[ModuleTableMap::UPDATED_AT] = true;
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
            if ($this->version !== '') {
                return false;
            }

            if ($this->category !== 'classic') {
                return false;
            }

            if ($this->mandatory !== 0) {
                return false;
            }

            if ($this->hidden !== 0) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : ModuleTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : ModuleTableMap::translateFieldName('Code', TableMap::TYPE_PHPNAME, $indexType)];
            $this->code = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : ModuleTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : ModuleTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->type = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : ModuleTableMap::translateFieldName('Category', TableMap::TYPE_PHPNAME, $indexType)];
            $this->category = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : ModuleTableMap::translateFieldName('Activate', TableMap::TYPE_PHPNAME, $indexType)];
            $this->activate = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : ModuleTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : ModuleTableMap::translateFieldName('FullNamespace', TableMap::TYPE_PHPNAME, $indexType)];
            $this->full_namespace = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : ModuleTableMap::translateFieldName('Mandatory', TableMap::TYPE_PHPNAME, $indexType)];
            $this->mandatory = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : ModuleTableMap::translateFieldName('Hidden', TableMap::TYPE_PHPNAME, $indexType)];
            $this->hidden = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : ModuleTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : ModuleTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 12; // 12 = ModuleTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Module object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(ModuleTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildModuleQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collOrdersRelatedByPaymentModuleId = null;

            $this->collOrdersRelatedByDeliveryModuleId = null;

            $this->collAreaDeliveryModules = null;

            $this->collProfileModules = null;

            $this->collModuleImages = null;

            $this->collCouponModules = null;

            $this->collOrderCouponModules = null;

            $this->collModuleHooks = null;

            $this->collModuleConfigs = null;

            $this->collIgnoredModuleHooks = null;

            $this->collModuleI18ns = null;

            $this->collCoupons = null;
            $this->collOrderCoupons = null;
            $this->collHooks = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Module::setDeleted()
     * @see Module::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(ModuleTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildModuleQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(ModuleTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(ModuleTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(ModuleTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(ModuleTableMap::UPDATED_AT)) {
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
                ModuleTableMap::addInstanceToPool($this);
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

            if ($this->couponsScheduledForDeletion !== null) {
                if (!$this->couponsScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->couponsScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    CouponModuleQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->couponsScheduledForDeletion = null;
                }

                foreach ($this->getCoupons() as $coupon) {
                    if ($coupon->isModified()) {
                        $coupon->save($con);
                    }
                }
            } elseif ($this->collCoupons) {
                foreach ($this->collCoupons as $coupon) {
                    if ($coupon->isModified()) {
                        $coupon->save($con);
                    }
                }
            }

            if ($this->orderCouponsScheduledForDeletion !== null) {
                if (!$this->orderCouponsScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->orderCouponsScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    OrderCouponModuleQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->orderCouponsScheduledForDeletion = null;
                }

                foreach ($this->getOrderCoupons() as $orderCoupon) {
                    if ($orderCoupon->isModified()) {
                        $orderCoupon->save($con);
                    }
                }
            } elseif ($this->collOrderCoupons) {
                foreach ($this->collOrderCoupons as $orderCoupon) {
                    if ($orderCoupon->isModified()) {
                        $orderCoupon->save($con);
                    }
                }
            }

            if ($this->hooksScheduledForDeletion !== null) {
                if (!$this->hooksScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->hooksScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    IgnoredModuleHookQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->hooksScheduledForDeletion = null;
                }

                foreach ($this->getHooks() as $hook) {
                    if ($hook->isModified()) {
                        $hook->save($con);
                    }
                }
            } elseif ($this->collHooks) {
                foreach ($this->collHooks as $hook) {
                    if ($hook->isModified()) {
                        $hook->save($con);
                    }
                }
            }

            if ($this->ordersRelatedByPaymentModuleIdScheduledForDeletion !== null) {
                if (!$this->ordersRelatedByPaymentModuleIdScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderQuery::create()
                        ->filterByPrimaryKeys($this->ordersRelatedByPaymentModuleIdScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->ordersRelatedByPaymentModuleIdScheduledForDeletion = null;
                }
            }

                if ($this->collOrdersRelatedByPaymentModuleId !== null) {
            foreach ($this->collOrdersRelatedByPaymentModuleId as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->ordersRelatedByDeliveryModuleIdScheduledForDeletion !== null) {
                if (!$this->ordersRelatedByDeliveryModuleIdScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderQuery::create()
                        ->filterByPrimaryKeys($this->ordersRelatedByDeliveryModuleIdScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->ordersRelatedByDeliveryModuleIdScheduledForDeletion = null;
                }
            }

                if ($this->collOrdersRelatedByDeliveryModuleId !== null) {
            foreach ($this->collOrdersRelatedByDeliveryModuleId as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->areaDeliveryModulesScheduledForDeletion !== null) {
                if (!$this->areaDeliveryModulesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AreaDeliveryModuleQuery::create()
                        ->filterByPrimaryKeys($this->areaDeliveryModulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->areaDeliveryModulesScheduledForDeletion = null;
                }
            }

                if ($this->collAreaDeliveryModules !== null) {
            foreach ($this->collAreaDeliveryModules as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->profileModulesScheduledForDeletion !== null) {
                if (!$this->profileModulesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProfileModuleQuery::create()
                        ->filterByPrimaryKeys($this->profileModulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->profileModulesScheduledForDeletion = null;
                }
            }

                if ($this->collProfileModules !== null) {
            foreach ($this->collProfileModules as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->moduleImagesScheduledForDeletion !== null) {
                if (!$this->moduleImagesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ModuleImageQuery::create()
                        ->filterByPrimaryKeys($this->moduleImagesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->moduleImagesScheduledForDeletion = null;
                }
            }

                if ($this->collModuleImages !== null) {
            foreach ($this->collModuleImages as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->couponModulesScheduledForDeletion !== null) {
                if (!$this->couponModulesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CouponModuleQuery::create()
                        ->filterByPrimaryKeys($this->couponModulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->couponModulesScheduledForDeletion = null;
                }
            }

                if ($this->collCouponModules !== null) {
            foreach ($this->collCouponModules as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->orderCouponModulesScheduledForDeletion !== null) {
                if (!$this->orderCouponModulesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderCouponModuleQuery::create()
                        ->filterByPrimaryKeys($this->orderCouponModulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderCouponModulesScheduledForDeletion = null;
                }
            }

                if ($this->collOrderCouponModules !== null) {
            foreach ($this->collOrderCouponModules as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->moduleHooksScheduledForDeletion !== null) {
                if (!$this->moduleHooksScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ModuleHookQuery::create()
                        ->filterByPrimaryKeys($this->moduleHooksScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->moduleHooksScheduledForDeletion = null;
                }
            }

                if ($this->collModuleHooks !== null) {
            foreach ($this->collModuleHooks as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->moduleConfigsScheduledForDeletion !== null) {
                if (!$this->moduleConfigsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ModuleConfigQuery::create()
                        ->filterByPrimaryKeys($this->moduleConfigsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->moduleConfigsScheduledForDeletion = null;
                }
            }

                if ($this->collModuleConfigs !== null) {
            foreach ($this->collModuleConfigs as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->ignoredModuleHooksScheduledForDeletion !== null) {
                if (!$this->ignoredModuleHooksScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\IgnoredModuleHookQuery::create()
                        ->filterByPrimaryKeys($this->ignoredModuleHooksScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->ignoredModuleHooksScheduledForDeletion = null;
                }
            }

                if ($this->collIgnoredModuleHooks !== null) {
            foreach ($this->collIgnoredModuleHooks as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->moduleI18nsScheduledForDeletion !== null) {
                if (!$this->moduleI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ModuleI18nQuery::create()
                        ->filterByPrimaryKeys($this->moduleI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->moduleI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collModuleI18ns !== null) {
            foreach ($this->collModuleI18ns as $referrerFK) {
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

        $this->modifiedColumns[ModuleTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . ModuleTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(ModuleTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(ModuleTableMap::CODE)) {
            $modifiedColumns[':p' . $index++]  = '`CODE`';
        }
        if ($this->isColumnModified(ModuleTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(ModuleTableMap::TYPE)) {
            $modifiedColumns[':p' . $index++]  = '`TYPE`';
        }
        if ($this->isColumnModified(ModuleTableMap::CATEGORY)) {
            $modifiedColumns[':p' . $index++]  = '`CATEGORY`';
        }
        if ($this->isColumnModified(ModuleTableMap::ACTIVATE)) {
            $modifiedColumns[':p' . $index++]  = '`ACTIVATE`';
        }
        if ($this->isColumnModified(ModuleTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(ModuleTableMap::FULL_NAMESPACE)) {
            $modifiedColumns[':p' . $index++]  = '`FULL_NAMESPACE`';
        }
        if ($this->isColumnModified(ModuleTableMap::MANDATORY)) {
            $modifiedColumns[':p' . $index++]  = '`MANDATORY`';
        }
        if ($this->isColumnModified(ModuleTableMap::HIDDEN)) {
            $modifiedColumns[':p' . $index++]  = '`HIDDEN`';
        }
        if ($this->isColumnModified(ModuleTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(ModuleTableMap::UPDATED_AT)) {
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
                    case '`VERSION`':
                        $stmt->bindValue($identifier, $this->version, PDO::PARAM_STR);
                        break;
                    case '`TYPE`':
                        $stmt->bindValue($identifier, $this->type, PDO::PARAM_INT);
                        break;
                    case '`CATEGORY`':
                        $stmt->bindValue($identifier, $this->category, PDO::PARAM_STR);
                        break;
                    case '`ACTIVATE`':
                        $stmt->bindValue($identifier, $this->activate, PDO::PARAM_INT);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
                        break;
                    case '`FULL_NAMESPACE`':
                        $stmt->bindValue($identifier, $this->full_namespace, PDO::PARAM_STR);
                        break;
                    case '`MANDATORY`':
                        $stmt->bindValue($identifier, $this->mandatory, PDO::PARAM_INT);
                        break;
                    case '`HIDDEN`':
                        $stmt->bindValue($identifier, $this->hidden, PDO::PARAM_INT);
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
        $pos = ModuleTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getCode();
                break;
            case 2:
                return $this->getVersion();
                break;
            case 3:
                return $this->getType();
                break;
            case 4:
                return $this->getCategory();
                break;
            case 5:
                return $this->getActivate();
                break;
            case 6:
                return $this->getPosition();
                break;
            case 7:
                return $this->getFullNamespace();
                break;
            case 8:
                return $this->getMandatory();
                break;
            case 9:
                return $this->getHidden();
                break;
            case 10:
                return $this->getCreatedAt();
                break;
            case 11:
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
        if (isset($alreadyDumpedObjects['Module'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Module'][$this->getPrimaryKey()] = true;
        $keys = ModuleTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCode(),
            $keys[2] => $this->getVersion(),
            $keys[3] => $this->getType(),
            $keys[4] => $this->getCategory(),
            $keys[5] => $this->getActivate(),
            $keys[6] => $this->getPosition(),
            $keys[7] => $this->getFullNamespace(),
            $keys[8] => $this->getMandatory(),
            $keys[9] => $this->getHidden(),
            $keys[10] => $this->getCreatedAt(),
            $keys[11] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collOrdersRelatedByPaymentModuleId) {
                $result['OrdersRelatedByPaymentModuleId'] = $this->collOrdersRelatedByPaymentModuleId->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrdersRelatedByDeliveryModuleId) {
                $result['OrdersRelatedByDeliveryModuleId'] = $this->collOrdersRelatedByDeliveryModuleId->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAreaDeliveryModules) {
                $result['AreaDeliveryModules'] = $this->collAreaDeliveryModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProfileModules) {
                $result['ProfileModules'] = $this->collProfileModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collModuleImages) {
                $result['ModuleImages'] = $this->collModuleImages->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCouponModules) {
                $result['CouponModules'] = $this->collCouponModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrderCouponModules) {
                $result['OrderCouponModules'] = $this->collOrderCouponModules->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collModuleHooks) {
                $result['ModuleHooks'] = $this->collModuleHooks->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collModuleConfigs) {
                $result['ModuleConfigs'] = $this->collModuleConfigs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collIgnoredModuleHooks) {
                $result['IgnoredModuleHooks'] = $this->collIgnoredModuleHooks->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collModuleI18ns) {
                $result['ModuleI18ns'] = $this->collModuleI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = ModuleTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setCode($value);
                break;
            case 2:
                $this->setVersion($value);
                break;
            case 3:
                $this->setType($value);
                break;
            case 4:
                $this->setCategory($value);
                break;
            case 5:
                $this->setActivate($value);
                break;
            case 6:
                $this->setPosition($value);
                break;
            case 7:
                $this->setFullNamespace($value);
                break;
            case 8:
                $this->setMandatory($value);
                break;
            case 9:
                $this->setHidden($value);
                break;
            case 10:
                $this->setCreatedAt($value);
                break;
            case 11:
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
        $keys = ModuleTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCode($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setVersion($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setType($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setCategory($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setActivate($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setPosition($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setFullNamespace($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setMandatory($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setHidden($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setCreatedAt($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setUpdatedAt($arr[$keys[11]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(ModuleTableMap::DATABASE_NAME);

        if ($this->isColumnModified(ModuleTableMap::ID)) $criteria->add(ModuleTableMap::ID, $this->id);
        if ($this->isColumnModified(ModuleTableMap::CODE)) $criteria->add(ModuleTableMap::CODE, $this->code);
        if ($this->isColumnModified(ModuleTableMap::VERSION)) $criteria->add(ModuleTableMap::VERSION, $this->version);
        if ($this->isColumnModified(ModuleTableMap::TYPE)) $criteria->add(ModuleTableMap::TYPE, $this->type);
        if ($this->isColumnModified(ModuleTableMap::CATEGORY)) $criteria->add(ModuleTableMap::CATEGORY, $this->category);
        if ($this->isColumnModified(ModuleTableMap::ACTIVATE)) $criteria->add(ModuleTableMap::ACTIVATE, $this->activate);
        if ($this->isColumnModified(ModuleTableMap::POSITION)) $criteria->add(ModuleTableMap::POSITION, $this->position);
        if ($this->isColumnModified(ModuleTableMap::FULL_NAMESPACE)) $criteria->add(ModuleTableMap::FULL_NAMESPACE, $this->full_namespace);
        if ($this->isColumnModified(ModuleTableMap::MANDATORY)) $criteria->add(ModuleTableMap::MANDATORY, $this->mandatory);
        if ($this->isColumnModified(ModuleTableMap::HIDDEN)) $criteria->add(ModuleTableMap::HIDDEN, $this->hidden);
        if ($this->isColumnModified(ModuleTableMap::CREATED_AT)) $criteria->add(ModuleTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(ModuleTableMap::UPDATED_AT)) $criteria->add(ModuleTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(ModuleTableMap::DATABASE_NAME);
        $criteria->add(ModuleTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Module (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCode($this->getCode());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setType($this->getType());
        $copyObj->setCategory($this->getCategory());
        $copyObj->setActivate($this->getActivate());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setFullNamespace($this->getFullNamespace());
        $copyObj->setMandatory($this->getMandatory());
        $copyObj->setHidden($this->getHidden());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getOrdersRelatedByPaymentModuleId() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderRelatedByPaymentModuleId($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrdersRelatedByDeliveryModuleId() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderRelatedByDeliveryModuleId($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAreaDeliveryModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAreaDeliveryModule($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProfileModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProfileModule($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getModuleImages() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addModuleImage($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCouponModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCouponModule($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrderCouponModules() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderCouponModule($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getModuleHooks() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addModuleHook($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getModuleConfigs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addModuleConfig($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getIgnoredModuleHooks() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addIgnoredModuleHook($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getModuleI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addModuleI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Module Clone of current object.
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
        if ('OrderRelatedByPaymentModuleId' == $relationName) {
            return $this->initOrdersRelatedByPaymentModuleId();
        }
        if ('OrderRelatedByDeliveryModuleId' == $relationName) {
            return $this->initOrdersRelatedByDeliveryModuleId();
        }
        if ('AreaDeliveryModule' == $relationName) {
            return $this->initAreaDeliveryModules();
        }
        if ('ProfileModule' == $relationName) {
            return $this->initProfileModules();
        }
        if ('ModuleImage' == $relationName) {
            return $this->initModuleImages();
        }
        if ('CouponModule' == $relationName) {
            return $this->initCouponModules();
        }
        if ('OrderCouponModule' == $relationName) {
            return $this->initOrderCouponModules();
        }
        if ('ModuleHook' == $relationName) {
            return $this->initModuleHooks();
        }
        if ('ModuleConfig' == $relationName) {
            return $this->initModuleConfigs();
        }
        if ('IgnoredModuleHook' == $relationName) {
            return $this->initIgnoredModuleHooks();
        }
        if ('ModuleI18n' == $relationName) {
            return $this->initModuleI18ns();
        }
    }

    /**
     * Clears out the collOrdersRelatedByPaymentModuleId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrdersRelatedByPaymentModuleId()
     */
    public function clearOrdersRelatedByPaymentModuleId()
    {
        $this->collOrdersRelatedByPaymentModuleId = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrdersRelatedByPaymentModuleId collection loaded partially.
     */
    public function resetPartialOrdersRelatedByPaymentModuleId($v = true)
    {
        $this->collOrdersRelatedByPaymentModuleIdPartial = $v;
    }

    /**
     * Initializes the collOrdersRelatedByPaymentModuleId collection.
     *
     * By default this just sets the collOrdersRelatedByPaymentModuleId collection to an empty array (like clearcollOrdersRelatedByPaymentModuleId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrdersRelatedByPaymentModuleId($overrideExisting = true)
    {
        if (null !== $this->collOrdersRelatedByPaymentModuleId && !$overrideExisting) {
            return;
        }
        $this->collOrdersRelatedByPaymentModuleId = new ObjectCollection();
        $this->collOrdersRelatedByPaymentModuleId->setModel('\Thelia\Model\Order');
    }

    /**
     * Gets an array of ChildOrder objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrder[] List of ChildOrder objects
     * @throws PropelException
     */
    public function getOrdersRelatedByPaymentModuleId($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersRelatedByPaymentModuleIdPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByPaymentModuleId || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByPaymentModuleId) {
                // return empty collection
                $this->initOrdersRelatedByPaymentModuleId();
            } else {
                $collOrdersRelatedByPaymentModuleId = ChildOrderQuery::create(null, $criteria)
                    ->filterByModuleRelatedByPaymentModuleId($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrdersRelatedByPaymentModuleIdPartial && count($collOrdersRelatedByPaymentModuleId)) {
                        $this->initOrdersRelatedByPaymentModuleId(false);

                        foreach ($collOrdersRelatedByPaymentModuleId as $obj) {
                            if (false == $this->collOrdersRelatedByPaymentModuleId->contains($obj)) {
                                $this->collOrdersRelatedByPaymentModuleId->append($obj);
                            }
                        }

                        $this->collOrdersRelatedByPaymentModuleIdPartial = true;
                    }

                    reset($collOrdersRelatedByPaymentModuleId);

                    return $collOrdersRelatedByPaymentModuleId;
                }

                if ($partial && $this->collOrdersRelatedByPaymentModuleId) {
                    foreach ($this->collOrdersRelatedByPaymentModuleId as $obj) {
                        if ($obj->isNew()) {
                            $collOrdersRelatedByPaymentModuleId[] = $obj;
                        }
                    }
                }

                $this->collOrdersRelatedByPaymentModuleId = $collOrdersRelatedByPaymentModuleId;
                $this->collOrdersRelatedByPaymentModuleIdPartial = false;
            }
        }

        return $this->collOrdersRelatedByPaymentModuleId;
    }

    /**
     * Sets a collection of OrderRelatedByPaymentModuleId objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $ordersRelatedByPaymentModuleId A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setOrdersRelatedByPaymentModuleId(Collection $ordersRelatedByPaymentModuleId, ConnectionInterface $con = null)
    {
        $ordersRelatedByPaymentModuleIdToDelete = $this->getOrdersRelatedByPaymentModuleId(new Criteria(), $con)->diff($ordersRelatedByPaymentModuleId);


        $this->ordersRelatedByPaymentModuleIdScheduledForDeletion = $ordersRelatedByPaymentModuleIdToDelete;

        foreach ($ordersRelatedByPaymentModuleIdToDelete as $orderRelatedByPaymentModuleIdRemoved) {
            $orderRelatedByPaymentModuleIdRemoved->setModuleRelatedByPaymentModuleId(null);
        }

        $this->collOrdersRelatedByPaymentModuleId = null;
        foreach ($ordersRelatedByPaymentModuleId as $orderRelatedByPaymentModuleId) {
            $this->addOrderRelatedByPaymentModuleId($orderRelatedByPaymentModuleId);
        }

        $this->collOrdersRelatedByPaymentModuleId = $ordersRelatedByPaymentModuleId;
        $this->collOrdersRelatedByPaymentModuleIdPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Order objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Order objects.
     * @throws PropelException
     */
    public function countOrdersRelatedByPaymentModuleId(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersRelatedByPaymentModuleIdPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByPaymentModuleId || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByPaymentModuleId) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrdersRelatedByPaymentModuleId());
            }

            $query = ChildOrderQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModuleRelatedByPaymentModuleId($this)
                ->count($con);
        }

        return count($this->collOrdersRelatedByPaymentModuleId);
    }

    /**
     * Method called to associate a ChildOrder object to this object
     * through the ChildOrder foreign key attribute.
     *
     * @param    ChildOrder $l ChildOrder
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addOrderRelatedByPaymentModuleId(ChildOrder $l)
    {
        if ($this->collOrdersRelatedByPaymentModuleId === null) {
            $this->initOrdersRelatedByPaymentModuleId();
            $this->collOrdersRelatedByPaymentModuleIdPartial = true;
        }

        if (!in_array($l, $this->collOrdersRelatedByPaymentModuleId->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderRelatedByPaymentModuleId($l);
        }

        return $this;
    }

    /**
     * @param OrderRelatedByPaymentModuleId $orderRelatedByPaymentModuleId The orderRelatedByPaymentModuleId object to add.
     */
    protected function doAddOrderRelatedByPaymentModuleId($orderRelatedByPaymentModuleId)
    {
        $this->collOrdersRelatedByPaymentModuleId[]= $orderRelatedByPaymentModuleId;
        $orderRelatedByPaymentModuleId->setModuleRelatedByPaymentModuleId($this);
    }

    /**
     * @param  OrderRelatedByPaymentModuleId $orderRelatedByPaymentModuleId The orderRelatedByPaymentModuleId object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeOrderRelatedByPaymentModuleId($orderRelatedByPaymentModuleId)
    {
        if ($this->getOrdersRelatedByPaymentModuleId()->contains($orderRelatedByPaymentModuleId)) {
            $this->collOrdersRelatedByPaymentModuleId->remove($this->collOrdersRelatedByPaymentModuleId->search($orderRelatedByPaymentModuleId));
            if (null === $this->ordersRelatedByPaymentModuleIdScheduledForDeletion) {
                $this->ordersRelatedByPaymentModuleIdScheduledForDeletion = clone $this->collOrdersRelatedByPaymentModuleId;
                $this->ordersRelatedByPaymentModuleIdScheduledForDeletion->clear();
            }
            $this->ordersRelatedByPaymentModuleIdScheduledForDeletion[]= clone $orderRelatedByPaymentModuleId;
            $orderRelatedByPaymentModuleId->setModuleRelatedByPaymentModuleId(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByPaymentModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByPaymentModuleIdJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getOrdersRelatedByPaymentModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByPaymentModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByPaymentModuleIdJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getOrdersRelatedByPaymentModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByPaymentModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByPaymentModuleIdJoinOrderAddressRelatedByInvoiceOrderAddressId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderAddressRelatedByInvoiceOrderAddressId', $joinBehavior);

        return $this->getOrdersRelatedByPaymentModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByPaymentModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByPaymentModuleIdJoinOrderAddressRelatedByDeliveryOrderAddressId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderAddressRelatedByDeliveryOrderAddressId', $joinBehavior);

        return $this->getOrdersRelatedByPaymentModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByPaymentModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByPaymentModuleIdJoinOrderStatus($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderStatus', $joinBehavior);

        return $this->getOrdersRelatedByPaymentModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByPaymentModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByPaymentModuleIdJoinLang($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Lang', $joinBehavior);

        return $this->getOrdersRelatedByPaymentModuleId($query, $con);
    }

    /**
     * Clears out the collOrdersRelatedByDeliveryModuleId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrdersRelatedByDeliveryModuleId()
     */
    public function clearOrdersRelatedByDeliveryModuleId()
    {
        $this->collOrdersRelatedByDeliveryModuleId = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrdersRelatedByDeliveryModuleId collection loaded partially.
     */
    public function resetPartialOrdersRelatedByDeliveryModuleId($v = true)
    {
        $this->collOrdersRelatedByDeliveryModuleIdPartial = $v;
    }

    /**
     * Initializes the collOrdersRelatedByDeliveryModuleId collection.
     *
     * By default this just sets the collOrdersRelatedByDeliveryModuleId collection to an empty array (like clearcollOrdersRelatedByDeliveryModuleId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrdersRelatedByDeliveryModuleId($overrideExisting = true)
    {
        if (null !== $this->collOrdersRelatedByDeliveryModuleId && !$overrideExisting) {
            return;
        }
        $this->collOrdersRelatedByDeliveryModuleId = new ObjectCollection();
        $this->collOrdersRelatedByDeliveryModuleId->setModel('\Thelia\Model\Order');
    }

    /**
     * Gets an array of ChildOrder objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrder[] List of ChildOrder objects
     * @throws PropelException
     */
    public function getOrdersRelatedByDeliveryModuleId($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersRelatedByDeliveryModuleIdPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByDeliveryModuleId || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByDeliveryModuleId) {
                // return empty collection
                $this->initOrdersRelatedByDeliveryModuleId();
            } else {
                $collOrdersRelatedByDeliveryModuleId = ChildOrderQuery::create(null, $criteria)
                    ->filterByModuleRelatedByDeliveryModuleId($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrdersRelatedByDeliveryModuleIdPartial && count($collOrdersRelatedByDeliveryModuleId)) {
                        $this->initOrdersRelatedByDeliveryModuleId(false);

                        foreach ($collOrdersRelatedByDeliveryModuleId as $obj) {
                            if (false == $this->collOrdersRelatedByDeliveryModuleId->contains($obj)) {
                                $this->collOrdersRelatedByDeliveryModuleId->append($obj);
                            }
                        }

                        $this->collOrdersRelatedByDeliveryModuleIdPartial = true;
                    }

                    reset($collOrdersRelatedByDeliveryModuleId);

                    return $collOrdersRelatedByDeliveryModuleId;
                }

                if ($partial && $this->collOrdersRelatedByDeliveryModuleId) {
                    foreach ($this->collOrdersRelatedByDeliveryModuleId as $obj) {
                        if ($obj->isNew()) {
                            $collOrdersRelatedByDeliveryModuleId[] = $obj;
                        }
                    }
                }

                $this->collOrdersRelatedByDeliveryModuleId = $collOrdersRelatedByDeliveryModuleId;
                $this->collOrdersRelatedByDeliveryModuleIdPartial = false;
            }
        }

        return $this->collOrdersRelatedByDeliveryModuleId;
    }

    /**
     * Sets a collection of OrderRelatedByDeliveryModuleId objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $ordersRelatedByDeliveryModuleId A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setOrdersRelatedByDeliveryModuleId(Collection $ordersRelatedByDeliveryModuleId, ConnectionInterface $con = null)
    {
        $ordersRelatedByDeliveryModuleIdToDelete = $this->getOrdersRelatedByDeliveryModuleId(new Criteria(), $con)->diff($ordersRelatedByDeliveryModuleId);


        $this->ordersRelatedByDeliveryModuleIdScheduledForDeletion = $ordersRelatedByDeliveryModuleIdToDelete;

        foreach ($ordersRelatedByDeliveryModuleIdToDelete as $orderRelatedByDeliveryModuleIdRemoved) {
            $orderRelatedByDeliveryModuleIdRemoved->setModuleRelatedByDeliveryModuleId(null);
        }

        $this->collOrdersRelatedByDeliveryModuleId = null;
        foreach ($ordersRelatedByDeliveryModuleId as $orderRelatedByDeliveryModuleId) {
            $this->addOrderRelatedByDeliveryModuleId($orderRelatedByDeliveryModuleId);
        }

        $this->collOrdersRelatedByDeliveryModuleId = $ordersRelatedByDeliveryModuleId;
        $this->collOrdersRelatedByDeliveryModuleIdPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Order objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Order objects.
     * @throws PropelException
     */
    public function countOrdersRelatedByDeliveryModuleId(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrdersRelatedByDeliveryModuleIdPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByDeliveryModuleId || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByDeliveryModuleId) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrdersRelatedByDeliveryModuleId());
            }

            $query = ChildOrderQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModuleRelatedByDeliveryModuleId($this)
                ->count($con);
        }

        return count($this->collOrdersRelatedByDeliveryModuleId);
    }

    /**
     * Method called to associate a ChildOrder object to this object
     * through the ChildOrder foreign key attribute.
     *
     * @param    ChildOrder $l ChildOrder
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addOrderRelatedByDeliveryModuleId(ChildOrder $l)
    {
        if ($this->collOrdersRelatedByDeliveryModuleId === null) {
            $this->initOrdersRelatedByDeliveryModuleId();
            $this->collOrdersRelatedByDeliveryModuleIdPartial = true;
        }

        if (!in_array($l, $this->collOrdersRelatedByDeliveryModuleId->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderRelatedByDeliveryModuleId($l);
        }

        return $this;
    }

    /**
     * @param OrderRelatedByDeliveryModuleId $orderRelatedByDeliveryModuleId The orderRelatedByDeliveryModuleId object to add.
     */
    protected function doAddOrderRelatedByDeliveryModuleId($orderRelatedByDeliveryModuleId)
    {
        $this->collOrdersRelatedByDeliveryModuleId[]= $orderRelatedByDeliveryModuleId;
        $orderRelatedByDeliveryModuleId->setModuleRelatedByDeliveryModuleId($this);
    }

    /**
     * @param  OrderRelatedByDeliveryModuleId $orderRelatedByDeliveryModuleId The orderRelatedByDeliveryModuleId object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeOrderRelatedByDeliveryModuleId($orderRelatedByDeliveryModuleId)
    {
        if ($this->getOrdersRelatedByDeliveryModuleId()->contains($orderRelatedByDeliveryModuleId)) {
            $this->collOrdersRelatedByDeliveryModuleId->remove($this->collOrdersRelatedByDeliveryModuleId->search($orderRelatedByDeliveryModuleId));
            if (null === $this->ordersRelatedByDeliveryModuleIdScheduledForDeletion) {
                $this->ordersRelatedByDeliveryModuleIdScheduledForDeletion = clone $this->collOrdersRelatedByDeliveryModuleId;
                $this->ordersRelatedByDeliveryModuleIdScheduledForDeletion->clear();
            }
            $this->ordersRelatedByDeliveryModuleIdScheduledForDeletion[]= clone $orderRelatedByDeliveryModuleId;
            $orderRelatedByDeliveryModuleId->setModuleRelatedByDeliveryModuleId(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryModuleIdJoinCurrency($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Currency', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryModuleIdJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryModuleIdJoinOrderAddressRelatedByInvoiceOrderAddressId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderAddressRelatedByInvoiceOrderAddressId', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryModuleIdJoinOrderAddressRelatedByDeliveryOrderAddressId($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderAddressRelatedByDeliveryOrderAddressId', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryModuleIdJoinOrderStatus($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('OrderStatus', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryModuleId($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrdersRelatedByDeliveryModuleId from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrder[] List of ChildOrder objects
     */
    public function getOrdersRelatedByDeliveryModuleIdJoinLang($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderQuery::create(null, $criteria);
        $query->joinWith('Lang', $joinBehavior);

        return $this->getOrdersRelatedByDeliveryModuleId($query, $con);
    }

    /**
     * Clears out the collAreaDeliveryModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAreaDeliveryModules()
     */
    public function clearAreaDeliveryModules()
    {
        $this->collAreaDeliveryModules = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAreaDeliveryModules collection loaded partially.
     */
    public function resetPartialAreaDeliveryModules($v = true)
    {
        $this->collAreaDeliveryModulesPartial = $v;
    }

    /**
     * Initializes the collAreaDeliveryModules collection.
     *
     * By default this just sets the collAreaDeliveryModules collection to an empty array (like clearcollAreaDeliveryModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAreaDeliveryModules($overrideExisting = true)
    {
        if (null !== $this->collAreaDeliveryModules && !$overrideExisting) {
            return;
        }
        $this->collAreaDeliveryModules = new ObjectCollection();
        $this->collAreaDeliveryModules->setModel('\Thelia\Model\AreaDeliveryModule');
    }

    /**
     * Gets an array of ChildAreaDeliveryModule objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAreaDeliveryModule[] List of ChildAreaDeliveryModule objects
     * @throws PropelException
     */
    public function getAreaDeliveryModules($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAreaDeliveryModulesPartial && !$this->isNew();
        if (null === $this->collAreaDeliveryModules || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAreaDeliveryModules) {
                // return empty collection
                $this->initAreaDeliveryModules();
            } else {
                $collAreaDeliveryModules = ChildAreaDeliveryModuleQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAreaDeliveryModulesPartial && count($collAreaDeliveryModules)) {
                        $this->initAreaDeliveryModules(false);

                        foreach ($collAreaDeliveryModules as $obj) {
                            if (false == $this->collAreaDeliveryModules->contains($obj)) {
                                $this->collAreaDeliveryModules->append($obj);
                            }
                        }

                        $this->collAreaDeliveryModulesPartial = true;
                    }

                    reset($collAreaDeliveryModules);

                    return $collAreaDeliveryModules;
                }

                if ($partial && $this->collAreaDeliveryModules) {
                    foreach ($this->collAreaDeliveryModules as $obj) {
                        if ($obj->isNew()) {
                            $collAreaDeliveryModules[] = $obj;
                        }
                    }
                }

                $this->collAreaDeliveryModules = $collAreaDeliveryModules;
                $this->collAreaDeliveryModulesPartial = false;
            }
        }

        return $this->collAreaDeliveryModules;
    }

    /**
     * Sets a collection of AreaDeliveryModule objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $areaDeliveryModules A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setAreaDeliveryModules(Collection $areaDeliveryModules, ConnectionInterface $con = null)
    {
        $areaDeliveryModulesToDelete = $this->getAreaDeliveryModules(new Criteria(), $con)->diff($areaDeliveryModules);


        $this->areaDeliveryModulesScheduledForDeletion = $areaDeliveryModulesToDelete;

        foreach ($areaDeliveryModulesToDelete as $areaDeliveryModuleRemoved) {
            $areaDeliveryModuleRemoved->setModule(null);
        }

        $this->collAreaDeliveryModules = null;
        foreach ($areaDeliveryModules as $areaDeliveryModule) {
            $this->addAreaDeliveryModule($areaDeliveryModule);
        }

        $this->collAreaDeliveryModules = $areaDeliveryModules;
        $this->collAreaDeliveryModulesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related AreaDeliveryModule objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related AreaDeliveryModule objects.
     * @throws PropelException
     */
    public function countAreaDeliveryModules(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAreaDeliveryModulesPartial && !$this->isNew();
        if (null === $this->collAreaDeliveryModules || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAreaDeliveryModules) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAreaDeliveryModules());
            }

            $query = ChildAreaDeliveryModuleQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collAreaDeliveryModules);
    }

    /**
     * Method called to associate a ChildAreaDeliveryModule object to this object
     * through the ChildAreaDeliveryModule foreign key attribute.
     *
     * @param    ChildAreaDeliveryModule $l ChildAreaDeliveryModule
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addAreaDeliveryModule(ChildAreaDeliveryModule $l)
    {
        if ($this->collAreaDeliveryModules === null) {
            $this->initAreaDeliveryModules();
            $this->collAreaDeliveryModulesPartial = true;
        }

        if (!in_array($l, $this->collAreaDeliveryModules->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAreaDeliveryModule($l);
        }

        return $this;
    }

    /**
     * @param AreaDeliveryModule $areaDeliveryModule The areaDeliveryModule object to add.
     */
    protected function doAddAreaDeliveryModule($areaDeliveryModule)
    {
        $this->collAreaDeliveryModules[]= $areaDeliveryModule;
        $areaDeliveryModule->setModule($this);
    }

    /**
     * @param  AreaDeliveryModule $areaDeliveryModule The areaDeliveryModule object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeAreaDeliveryModule($areaDeliveryModule)
    {
        if ($this->getAreaDeliveryModules()->contains($areaDeliveryModule)) {
            $this->collAreaDeliveryModules->remove($this->collAreaDeliveryModules->search($areaDeliveryModule));
            if (null === $this->areaDeliveryModulesScheduledForDeletion) {
                $this->areaDeliveryModulesScheduledForDeletion = clone $this->collAreaDeliveryModules;
                $this->areaDeliveryModulesScheduledForDeletion->clear();
            }
            $this->areaDeliveryModulesScheduledForDeletion[]= clone $areaDeliveryModule;
            $areaDeliveryModule->setModule(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related AreaDeliveryModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAreaDeliveryModule[] List of ChildAreaDeliveryModule objects
     */
    public function getAreaDeliveryModulesJoinArea($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAreaDeliveryModuleQuery::create(null, $criteria);
        $query->joinWith('Area', $joinBehavior);

        return $this->getAreaDeliveryModules($query, $con);
    }

    /**
     * Clears out the collProfileModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProfileModules()
     */
    public function clearProfileModules()
    {
        $this->collProfileModules = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProfileModules collection loaded partially.
     */
    public function resetPartialProfileModules($v = true)
    {
        $this->collProfileModulesPartial = $v;
    }

    /**
     * Initializes the collProfileModules collection.
     *
     * By default this just sets the collProfileModules collection to an empty array (like clearcollProfileModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProfileModules($overrideExisting = true)
    {
        if (null !== $this->collProfileModules && !$overrideExisting) {
            return;
        }
        $this->collProfileModules = new ObjectCollection();
        $this->collProfileModules->setModel('\Thelia\Model\ProfileModule');
    }

    /**
     * Gets an array of ChildProfileModule objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProfileModule[] List of ChildProfileModule objects
     * @throws PropelException
     */
    public function getProfileModules($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProfileModulesPartial && !$this->isNew();
        if (null === $this->collProfileModules || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProfileModules) {
                // return empty collection
                $this->initProfileModules();
            } else {
                $collProfileModules = ChildProfileModuleQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProfileModulesPartial && count($collProfileModules)) {
                        $this->initProfileModules(false);

                        foreach ($collProfileModules as $obj) {
                            if (false == $this->collProfileModules->contains($obj)) {
                                $this->collProfileModules->append($obj);
                            }
                        }

                        $this->collProfileModulesPartial = true;
                    }

                    reset($collProfileModules);

                    return $collProfileModules;
                }

                if ($partial && $this->collProfileModules) {
                    foreach ($this->collProfileModules as $obj) {
                        if ($obj->isNew()) {
                            $collProfileModules[] = $obj;
                        }
                    }
                }

                $this->collProfileModules = $collProfileModules;
                $this->collProfileModulesPartial = false;
            }
        }

        return $this->collProfileModules;
    }

    /**
     * Sets a collection of ProfileModule objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $profileModules A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setProfileModules(Collection $profileModules, ConnectionInterface $con = null)
    {
        $profileModulesToDelete = $this->getProfileModules(new Criteria(), $con)->diff($profileModules);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->profileModulesScheduledForDeletion = clone $profileModulesToDelete;

        foreach ($profileModulesToDelete as $profileModuleRemoved) {
            $profileModuleRemoved->setModule(null);
        }

        $this->collProfileModules = null;
        foreach ($profileModules as $profileModule) {
            $this->addProfileModule($profileModule);
        }

        $this->collProfileModules = $profileModules;
        $this->collProfileModulesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProfileModule objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProfileModule objects.
     * @throws PropelException
     */
    public function countProfileModules(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProfileModulesPartial && !$this->isNew();
        if (null === $this->collProfileModules || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProfileModules) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProfileModules());
            }

            $query = ChildProfileModuleQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collProfileModules);
    }

    /**
     * Method called to associate a ChildProfileModule object to this object
     * through the ChildProfileModule foreign key attribute.
     *
     * @param    ChildProfileModule $l ChildProfileModule
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addProfileModule(ChildProfileModule $l)
    {
        if ($this->collProfileModules === null) {
            $this->initProfileModules();
            $this->collProfileModulesPartial = true;
        }

        if (!in_array($l, $this->collProfileModules->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProfileModule($l);
        }

        return $this;
    }

    /**
     * @param ProfileModule $profileModule The profileModule object to add.
     */
    protected function doAddProfileModule($profileModule)
    {
        $this->collProfileModules[]= $profileModule;
        $profileModule->setModule($this);
    }

    /**
     * @param  ProfileModule $profileModule The profileModule object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeProfileModule($profileModule)
    {
        if ($this->getProfileModules()->contains($profileModule)) {
            $this->collProfileModules->remove($this->collProfileModules->search($profileModule));
            if (null === $this->profileModulesScheduledForDeletion) {
                $this->profileModulesScheduledForDeletion = clone $this->collProfileModules;
                $this->profileModulesScheduledForDeletion->clear();
            }
            $this->profileModulesScheduledForDeletion[]= clone $profileModule;
            $profileModule->setModule(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related ProfileModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProfileModule[] List of ChildProfileModule objects
     */
    public function getProfileModulesJoinProfile($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProfileModuleQuery::create(null, $criteria);
        $query->joinWith('Profile', $joinBehavior);

        return $this->getProfileModules($query, $con);
    }

    /**
     * Clears out the collModuleImages collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addModuleImages()
     */
    public function clearModuleImages()
    {
        $this->collModuleImages = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collModuleImages collection loaded partially.
     */
    public function resetPartialModuleImages($v = true)
    {
        $this->collModuleImagesPartial = $v;
    }

    /**
     * Initializes the collModuleImages collection.
     *
     * By default this just sets the collModuleImages collection to an empty array (like clearcollModuleImages());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initModuleImages($overrideExisting = true)
    {
        if (null !== $this->collModuleImages && !$overrideExisting) {
            return;
        }
        $this->collModuleImages = new ObjectCollection();
        $this->collModuleImages->setModel('\Thelia\Model\ModuleImage');
    }

    /**
     * Gets an array of ChildModuleImage objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildModuleImage[] List of ChildModuleImage objects
     * @throws PropelException
     */
    public function getModuleImages($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleImagesPartial && !$this->isNew();
        if (null === $this->collModuleImages || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collModuleImages) {
                // return empty collection
                $this->initModuleImages();
            } else {
                $collModuleImages = ChildModuleImageQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collModuleImagesPartial && count($collModuleImages)) {
                        $this->initModuleImages(false);

                        foreach ($collModuleImages as $obj) {
                            if (false == $this->collModuleImages->contains($obj)) {
                                $this->collModuleImages->append($obj);
                            }
                        }

                        $this->collModuleImagesPartial = true;
                    }

                    reset($collModuleImages);

                    return $collModuleImages;
                }

                if ($partial && $this->collModuleImages) {
                    foreach ($this->collModuleImages as $obj) {
                        if ($obj->isNew()) {
                            $collModuleImages[] = $obj;
                        }
                    }
                }

                $this->collModuleImages = $collModuleImages;
                $this->collModuleImagesPartial = false;
            }
        }

        return $this->collModuleImages;
    }

    /**
     * Sets a collection of ModuleImage objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $moduleImages A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setModuleImages(Collection $moduleImages, ConnectionInterface $con = null)
    {
        $moduleImagesToDelete = $this->getModuleImages(new Criteria(), $con)->diff($moduleImages);


        $this->moduleImagesScheduledForDeletion = $moduleImagesToDelete;

        foreach ($moduleImagesToDelete as $moduleImageRemoved) {
            $moduleImageRemoved->setModule(null);
        }

        $this->collModuleImages = null;
        foreach ($moduleImages as $moduleImage) {
            $this->addModuleImage($moduleImage);
        }

        $this->collModuleImages = $moduleImages;
        $this->collModuleImagesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ModuleImage objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ModuleImage objects.
     * @throws PropelException
     */
    public function countModuleImages(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleImagesPartial && !$this->isNew();
        if (null === $this->collModuleImages || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collModuleImages) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getModuleImages());
            }

            $query = ChildModuleImageQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collModuleImages);
    }

    /**
     * Method called to associate a ChildModuleImage object to this object
     * through the ChildModuleImage foreign key attribute.
     *
     * @param    ChildModuleImage $l ChildModuleImage
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addModuleImage(ChildModuleImage $l)
    {
        if ($this->collModuleImages === null) {
            $this->initModuleImages();
            $this->collModuleImagesPartial = true;
        }

        if (!in_array($l, $this->collModuleImages->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddModuleImage($l);
        }

        return $this;
    }

    /**
     * @param ModuleImage $moduleImage The moduleImage object to add.
     */
    protected function doAddModuleImage($moduleImage)
    {
        $this->collModuleImages[]= $moduleImage;
        $moduleImage->setModule($this);
    }

    /**
     * @param  ModuleImage $moduleImage The moduleImage object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeModuleImage($moduleImage)
    {
        if ($this->getModuleImages()->contains($moduleImage)) {
            $this->collModuleImages->remove($this->collModuleImages->search($moduleImage));
            if (null === $this->moduleImagesScheduledForDeletion) {
                $this->moduleImagesScheduledForDeletion = clone $this->collModuleImages;
                $this->moduleImagesScheduledForDeletion->clear();
            }
            $this->moduleImagesScheduledForDeletion[]= clone $moduleImage;
            $moduleImage->setModule(null);
        }

        return $this;
    }

    /**
     * Clears out the collCouponModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCouponModules()
     */
    public function clearCouponModules()
    {
        $this->collCouponModules = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCouponModules collection loaded partially.
     */
    public function resetPartialCouponModules($v = true)
    {
        $this->collCouponModulesPartial = $v;
    }

    /**
     * Initializes the collCouponModules collection.
     *
     * By default this just sets the collCouponModules collection to an empty array (like clearcollCouponModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCouponModules($overrideExisting = true)
    {
        if (null !== $this->collCouponModules && !$overrideExisting) {
            return;
        }
        $this->collCouponModules = new ObjectCollection();
        $this->collCouponModules->setModel('\Thelia\Model\CouponModule');
    }

    /**
     * Gets an array of ChildCouponModule objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCouponModule[] List of ChildCouponModule objects
     * @throws PropelException
     */
    public function getCouponModules($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponModulesPartial && !$this->isNew();
        if (null === $this->collCouponModules || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCouponModules) {
                // return empty collection
                $this->initCouponModules();
            } else {
                $collCouponModules = ChildCouponModuleQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCouponModulesPartial && count($collCouponModules)) {
                        $this->initCouponModules(false);

                        foreach ($collCouponModules as $obj) {
                            if (false == $this->collCouponModules->contains($obj)) {
                                $this->collCouponModules->append($obj);
                            }
                        }

                        $this->collCouponModulesPartial = true;
                    }

                    reset($collCouponModules);

                    return $collCouponModules;
                }

                if ($partial && $this->collCouponModules) {
                    foreach ($this->collCouponModules as $obj) {
                        if ($obj->isNew()) {
                            $collCouponModules[] = $obj;
                        }
                    }
                }

                $this->collCouponModules = $collCouponModules;
                $this->collCouponModulesPartial = false;
            }
        }

        return $this->collCouponModules;
    }

    /**
     * Sets a collection of CouponModule objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $couponModules A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setCouponModules(Collection $couponModules, ConnectionInterface $con = null)
    {
        $couponModulesToDelete = $this->getCouponModules(new Criteria(), $con)->diff($couponModules);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->couponModulesScheduledForDeletion = clone $couponModulesToDelete;

        foreach ($couponModulesToDelete as $couponModuleRemoved) {
            $couponModuleRemoved->setModule(null);
        }

        $this->collCouponModules = null;
        foreach ($couponModules as $couponModule) {
            $this->addCouponModule($couponModule);
        }

        $this->collCouponModules = $couponModules;
        $this->collCouponModulesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CouponModule objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CouponModule objects.
     * @throws PropelException
     */
    public function countCouponModules(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponModulesPartial && !$this->isNew();
        if (null === $this->collCouponModules || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCouponModules) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCouponModules());
            }

            $query = ChildCouponModuleQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collCouponModules);
    }

    /**
     * Method called to associate a ChildCouponModule object to this object
     * through the ChildCouponModule foreign key attribute.
     *
     * @param    ChildCouponModule $l ChildCouponModule
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addCouponModule(ChildCouponModule $l)
    {
        if ($this->collCouponModules === null) {
            $this->initCouponModules();
            $this->collCouponModulesPartial = true;
        }

        if (!in_array($l, $this->collCouponModules->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCouponModule($l);
        }

        return $this;
    }

    /**
     * @param CouponModule $couponModule The couponModule object to add.
     */
    protected function doAddCouponModule($couponModule)
    {
        $this->collCouponModules[]= $couponModule;
        $couponModule->setModule($this);
    }

    /**
     * @param  CouponModule $couponModule The couponModule object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeCouponModule($couponModule)
    {
        if ($this->getCouponModules()->contains($couponModule)) {
            $this->collCouponModules->remove($this->collCouponModules->search($couponModule));
            if (null === $this->couponModulesScheduledForDeletion) {
                $this->couponModulesScheduledForDeletion = clone $this->collCouponModules;
                $this->couponModulesScheduledForDeletion->clear();
            }
            $this->couponModulesScheduledForDeletion[]= clone $couponModule;
            $couponModule->setModule(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related CouponModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCouponModule[] List of ChildCouponModule objects
     */
    public function getCouponModulesJoinCoupon($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCouponModuleQuery::create(null, $criteria);
        $query->joinWith('Coupon', $joinBehavior);

        return $this->getCouponModules($query, $con);
    }

    /**
     * Clears out the collOrderCouponModules collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderCouponModules()
     */
    public function clearOrderCouponModules()
    {
        $this->collOrderCouponModules = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderCouponModules collection loaded partially.
     */
    public function resetPartialOrderCouponModules($v = true)
    {
        $this->collOrderCouponModulesPartial = $v;
    }

    /**
     * Initializes the collOrderCouponModules collection.
     *
     * By default this just sets the collOrderCouponModules collection to an empty array (like clearcollOrderCouponModules());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderCouponModules($overrideExisting = true)
    {
        if (null !== $this->collOrderCouponModules && !$overrideExisting) {
            return;
        }
        $this->collOrderCouponModules = new ObjectCollection();
        $this->collOrderCouponModules->setModel('\Thelia\Model\OrderCouponModule');
    }

    /**
     * Gets an array of ChildOrderCouponModule objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderCouponModule[] List of ChildOrderCouponModule objects
     * @throws PropelException
     */
    public function getOrderCouponModules($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponModulesPartial && !$this->isNew();
        if (null === $this->collOrderCouponModules || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderCouponModules) {
                // return empty collection
                $this->initOrderCouponModules();
            } else {
                $collOrderCouponModules = ChildOrderCouponModuleQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderCouponModulesPartial && count($collOrderCouponModules)) {
                        $this->initOrderCouponModules(false);

                        foreach ($collOrderCouponModules as $obj) {
                            if (false == $this->collOrderCouponModules->contains($obj)) {
                                $this->collOrderCouponModules->append($obj);
                            }
                        }

                        $this->collOrderCouponModulesPartial = true;
                    }

                    reset($collOrderCouponModules);

                    return $collOrderCouponModules;
                }

                if ($partial && $this->collOrderCouponModules) {
                    foreach ($this->collOrderCouponModules as $obj) {
                        if ($obj->isNew()) {
                            $collOrderCouponModules[] = $obj;
                        }
                    }
                }

                $this->collOrderCouponModules = $collOrderCouponModules;
                $this->collOrderCouponModulesPartial = false;
            }
        }

        return $this->collOrderCouponModules;
    }

    /**
     * Sets a collection of OrderCouponModule objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderCouponModules A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setOrderCouponModules(Collection $orderCouponModules, ConnectionInterface $con = null)
    {
        $orderCouponModulesToDelete = $this->getOrderCouponModules(new Criteria(), $con)->diff($orderCouponModules);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->orderCouponModulesScheduledForDeletion = clone $orderCouponModulesToDelete;

        foreach ($orderCouponModulesToDelete as $orderCouponModuleRemoved) {
            $orderCouponModuleRemoved->setModule(null);
        }

        $this->collOrderCouponModules = null;
        foreach ($orderCouponModules as $orderCouponModule) {
            $this->addOrderCouponModule($orderCouponModule);
        }

        $this->collOrderCouponModules = $orderCouponModules;
        $this->collOrderCouponModulesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderCouponModule objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderCouponModule objects.
     * @throws PropelException
     */
    public function countOrderCouponModules(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponModulesPartial && !$this->isNew();
        if (null === $this->collOrderCouponModules || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderCouponModules) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderCouponModules());
            }

            $query = ChildOrderCouponModuleQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collOrderCouponModules);
    }

    /**
     * Method called to associate a ChildOrderCouponModule object to this object
     * through the ChildOrderCouponModule foreign key attribute.
     *
     * @param    ChildOrderCouponModule $l ChildOrderCouponModule
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addOrderCouponModule(ChildOrderCouponModule $l)
    {
        if ($this->collOrderCouponModules === null) {
            $this->initOrderCouponModules();
            $this->collOrderCouponModulesPartial = true;
        }

        if (!in_array($l, $this->collOrderCouponModules->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderCouponModule($l);
        }

        return $this;
    }

    /**
     * @param OrderCouponModule $orderCouponModule The orderCouponModule object to add.
     */
    protected function doAddOrderCouponModule($orderCouponModule)
    {
        $this->collOrderCouponModules[]= $orderCouponModule;
        $orderCouponModule->setModule($this);
    }

    /**
     * @param  OrderCouponModule $orderCouponModule The orderCouponModule object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeOrderCouponModule($orderCouponModule)
    {
        if ($this->getOrderCouponModules()->contains($orderCouponModule)) {
            $this->collOrderCouponModules->remove($this->collOrderCouponModules->search($orderCouponModule));
            if (null === $this->orderCouponModulesScheduledForDeletion) {
                $this->orderCouponModulesScheduledForDeletion = clone $this->collOrderCouponModules;
                $this->orderCouponModulesScheduledForDeletion->clear();
            }
            $this->orderCouponModulesScheduledForDeletion[]= clone $orderCouponModule;
            $orderCouponModule->setModule(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related OrderCouponModules from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrderCouponModule[] List of ChildOrderCouponModule objects
     */
    public function getOrderCouponModulesJoinOrderCoupon($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderCouponModuleQuery::create(null, $criteria);
        $query->joinWith('OrderCoupon', $joinBehavior);

        return $this->getOrderCouponModules($query, $con);
    }

    /**
     * Clears out the collModuleHooks collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addModuleHooks()
     */
    public function clearModuleHooks()
    {
        $this->collModuleHooks = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collModuleHooks collection loaded partially.
     */
    public function resetPartialModuleHooks($v = true)
    {
        $this->collModuleHooksPartial = $v;
    }

    /**
     * Initializes the collModuleHooks collection.
     *
     * By default this just sets the collModuleHooks collection to an empty array (like clearcollModuleHooks());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initModuleHooks($overrideExisting = true)
    {
        if (null !== $this->collModuleHooks && !$overrideExisting) {
            return;
        }
        $this->collModuleHooks = new ObjectCollection();
        $this->collModuleHooks->setModel('\Thelia\Model\ModuleHook');
    }

    /**
     * Gets an array of ChildModuleHook objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildModuleHook[] List of ChildModuleHook objects
     * @throws PropelException
     */
    public function getModuleHooks($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleHooksPartial && !$this->isNew();
        if (null === $this->collModuleHooks || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collModuleHooks) {
                // return empty collection
                $this->initModuleHooks();
            } else {
                $collModuleHooks = ChildModuleHookQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collModuleHooksPartial && count($collModuleHooks)) {
                        $this->initModuleHooks(false);

                        foreach ($collModuleHooks as $obj) {
                            if (false == $this->collModuleHooks->contains($obj)) {
                                $this->collModuleHooks->append($obj);
                            }
                        }

                        $this->collModuleHooksPartial = true;
                    }

                    reset($collModuleHooks);

                    return $collModuleHooks;
                }

                if ($partial && $this->collModuleHooks) {
                    foreach ($this->collModuleHooks as $obj) {
                        if ($obj->isNew()) {
                            $collModuleHooks[] = $obj;
                        }
                    }
                }

                $this->collModuleHooks = $collModuleHooks;
                $this->collModuleHooksPartial = false;
            }
        }

        return $this->collModuleHooks;
    }

    /**
     * Sets a collection of ModuleHook objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $moduleHooks A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setModuleHooks(Collection $moduleHooks, ConnectionInterface $con = null)
    {
        $moduleHooksToDelete = $this->getModuleHooks(new Criteria(), $con)->diff($moduleHooks);


        $this->moduleHooksScheduledForDeletion = $moduleHooksToDelete;

        foreach ($moduleHooksToDelete as $moduleHookRemoved) {
            $moduleHookRemoved->setModule(null);
        }

        $this->collModuleHooks = null;
        foreach ($moduleHooks as $moduleHook) {
            $this->addModuleHook($moduleHook);
        }

        $this->collModuleHooks = $moduleHooks;
        $this->collModuleHooksPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ModuleHook objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ModuleHook objects.
     * @throws PropelException
     */
    public function countModuleHooks(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleHooksPartial && !$this->isNew();
        if (null === $this->collModuleHooks || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collModuleHooks) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getModuleHooks());
            }

            $query = ChildModuleHookQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collModuleHooks);
    }

    /**
     * Method called to associate a ChildModuleHook object to this object
     * through the ChildModuleHook foreign key attribute.
     *
     * @param    ChildModuleHook $l ChildModuleHook
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addModuleHook(ChildModuleHook $l)
    {
        if ($this->collModuleHooks === null) {
            $this->initModuleHooks();
            $this->collModuleHooksPartial = true;
        }

        if (!in_array($l, $this->collModuleHooks->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddModuleHook($l);
        }

        return $this;
    }

    /**
     * @param ModuleHook $moduleHook The moduleHook object to add.
     */
    protected function doAddModuleHook($moduleHook)
    {
        $this->collModuleHooks[]= $moduleHook;
        $moduleHook->setModule($this);
    }

    /**
     * @param  ModuleHook $moduleHook The moduleHook object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeModuleHook($moduleHook)
    {
        if ($this->getModuleHooks()->contains($moduleHook)) {
            $this->collModuleHooks->remove($this->collModuleHooks->search($moduleHook));
            if (null === $this->moduleHooksScheduledForDeletion) {
                $this->moduleHooksScheduledForDeletion = clone $this->collModuleHooks;
                $this->moduleHooksScheduledForDeletion->clear();
            }
            $this->moduleHooksScheduledForDeletion[]= clone $moduleHook;
            $moduleHook->setModule(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related ModuleHooks from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildModuleHook[] List of ChildModuleHook objects
     */
    public function getModuleHooksJoinHook($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildModuleHookQuery::create(null, $criteria);
        $query->joinWith('Hook', $joinBehavior);

        return $this->getModuleHooks($query, $con);
    }

    /**
     * Clears out the collModuleConfigs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addModuleConfigs()
     */
    public function clearModuleConfigs()
    {
        $this->collModuleConfigs = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collModuleConfigs collection loaded partially.
     */
    public function resetPartialModuleConfigs($v = true)
    {
        $this->collModuleConfigsPartial = $v;
    }

    /**
     * Initializes the collModuleConfigs collection.
     *
     * By default this just sets the collModuleConfigs collection to an empty array (like clearcollModuleConfigs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initModuleConfigs($overrideExisting = true)
    {
        if (null !== $this->collModuleConfigs && !$overrideExisting) {
            return;
        }
        $this->collModuleConfigs = new ObjectCollection();
        $this->collModuleConfigs->setModel('\Thelia\Model\ModuleConfig');
    }

    /**
     * Gets an array of ChildModuleConfig objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildModuleConfig[] List of ChildModuleConfig objects
     * @throws PropelException
     */
    public function getModuleConfigs($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleConfigsPartial && !$this->isNew();
        if (null === $this->collModuleConfigs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collModuleConfigs) {
                // return empty collection
                $this->initModuleConfigs();
            } else {
                $collModuleConfigs = ChildModuleConfigQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collModuleConfigsPartial && count($collModuleConfigs)) {
                        $this->initModuleConfigs(false);

                        foreach ($collModuleConfigs as $obj) {
                            if (false == $this->collModuleConfigs->contains($obj)) {
                                $this->collModuleConfigs->append($obj);
                            }
                        }

                        $this->collModuleConfigsPartial = true;
                    }

                    reset($collModuleConfigs);

                    return $collModuleConfigs;
                }

                if ($partial && $this->collModuleConfigs) {
                    foreach ($this->collModuleConfigs as $obj) {
                        if ($obj->isNew()) {
                            $collModuleConfigs[] = $obj;
                        }
                    }
                }

                $this->collModuleConfigs = $collModuleConfigs;
                $this->collModuleConfigsPartial = false;
            }
        }

        return $this->collModuleConfigs;
    }

    /**
     * Sets a collection of ModuleConfig objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $moduleConfigs A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setModuleConfigs(Collection $moduleConfigs, ConnectionInterface $con = null)
    {
        $moduleConfigsToDelete = $this->getModuleConfigs(new Criteria(), $con)->diff($moduleConfigs);


        $this->moduleConfigsScheduledForDeletion = $moduleConfigsToDelete;

        foreach ($moduleConfigsToDelete as $moduleConfigRemoved) {
            $moduleConfigRemoved->setModule(null);
        }

        $this->collModuleConfigs = null;
        foreach ($moduleConfigs as $moduleConfig) {
            $this->addModuleConfig($moduleConfig);
        }

        $this->collModuleConfigs = $moduleConfigs;
        $this->collModuleConfigsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ModuleConfig objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ModuleConfig objects.
     * @throws PropelException
     */
    public function countModuleConfigs(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleConfigsPartial && !$this->isNew();
        if (null === $this->collModuleConfigs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collModuleConfigs) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getModuleConfigs());
            }

            $query = ChildModuleConfigQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collModuleConfigs);
    }

    /**
     * Method called to associate a ChildModuleConfig object to this object
     * through the ChildModuleConfig foreign key attribute.
     *
     * @param    ChildModuleConfig $l ChildModuleConfig
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addModuleConfig(ChildModuleConfig $l)
    {
        if ($this->collModuleConfigs === null) {
            $this->initModuleConfigs();
            $this->collModuleConfigsPartial = true;
        }

        if (!in_array($l, $this->collModuleConfigs->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddModuleConfig($l);
        }

        return $this;
    }

    /**
     * @param ModuleConfig $moduleConfig The moduleConfig object to add.
     */
    protected function doAddModuleConfig($moduleConfig)
    {
        $this->collModuleConfigs[]= $moduleConfig;
        $moduleConfig->setModule($this);
    }

    /**
     * @param  ModuleConfig $moduleConfig The moduleConfig object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeModuleConfig($moduleConfig)
    {
        if ($this->getModuleConfigs()->contains($moduleConfig)) {
            $this->collModuleConfigs->remove($this->collModuleConfigs->search($moduleConfig));
            if (null === $this->moduleConfigsScheduledForDeletion) {
                $this->moduleConfigsScheduledForDeletion = clone $this->collModuleConfigs;
                $this->moduleConfigsScheduledForDeletion->clear();
            }
            $this->moduleConfigsScheduledForDeletion[]= clone $moduleConfig;
            $moduleConfig->setModule(null);
        }

        return $this;
    }

    /**
     * Clears out the collIgnoredModuleHooks collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addIgnoredModuleHooks()
     */
    public function clearIgnoredModuleHooks()
    {
        $this->collIgnoredModuleHooks = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collIgnoredModuleHooks collection loaded partially.
     */
    public function resetPartialIgnoredModuleHooks($v = true)
    {
        $this->collIgnoredModuleHooksPartial = $v;
    }

    /**
     * Initializes the collIgnoredModuleHooks collection.
     *
     * By default this just sets the collIgnoredModuleHooks collection to an empty array (like clearcollIgnoredModuleHooks());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initIgnoredModuleHooks($overrideExisting = true)
    {
        if (null !== $this->collIgnoredModuleHooks && !$overrideExisting) {
            return;
        }
        $this->collIgnoredModuleHooks = new ObjectCollection();
        $this->collIgnoredModuleHooks->setModel('\Thelia\Model\IgnoredModuleHook');
    }

    /**
     * Gets an array of ChildIgnoredModuleHook objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildIgnoredModuleHook[] List of ChildIgnoredModuleHook objects
     * @throws PropelException
     */
    public function getIgnoredModuleHooks($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collIgnoredModuleHooksPartial && !$this->isNew();
        if (null === $this->collIgnoredModuleHooks || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collIgnoredModuleHooks) {
                // return empty collection
                $this->initIgnoredModuleHooks();
            } else {
                $collIgnoredModuleHooks = ChildIgnoredModuleHookQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collIgnoredModuleHooksPartial && count($collIgnoredModuleHooks)) {
                        $this->initIgnoredModuleHooks(false);

                        foreach ($collIgnoredModuleHooks as $obj) {
                            if (false == $this->collIgnoredModuleHooks->contains($obj)) {
                                $this->collIgnoredModuleHooks->append($obj);
                            }
                        }

                        $this->collIgnoredModuleHooksPartial = true;
                    }

                    reset($collIgnoredModuleHooks);

                    return $collIgnoredModuleHooks;
                }

                if ($partial && $this->collIgnoredModuleHooks) {
                    foreach ($this->collIgnoredModuleHooks as $obj) {
                        if ($obj->isNew()) {
                            $collIgnoredModuleHooks[] = $obj;
                        }
                    }
                }

                $this->collIgnoredModuleHooks = $collIgnoredModuleHooks;
                $this->collIgnoredModuleHooksPartial = false;
            }
        }

        return $this->collIgnoredModuleHooks;
    }

    /**
     * Sets a collection of IgnoredModuleHook objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $ignoredModuleHooks A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setIgnoredModuleHooks(Collection $ignoredModuleHooks, ConnectionInterface $con = null)
    {
        $ignoredModuleHooksToDelete = $this->getIgnoredModuleHooks(new Criteria(), $con)->diff($ignoredModuleHooks);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->ignoredModuleHooksScheduledForDeletion = clone $ignoredModuleHooksToDelete;

        foreach ($ignoredModuleHooksToDelete as $ignoredModuleHookRemoved) {
            $ignoredModuleHookRemoved->setModule(null);
        }

        $this->collIgnoredModuleHooks = null;
        foreach ($ignoredModuleHooks as $ignoredModuleHook) {
            $this->addIgnoredModuleHook($ignoredModuleHook);
        }

        $this->collIgnoredModuleHooks = $ignoredModuleHooks;
        $this->collIgnoredModuleHooksPartial = false;

        return $this;
    }

    /**
     * Returns the number of related IgnoredModuleHook objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related IgnoredModuleHook objects.
     * @throws PropelException
     */
    public function countIgnoredModuleHooks(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collIgnoredModuleHooksPartial && !$this->isNew();
        if (null === $this->collIgnoredModuleHooks || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collIgnoredModuleHooks) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getIgnoredModuleHooks());
            }

            $query = ChildIgnoredModuleHookQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collIgnoredModuleHooks);
    }

    /**
     * Method called to associate a ChildIgnoredModuleHook object to this object
     * through the ChildIgnoredModuleHook foreign key attribute.
     *
     * @param    ChildIgnoredModuleHook $l ChildIgnoredModuleHook
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addIgnoredModuleHook(ChildIgnoredModuleHook $l)
    {
        if ($this->collIgnoredModuleHooks === null) {
            $this->initIgnoredModuleHooks();
            $this->collIgnoredModuleHooksPartial = true;
        }

        if (!in_array($l, $this->collIgnoredModuleHooks->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddIgnoredModuleHook($l);
        }

        return $this;
    }

    /**
     * @param IgnoredModuleHook $ignoredModuleHook The ignoredModuleHook object to add.
     */
    protected function doAddIgnoredModuleHook($ignoredModuleHook)
    {
        $this->collIgnoredModuleHooks[]= $ignoredModuleHook;
        $ignoredModuleHook->setModule($this);
    }

    /**
     * @param  IgnoredModuleHook $ignoredModuleHook The ignoredModuleHook object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeIgnoredModuleHook($ignoredModuleHook)
    {
        if ($this->getIgnoredModuleHooks()->contains($ignoredModuleHook)) {
            $this->collIgnoredModuleHooks->remove($this->collIgnoredModuleHooks->search($ignoredModuleHook));
            if (null === $this->ignoredModuleHooksScheduledForDeletion) {
                $this->ignoredModuleHooksScheduledForDeletion = clone $this->collIgnoredModuleHooks;
                $this->ignoredModuleHooksScheduledForDeletion->clear();
            }
            $this->ignoredModuleHooksScheduledForDeletion[]= clone $ignoredModuleHook;
            $ignoredModuleHook->setModule(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Module is new, it will return
     * an empty collection; or if this Module has previously
     * been saved, it will retrieve related IgnoredModuleHooks from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Module.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildIgnoredModuleHook[] List of ChildIgnoredModuleHook objects
     */
    public function getIgnoredModuleHooksJoinHook($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildIgnoredModuleHookQuery::create(null, $criteria);
        $query->joinWith('Hook', $joinBehavior);

        return $this->getIgnoredModuleHooks($query, $con);
    }

    /**
     * Clears out the collModuleI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addModuleI18ns()
     */
    public function clearModuleI18ns()
    {
        $this->collModuleI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collModuleI18ns collection loaded partially.
     */
    public function resetPartialModuleI18ns($v = true)
    {
        $this->collModuleI18nsPartial = $v;
    }

    /**
     * Initializes the collModuleI18ns collection.
     *
     * By default this just sets the collModuleI18ns collection to an empty array (like clearcollModuleI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initModuleI18ns($overrideExisting = true)
    {
        if (null !== $this->collModuleI18ns && !$overrideExisting) {
            return;
        }
        $this->collModuleI18ns = new ObjectCollection();
        $this->collModuleI18ns->setModel('\Thelia\Model\ModuleI18n');
    }

    /**
     * Gets an array of ChildModuleI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildModuleI18n[] List of ChildModuleI18n objects
     * @throws PropelException
     */
    public function getModuleI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleI18nsPartial && !$this->isNew();
        if (null === $this->collModuleI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collModuleI18ns) {
                // return empty collection
                $this->initModuleI18ns();
            } else {
                $collModuleI18ns = ChildModuleI18nQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collModuleI18nsPartial && count($collModuleI18ns)) {
                        $this->initModuleI18ns(false);

                        foreach ($collModuleI18ns as $obj) {
                            if (false == $this->collModuleI18ns->contains($obj)) {
                                $this->collModuleI18ns->append($obj);
                            }
                        }

                        $this->collModuleI18nsPartial = true;
                    }

                    reset($collModuleI18ns);

                    return $collModuleI18ns;
                }

                if ($partial && $this->collModuleI18ns) {
                    foreach ($this->collModuleI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collModuleI18ns[] = $obj;
                        }
                    }
                }

                $this->collModuleI18ns = $collModuleI18ns;
                $this->collModuleI18nsPartial = false;
            }
        }

        return $this->collModuleI18ns;
    }

    /**
     * Sets a collection of ModuleI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $moduleI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildModule The current object (for fluent API support)
     */
    public function setModuleI18ns(Collection $moduleI18ns, ConnectionInterface $con = null)
    {
        $moduleI18nsToDelete = $this->getModuleI18ns(new Criteria(), $con)->diff($moduleI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->moduleI18nsScheduledForDeletion = clone $moduleI18nsToDelete;

        foreach ($moduleI18nsToDelete as $moduleI18nRemoved) {
            $moduleI18nRemoved->setModule(null);
        }

        $this->collModuleI18ns = null;
        foreach ($moduleI18ns as $moduleI18n) {
            $this->addModuleI18n($moduleI18n);
        }

        $this->collModuleI18ns = $moduleI18ns;
        $this->collModuleI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ModuleI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ModuleI18n objects.
     * @throws PropelException
     */
    public function countModuleI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collModuleI18nsPartial && !$this->isNew();
        if (null === $this->collModuleI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collModuleI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getModuleI18ns());
            }

            $query = ChildModuleI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByModule($this)
                ->count($con);
        }

        return count($this->collModuleI18ns);
    }

    /**
     * Method called to associate a ChildModuleI18n object to this object
     * through the ChildModuleI18n foreign key attribute.
     *
     * @param    ChildModuleI18n $l ChildModuleI18n
     * @return   \Thelia\Model\Module The current object (for fluent API support)
     */
    public function addModuleI18n(ChildModuleI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collModuleI18ns === null) {
            $this->initModuleI18ns();
            $this->collModuleI18nsPartial = true;
        }

        if (!in_array($l, $this->collModuleI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddModuleI18n($l);
        }

        return $this;
    }

    /**
     * @param ModuleI18n $moduleI18n The moduleI18n object to add.
     */
    protected function doAddModuleI18n($moduleI18n)
    {
        $this->collModuleI18ns[]= $moduleI18n;
        $moduleI18n->setModule($this);
    }

    /**
     * @param  ModuleI18n $moduleI18n The moduleI18n object to remove.
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeModuleI18n($moduleI18n)
    {
        if ($this->getModuleI18ns()->contains($moduleI18n)) {
            $this->collModuleI18ns->remove($this->collModuleI18ns->search($moduleI18n));
            if (null === $this->moduleI18nsScheduledForDeletion) {
                $this->moduleI18nsScheduledForDeletion = clone $this->collModuleI18ns;
                $this->moduleI18nsScheduledForDeletion->clear();
            }
            $this->moduleI18nsScheduledForDeletion[]= clone $moduleI18n;
            $moduleI18n->setModule(null);
        }

        return $this;
    }

    /**
     * Clears out the collCoupons collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCoupons()
     */
    public function clearCoupons()
    {
        $this->collCoupons = null; // important to set this to NULL since that means it is uninitialized
        $this->collCouponsPartial = null;
    }

    /**
     * Initializes the collCoupons collection.
     *
     * By default this just sets the collCoupons collection to an empty collection (like clearCoupons());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initCoupons()
    {
        $this->collCoupons = new ObjectCollection();
        $this->collCoupons->setModel('\Thelia\Model\Coupon');
    }

    /**
     * Gets a collection of ChildCoupon objects related by a many-to-many relationship
     * to the current object by way of the coupon_module cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildCoupon[] List of ChildCoupon objects
     */
    public function getCoupons($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collCoupons || null !== $criteria) {
            if ($this->isNew() && null === $this->collCoupons) {
                // return empty collection
                $this->initCoupons();
            } else {
                $collCoupons = ChildCouponQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collCoupons;
                }
                $this->collCoupons = $collCoupons;
            }
        }

        return $this->collCoupons;
    }

    /**
     * Sets a collection of Coupon objects related by a many-to-many relationship
     * to the current object by way of the coupon_module cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $coupons A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildModule The current object (for fluent API support)
     */
    public function setCoupons(Collection $coupons, ConnectionInterface $con = null)
    {
        $this->clearCoupons();
        $currentCoupons = $this->getCoupons();

        $this->couponsScheduledForDeletion = $currentCoupons->diff($coupons);

        foreach ($coupons as $coupon) {
            if (!$currentCoupons->contains($coupon)) {
                $this->doAddCoupon($coupon);
            }
        }

        $this->collCoupons = $coupons;

        return $this;
    }

    /**
     * Gets the number of ChildCoupon objects related by a many-to-many relationship
     * to the current object by way of the coupon_module cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildCoupon objects
     */
    public function countCoupons($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collCoupons || null !== $criteria) {
            if ($this->isNew() && null === $this->collCoupons) {
                return 0;
            } else {
                $query = ChildCouponQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByModule($this)
                    ->count($con);
            }
        } else {
            return count($this->collCoupons);
        }
    }

    /**
     * Associate a ChildCoupon object to this object
     * through the coupon_module cross reference table.
     *
     * @param  ChildCoupon $coupon The ChildCouponModule object to relate
     * @return ChildModule The current object (for fluent API support)
     */
    public function addCoupon(ChildCoupon $coupon)
    {
        if ($this->collCoupons === null) {
            $this->initCoupons();
        }

        if (!$this->collCoupons->contains($coupon)) { // only add it if the **same** object is not already associated
            $this->doAddCoupon($coupon);
            $this->collCoupons[] = $coupon;
        }

        return $this;
    }

    /**
     * @param    Coupon $coupon The coupon object to add.
     */
    protected function doAddCoupon($coupon)
    {
        $couponModule = new ChildCouponModule();
        $couponModule->setCoupon($coupon);
        $this->addCouponModule($couponModule);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$coupon->getModules()->contains($this)) {
            $foreignCollection   = $coupon->getModules();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildCoupon object to this object
     * through the coupon_module cross reference table.
     *
     * @param ChildCoupon $coupon The ChildCouponModule object to relate
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeCoupon(ChildCoupon $coupon)
    {
        if ($this->getCoupons()->contains($coupon)) {
            $this->collCoupons->remove($this->collCoupons->search($coupon));

            if (null === $this->couponsScheduledForDeletion) {
                $this->couponsScheduledForDeletion = clone $this->collCoupons;
                $this->couponsScheduledForDeletion->clear();
            }

            $this->couponsScheduledForDeletion[] = $coupon;
        }

        return $this;
    }

    /**
     * Clears out the collOrderCoupons collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderCoupons()
     */
    public function clearOrderCoupons()
    {
        $this->collOrderCoupons = null; // important to set this to NULL since that means it is uninitialized
        $this->collOrderCouponsPartial = null;
    }

    /**
     * Initializes the collOrderCoupons collection.
     *
     * By default this just sets the collOrderCoupons collection to an empty collection (like clearOrderCoupons());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initOrderCoupons()
    {
        $this->collOrderCoupons = new ObjectCollection();
        $this->collOrderCoupons->setModel('\Thelia\Model\OrderCoupon');
    }

    /**
     * Gets a collection of ChildOrderCoupon objects related by a many-to-many relationship
     * to the current object by way of the order_coupon_module cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildOrderCoupon[] List of ChildOrderCoupon objects
     */
    public function getOrderCoupons($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collOrderCoupons || null !== $criteria) {
            if ($this->isNew() && null === $this->collOrderCoupons) {
                // return empty collection
                $this->initOrderCoupons();
            } else {
                $collOrderCoupons = ChildOrderCouponQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collOrderCoupons;
                }
                $this->collOrderCoupons = $collOrderCoupons;
            }
        }

        return $this->collOrderCoupons;
    }

    /**
     * Sets a collection of OrderCoupon objects related by a many-to-many relationship
     * to the current object by way of the order_coupon_module cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $orderCoupons A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildModule The current object (for fluent API support)
     */
    public function setOrderCoupons(Collection $orderCoupons, ConnectionInterface $con = null)
    {
        $this->clearOrderCoupons();
        $currentOrderCoupons = $this->getOrderCoupons();

        $this->orderCouponsScheduledForDeletion = $currentOrderCoupons->diff($orderCoupons);

        foreach ($orderCoupons as $orderCoupon) {
            if (!$currentOrderCoupons->contains($orderCoupon)) {
                $this->doAddOrderCoupon($orderCoupon);
            }
        }

        $this->collOrderCoupons = $orderCoupons;

        return $this;
    }

    /**
     * Gets the number of ChildOrderCoupon objects related by a many-to-many relationship
     * to the current object by way of the order_coupon_module cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildOrderCoupon objects
     */
    public function countOrderCoupons($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collOrderCoupons || null !== $criteria) {
            if ($this->isNew() && null === $this->collOrderCoupons) {
                return 0;
            } else {
                $query = ChildOrderCouponQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByModule($this)
                    ->count($con);
            }
        } else {
            return count($this->collOrderCoupons);
        }
    }

    /**
     * Associate a ChildOrderCoupon object to this object
     * through the order_coupon_module cross reference table.
     *
     * @param  ChildOrderCoupon $orderCoupon The ChildOrderCouponModule object to relate
     * @return ChildModule The current object (for fluent API support)
     */
    public function addOrderCoupon(ChildOrderCoupon $orderCoupon)
    {
        if ($this->collOrderCoupons === null) {
            $this->initOrderCoupons();
        }

        if (!$this->collOrderCoupons->contains($orderCoupon)) { // only add it if the **same** object is not already associated
            $this->doAddOrderCoupon($orderCoupon);
            $this->collOrderCoupons[] = $orderCoupon;
        }

        return $this;
    }

    /**
     * @param    OrderCoupon $orderCoupon The orderCoupon object to add.
     */
    protected function doAddOrderCoupon($orderCoupon)
    {
        $orderCouponModule = new ChildOrderCouponModule();
        $orderCouponModule->setOrderCoupon($orderCoupon);
        $this->addOrderCouponModule($orderCouponModule);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$orderCoupon->getModules()->contains($this)) {
            $foreignCollection   = $orderCoupon->getModules();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildOrderCoupon object to this object
     * through the order_coupon_module cross reference table.
     *
     * @param ChildOrderCoupon $orderCoupon The ChildOrderCouponModule object to relate
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeOrderCoupon(ChildOrderCoupon $orderCoupon)
    {
        if ($this->getOrderCoupons()->contains($orderCoupon)) {
            $this->collOrderCoupons->remove($this->collOrderCoupons->search($orderCoupon));

            if (null === $this->orderCouponsScheduledForDeletion) {
                $this->orderCouponsScheduledForDeletion = clone $this->collOrderCoupons;
                $this->orderCouponsScheduledForDeletion->clear();
            }

            $this->orderCouponsScheduledForDeletion[] = $orderCoupon;
        }

        return $this;
    }

    /**
     * Clears out the collHooks collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addHooks()
     */
    public function clearHooks()
    {
        $this->collHooks = null; // important to set this to NULL since that means it is uninitialized
        $this->collHooksPartial = null;
    }

    /**
     * Initializes the collHooks collection.
     *
     * By default this just sets the collHooks collection to an empty collection (like clearHooks());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initHooks()
    {
        $this->collHooks = new ObjectCollection();
        $this->collHooks->setModel('\Thelia\Model\Hook');
    }

    /**
     * Gets a collection of ChildHook objects related by a many-to-many relationship
     * to the current object by way of the ignored_module_hook cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildModule is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildHook[] List of ChildHook objects
     */
    public function getHooks($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collHooks || null !== $criteria) {
            if ($this->isNew() && null === $this->collHooks) {
                // return empty collection
                $this->initHooks();
            } else {
                $collHooks = ChildHookQuery::create(null, $criteria)
                    ->filterByModule($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collHooks;
                }
                $this->collHooks = $collHooks;
            }
        }

        return $this->collHooks;
    }

    /**
     * Sets a collection of Hook objects related by a many-to-many relationship
     * to the current object by way of the ignored_module_hook cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $hooks A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildModule The current object (for fluent API support)
     */
    public function setHooks(Collection $hooks, ConnectionInterface $con = null)
    {
        $this->clearHooks();
        $currentHooks = $this->getHooks();

        $this->hooksScheduledForDeletion = $currentHooks->diff($hooks);

        foreach ($hooks as $hook) {
            if (!$currentHooks->contains($hook)) {
                $this->doAddHook($hook);
            }
        }

        $this->collHooks = $hooks;

        return $this;
    }

    /**
     * Gets the number of ChildHook objects related by a many-to-many relationship
     * to the current object by way of the ignored_module_hook cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildHook objects
     */
    public function countHooks($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collHooks || null !== $criteria) {
            if ($this->isNew() && null === $this->collHooks) {
                return 0;
            } else {
                $query = ChildHookQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByModule($this)
                    ->count($con);
            }
        } else {
            return count($this->collHooks);
        }
    }

    /**
     * Associate a ChildHook object to this object
     * through the ignored_module_hook cross reference table.
     *
     * @param  ChildHook $hook The ChildIgnoredModuleHook object to relate
     * @return ChildModule The current object (for fluent API support)
     */
    public function addHook(ChildHook $hook)
    {
        if ($this->collHooks === null) {
            $this->initHooks();
        }

        if (!$this->collHooks->contains($hook)) { // only add it if the **same** object is not already associated
            $this->doAddHook($hook);
            $this->collHooks[] = $hook;
        }

        return $this;
    }

    /**
     * @param    Hook $hook The hook object to add.
     */
    protected function doAddHook($hook)
    {
        $ignoredModuleHook = new ChildIgnoredModuleHook();
        $ignoredModuleHook->setHook($hook);
        $this->addIgnoredModuleHook($ignoredModuleHook);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$hook->getModules()->contains($this)) {
            $foreignCollection   = $hook->getModules();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildHook object to this object
     * through the ignored_module_hook cross reference table.
     *
     * @param ChildHook $hook The ChildIgnoredModuleHook object to relate
     * @return ChildModule The current object (for fluent API support)
     */
    public function removeHook(ChildHook $hook)
    {
        if ($this->getHooks()->contains($hook)) {
            $this->collHooks->remove($this->collHooks->search($hook));

            if (null === $this->hooksScheduledForDeletion) {
                $this->hooksScheduledForDeletion = clone $this->collHooks;
                $this->hooksScheduledForDeletion->clear();
            }

            $this->hooksScheduledForDeletion[] = $hook;
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
        $this->version = null;
        $this->type = null;
        $this->category = null;
        $this->activate = null;
        $this->position = null;
        $this->full_namespace = null;
        $this->mandatory = null;
        $this->hidden = null;
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
            if ($this->collOrdersRelatedByPaymentModuleId) {
                foreach ($this->collOrdersRelatedByPaymentModuleId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrdersRelatedByDeliveryModuleId) {
                foreach ($this->collOrdersRelatedByDeliveryModuleId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAreaDeliveryModules) {
                foreach ($this->collAreaDeliveryModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProfileModules) {
                foreach ($this->collProfileModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collModuleImages) {
                foreach ($this->collModuleImages as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCouponModules) {
                foreach ($this->collCouponModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrderCouponModules) {
                foreach ($this->collOrderCouponModules as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collModuleHooks) {
                foreach ($this->collModuleHooks as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collModuleConfigs) {
                foreach ($this->collModuleConfigs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collIgnoredModuleHooks) {
                foreach ($this->collIgnoredModuleHooks as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collModuleI18ns) {
                foreach ($this->collModuleI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCoupons) {
                foreach ($this->collCoupons as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrderCoupons) {
                foreach ($this->collOrderCoupons as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collHooks) {
                foreach ($this->collHooks as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collOrdersRelatedByPaymentModuleId = null;
        $this->collOrdersRelatedByDeliveryModuleId = null;
        $this->collAreaDeliveryModules = null;
        $this->collProfileModules = null;
        $this->collModuleImages = null;
        $this->collCouponModules = null;
        $this->collOrderCouponModules = null;
        $this->collModuleHooks = null;
        $this->collModuleConfigs = null;
        $this->collIgnoredModuleHooks = null;
        $this->collModuleI18ns = null;
        $this->collCoupons = null;
        $this->collOrderCoupons = null;
        $this->collHooks = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(ModuleTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildModule The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[ModuleTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildModule The current object (for fluent API support)
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
     * @return ChildModuleI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collModuleI18ns) {
                foreach ($this->collModuleI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildModuleI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildModuleI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addModuleI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildModule The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildModuleI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collModuleI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collModuleI18ns[$key]);
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
     * @return ChildModuleI18n */
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
         * @return   \Thelia\Model\ModuleI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ModuleI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ModuleI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ModuleI18n The current object (for fluent API support)
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
