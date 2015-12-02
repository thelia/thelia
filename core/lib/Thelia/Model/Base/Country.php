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
use Thelia\Model\Address as ChildAddress;
use Thelia\Model\AddressQuery as ChildAddressQuery;
use Thelia\Model\Area as ChildArea;
use Thelia\Model\AreaQuery as ChildAreaQuery;
use Thelia\Model\Country as ChildCountry;
use Thelia\Model\CountryArea as ChildCountryArea;
use Thelia\Model\CountryAreaQuery as ChildCountryAreaQuery;
use Thelia\Model\CountryI18n as ChildCountryI18n;
use Thelia\Model\CountryI18nQuery as ChildCountryI18nQuery;
use Thelia\Model\CountryQuery as ChildCountryQuery;
use Thelia\Model\Coupon as ChildCoupon;
use Thelia\Model\CouponCountry as ChildCouponCountry;
use Thelia\Model\CouponCountryQuery as ChildCouponCountryQuery;
use Thelia\Model\CouponQuery as ChildCouponQuery;
use Thelia\Model\OrderAddress as ChildOrderAddress;
use Thelia\Model\OrderAddressQuery as ChildOrderAddressQuery;
use Thelia\Model\OrderCoupon as ChildOrderCoupon;
use Thelia\Model\OrderCouponCountry as ChildOrderCouponCountry;
use Thelia\Model\OrderCouponCountryQuery as ChildOrderCouponCountryQuery;
use Thelia\Model\OrderCouponQuery as ChildOrderCouponQuery;
use Thelia\Model\State as ChildState;
use Thelia\Model\StateQuery as ChildStateQuery;
use Thelia\Model\TaxRuleCountry as ChildTaxRuleCountry;
use Thelia\Model\TaxRuleCountryQuery as ChildTaxRuleCountryQuery;
use Thelia\Model\Map\CountryTableMap;

abstract class Country implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\CountryTableMap';


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
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $visible;

    /**
     * The value for the isocode field.
     * @var        string
     */
    protected $isocode;

    /**
     * The value for the isoalpha2 field.
     * @var        string
     */
    protected $isoalpha2;

    /**
     * The value for the isoalpha3 field.
     * @var        string
     */
    protected $isoalpha3;

    /**
     * The value for the has_states field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $has_states;

    /**
     * The value for the need_zip_code field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $need_zip_code;

    /**
     * The value for the zip_code_format field.
     * @var        string
     */
    protected $zip_code_format;

    /**
     * The value for the by_default field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $by_default;

    /**
     * The value for the shop_country field.
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $shop_country;

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
     * @var        ObjectCollection|ChildState[] Collection to store aggregation of ChildState objects.
     */
    protected $collStates;
    protected $collStatesPartial;

    /**
     * @var        ObjectCollection|ChildTaxRuleCountry[] Collection to store aggregation of ChildTaxRuleCountry objects.
     */
    protected $collTaxRuleCountries;
    protected $collTaxRuleCountriesPartial;

    /**
     * @var        ObjectCollection|ChildAddress[] Collection to store aggregation of ChildAddress objects.
     */
    protected $collAddresses;
    protected $collAddressesPartial;

    /**
     * @var        ObjectCollection|ChildOrderAddress[] Collection to store aggregation of ChildOrderAddress objects.
     */
    protected $collOrderAddresses;
    protected $collOrderAddressesPartial;

    /**
     * @var        ObjectCollection|ChildCouponCountry[] Collection to store aggregation of ChildCouponCountry objects.
     */
    protected $collCouponCountries;
    protected $collCouponCountriesPartial;

    /**
     * @var        ObjectCollection|ChildOrderCouponCountry[] Collection to store aggregation of ChildOrderCouponCountry objects.
     */
    protected $collOrderCouponCountries;
    protected $collOrderCouponCountriesPartial;

    /**
     * @var        ObjectCollection|ChildCountryArea[] Collection to store aggregation of ChildCountryArea objects.
     */
    protected $collCountryAreas;
    protected $collCountryAreasPartial;

    /**
     * @var        ObjectCollection|ChildCountryI18n[] Collection to store aggregation of ChildCountryI18n objects.
     */
    protected $collCountryI18ns;
    protected $collCountryI18nsPartial;

    /**
     * @var        ChildCoupon[] Collection to store aggregation of ChildCoupon objects.
     */
    protected $collCoupons;

    /**
     * @var        ChildOrderCoupon[] Collection to store aggregation of ChildOrderCoupon objects.
     */
    protected $collOrderCoupons;

    /**
     * @var        ChildArea[] Collection to store aggregation of ChildArea objects.
     */
    protected $collAreas;

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
     * @var        array[ChildCountryI18n]
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
    protected $areasScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $statesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $taxRuleCountriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $addressesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderAddressesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $couponCountriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderCouponCountriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $countryAreasScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $countryI18nsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->visible = 0;
        $this->has_states = 0;
        $this->need_zip_code = 0;
        $this->by_default = 0;
        $this->shop_country = false;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Country object.
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
     * Compares this with another <code>Country</code> instance.  If
     * <code>obj</code> is an instance of <code>Country</code>, delegates to
     * <code>equals(Country)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Country The current object, for fluid interface
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
     * @return Country The current object, for fluid interface
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
     * Get the [isocode] column value.
     *
     * @return   string
     */
    public function getIsocode()
    {

        return $this->isocode;
    }

    /**
     * Get the [isoalpha2] column value.
     *
     * @return   string
     */
    public function getIsoalpha2()
    {

        return $this->isoalpha2;
    }

    /**
     * Get the [isoalpha3] column value.
     *
     * @return   string
     */
    public function getIsoalpha3()
    {

        return $this->isoalpha3;
    }

    /**
     * Get the [has_states] column value.
     *
     * @return   int
     */
    public function getHasStates()
    {

        return $this->has_states;
    }

    /**
     * Get the [need_zip_code] column value.
     *
     * @return   int
     */
    public function getNeedZipCode()
    {

        return $this->need_zip_code;
    }

    /**
     * Get the [zip_code_format] column value.
     *
     * @return   string
     */
    public function getZipCodeFormat()
    {

        return $this->zip_code_format;
    }

    /**
     * Get the [by_default] column value.
     *
     * @return   int
     */
    public function getByDefault()
    {

        return $this->by_default;
    }

    /**
     * Get the [shop_country] column value.
     *
     * @return   boolean
     */
    public function getShopCountry()
    {

        return $this->shop_country;
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
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[CountryTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [visible] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[CountryTableMap::VISIBLE] = true;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [isocode] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setIsocode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->isocode !== $v) {
            $this->isocode = $v;
            $this->modifiedColumns[CountryTableMap::ISOCODE] = true;
        }


        return $this;
    } // setIsocode()

    /**
     * Set the value of [isoalpha2] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setIsoalpha2($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->isoalpha2 !== $v) {
            $this->isoalpha2 = $v;
            $this->modifiedColumns[CountryTableMap::ISOALPHA2] = true;
        }


        return $this;
    } // setIsoalpha2()

    /**
     * Set the value of [isoalpha3] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setIsoalpha3($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->isoalpha3 !== $v) {
            $this->isoalpha3 = $v;
            $this->modifiedColumns[CountryTableMap::ISOALPHA3] = true;
        }


        return $this;
    } // setIsoalpha3()

    /**
     * Set the value of [has_states] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setHasStates($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->has_states !== $v) {
            $this->has_states = $v;
            $this->modifiedColumns[CountryTableMap::HAS_STATES] = true;
        }


        return $this;
    } // setHasStates()

    /**
     * Set the value of [need_zip_code] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setNeedZipCode($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->need_zip_code !== $v) {
            $this->need_zip_code = $v;
            $this->modifiedColumns[CountryTableMap::NEED_ZIP_CODE] = true;
        }


        return $this;
    } // setNeedZipCode()

    /**
     * Set the value of [zip_code_format] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setZipCodeFormat($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->zip_code_format !== $v) {
            $this->zip_code_format = $v;
            $this->modifiedColumns[CountryTableMap::ZIP_CODE_FORMAT] = true;
        }


        return $this;
    } // setZipCodeFormat()

    /**
     * Set the value of [by_default] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setByDefault($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->by_default !== $v) {
            $this->by_default = $v;
            $this->modifiedColumns[CountryTableMap::BY_DEFAULT] = true;
        }


        return $this;
    } // setByDefault()

    /**
     * Sets the value of the [shop_country] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param      boolean|integer|string $v The new value
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setShopCountry($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->shop_country !== $v) {
            $this->shop_country = $v;
            $this->modifiedColumns[CountryTableMap::SHOP_COUNTRY] = true;
        }


        return $this;
    } // setShopCountry()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[CountryTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[CountryTableMap::UPDATED_AT] = true;
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
            if ($this->visible !== 0) {
                return false;
            }

            if ($this->has_states !== 0) {
                return false;
            }

            if ($this->need_zip_code !== 0) {
                return false;
            }

            if ($this->by_default !== 0) {
                return false;
            }

            if ($this->shop_country !== false) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : CountryTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : CountryTableMap::translateFieldName('Visible', TableMap::TYPE_PHPNAME, $indexType)];
            $this->visible = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : CountryTableMap::translateFieldName('Isocode', TableMap::TYPE_PHPNAME, $indexType)];
            $this->isocode = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : CountryTableMap::translateFieldName('Isoalpha2', TableMap::TYPE_PHPNAME, $indexType)];
            $this->isoalpha2 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : CountryTableMap::translateFieldName('Isoalpha3', TableMap::TYPE_PHPNAME, $indexType)];
            $this->isoalpha3 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : CountryTableMap::translateFieldName('HasStates', TableMap::TYPE_PHPNAME, $indexType)];
            $this->has_states = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : CountryTableMap::translateFieldName('NeedZipCode', TableMap::TYPE_PHPNAME, $indexType)];
            $this->need_zip_code = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : CountryTableMap::translateFieldName('ZipCodeFormat', TableMap::TYPE_PHPNAME, $indexType)];
            $this->zip_code_format = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : CountryTableMap::translateFieldName('ByDefault', TableMap::TYPE_PHPNAME, $indexType)];
            $this->by_default = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : CountryTableMap::translateFieldName('ShopCountry', TableMap::TYPE_PHPNAME, $indexType)];
            $this->shop_country = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : CountryTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : CountryTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 12; // 12 = CountryTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Country object", 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(CountryTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildCountryQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collStates = null;

            $this->collTaxRuleCountries = null;

            $this->collAddresses = null;

            $this->collOrderAddresses = null;

            $this->collCouponCountries = null;

            $this->collOrderCouponCountries = null;

            $this->collCountryAreas = null;

            $this->collCountryI18ns = null;

            $this->collCoupons = null;
            $this->collOrderCoupons = null;
            $this->collAreas = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Country::setDeleted()
     * @see Country::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(CountryTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildCountryQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(CountryTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(CountryTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(CountryTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(CountryTableMap::UPDATED_AT)) {
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
                CountryTableMap::addInstanceToPool($this);
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
                        $pks[] = array($pk, $remotePk);
                    }

                    CouponCountryQuery::create()
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
                        $pks[] = array($pk, $remotePk);
                    }

                    OrderCouponCountryQuery::create()
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

            if ($this->areasScheduledForDeletion !== null) {
                if (!$this->areasScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->areasScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    CountryAreaQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->areasScheduledForDeletion = null;
                }

                foreach ($this->getAreas() as $area) {
                    if ($area->isModified()) {
                        $area->save($con);
                    }
                }
            } elseif ($this->collAreas) {
                foreach ($this->collAreas as $area) {
                    if ($area->isModified()) {
                        $area->save($con);
                    }
                }
            }

            if ($this->statesScheduledForDeletion !== null) {
                if (!$this->statesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\StateQuery::create()
                        ->filterByPrimaryKeys($this->statesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->statesScheduledForDeletion = null;
                }
            }

                if ($this->collStates !== null) {
            foreach ($this->collStates as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->taxRuleCountriesScheduledForDeletion !== null) {
                if (!$this->taxRuleCountriesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\TaxRuleCountryQuery::create()
                        ->filterByPrimaryKeys($this->taxRuleCountriesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->taxRuleCountriesScheduledForDeletion = null;
                }
            }

                if ($this->collTaxRuleCountries !== null) {
            foreach ($this->collTaxRuleCountries as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->addressesScheduledForDeletion !== null) {
                if (!$this->addressesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AddressQuery::create()
                        ->filterByPrimaryKeys($this->addressesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->addressesScheduledForDeletion = null;
                }
            }

                if ($this->collAddresses !== null) {
            foreach ($this->collAddresses as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->orderAddressesScheduledForDeletion !== null) {
                if (!$this->orderAddressesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderAddressQuery::create()
                        ->filterByPrimaryKeys($this->orderAddressesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderAddressesScheduledForDeletion = null;
                }
            }

                if ($this->collOrderAddresses !== null) {
            foreach ($this->collOrderAddresses as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->couponCountriesScheduledForDeletion !== null) {
                if (!$this->couponCountriesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CouponCountryQuery::create()
                        ->filterByPrimaryKeys($this->couponCountriesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->couponCountriesScheduledForDeletion = null;
                }
            }

                if ($this->collCouponCountries !== null) {
            foreach ($this->collCouponCountries as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->orderCouponCountriesScheduledForDeletion !== null) {
                if (!$this->orderCouponCountriesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderCouponCountryQuery::create()
                        ->filterByPrimaryKeys($this->orderCouponCountriesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderCouponCountriesScheduledForDeletion = null;
                }
            }

                if ($this->collOrderCouponCountries !== null) {
            foreach ($this->collOrderCouponCountries as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->countryAreasScheduledForDeletion !== null) {
                if (!$this->countryAreasScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CountryAreaQuery::create()
                        ->filterByPrimaryKeys($this->countryAreasScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->countryAreasScheduledForDeletion = null;
                }
            }

                if ($this->collCountryAreas !== null) {
            foreach ($this->collCountryAreas as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->countryI18nsScheduledForDeletion !== null) {
                if (!$this->countryI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CountryI18nQuery::create()
                        ->filterByPrimaryKeys($this->countryI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->countryI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collCountryI18ns !== null) {
            foreach ($this->collCountryI18ns as $referrerFK) {
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

        $this->modifiedColumns[CountryTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . CountryTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CountryTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CountryTableMap::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`VISIBLE`';
        }
        if ($this->isColumnModified(CountryTableMap::ISOCODE)) {
            $modifiedColumns[':p' . $index++]  = '`ISOCODE`';
        }
        if ($this->isColumnModified(CountryTableMap::ISOALPHA2)) {
            $modifiedColumns[':p' . $index++]  = '`ISOALPHA2`';
        }
        if ($this->isColumnModified(CountryTableMap::ISOALPHA3)) {
            $modifiedColumns[':p' . $index++]  = '`ISOALPHA3`';
        }
        if ($this->isColumnModified(CountryTableMap::HAS_STATES)) {
            $modifiedColumns[':p' . $index++]  = '`HAS_STATES`';
        }
        if ($this->isColumnModified(CountryTableMap::NEED_ZIP_CODE)) {
            $modifiedColumns[':p' . $index++]  = '`NEED_ZIP_CODE`';
        }
        if ($this->isColumnModified(CountryTableMap::ZIP_CODE_FORMAT)) {
            $modifiedColumns[':p' . $index++]  = '`ZIP_CODE_FORMAT`';
        }
        if ($this->isColumnModified(CountryTableMap::BY_DEFAULT)) {
            $modifiedColumns[':p' . $index++]  = '`BY_DEFAULT`';
        }
        if ($this->isColumnModified(CountryTableMap::SHOP_COUNTRY)) {
            $modifiedColumns[':p' . $index++]  = '`SHOP_COUNTRY`';
        }
        if ($this->isColumnModified(CountryTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CountryTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `country` (%s) VALUES (%s)',
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
                    case '`ISOCODE`':
                        $stmt->bindValue($identifier, $this->isocode, PDO::PARAM_STR);
                        break;
                    case '`ISOALPHA2`':
                        $stmt->bindValue($identifier, $this->isoalpha2, PDO::PARAM_STR);
                        break;
                    case '`ISOALPHA3`':
                        $stmt->bindValue($identifier, $this->isoalpha3, PDO::PARAM_STR);
                        break;
                    case '`HAS_STATES`':
                        $stmt->bindValue($identifier, $this->has_states, PDO::PARAM_INT);
                        break;
                    case '`NEED_ZIP_CODE`':
                        $stmt->bindValue($identifier, $this->need_zip_code, PDO::PARAM_INT);
                        break;
                    case '`ZIP_CODE_FORMAT`':
                        $stmt->bindValue($identifier, $this->zip_code_format, PDO::PARAM_STR);
                        break;
                    case '`BY_DEFAULT`':
                        $stmt->bindValue($identifier, $this->by_default, PDO::PARAM_INT);
                        break;
                    case '`SHOP_COUNTRY`':
                        $stmt->bindValue($identifier, (int) $this->shop_country, PDO::PARAM_INT);
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
        $pos = CountryTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getIsocode();
                break;
            case 3:
                return $this->getIsoalpha2();
                break;
            case 4:
                return $this->getIsoalpha3();
                break;
            case 5:
                return $this->getHasStates();
                break;
            case 6:
                return $this->getNeedZipCode();
                break;
            case 7:
                return $this->getZipCodeFormat();
                break;
            case 8:
                return $this->getByDefault();
                break;
            case 9:
                return $this->getShopCountry();
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
        if (isset($alreadyDumpedObjects['Country'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Country'][$this->getPrimaryKey()] = true;
        $keys = CountryTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getVisible(),
            $keys[2] => $this->getIsocode(),
            $keys[3] => $this->getIsoalpha2(),
            $keys[4] => $this->getIsoalpha3(),
            $keys[5] => $this->getHasStates(),
            $keys[6] => $this->getNeedZipCode(),
            $keys[7] => $this->getZipCodeFormat(),
            $keys[8] => $this->getByDefault(),
            $keys[9] => $this->getShopCountry(),
            $keys[10] => $this->getCreatedAt(),
            $keys[11] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collStates) {
                $result['States'] = $this->collStates->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collTaxRuleCountries) {
                $result['TaxRuleCountries'] = $this->collTaxRuleCountries->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAddresses) {
                $result['Addresses'] = $this->collAddresses->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrderAddresses) {
                $result['OrderAddresses'] = $this->collOrderAddresses->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCouponCountries) {
                $result['CouponCountries'] = $this->collCouponCountries->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrderCouponCountries) {
                $result['OrderCouponCountries'] = $this->collOrderCouponCountries->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCountryAreas) {
                $result['CountryAreas'] = $this->collCountryAreas->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCountryI18ns) {
                $result['CountryI18ns'] = $this->collCountryI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = CountryTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setIsocode($value);
                break;
            case 3:
                $this->setIsoalpha2($value);
                break;
            case 4:
                $this->setIsoalpha3($value);
                break;
            case 5:
                $this->setHasStates($value);
                break;
            case 6:
                $this->setNeedZipCode($value);
                break;
            case 7:
                $this->setZipCodeFormat($value);
                break;
            case 8:
                $this->setByDefault($value);
                break;
            case 9:
                $this->setShopCountry($value);
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
        $keys = CountryTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setVisible($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setIsocode($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setIsoalpha2($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setIsoalpha3($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setHasStates($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setNeedZipCode($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setZipCodeFormat($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setByDefault($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setShopCountry($arr[$keys[9]]);
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
        $criteria = new Criteria(CountryTableMap::DATABASE_NAME);

        if ($this->isColumnModified(CountryTableMap::ID)) $criteria->add(CountryTableMap::ID, $this->id);
        if ($this->isColumnModified(CountryTableMap::VISIBLE)) $criteria->add(CountryTableMap::VISIBLE, $this->visible);
        if ($this->isColumnModified(CountryTableMap::ISOCODE)) $criteria->add(CountryTableMap::ISOCODE, $this->isocode);
        if ($this->isColumnModified(CountryTableMap::ISOALPHA2)) $criteria->add(CountryTableMap::ISOALPHA2, $this->isoalpha2);
        if ($this->isColumnModified(CountryTableMap::ISOALPHA3)) $criteria->add(CountryTableMap::ISOALPHA3, $this->isoalpha3);
        if ($this->isColumnModified(CountryTableMap::HAS_STATES)) $criteria->add(CountryTableMap::HAS_STATES, $this->has_states);
        if ($this->isColumnModified(CountryTableMap::NEED_ZIP_CODE)) $criteria->add(CountryTableMap::NEED_ZIP_CODE, $this->need_zip_code);
        if ($this->isColumnModified(CountryTableMap::ZIP_CODE_FORMAT)) $criteria->add(CountryTableMap::ZIP_CODE_FORMAT, $this->zip_code_format);
        if ($this->isColumnModified(CountryTableMap::BY_DEFAULT)) $criteria->add(CountryTableMap::BY_DEFAULT, $this->by_default);
        if ($this->isColumnModified(CountryTableMap::SHOP_COUNTRY)) $criteria->add(CountryTableMap::SHOP_COUNTRY, $this->shop_country);
        if ($this->isColumnModified(CountryTableMap::CREATED_AT)) $criteria->add(CountryTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CountryTableMap::UPDATED_AT)) $criteria->add(CountryTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(CountryTableMap::DATABASE_NAME);
        $criteria->add(CountryTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Country (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setVisible($this->getVisible());
        $copyObj->setIsocode($this->getIsocode());
        $copyObj->setIsoalpha2($this->getIsoalpha2());
        $copyObj->setIsoalpha3($this->getIsoalpha3());
        $copyObj->setHasStates($this->getHasStates());
        $copyObj->setNeedZipCode($this->getNeedZipCode());
        $copyObj->setZipCodeFormat($this->getZipCodeFormat());
        $copyObj->setByDefault($this->getByDefault());
        $copyObj->setShopCountry($this->getShopCountry());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getStates() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addState($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getTaxRuleCountries() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addTaxRuleCountry($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAddresses() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAddress($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrderAddresses() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderAddress($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCouponCountries() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCouponCountry($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrderCouponCountries() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderCouponCountry($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCountryAreas() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCountryArea($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCountryI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCountryI18n($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Country Clone of current object.
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
        if ('State' == $relationName) {
            return $this->initStates();
        }
        if ('TaxRuleCountry' == $relationName) {
            return $this->initTaxRuleCountries();
        }
        if ('Address' == $relationName) {
            return $this->initAddresses();
        }
        if ('OrderAddress' == $relationName) {
            return $this->initOrderAddresses();
        }
        if ('CouponCountry' == $relationName) {
            return $this->initCouponCountries();
        }
        if ('OrderCouponCountry' == $relationName) {
            return $this->initOrderCouponCountries();
        }
        if ('CountryArea' == $relationName) {
            return $this->initCountryAreas();
        }
        if ('CountryI18n' == $relationName) {
            return $this->initCountryI18ns();
        }
    }

    /**
     * Clears out the collStates collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addStates()
     */
    public function clearStates()
    {
        $this->collStates = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collStates collection loaded partially.
     */
    public function resetPartialStates($v = true)
    {
        $this->collStatesPartial = $v;
    }

    /**
     * Initializes the collStates collection.
     *
     * By default this just sets the collStates collection to an empty array (like clearcollStates());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initStates($overrideExisting = true)
    {
        if (null !== $this->collStates && !$overrideExisting) {
            return;
        }
        $this->collStates = new ObjectCollection();
        $this->collStates->setModel('\Thelia\Model\State');
    }

    /**
     * Gets an array of ChildState objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildState[] List of ChildState objects
     * @throws PropelException
     */
    public function getStates($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collStatesPartial && !$this->isNew();
        if (null === $this->collStates || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collStates) {
                // return empty collection
                $this->initStates();
            } else {
                $collStates = ChildStateQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collStatesPartial && count($collStates)) {
                        $this->initStates(false);

                        foreach ($collStates as $obj) {
                            if (false == $this->collStates->contains($obj)) {
                                $this->collStates->append($obj);
                            }
                        }

                        $this->collStatesPartial = true;
                    }

                    reset($collStates);

                    return $collStates;
                }

                if ($partial && $this->collStates) {
                    foreach ($this->collStates as $obj) {
                        if ($obj->isNew()) {
                            $collStates[] = $obj;
                        }
                    }
                }

                $this->collStates = $collStates;
                $this->collStatesPartial = false;
            }
        }

        return $this->collStates;
    }

    /**
     * Sets a collection of State objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $states A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCountry The current object (for fluent API support)
     */
    public function setStates(Collection $states, ConnectionInterface $con = null)
    {
        $statesToDelete = $this->getStates(new Criteria(), $con)->diff($states);


        $this->statesScheduledForDeletion = $statesToDelete;

        foreach ($statesToDelete as $stateRemoved) {
            $stateRemoved->setCountry(null);
        }

        $this->collStates = null;
        foreach ($states as $state) {
            $this->addState($state);
        }

        $this->collStates = $states;
        $this->collStatesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related State objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related State objects.
     * @throws PropelException
     */
    public function countStates(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collStatesPartial && !$this->isNew();
        if (null === $this->collStates || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collStates) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getStates());
            }

            $query = ChildStateQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCountry($this)
                ->count($con);
        }

        return count($this->collStates);
    }

    /**
     * Method called to associate a ChildState object to this object
     * through the ChildState foreign key attribute.
     *
     * @param    ChildState $l ChildState
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function addState(ChildState $l)
    {
        if ($this->collStates === null) {
            $this->initStates();
            $this->collStatesPartial = true;
        }

        if (!in_array($l, $this->collStates->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddState($l);
        }

        return $this;
    }

    /**
     * @param State $state The state object to add.
     */
    protected function doAddState($state)
    {
        $this->collStates[]= $state;
        $state->setCountry($this);
    }

    /**
     * @param  State $state The state object to remove.
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeState($state)
    {
        if ($this->getStates()->contains($state)) {
            $this->collStates->remove($this->collStates->search($state));
            if (null === $this->statesScheduledForDeletion) {
                $this->statesScheduledForDeletion = clone $this->collStates;
                $this->statesScheduledForDeletion->clear();
            }
            $this->statesScheduledForDeletion[]= clone $state;
            $state->setCountry(null);
        }

        return $this;
    }

    /**
     * Clears out the collTaxRuleCountries collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addTaxRuleCountries()
     */
    public function clearTaxRuleCountries()
    {
        $this->collTaxRuleCountries = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collTaxRuleCountries collection loaded partially.
     */
    public function resetPartialTaxRuleCountries($v = true)
    {
        $this->collTaxRuleCountriesPartial = $v;
    }

    /**
     * Initializes the collTaxRuleCountries collection.
     *
     * By default this just sets the collTaxRuleCountries collection to an empty array (like clearcollTaxRuleCountries());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initTaxRuleCountries($overrideExisting = true)
    {
        if (null !== $this->collTaxRuleCountries && !$overrideExisting) {
            return;
        }
        $this->collTaxRuleCountries = new ObjectCollection();
        $this->collTaxRuleCountries->setModel('\Thelia\Model\TaxRuleCountry');
    }

    /**
     * Gets an array of ChildTaxRuleCountry objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildTaxRuleCountry[] List of ChildTaxRuleCountry objects
     * @throws PropelException
     */
    public function getTaxRuleCountries($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collTaxRuleCountriesPartial && !$this->isNew();
        if (null === $this->collTaxRuleCountries || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collTaxRuleCountries) {
                // return empty collection
                $this->initTaxRuleCountries();
            } else {
                $collTaxRuleCountries = ChildTaxRuleCountryQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collTaxRuleCountriesPartial && count($collTaxRuleCountries)) {
                        $this->initTaxRuleCountries(false);

                        foreach ($collTaxRuleCountries as $obj) {
                            if (false == $this->collTaxRuleCountries->contains($obj)) {
                                $this->collTaxRuleCountries->append($obj);
                            }
                        }

                        $this->collTaxRuleCountriesPartial = true;
                    }

                    reset($collTaxRuleCountries);

                    return $collTaxRuleCountries;
                }

                if ($partial && $this->collTaxRuleCountries) {
                    foreach ($this->collTaxRuleCountries as $obj) {
                        if ($obj->isNew()) {
                            $collTaxRuleCountries[] = $obj;
                        }
                    }
                }

                $this->collTaxRuleCountries = $collTaxRuleCountries;
                $this->collTaxRuleCountriesPartial = false;
            }
        }

        return $this->collTaxRuleCountries;
    }

    /**
     * Sets a collection of TaxRuleCountry objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $taxRuleCountries A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCountry The current object (for fluent API support)
     */
    public function setTaxRuleCountries(Collection $taxRuleCountries, ConnectionInterface $con = null)
    {
        $taxRuleCountriesToDelete = $this->getTaxRuleCountries(new Criteria(), $con)->diff($taxRuleCountries);


        $this->taxRuleCountriesScheduledForDeletion = $taxRuleCountriesToDelete;

        foreach ($taxRuleCountriesToDelete as $taxRuleCountryRemoved) {
            $taxRuleCountryRemoved->setCountry(null);
        }

        $this->collTaxRuleCountries = null;
        foreach ($taxRuleCountries as $taxRuleCountry) {
            $this->addTaxRuleCountry($taxRuleCountry);
        }

        $this->collTaxRuleCountries = $taxRuleCountries;
        $this->collTaxRuleCountriesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related TaxRuleCountry objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related TaxRuleCountry objects.
     * @throws PropelException
     */
    public function countTaxRuleCountries(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collTaxRuleCountriesPartial && !$this->isNew();
        if (null === $this->collTaxRuleCountries || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collTaxRuleCountries) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getTaxRuleCountries());
            }

            $query = ChildTaxRuleCountryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCountry($this)
                ->count($con);
        }

        return count($this->collTaxRuleCountries);
    }

    /**
     * Method called to associate a ChildTaxRuleCountry object to this object
     * through the ChildTaxRuleCountry foreign key attribute.
     *
     * @param    ChildTaxRuleCountry $l ChildTaxRuleCountry
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function addTaxRuleCountry(ChildTaxRuleCountry $l)
    {
        if ($this->collTaxRuleCountries === null) {
            $this->initTaxRuleCountries();
            $this->collTaxRuleCountriesPartial = true;
        }

        if (!in_array($l, $this->collTaxRuleCountries->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddTaxRuleCountry($l);
        }

        return $this;
    }

    /**
     * @param TaxRuleCountry $taxRuleCountry The taxRuleCountry object to add.
     */
    protected function doAddTaxRuleCountry($taxRuleCountry)
    {
        $this->collTaxRuleCountries[]= $taxRuleCountry;
        $taxRuleCountry->setCountry($this);
    }

    /**
     * @param  TaxRuleCountry $taxRuleCountry The taxRuleCountry object to remove.
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeTaxRuleCountry($taxRuleCountry)
    {
        if ($this->getTaxRuleCountries()->contains($taxRuleCountry)) {
            $this->collTaxRuleCountries->remove($this->collTaxRuleCountries->search($taxRuleCountry));
            if (null === $this->taxRuleCountriesScheduledForDeletion) {
                $this->taxRuleCountriesScheduledForDeletion = clone $this->collTaxRuleCountries;
                $this->taxRuleCountriesScheduledForDeletion->clear();
            }
            $this->taxRuleCountriesScheduledForDeletion[]= clone $taxRuleCountry;
            $taxRuleCountry->setCountry(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related TaxRuleCountries from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildTaxRuleCountry[] List of ChildTaxRuleCountry objects
     */
    public function getTaxRuleCountriesJoinTax($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildTaxRuleCountryQuery::create(null, $criteria);
        $query->joinWith('Tax', $joinBehavior);

        return $this->getTaxRuleCountries($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related TaxRuleCountries from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildTaxRuleCountry[] List of ChildTaxRuleCountry objects
     */
    public function getTaxRuleCountriesJoinTaxRule($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildTaxRuleCountryQuery::create(null, $criteria);
        $query->joinWith('TaxRule', $joinBehavior);

        return $this->getTaxRuleCountries($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related TaxRuleCountries from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildTaxRuleCountry[] List of ChildTaxRuleCountry objects
     */
    public function getTaxRuleCountriesJoinState($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildTaxRuleCountryQuery::create(null, $criteria);
        $query->joinWith('State', $joinBehavior);

        return $this->getTaxRuleCountries($query, $con);
    }

    /**
     * Clears out the collAddresses collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAddresses()
     */
    public function clearAddresses()
    {
        $this->collAddresses = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAddresses collection loaded partially.
     */
    public function resetPartialAddresses($v = true)
    {
        $this->collAddressesPartial = $v;
    }

    /**
     * Initializes the collAddresses collection.
     *
     * By default this just sets the collAddresses collection to an empty array (like clearcollAddresses());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAddresses($overrideExisting = true)
    {
        if (null !== $this->collAddresses && !$overrideExisting) {
            return;
        }
        $this->collAddresses = new ObjectCollection();
        $this->collAddresses->setModel('\Thelia\Model\Address');
    }

    /**
     * Gets an array of ChildAddress objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAddress[] List of ChildAddress objects
     * @throws PropelException
     */
    public function getAddresses($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAddressesPartial && !$this->isNew();
        if (null === $this->collAddresses || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAddresses) {
                // return empty collection
                $this->initAddresses();
            } else {
                $collAddresses = ChildAddressQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAddressesPartial && count($collAddresses)) {
                        $this->initAddresses(false);

                        foreach ($collAddresses as $obj) {
                            if (false == $this->collAddresses->contains($obj)) {
                                $this->collAddresses->append($obj);
                            }
                        }

                        $this->collAddressesPartial = true;
                    }

                    reset($collAddresses);

                    return $collAddresses;
                }

                if ($partial && $this->collAddresses) {
                    foreach ($this->collAddresses as $obj) {
                        if ($obj->isNew()) {
                            $collAddresses[] = $obj;
                        }
                    }
                }

                $this->collAddresses = $collAddresses;
                $this->collAddressesPartial = false;
            }
        }

        return $this->collAddresses;
    }

    /**
     * Sets a collection of Address objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $addresses A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCountry The current object (for fluent API support)
     */
    public function setAddresses(Collection $addresses, ConnectionInterface $con = null)
    {
        $addressesToDelete = $this->getAddresses(new Criteria(), $con)->diff($addresses);


        $this->addressesScheduledForDeletion = $addressesToDelete;

        foreach ($addressesToDelete as $addressRemoved) {
            $addressRemoved->setCountry(null);
        }

        $this->collAddresses = null;
        foreach ($addresses as $address) {
            $this->addAddress($address);
        }

        $this->collAddresses = $addresses;
        $this->collAddressesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Address objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Address objects.
     * @throws PropelException
     */
    public function countAddresses(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAddressesPartial && !$this->isNew();
        if (null === $this->collAddresses || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAddresses) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAddresses());
            }

            $query = ChildAddressQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCountry($this)
                ->count($con);
        }

        return count($this->collAddresses);
    }

    /**
     * Method called to associate a ChildAddress object to this object
     * through the ChildAddress foreign key attribute.
     *
     * @param    ChildAddress $l ChildAddress
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function addAddress(ChildAddress $l)
    {
        if ($this->collAddresses === null) {
            $this->initAddresses();
            $this->collAddressesPartial = true;
        }

        if (!in_array($l, $this->collAddresses->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAddress($l);
        }

        return $this;
    }

    /**
     * @param Address $address The address object to add.
     */
    protected function doAddAddress($address)
    {
        $this->collAddresses[]= $address;
        $address->setCountry($this);
    }

    /**
     * @param  Address $address The address object to remove.
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeAddress($address)
    {
        if ($this->getAddresses()->contains($address)) {
            $this->collAddresses->remove($this->collAddresses->search($address));
            if (null === $this->addressesScheduledForDeletion) {
                $this->addressesScheduledForDeletion = clone $this->collAddresses;
                $this->addressesScheduledForDeletion->clear();
            }
            $this->addressesScheduledForDeletion[]= clone $address;
            $address->setCountry(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAddress[] List of ChildAddress objects
     */
    public function getAddressesJoinCustomer($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAddressQuery::create(null, $criteria);
        $query->joinWith('Customer', $joinBehavior);

        return $this->getAddresses($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAddress[] List of ChildAddress objects
     */
    public function getAddressesJoinCustomerTitle($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAddressQuery::create(null, $criteria);
        $query->joinWith('CustomerTitle', $joinBehavior);

        return $this->getAddresses($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related Addresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAddress[] List of ChildAddress objects
     */
    public function getAddressesJoinState($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAddressQuery::create(null, $criteria);
        $query->joinWith('State', $joinBehavior);

        return $this->getAddresses($query, $con);
    }

    /**
     * Clears out the collOrderAddresses collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderAddresses()
     */
    public function clearOrderAddresses()
    {
        $this->collOrderAddresses = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderAddresses collection loaded partially.
     */
    public function resetPartialOrderAddresses($v = true)
    {
        $this->collOrderAddressesPartial = $v;
    }

    /**
     * Initializes the collOrderAddresses collection.
     *
     * By default this just sets the collOrderAddresses collection to an empty array (like clearcollOrderAddresses());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderAddresses($overrideExisting = true)
    {
        if (null !== $this->collOrderAddresses && !$overrideExisting) {
            return;
        }
        $this->collOrderAddresses = new ObjectCollection();
        $this->collOrderAddresses->setModel('\Thelia\Model\OrderAddress');
    }

    /**
     * Gets an array of ChildOrderAddress objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderAddress[] List of ChildOrderAddress objects
     * @throws PropelException
     */
    public function getOrderAddresses($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderAddressesPartial && !$this->isNew();
        if (null === $this->collOrderAddresses || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderAddresses) {
                // return empty collection
                $this->initOrderAddresses();
            } else {
                $collOrderAddresses = ChildOrderAddressQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderAddressesPartial && count($collOrderAddresses)) {
                        $this->initOrderAddresses(false);

                        foreach ($collOrderAddresses as $obj) {
                            if (false == $this->collOrderAddresses->contains($obj)) {
                                $this->collOrderAddresses->append($obj);
                            }
                        }

                        $this->collOrderAddressesPartial = true;
                    }

                    reset($collOrderAddresses);

                    return $collOrderAddresses;
                }

                if ($partial && $this->collOrderAddresses) {
                    foreach ($this->collOrderAddresses as $obj) {
                        if ($obj->isNew()) {
                            $collOrderAddresses[] = $obj;
                        }
                    }
                }

                $this->collOrderAddresses = $collOrderAddresses;
                $this->collOrderAddressesPartial = false;
            }
        }

        return $this->collOrderAddresses;
    }

    /**
     * Sets a collection of OrderAddress objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderAddresses A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCountry The current object (for fluent API support)
     */
    public function setOrderAddresses(Collection $orderAddresses, ConnectionInterface $con = null)
    {
        $orderAddressesToDelete = $this->getOrderAddresses(new Criteria(), $con)->diff($orderAddresses);


        $this->orderAddressesScheduledForDeletion = $orderAddressesToDelete;

        foreach ($orderAddressesToDelete as $orderAddressRemoved) {
            $orderAddressRemoved->setCountry(null);
        }

        $this->collOrderAddresses = null;
        foreach ($orderAddresses as $orderAddress) {
            $this->addOrderAddress($orderAddress);
        }

        $this->collOrderAddresses = $orderAddresses;
        $this->collOrderAddressesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderAddress objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderAddress objects.
     * @throws PropelException
     */
    public function countOrderAddresses(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderAddressesPartial && !$this->isNew();
        if (null === $this->collOrderAddresses || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderAddresses) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderAddresses());
            }

            $query = ChildOrderAddressQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCountry($this)
                ->count($con);
        }

        return count($this->collOrderAddresses);
    }

    /**
     * Method called to associate a ChildOrderAddress object to this object
     * through the ChildOrderAddress foreign key attribute.
     *
     * @param    ChildOrderAddress $l ChildOrderAddress
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function addOrderAddress(ChildOrderAddress $l)
    {
        if ($this->collOrderAddresses === null) {
            $this->initOrderAddresses();
            $this->collOrderAddressesPartial = true;
        }

        if (!in_array($l, $this->collOrderAddresses->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderAddress($l);
        }

        return $this;
    }

    /**
     * @param OrderAddress $orderAddress The orderAddress object to add.
     */
    protected function doAddOrderAddress($orderAddress)
    {
        $this->collOrderAddresses[]= $orderAddress;
        $orderAddress->setCountry($this);
    }

    /**
     * @param  OrderAddress $orderAddress The orderAddress object to remove.
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeOrderAddress($orderAddress)
    {
        if ($this->getOrderAddresses()->contains($orderAddress)) {
            $this->collOrderAddresses->remove($this->collOrderAddresses->search($orderAddress));
            if (null === $this->orderAddressesScheduledForDeletion) {
                $this->orderAddressesScheduledForDeletion = clone $this->collOrderAddresses;
                $this->orderAddressesScheduledForDeletion->clear();
            }
            $this->orderAddressesScheduledForDeletion[]= clone $orderAddress;
            $orderAddress->setCountry(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related OrderAddresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrderAddress[] List of ChildOrderAddress objects
     */
    public function getOrderAddressesJoinCustomerTitle($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderAddressQuery::create(null, $criteria);
        $query->joinWith('CustomerTitle', $joinBehavior);

        return $this->getOrderAddresses($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related OrderAddresses from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrderAddress[] List of ChildOrderAddress objects
     */
    public function getOrderAddressesJoinState($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderAddressQuery::create(null, $criteria);
        $query->joinWith('State', $joinBehavior);

        return $this->getOrderAddresses($query, $con);
    }

    /**
     * Clears out the collCouponCountries collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCouponCountries()
     */
    public function clearCouponCountries()
    {
        $this->collCouponCountries = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCouponCountries collection loaded partially.
     */
    public function resetPartialCouponCountries($v = true)
    {
        $this->collCouponCountriesPartial = $v;
    }

    /**
     * Initializes the collCouponCountries collection.
     *
     * By default this just sets the collCouponCountries collection to an empty array (like clearcollCouponCountries());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCouponCountries($overrideExisting = true)
    {
        if (null !== $this->collCouponCountries && !$overrideExisting) {
            return;
        }
        $this->collCouponCountries = new ObjectCollection();
        $this->collCouponCountries->setModel('\Thelia\Model\CouponCountry');
    }

    /**
     * Gets an array of ChildCouponCountry objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCouponCountry[] List of ChildCouponCountry objects
     * @throws PropelException
     */
    public function getCouponCountries($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponCountriesPartial && !$this->isNew();
        if (null === $this->collCouponCountries || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCouponCountries) {
                // return empty collection
                $this->initCouponCountries();
            } else {
                $collCouponCountries = ChildCouponCountryQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCouponCountriesPartial && count($collCouponCountries)) {
                        $this->initCouponCountries(false);

                        foreach ($collCouponCountries as $obj) {
                            if (false == $this->collCouponCountries->contains($obj)) {
                                $this->collCouponCountries->append($obj);
                            }
                        }

                        $this->collCouponCountriesPartial = true;
                    }

                    reset($collCouponCountries);

                    return $collCouponCountries;
                }

                if ($partial && $this->collCouponCountries) {
                    foreach ($this->collCouponCountries as $obj) {
                        if ($obj->isNew()) {
                            $collCouponCountries[] = $obj;
                        }
                    }
                }

                $this->collCouponCountries = $collCouponCountries;
                $this->collCouponCountriesPartial = false;
            }
        }

        return $this->collCouponCountries;
    }

    /**
     * Sets a collection of CouponCountry objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $couponCountries A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCountry The current object (for fluent API support)
     */
    public function setCouponCountries(Collection $couponCountries, ConnectionInterface $con = null)
    {
        $couponCountriesToDelete = $this->getCouponCountries(new Criteria(), $con)->diff($couponCountries);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->couponCountriesScheduledForDeletion = clone $couponCountriesToDelete;

        foreach ($couponCountriesToDelete as $couponCountryRemoved) {
            $couponCountryRemoved->setCountry(null);
        }

        $this->collCouponCountries = null;
        foreach ($couponCountries as $couponCountry) {
            $this->addCouponCountry($couponCountry);
        }

        $this->collCouponCountries = $couponCountries;
        $this->collCouponCountriesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CouponCountry objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CouponCountry objects.
     * @throws PropelException
     */
    public function countCouponCountries(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCouponCountriesPartial && !$this->isNew();
        if (null === $this->collCouponCountries || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCouponCountries) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCouponCountries());
            }

            $query = ChildCouponCountryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCountry($this)
                ->count($con);
        }

        return count($this->collCouponCountries);
    }

    /**
     * Method called to associate a ChildCouponCountry object to this object
     * through the ChildCouponCountry foreign key attribute.
     *
     * @param    ChildCouponCountry $l ChildCouponCountry
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function addCouponCountry(ChildCouponCountry $l)
    {
        if ($this->collCouponCountries === null) {
            $this->initCouponCountries();
            $this->collCouponCountriesPartial = true;
        }

        if (!in_array($l, $this->collCouponCountries->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCouponCountry($l);
        }

        return $this;
    }

    /**
     * @param CouponCountry $couponCountry The couponCountry object to add.
     */
    protected function doAddCouponCountry($couponCountry)
    {
        $this->collCouponCountries[]= $couponCountry;
        $couponCountry->setCountry($this);
    }

    /**
     * @param  CouponCountry $couponCountry The couponCountry object to remove.
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeCouponCountry($couponCountry)
    {
        if ($this->getCouponCountries()->contains($couponCountry)) {
            $this->collCouponCountries->remove($this->collCouponCountries->search($couponCountry));
            if (null === $this->couponCountriesScheduledForDeletion) {
                $this->couponCountriesScheduledForDeletion = clone $this->collCouponCountries;
                $this->couponCountriesScheduledForDeletion->clear();
            }
            $this->couponCountriesScheduledForDeletion[]= clone $couponCountry;
            $couponCountry->setCountry(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related CouponCountries from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCouponCountry[] List of ChildCouponCountry objects
     */
    public function getCouponCountriesJoinCoupon($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCouponCountryQuery::create(null, $criteria);
        $query->joinWith('Coupon', $joinBehavior);

        return $this->getCouponCountries($query, $con);
    }

    /**
     * Clears out the collOrderCouponCountries collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderCouponCountries()
     */
    public function clearOrderCouponCountries()
    {
        $this->collOrderCouponCountries = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderCouponCountries collection loaded partially.
     */
    public function resetPartialOrderCouponCountries($v = true)
    {
        $this->collOrderCouponCountriesPartial = $v;
    }

    /**
     * Initializes the collOrderCouponCountries collection.
     *
     * By default this just sets the collOrderCouponCountries collection to an empty array (like clearcollOrderCouponCountries());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderCouponCountries($overrideExisting = true)
    {
        if (null !== $this->collOrderCouponCountries && !$overrideExisting) {
            return;
        }
        $this->collOrderCouponCountries = new ObjectCollection();
        $this->collOrderCouponCountries->setModel('\Thelia\Model\OrderCouponCountry');
    }

    /**
     * Gets an array of ChildOrderCouponCountry objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderCouponCountry[] List of ChildOrderCouponCountry objects
     * @throws PropelException
     */
    public function getOrderCouponCountries($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponCountriesPartial && !$this->isNew();
        if (null === $this->collOrderCouponCountries || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderCouponCountries) {
                // return empty collection
                $this->initOrderCouponCountries();
            } else {
                $collOrderCouponCountries = ChildOrderCouponCountryQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderCouponCountriesPartial && count($collOrderCouponCountries)) {
                        $this->initOrderCouponCountries(false);

                        foreach ($collOrderCouponCountries as $obj) {
                            if (false == $this->collOrderCouponCountries->contains($obj)) {
                                $this->collOrderCouponCountries->append($obj);
                            }
                        }

                        $this->collOrderCouponCountriesPartial = true;
                    }

                    reset($collOrderCouponCountries);

                    return $collOrderCouponCountries;
                }

                if ($partial && $this->collOrderCouponCountries) {
                    foreach ($this->collOrderCouponCountries as $obj) {
                        if ($obj->isNew()) {
                            $collOrderCouponCountries[] = $obj;
                        }
                    }
                }

                $this->collOrderCouponCountries = $collOrderCouponCountries;
                $this->collOrderCouponCountriesPartial = false;
            }
        }

        return $this->collOrderCouponCountries;
    }

    /**
     * Sets a collection of OrderCouponCountry objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderCouponCountries A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCountry The current object (for fluent API support)
     */
    public function setOrderCouponCountries(Collection $orderCouponCountries, ConnectionInterface $con = null)
    {
        $orderCouponCountriesToDelete = $this->getOrderCouponCountries(new Criteria(), $con)->diff($orderCouponCountries);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->orderCouponCountriesScheduledForDeletion = clone $orderCouponCountriesToDelete;

        foreach ($orderCouponCountriesToDelete as $orderCouponCountryRemoved) {
            $orderCouponCountryRemoved->setCountry(null);
        }

        $this->collOrderCouponCountries = null;
        foreach ($orderCouponCountries as $orderCouponCountry) {
            $this->addOrderCouponCountry($orderCouponCountry);
        }

        $this->collOrderCouponCountries = $orderCouponCountries;
        $this->collOrderCouponCountriesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderCouponCountry objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderCouponCountry objects.
     * @throws PropelException
     */
    public function countOrderCouponCountries(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponCountriesPartial && !$this->isNew();
        if (null === $this->collOrderCouponCountries || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderCouponCountries) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderCouponCountries());
            }

            $query = ChildOrderCouponCountryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCountry($this)
                ->count($con);
        }

        return count($this->collOrderCouponCountries);
    }

    /**
     * Method called to associate a ChildOrderCouponCountry object to this object
     * through the ChildOrderCouponCountry foreign key attribute.
     *
     * @param    ChildOrderCouponCountry $l ChildOrderCouponCountry
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function addOrderCouponCountry(ChildOrderCouponCountry $l)
    {
        if ($this->collOrderCouponCountries === null) {
            $this->initOrderCouponCountries();
            $this->collOrderCouponCountriesPartial = true;
        }

        if (!in_array($l, $this->collOrderCouponCountries->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderCouponCountry($l);
        }

        return $this;
    }

    /**
     * @param OrderCouponCountry $orderCouponCountry The orderCouponCountry object to add.
     */
    protected function doAddOrderCouponCountry($orderCouponCountry)
    {
        $this->collOrderCouponCountries[]= $orderCouponCountry;
        $orderCouponCountry->setCountry($this);
    }

    /**
     * @param  OrderCouponCountry $orderCouponCountry The orderCouponCountry object to remove.
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeOrderCouponCountry($orderCouponCountry)
    {
        if ($this->getOrderCouponCountries()->contains($orderCouponCountry)) {
            $this->collOrderCouponCountries->remove($this->collOrderCouponCountries->search($orderCouponCountry));
            if (null === $this->orderCouponCountriesScheduledForDeletion) {
                $this->orderCouponCountriesScheduledForDeletion = clone $this->collOrderCouponCountries;
                $this->orderCouponCountriesScheduledForDeletion->clear();
            }
            $this->orderCouponCountriesScheduledForDeletion[]= clone $orderCouponCountry;
            $orderCouponCountry->setCountry(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related OrderCouponCountries from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildOrderCouponCountry[] List of ChildOrderCouponCountry objects
     */
    public function getOrderCouponCountriesJoinOrderCoupon($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildOrderCouponCountryQuery::create(null, $criteria);
        $query->joinWith('OrderCoupon', $joinBehavior);

        return $this->getOrderCouponCountries($query, $con);
    }

    /**
     * Clears out the collCountryAreas collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCountryAreas()
     */
    public function clearCountryAreas()
    {
        $this->collCountryAreas = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCountryAreas collection loaded partially.
     */
    public function resetPartialCountryAreas($v = true)
    {
        $this->collCountryAreasPartial = $v;
    }

    /**
     * Initializes the collCountryAreas collection.
     *
     * By default this just sets the collCountryAreas collection to an empty array (like clearcollCountryAreas());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCountryAreas($overrideExisting = true)
    {
        if (null !== $this->collCountryAreas && !$overrideExisting) {
            return;
        }
        $this->collCountryAreas = new ObjectCollection();
        $this->collCountryAreas->setModel('\Thelia\Model\CountryArea');
    }

    /**
     * Gets an array of ChildCountryArea objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCountryArea[] List of ChildCountryArea objects
     * @throws PropelException
     */
    public function getCountryAreas($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCountryAreasPartial && !$this->isNew();
        if (null === $this->collCountryAreas || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCountryAreas) {
                // return empty collection
                $this->initCountryAreas();
            } else {
                $collCountryAreas = ChildCountryAreaQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCountryAreasPartial && count($collCountryAreas)) {
                        $this->initCountryAreas(false);

                        foreach ($collCountryAreas as $obj) {
                            if (false == $this->collCountryAreas->contains($obj)) {
                                $this->collCountryAreas->append($obj);
                            }
                        }

                        $this->collCountryAreasPartial = true;
                    }

                    reset($collCountryAreas);

                    return $collCountryAreas;
                }

                if ($partial && $this->collCountryAreas) {
                    foreach ($this->collCountryAreas as $obj) {
                        if ($obj->isNew()) {
                            $collCountryAreas[] = $obj;
                        }
                    }
                }

                $this->collCountryAreas = $collCountryAreas;
                $this->collCountryAreasPartial = false;
            }
        }

        return $this->collCountryAreas;
    }

    /**
     * Sets a collection of CountryArea objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $countryAreas A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCountry The current object (for fluent API support)
     */
    public function setCountryAreas(Collection $countryAreas, ConnectionInterface $con = null)
    {
        $countryAreasToDelete = $this->getCountryAreas(new Criteria(), $con)->diff($countryAreas);


        $this->countryAreasScheduledForDeletion = $countryAreasToDelete;

        foreach ($countryAreasToDelete as $countryAreaRemoved) {
            $countryAreaRemoved->setCountry(null);
        }

        $this->collCountryAreas = null;
        foreach ($countryAreas as $countryArea) {
            $this->addCountryArea($countryArea);
        }

        $this->collCountryAreas = $countryAreas;
        $this->collCountryAreasPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CountryArea objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CountryArea objects.
     * @throws PropelException
     */
    public function countCountryAreas(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCountryAreasPartial && !$this->isNew();
        if (null === $this->collCountryAreas || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCountryAreas) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCountryAreas());
            }

            $query = ChildCountryAreaQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCountry($this)
                ->count($con);
        }

        return count($this->collCountryAreas);
    }

    /**
     * Method called to associate a ChildCountryArea object to this object
     * through the ChildCountryArea foreign key attribute.
     *
     * @param    ChildCountryArea $l ChildCountryArea
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function addCountryArea(ChildCountryArea $l)
    {
        if ($this->collCountryAreas === null) {
            $this->initCountryAreas();
            $this->collCountryAreasPartial = true;
        }

        if (!in_array($l, $this->collCountryAreas->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCountryArea($l);
        }

        return $this;
    }

    /**
     * @param CountryArea $countryArea The countryArea object to add.
     */
    protected function doAddCountryArea($countryArea)
    {
        $this->collCountryAreas[]= $countryArea;
        $countryArea->setCountry($this);
    }

    /**
     * @param  CountryArea $countryArea The countryArea object to remove.
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeCountryArea($countryArea)
    {
        if ($this->getCountryAreas()->contains($countryArea)) {
            $this->collCountryAreas->remove($this->collCountryAreas->search($countryArea));
            if (null === $this->countryAreasScheduledForDeletion) {
                $this->countryAreasScheduledForDeletion = clone $this->collCountryAreas;
                $this->countryAreasScheduledForDeletion->clear();
            }
            $this->countryAreasScheduledForDeletion[]= clone $countryArea;
            $countryArea->setCountry(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Country is new, it will return
     * an empty collection; or if this Country has previously
     * been saved, it will retrieve related CountryAreas from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Country.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCountryArea[] List of ChildCountryArea objects
     */
    public function getCountryAreasJoinArea($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCountryAreaQuery::create(null, $criteria);
        $query->joinWith('Area', $joinBehavior);

        return $this->getCountryAreas($query, $con);
    }

    /**
     * Clears out the collCountryI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCountryI18ns()
     */
    public function clearCountryI18ns()
    {
        $this->collCountryI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCountryI18ns collection loaded partially.
     */
    public function resetPartialCountryI18ns($v = true)
    {
        $this->collCountryI18nsPartial = $v;
    }

    /**
     * Initializes the collCountryI18ns collection.
     *
     * By default this just sets the collCountryI18ns collection to an empty array (like clearcollCountryI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCountryI18ns($overrideExisting = true)
    {
        if (null !== $this->collCountryI18ns && !$overrideExisting) {
            return;
        }
        $this->collCountryI18ns = new ObjectCollection();
        $this->collCountryI18ns->setModel('\Thelia\Model\CountryI18n');
    }

    /**
     * Gets an array of ChildCountryI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCountryI18n[] List of ChildCountryI18n objects
     * @throws PropelException
     */
    public function getCountryI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCountryI18nsPartial && !$this->isNew();
        if (null === $this->collCountryI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCountryI18ns) {
                // return empty collection
                $this->initCountryI18ns();
            } else {
                $collCountryI18ns = ChildCountryI18nQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCountryI18nsPartial && count($collCountryI18ns)) {
                        $this->initCountryI18ns(false);

                        foreach ($collCountryI18ns as $obj) {
                            if (false == $this->collCountryI18ns->contains($obj)) {
                                $this->collCountryI18ns->append($obj);
                            }
                        }

                        $this->collCountryI18nsPartial = true;
                    }

                    reset($collCountryI18ns);

                    return $collCountryI18ns;
                }

                if ($partial && $this->collCountryI18ns) {
                    foreach ($this->collCountryI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collCountryI18ns[] = $obj;
                        }
                    }
                }

                $this->collCountryI18ns = $collCountryI18ns;
                $this->collCountryI18nsPartial = false;
            }
        }

        return $this->collCountryI18ns;
    }

    /**
     * Sets a collection of CountryI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $countryI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildCountry The current object (for fluent API support)
     */
    public function setCountryI18ns(Collection $countryI18ns, ConnectionInterface $con = null)
    {
        $countryI18nsToDelete = $this->getCountryI18ns(new Criteria(), $con)->diff($countryI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->countryI18nsScheduledForDeletion = clone $countryI18nsToDelete;

        foreach ($countryI18nsToDelete as $countryI18nRemoved) {
            $countryI18nRemoved->setCountry(null);
        }

        $this->collCountryI18ns = null;
        foreach ($countryI18ns as $countryI18n) {
            $this->addCountryI18n($countryI18n);
        }

        $this->collCountryI18ns = $countryI18ns;
        $this->collCountryI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CountryI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CountryI18n objects.
     * @throws PropelException
     */
    public function countCountryI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCountryI18nsPartial && !$this->isNew();
        if (null === $this->collCountryI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCountryI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCountryI18ns());
            }

            $query = ChildCountryI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByCountry($this)
                ->count($con);
        }

        return count($this->collCountryI18ns);
    }

    /**
     * Method called to associate a ChildCountryI18n object to this object
     * through the ChildCountryI18n foreign key attribute.
     *
     * @param    ChildCountryI18n $l ChildCountryI18n
     * @return   \Thelia\Model\Country The current object (for fluent API support)
     */
    public function addCountryI18n(ChildCountryI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collCountryI18ns === null) {
            $this->initCountryI18ns();
            $this->collCountryI18nsPartial = true;
        }

        if (!in_array($l, $this->collCountryI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCountryI18n($l);
        }

        return $this;
    }

    /**
     * @param CountryI18n $countryI18n The countryI18n object to add.
     */
    protected function doAddCountryI18n($countryI18n)
    {
        $this->collCountryI18ns[]= $countryI18n;
        $countryI18n->setCountry($this);
    }

    /**
     * @param  CountryI18n $countryI18n The countryI18n object to remove.
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeCountryI18n($countryI18n)
    {
        if ($this->getCountryI18ns()->contains($countryI18n)) {
            $this->collCountryI18ns->remove($this->collCountryI18ns->search($countryI18n));
            if (null === $this->countryI18nsScheduledForDeletion) {
                $this->countryI18nsScheduledForDeletion = clone $this->collCountryI18ns;
                $this->countryI18nsScheduledForDeletion->clear();
            }
            $this->countryI18nsScheduledForDeletion[]= clone $countryI18n;
            $countryI18n->setCountry(null);
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
     * to the current object by way of the coupon_country cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
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
                    ->filterByCountry($this)
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
     * to the current object by way of the coupon_country cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $coupons A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildCountry The current object (for fluent API support)
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
     * to the current object by way of the coupon_country cross-reference table.
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
                    ->filterByCountry($this)
                    ->count($con);
            }
        } else {
            return count($this->collCoupons);
        }
    }

    /**
     * Associate a ChildCoupon object to this object
     * through the coupon_country cross reference table.
     *
     * @param  ChildCoupon $coupon The ChildCouponCountry object to relate
     * @return ChildCountry The current object (for fluent API support)
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
        $couponCountry = new ChildCouponCountry();
        $couponCountry->setCoupon($coupon);
        $this->addCouponCountry($couponCountry);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$coupon->getCountries()->contains($this)) {
            $foreignCollection   = $coupon->getCountries();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildCoupon object to this object
     * through the coupon_country cross reference table.
     *
     * @param ChildCoupon $coupon The ChildCouponCountry object to relate
     * @return ChildCountry The current object (for fluent API support)
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
     * to the current object by way of the order_coupon_country cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
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
                    ->filterByCountry($this)
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
     * to the current object by way of the order_coupon_country cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $orderCoupons A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildCountry The current object (for fluent API support)
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
     * to the current object by way of the order_coupon_country cross-reference table.
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
                    ->filterByCountry($this)
                    ->count($con);
            }
        } else {
            return count($this->collOrderCoupons);
        }
    }

    /**
     * Associate a ChildOrderCoupon object to this object
     * through the order_coupon_country cross reference table.
     *
     * @param  ChildOrderCoupon $orderCoupon The ChildOrderCouponCountry object to relate
     * @return ChildCountry The current object (for fluent API support)
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
        $orderCouponCountry = new ChildOrderCouponCountry();
        $orderCouponCountry->setOrderCoupon($orderCoupon);
        $this->addOrderCouponCountry($orderCouponCountry);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$orderCoupon->getCountries()->contains($this)) {
            $foreignCollection   = $orderCoupon->getCountries();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildOrderCoupon object to this object
     * through the order_coupon_country cross reference table.
     *
     * @param ChildOrderCoupon $orderCoupon The ChildOrderCouponCountry object to relate
     * @return ChildCountry The current object (for fluent API support)
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
     * Clears out the collAreas collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAreas()
     */
    public function clearAreas()
    {
        $this->collAreas = null; // important to set this to NULL since that means it is uninitialized
        $this->collAreasPartial = null;
    }

    /**
     * Initializes the collAreas collection.
     *
     * By default this just sets the collAreas collection to an empty collection (like clearAreas());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initAreas()
    {
        $this->collAreas = new ObjectCollection();
        $this->collAreas->setModel('\Thelia\Model\Area');
    }

    /**
     * Gets a collection of ChildArea objects related by a many-to-many relationship
     * to the current object by way of the country_area cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildCountry is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildArea[] List of ChildArea objects
     */
    public function getAreas($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collAreas || null !== $criteria) {
            if ($this->isNew() && null === $this->collAreas) {
                // return empty collection
                $this->initAreas();
            } else {
                $collAreas = ChildAreaQuery::create(null, $criteria)
                    ->filterByCountry($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collAreas;
                }
                $this->collAreas = $collAreas;
            }
        }

        return $this->collAreas;
    }

    /**
     * Sets a collection of Area objects related by a many-to-many relationship
     * to the current object by way of the country_area cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $areas A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildCountry The current object (for fluent API support)
     */
    public function setAreas(Collection $areas, ConnectionInterface $con = null)
    {
        $this->clearAreas();
        $currentAreas = $this->getAreas();

        $this->areasScheduledForDeletion = $currentAreas->diff($areas);

        foreach ($areas as $area) {
            if (!$currentAreas->contains($area)) {
                $this->doAddArea($area);
            }
        }

        $this->collAreas = $areas;

        return $this;
    }

    /**
     * Gets the number of ChildArea objects related by a many-to-many relationship
     * to the current object by way of the country_area cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildArea objects
     */
    public function countAreas($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collAreas || null !== $criteria) {
            if ($this->isNew() && null === $this->collAreas) {
                return 0;
            } else {
                $query = ChildAreaQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCountry($this)
                    ->count($con);
            }
        } else {
            return count($this->collAreas);
        }
    }

    /**
     * Associate a ChildArea object to this object
     * through the country_area cross reference table.
     *
     * @param  ChildArea $area The ChildCountryArea object to relate
     * @return ChildCountry The current object (for fluent API support)
     */
    public function addArea(ChildArea $area)
    {
        if ($this->collAreas === null) {
            $this->initAreas();
        }

        if (!$this->collAreas->contains($area)) { // only add it if the **same** object is not already associated
            $this->doAddArea($area);
            $this->collAreas[] = $area;
        }

        return $this;
    }

    /**
     * @param    Area $area The area object to add.
     */
    protected function doAddArea($area)
    {
        $countryArea = new ChildCountryArea();
        $countryArea->setArea($area);
        $this->addCountryArea($countryArea);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$area->getCountries()->contains($this)) {
            $foreignCollection   = $area->getCountries();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildArea object to this object
     * through the country_area cross reference table.
     *
     * @param ChildArea $area The ChildCountryArea object to relate
     * @return ChildCountry The current object (for fluent API support)
     */
    public function removeArea(ChildArea $area)
    {
        if ($this->getAreas()->contains($area)) {
            $this->collAreas->remove($this->collAreas->search($area));

            if (null === $this->areasScheduledForDeletion) {
                $this->areasScheduledForDeletion = clone $this->collAreas;
                $this->areasScheduledForDeletion->clear();
            }

            $this->areasScheduledForDeletion[] = $area;
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
        $this->isocode = null;
        $this->isoalpha2 = null;
        $this->isoalpha3 = null;
        $this->has_states = null;
        $this->need_zip_code = null;
        $this->zip_code_format = null;
        $this->by_default = null;
        $this->shop_country = null;
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
            if ($this->collStates) {
                foreach ($this->collStates as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collTaxRuleCountries) {
                foreach ($this->collTaxRuleCountries as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAddresses) {
                foreach ($this->collAddresses as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrderAddresses) {
                foreach ($this->collOrderAddresses as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCouponCountries) {
                foreach ($this->collCouponCountries as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrderCouponCountries) {
                foreach ($this->collOrderCouponCountries as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCountryAreas) {
                foreach ($this->collCountryAreas as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCountryI18ns) {
                foreach ($this->collCountryI18ns as $o) {
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
            if ($this->collAreas) {
                foreach ($this->collAreas as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collStates = null;
        $this->collTaxRuleCountries = null;
        $this->collAddresses = null;
        $this->collOrderAddresses = null;
        $this->collCouponCountries = null;
        $this->collOrderCouponCountries = null;
        $this->collCountryAreas = null;
        $this->collCountryI18ns = null;
        $this->collCoupons = null;
        $this->collOrderCoupons = null;
        $this->collAreas = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CountryTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildCountry The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[CountryTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildCountry The current object (for fluent API support)
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
     * @return ChildCountryI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collCountryI18ns) {
                foreach ($this->collCountryI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildCountryI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildCountryI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addCountryI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildCountry The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildCountryI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collCountryI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collCountryI18ns[$key]);
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
     * @return ChildCountryI18n */
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
         * @return   \Thelia\Model\CountryI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\CountryI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\CountryI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\CountryI18n The current object (for fluent API support)
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
