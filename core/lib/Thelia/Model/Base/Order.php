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
use Thelia\Model\Currency as ChildCurrency;
use Thelia\Model\CurrencyQuery as ChildCurrencyQuery;
use Thelia\Model\Customer as ChildCustomer;
use Thelia\Model\CustomerQuery as ChildCustomerQuery;
use Thelia\Model\CustomerVersionQuery as ChildCustomerVersionQuery;
use Thelia\Model\Lang as ChildLang;
use Thelia\Model\LangQuery as ChildLangQuery;
use Thelia\Model\Module as ChildModule;
use Thelia\Model\ModuleQuery as ChildModuleQuery;
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderAddress as ChildOrderAddress;
use Thelia\Model\OrderAddressQuery as ChildOrderAddressQuery;
use Thelia\Model\OrderCoupon as ChildOrderCoupon;
use Thelia\Model\OrderCouponQuery as ChildOrderCouponQuery;
use Thelia\Model\OrderProduct as ChildOrderProduct;
use Thelia\Model\OrderProductQuery as ChildOrderProductQuery;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\OrderStatus as ChildOrderStatus;
use Thelia\Model\OrderStatusQuery as ChildOrderStatusQuery;
use Thelia\Model\OrderVersion as ChildOrderVersion;
use Thelia\Model\OrderVersionQuery as ChildOrderVersionQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\OrderVersionTableMap;

abstract class Order implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\OrderTableMap';


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
     * The value for the ref field.
     * @var        string
     */
    protected $ref;

    /**
     * The value for the customer_id field.
     * @var        int
     */
    protected $customer_id;

    /**
     * The value for the invoice_order_address_id field.
     * @var        int
     */
    protected $invoice_order_address_id;

    /**
     * The value for the delivery_order_address_id field.
     * @var        int
     */
    protected $delivery_order_address_id;

    /**
     * The value for the invoice_date field.
     * @var        string
     */
    protected $invoice_date;

    /**
     * The value for the currency_id field.
     * @var        int
     */
    protected $currency_id;

    /**
     * The value for the currency_rate field.
     * @var        double
     */
    protected $currency_rate;

    /**
     * The value for the transaction_ref field.
     * @var        string
     */
    protected $transaction_ref;

    /**
     * The value for the delivery_ref field.
     * @var        string
     */
    protected $delivery_ref;

    /**
     * The value for the invoice_ref field.
     * @var        string
     */
    protected $invoice_ref;

    /**
     * The value for the discount field.
     * Note: this column has a database default value of: '0.000000'
     * @var        string
     */
    protected $discount;

    /**
     * The value for the postage field.
     * Note: this column has a database default value of: '0.000000'
     * @var        string
     */
    protected $postage;

    /**
     * The value for the postage_tax field.
     * Note: this column has a database default value of: '0.000000'
     * @var        string
     */
    protected $postage_tax;

    /**
     * The value for the postage_tax_rule_title field.
     * @var        string
     */
    protected $postage_tax_rule_title;

    /**
     * The value for the payment_module_id field.
     * @var        int
     */
    protected $payment_module_id;

    /**
     * The value for the delivery_module_id field.
     * @var        int
     */
    protected $delivery_module_id;

    /**
     * The value for the status_id field.
     * @var        int
     */
    protected $status_id;

    /**
     * The value for the lang_id field.
     * @var        int
     */
    protected $lang_id;

    /**
     * The value for the cart_id field.
     * @var        int
     */
    protected $cart_id;

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
     * @var        Currency
     */
    protected $aCurrency;

    /**
     * @var        Customer
     */
    protected $aCustomer;

    /**
     * @var        OrderAddress
     */
    protected $aOrderAddressRelatedByInvoiceOrderAddressId;

    /**
     * @var        OrderAddress
     */
    protected $aOrderAddressRelatedByDeliveryOrderAddressId;

    /**
     * @var        OrderStatus
     */
    protected $aOrderStatus;

    /**
     * @var        Module
     */
    protected $aModuleRelatedByPaymentModuleId;

    /**
     * @var        Module
     */
    protected $aModuleRelatedByDeliveryModuleId;

    /**
     * @var        Lang
     */
    protected $aLang;

    /**
     * @var        ObjectCollection|ChildOrderProduct[] Collection to store aggregation of ChildOrderProduct objects.
     */
    protected $collOrderProducts;
    protected $collOrderProductsPartial;

    /**
     * @var        ObjectCollection|ChildOrderCoupon[] Collection to store aggregation of ChildOrderCoupon objects.
     */
    protected $collOrderCoupons;
    protected $collOrderCouponsPartial;

    /**
     * @var        ObjectCollection|ChildOrderVersion[] Collection to store aggregation of ChildOrderVersion objects.
     */
    protected $collOrderVersions;
    protected $collOrderVersionsPartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    // versionable behavior


    /**
     * @var bool
     */
    protected $enforceVersion = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderProductsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderCouponsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->discount = '0.000000';
        $this->postage = '0.000000';
        $this->postage_tax = '0.000000';
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Order object.
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
     * Compares this with another <code>Order</code> instance.  If
     * <code>obj</code> is an instance of <code>Order</code>, delegates to
     * <code>equals(Order)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Order The current object, for fluid interface
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
     * @return Order The current object, for fluid interface
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
     * Get the [ref] column value.
     *
     * @return   string
     */
    public function getRef()
    {

        return $this->ref;
    }

    /**
     * Get the [customer_id] column value.
     *
     * @return   int
     */
    public function getCustomerId()
    {

        return $this->customer_id;
    }

    /**
     * Get the [invoice_order_address_id] column value.
     *
     * @return   int
     */
    public function getInvoiceOrderAddressId()
    {

        return $this->invoice_order_address_id;
    }

    /**
     * Get the [delivery_order_address_id] column value.
     *
     * @return   int
     */
    public function getDeliveryOrderAddressId()
    {

        return $this->delivery_order_address_id;
    }

    /**
     * Get the [optionally formatted] temporal [invoice_date] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getInvoiceDate($format = NULL)
    {
        if ($format === null) {
            return $this->invoice_date;
        } else {
            return $this->invoice_date instanceof \DateTime ? $this->invoice_date->format($format) : null;
        }
    }

    /**
     * Get the [currency_id] column value.
     *
     * @return   int
     */
    public function getCurrencyId()
    {

        return $this->currency_id;
    }

    /**
     * Get the [currency_rate] column value.
     *
     * @return   double
     */
    public function getCurrencyRate()
    {

        return $this->currency_rate;
    }

    /**
     * Get the [transaction_ref] column value.
     * transaction reference - usually use to identify a transaction with banking modules
     * @return   string
     */
    public function getTransactionRef()
    {

        return $this->transaction_ref;
    }

    /**
     * Get the [delivery_ref] column value.
     * delivery reference - usually use to identify a delivery progress on a distant delivery tracker website
     * @return   string
     */
    public function getDeliveryRef()
    {

        return $this->delivery_ref;
    }

    /**
     * Get the [invoice_ref] column value.
     * the invoice reference
     * @return   string
     */
    public function getInvoiceRef()
    {

        return $this->invoice_ref;
    }

    /**
     * Get the [discount] column value.
     *
     * @return   string
     */
    public function getDiscount()
    {

        return $this->discount;
    }

    /**
     * Get the [postage] column value.
     *
     * @return   string
     */
    public function getPostage()
    {

        return $this->postage;
    }

    /**
     * Get the [postage_tax] column value.
     *
     * @return   string
     */
    public function getPostageTax()
    {

        return $this->postage_tax;
    }

    /**
     * Get the [postage_tax_rule_title] column value.
     *
     * @return   string
     */
    public function getPostageTaxRuleTitle()
    {

        return $this->postage_tax_rule_title;
    }

    /**
     * Get the [payment_module_id] column value.
     *
     * @return   int
     */
    public function getPaymentModuleId()
    {

        return $this->payment_module_id;
    }

    /**
     * Get the [delivery_module_id] column value.
     *
     * @return   int
     */
    public function getDeliveryModuleId()
    {

        return $this->delivery_module_id;
    }

    /**
     * Get the [status_id] column value.
     *
     * @return   int
     */
    public function getStatusId()
    {

        return $this->status_id;
    }

    /**
     * Get the [lang_id] column value.
     *
     * @return   int
     */
    public function getLangId()
    {

        return $this->lang_id;
    }

    /**
     * Get the [cart_id] column value.
     *
     * @return   int
     */
    public function getCartId()
    {

        return $this->cart_id;
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
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[OrderTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [ref] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->ref !== $v) {
            $this->ref = $v;
            $this->modifiedColumns[OrderTableMap::REF] = true;
        }


        return $this;
    } // setRef()

    /**
     * Set the value of [customer_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setCustomerId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->customer_id !== $v) {
            $this->customer_id = $v;
            $this->modifiedColumns[OrderTableMap::CUSTOMER_ID] = true;
        }

        if ($this->aCustomer !== null && $this->aCustomer->getId() !== $v) {
            $this->aCustomer = null;
        }


        return $this;
    } // setCustomerId()

    /**
     * Set the value of [invoice_order_address_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setInvoiceOrderAddressId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->invoice_order_address_id !== $v) {
            $this->invoice_order_address_id = $v;
            $this->modifiedColumns[OrderTableMap::INVOICE_ORDER_ADDRESS_ID] = true;
        }

        if ($this->aOrderAddressRelatedByInvoiceOrderAddressId !== null && $this->aOrderAddressRelatedByInvoiceOrderAddressId->getId() !== $v) {
            $this->aOrderAddressRelatedByInvoiceOrderAddressId = null;
        }


        return $this;
    } // setInvoiceOrderAddressId()

    /**
     * Set the value of [delivery_order_address_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setDeliveryOrderAddressId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->delivery_order_address_id !== $v) {
            $this->delivery_order_address_id = $v;
            $this->modifiedColumns[OrderTableMap::DELIVERY_ORDER_ADDRESS_ID] = true;
        }

        if ($this->aOrderAddressRelatedByDeliveryOrderAddressId !== null && $this->aOrderAddressRelatedByDeliveryOrderAddressId->getId() !== $v) {
            $this->aOrderAddressRelatedByDeliveryOrderAddressId = null;
        }


        return $this;
    } // setDeliveryOrderAddressId()

    /**
     * Sets the value of [invoice_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setInvoiceDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->invoice_date !== null || $dt !== null) {
            if ($dt !== $this->invoice_date) {
                $this->invoice_date = $dt;
                $this->modifiedColumns[OrderTableMap::INVOICE_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setInvoiceDate()

    /**
     * Set the value of [currency_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setCurrencyId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->currency_id !== $v) {
            $this->currency_id = $v;
            $this->modifiedColumns[OrderTableMap::CURRENCY_ID] = true;
        }

        if ($this->aCurrency !== null && $this->aCurrency->getId() !== $v) {
            $this->aCurrency = null;
        }


        return $this;
    } // setCurrencyId()

    /**
     * Set the value of [currency_rate] column.
     *
     * @param      double $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setCurrencyRate($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->currency_rate !== $v) {
            $this->currency_rate = $v;
            $this->modifiedColumns[OrderTableMap::CURRENCY_RATE] = true;
        }


        return $this;
    } // setCurrencyRate()

    /**
     * Set the value of [transaction_ref] column.
     * transaction reference - usually use to identify a transaction with banking modules
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setTransactionRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->transaction_ref !== $v) {
            $this->transaction_ref = $v;
            $this->modifiedColumns[OrderTableMap::TRANSACTION_REF] = true;
        }


        return $this;
    } // setTransactionRef()

    /**
     * Set the value of [delivery_ref] column.
     * delivery reference - usually use to identify a delivery progress on a distant delivery tracker website
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setDeliveryRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->delivery_ref !== $v) {
            $this->delivery_ref = $v;
            $this->modifiedColumns[OrderTableMap::DELIVERY_REF] = true;
        }


        return $this;
    } // setDeliveryRef()

    /**
     * Set the value of [invoice_ref] column.
     * the invoice reference
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setInvoiceRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->invoice_ref !== $v) {
            $this->invoice_ref = $v;
            $this->modifiedColumns[OrderTableMap::INVOICE_REF] = true;
        }


        return $this;
    } // setInvoiceRef()

    /**
     * Set the value of [discount] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setDiscount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->discount !== $v) {
            $this->discount = $v;
            $this->modifiedColumns[OrderTableMap::DISCOUNT] = true;
        }


        return $this;
    } // setDiscount()

    /**
     * Set the value of [postage] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setPostage($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->postage !== $v) {
            $this->postage = $v;
            $this->modifiedColumns[OrderTableMap::POSTAGE] = true;
        }


        return $this;
    } // setPostage()

    /**
     * Set the value of [postage_tax] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setPostageTax($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->postage_tax !== $v) {
            $this->postage_tax = $v;
            $this->modifiedColumns[OrderTableMap::POSTAGE_TAX] = true;
        }


        return $this;
    } // setPostageTax()

    /**
     * Set the value of [postage_tax_rule_title] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setPostageTaxRuleTitle($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->postage_tax_rule_title !== $v) {
            $this->postage_tax_rule_title = $v;
            $this->modifiedColumns[OrderTableMap::POSTAGE_TAX_RULE_TITLE] = true;
        }


        return $this;
    } // setPostageTaxRuleTitle()

    /**
     * Set the value of [payment_module_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setPaymentModuleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->payment_module_id !== $v) {
            $this->payment_module_id = $v;
            $this->modifiedColumns[OrderTableMap::PAYMENT_MODULE_ID] = true;
        }

        if ($this->aModuleRelatedByPaymentModuleId !== null && $this->aModuleRelatedByPaymentModuleId->getId() !== $v) {
            $this->aModuleRelatedByPaymentModuleId = null;
        }


        return $this;
    } // setPaymentModuleId()

    /**
     * Set the value of [delivery_module_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setDeliveryModuleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->delivery_module_id !== $v) {
            $this->delivery_module_id = $v;
            $this->modifiedColumns[OrderTableMap::DELIVERY_MODULE_ID] = true;
        }

        if ($this->aModuleRelatedByDeliveryModuleId !== null && $this->aModuleRelatedByDeliveryModuleId->getId() !== $v) {
            $this->aModuleRelatedByDeliveryModuleId = null;
        }


        return $this;
    } // setDeliveryModuleId()

    /**
     * Set the value of [status_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setStatusId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->status_id !== $v) {
            $this->status_id = $v;
            $this->modifiedColumns[OrderTableMap::STATUS_ID] = true;
        }

        if ($this->aOrderStatus !== null && $this->aOrderStatus->getId() !== $v) {
            $this->aOrderStatus = null;
        }


        return $this;
    } // setStatusId()

    /**
     * Set the value of [lang_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setLangId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->lang_id !== $v) {
            $this->lang_id = $v;
            $this->modifiedColumns[OrderTableMap::LANG_ID] = true;
        }

        if ($this->aLang !== null && $this->aLang->getId() !== $v) {
            $this->aLang = null;
        }


        return $this;
    } // setLangId()

    /**
     * Set the value of [cart_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setCartId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->cart_id !== $v) {
            $this->cart_id = $v;
            $this->modifiedColumns[OrderTableMap::CART_ID] = true;
        }


        return $this;
    } // setCartId()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[OrderTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[OrderTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[OrderTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[OrderTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[OrderTableMap::VERSION_CREATED_BY] = true;
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
            if ($this->discount !== '0.000000') {
                return false;
            }

            if ($this->postage !== '0.000000') {
                return false;
            }

            if ($this->postage_tax !== '0.000000') {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : OrderTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : OrderTableMap::translateFieldName('Ref', TableMap::TYPE_PHPNAME, $indexType)];
            $this->ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : OrderTableMap::translateFieldName('CustomerId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->customer_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : OrderTableMap::translateFieldName('InvoiceOrderAddressId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->invoice_order_address_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : OrderTableMap::translateFieldName('DeliveryOrderAddressId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->delivery_order_address_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : OrderTableMap::translateFieldName('InvoiceDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->invoice_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : OrderTableMap::translateFieldName('CurrencyId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->currency_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : OrderTableMap::translateFieldName('CurrencyRate', TableMap::TYPE_PHPNAME, $indexType)];
            $this->currency_rate = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : OrderTableMap::translateFieldName('TransactionRef', TableMap::TYPE_PHPNAME, $indexType)];
            $this->transaction_ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : OrderTableMap::translateFieldName('DeliveryRef', TableMap::TYPE_PHPNAME, $indexType)];
            $this->delivery_ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : OrderTableMap::translateFieldName('InvoiceRef', TableMap::TYPE_PHPNAME, $indexType)];
            $this->invoice_ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : OrderTableMap::translateFieldName('Discount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->discount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : OrderTableMap::translateFieldName('Postage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->postage = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : OrderTableMap::translateFieldName('PostageTax', TableMap::TYPE_PHPNAME, $indexType)];
            $this->postage_tax = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : OrderTableMap::translateFieldName('PostageTaxRuleTitle', TableMap::TYPE_PHPNAME, $indexType)];
            $this->postage_tax_rule_title = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : OrderTableMap::translateFieldName('PaymentModuleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->payment_module_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : OrderTableMap::translateFieldName('DeliveryModuleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->delivery_module_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : OrderTableMap::translateFieldName('StatusId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->status_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : OrderTableMap::translateFieldName('LangId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lang_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : OrderTableMap::translateFieldName('CartId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cart_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : OrderTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : OrderTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : OrderTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 23 + $startcol : OrderTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 24 + $startcol : OrderTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 25; // 25 = OrderTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Order object", 0, $e);
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
        if ($this->aCustomer !== null && $this->customer_id !== $this->aCustomer->getId()) {
            $this->aCustomer = null;
        }
        if ($this->aOrderAddressRelatedByInvoiceOrderAddressId !== null && $this->invoice_order_address_id !== $this->aOrderAddressRelatedByInvoiceOrderAddressId->getId()) {
            $this->aOrderAddressRelatedByInvoiceOrderAddressId = null;
        }
        if ($this->aOrderAddressRelatedByDeliveryOrderAddressId !== null && $this->delivery_order_address_id !== $this->aOrderAddressRelatedByDeliveryOrderAddressId->getId()) {
            $this->aOrderAddressRelatedByDeliveryOrderAddressId = null;
        }
        if ($this->aCurrency !== null && $this->currency_id !== $this->aCurrency->getId()) {
            $this->aCurrency = null;
        }
        if ($this->aModuleRelatedByPaymentModuleId !== null && $this->payment_module_id !== $this->aModuleRelatedByPaymentModuleId->getId()) {
            $this->aModuleRelatedByPaymentModuleId = null;
        }
        if ($this->aModuleRelatedByDeliveryModuleId !== null && $this->delivery_module_id !== $this->aModuleRelatedByDeliveryModuleId->getId()) {
            $this->aModuleRelatedByDeliveryModuleId = null;
        }
        if ($this->aOrderStatus !== null && $this->status_id !== $this->aOrderStatus->getId()) {
            $this->aOrderStatus = null;
        }
        if ($this->aLang !== null && $this->lang_id !== $this->aLang->getId()) {
            $this->aLang = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(OrderTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildOrderQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aCurrency = null;
            $this->aCustomer = null;
            $this->aOrderAddressRelatedByInvoiceOrderAddressId = null;
            $this->aOrderAddressRelatedByDeliveryOrderAddressId = null;
            $this->aOrderStatus = null;
            $this->aModuleRelatedByPaymentModuleId = null;
            $this->aModuleRelatedByDeliveryModuleId = null;
            $this->aLang = null;
            $this->collOrderProducts = null;

            $this->collOrderCoupons = null;

            $this->collOrderVersions = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Order::setDeleted()
     * @see Order::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildOrderQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(OrderTableMap::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(OrderTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(OrderTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(OrderTableMap::UPDATED_AT)) {
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
                OrderTableMap::addInstanceToPool($this);
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

            if ($this->aCurrency !== null) {
                if ($this->aCurrency->isModified() || $this->aCurrency->isNew()) {
                    $affectedRows += $this->aCurrency->save($con);
                }
                $this->setCurrency($this->aCurrency);
            }

            if ($this->aCustomer !== null) {
                if ($this->aCustomer->isModified() || $this->aCustomer->isNew()) {
                    $affectedRows += $this->aCustomer->save($con);
                }
                $this->setCustomer($this->aCustomer);
            }

            if ($this->aOrderAddressRelatedByInvoiceOrderAddressId !== null) {
                if ($this->aOrderAddressRelatedByInvoiceOrderAddressId->isModified() || $this->aOrderAddressRelatedByInvoiceOrderAddressId->isNew()) {
                    $affectedRows += $this->aOrderAddressRelatedByInvoiceOrderAddressId->save($con);
                }
                $this->setOrderAddressRelatedByInvoiceOrderAddressId($this->aOrderAddressRelatedByInvoiceOrderAddressId);
            }

            if ($this->aOrderAddressRelatedByDeliveryOrderAddressId !== null) {
                if ($this->aOrderAddressRelatedByDeliveryOrderAddressId->isModified() || $this->aOrderAddressRelatedByDeliveryOrderAddressId->isNew()) {
                    $affectedRows += $this->aOrderAddressRelatedByDeliveryOrderAddressId->save($con);
                }
                $this->setOrderAddressRelatedByDeliveryOrderAddressId($this->aOrderAddressRelatedByDeliveryOrderAddressId);
            }

            if ($this->aOrderStatus !== null) {
                if ($this->aOrderStatus->isModified() || $this->aOrderStatus->isNew()) {
                    $affectedRows += $this->aOrderStatus->save($con);
                }
                $this->setOrderStatus($this->aOrderStatus);
            }

            if ($this->aModuleRelatedByPaymentModuleId !== null) {
                if ($this->aModuleRelatedByPaymentModuleId->isModified() || $this->aModuleRelatedByPaymentModuleId->isNew()) {
                    $affectedRows += $this->aModuleRelatedByPaymentModuleId->save($con);
                }
                $this->setModuleRelatedByPaymentModuleId($this->aModuleRelatedByPaymentModuleId);
            }

            if ($this->aModuleRelatedByDeliveryModuleId !== null) {
                if ($this->aModuleRelatedByDeliveryModuleId->isModified() || $this->aModuleRelatedByDeliveryModuleId->isNew()) {
                    $affectedRows += $this->aModuleRelatedByDeliveryModuleId->save($con);
                }
                $this->setModuleRelatedByDeliveryModuleId($this->aModuleRelatedByDeliveryModuleId);
            }

            if ($this->aLang !== null) {
                if ($this->aLang->isModified() || $this->aLang->isNew()) {
                    $affectedRows += $this->aLang->save($con);
                }
                $this->setLang($this->aLang);
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

            if ($this->orderProductsScheduledForDeletion !== null) {
                if (!$this->orderProductsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderProductQuery::create()
                        ->filterByPrimaryKeys($this->orderProductsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderProductsScheduledForDeletion = null;
                }
            }

                if ($this->collOrderProducts !== null) {
            foreach ($this->collOrderProducts as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->orderCouponsScheduledForDeletion !== null) {
                if (!$this->orderCouponsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderCouponQuery::create()
                        ->filterByPrimaryKeys($this->orderCouponsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderCouponsScheduledForDeletion = null;
                }
            }

                if ($this->collOrderCoupons !== null) {
            foreach ($this->collOrderCoupons as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->orderVersionsScheduledForDeletion !== null) {
                if (!$this->orderVersionsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderVersionQuery::create()
                        ->filterByPrimaryKeys($this->orderVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderVersionsScheduledForDeletion = null;
                }
            }

                if ($this->collOrderVersions !== null) {
            foreach ($this->collOrderVersions as $referrerFK) {
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

        $this->modifiedColumns[OrderTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . OrderTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(OrderTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(OrderTableMap::REF)) {
            $modifiedColumns[':p' . $index++]  = '`REF`';
        }
        if ($this->isColumnModified(OrderTableMap::CUSTOMER_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CUSTOMER_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::INVOICE_ORDER_ADDRESS_ID)) {
            $modifiedColumns[':p' . $index++]  = '`INVOICE_ORDER_ADDRESS_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID)) {
            $modifiedColumns[':p' . $index++]  = '`DELIVERY_ORDER_ADDRESS_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::INVOICE_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`INVOICE_DATE`';
        }
        if ($this->isColumnModified(OrderTableMap::CURRENCY_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CURRENCY_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::CURRENCY_RATE)) {
            $modifiedColumns[':p' . $index++]  = '`CURRENCY_RATE`';
        }
        if ($this->isColumnModified(OrderTableMap::TRANSACTION_REF)) {
            $modifiedColumns[':p' . $index++]  = '`TRANSACTION_REF`';
        }
        if ($this->isColumnModified(OrderTableMap::DELIVERY_REF)) {
            $modifiedColumns[':p' . $index++]  = '`DELIVERY_REF`';
        }
        if ($this->isColumnModified(OrderTableMap::INVOICE_REF)) {
            $modifiedColumns[':p' . $index++]  = '`INVOICE_REF`';
        }
        if ($this->isColumnModified(OrderTableMap::DISCOUNT)) {
            $modifiedColumns[':p' . $index++]  = '`DISCOUNT`';
        }
        if ($this->isColumnModified(OrderTableMap::POSTAGE)) {
            $modifiedColumns[':p' . $index++]  = '`POSTAGE`';
        }
        if ($this->isColumnModified(OrderTableMap::POSTAGE_TAX)) {
            $modifiedColumns[':p' . $index++]  = '`POSTAGE_TAX`';
        }
        if ($this->isColumnModified(OrderTableMap::POSTAGE_TAX_RULE_TITLE)) {
            $modifiedColumns[':p' . $index++]  = '`POSTAGE_TAX_RULE_TITLE`';
        }
        if ($this->isColumnModified(OrderTableMap::PAYMENT_MODULE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`PAYMENT_MODULE_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::DELIVERY_MODULE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`DELIVERY_MODULE_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::STATUS_ID)) {
            $modifiedColumns[':p' . $index++]  = '`STATUS_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::LANG_ID)) {
            $modifiedColumns[':p' . $index++]  = '`LANG_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::CART_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CART_ID`';
        }
        if ($this->isColumnModified(OrderTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(OrderTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(OrderTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(OrderTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(OrderTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
        }

        $sql = sprintf(
            'INSERT INTO `order` (%s) VALUES (%s)',
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
                    case '`REF`':
                        $stmt->bindValue($identifier, $this->ref, PDO::PARAM_STR);
                        break;
                    case '`CUSTOMER_ID`':
                        $stmt->bindValue($identifier, $this->customer_id, PDO::PARAM_INT);
                        break;
                    case '`INVOICE_ORDER_ADDRESS_ID`':
                        $stmt->bindValue($identifier, $this->invoice_order_address_id, PDO::PARAM_INT);
                        break;
                    case '`DELIVERY_ORDER_ADDRESS_ID`':
                        $stmt->bindValue($identifier, $this->delivery_order_address_id, PDO::PARAM_INT);
                        break;
                    case '`INVOICE_DATE`':
                        $stmt->bindValue($identifier, $this->invoice_date ? $this->invoice_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case '`CURRENCY_ID`':
                        $stmt->bindValue($identifier, $this->currency_id, PDO::PARAM_INT);
                        break;
                    case '`CURRENCY_RATE`':
                        $stmt->bindValue($identifier, $this->currency_rate, PDO::PARAM_STR);
                        break;
                    case '`TRANSACTION_REF`':
                        $stmt->bindValue($identifier, $this->transaction_ref, PDO::PARAM_STR);
                        break;
                    case '`DELIVERY_REF`':
                        $stmt->bindValue($identifier, $this->delivery_ref, PDO::PARAM_STR);
                        break;
                    case '`INVOICE_REF`':
                        $stmt->bindValue($identifier, $this->invoice_ref, PDO::PARAM_STR);
                        break;
                    case '`DISCOUNT`':
                        $stmt->bindValue($identifier, $this->discount, PDO::PARAM_STR);
                        break;
                    case '`POSTAGE`':
                        $stmt->bindValue($identifier, $this->postage, PDO::PARAM_STR);
                        break;
                    case '`POSTAGE_TAX`':
                        $stmt->bindValue($identifier, $this->postage_tax, PDO::PARAM_STR);
                        break;
                    case '`POSTAGE_TAX_RULE_TITLE`':
                        $stmt->bindValue($identifier, $this->postage_tax_rule_title, PDO::PARAM_STR);
                        break;
                    case '`PAYMENT_MODULE_ID`':
                        $stmt->bindValue($identifier, $this->payment_module_id, PDO::PARAM_INT);
                        break;
                    case '`DELIVERY_MODULE_ID`':
                        $stmt->bindValue($identifier, $this->delivery_module_id, PDO::PARAM_INT);
                        break;
                    case '`STATUS_ID`':
                        $stmt->bindValue($identifier, $this->status_id, PDO::PARAM_INT);
                        break;
                    case '`LANG_ID`':
                        $stmt->bindValue($identifier, $this->lang_id, PDO::PARAM_INT);
                        break;
                    case '`CART_ID`':
                        $stmt->bindValue($identifier, $this->cart_id, PDO::PARAM_INT);
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
        $pos = OrderTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getRef();
                break;
            case 2:
                return $this->getCustomerId();
                break;
            case 3:
                return $this->getInvoiceOrderAddressId();
                break;
            case 4:
                return $this->getDeliveryOrderAddressId();
                break;
            case 5:
                return $this->getInvoiceDate();
                break;
            case 6:
                return $this->getCurrencyId();
                break;
            case 7:
                return $this->getCurrencyRate();
                break;
            case 8:
                return $this->getTransactionRef();
                break;
            case 9:
                return $this->getDeliveryRef();
                break;
            case 10:
                return $this->getInvoiceRef();
                break;
            case 11:
                return $this->getDiscount();
                break;
            case 12:
                return $this->getPostage();
                break;
            case 13:
                return $this->getPostageTax();
                break;
            case 14:
                return $this->getPostageTaxRuleTitle();
                break;
            case 15:
                return $this->getPaymentModuleId();
                break;
            case 16:
                return $this->getDeliveryModuleId();
                break;
            case 17:
                return $this->getStatusId();
                break;
            case 18:
                return $this->getLangId();
                break;
            case 19:
                return $this->getCartId();
                break;
            case 20:
                return $this->getCreatedAt();
                break;
            case 21:
                return $this->getUpdatedAt();
                break;
            case 22:
                return $this->getVersion();
                break;
            case 23:
                return $this->getVersionCreatedAt();
                break;
            case 24:
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
        if (isset($alreadyDumpedObjects['Order'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Order'][$this->getPrimaryKey()] = true;
        $keys = OrderTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getRef(),
            $keys[2] => $this->getCustomerId(),
            $keys[3] => $this->getInvoiceOrderAddressId(),
            $keys[4] => $this->getDeliveryOrderAddressId(),
            $keys[5] => $this->getInvoiceDate(),
            $keys[6] => $this->getCurrencyId(),
            $keys[7] => $this->getCurrencyRate(),
            $keys[8] => $this->getTransactionRef(),
            $keys[9] => $this->getDeliveryRef(),
            $keys[10] => $this->getInvoiceRef(),
            $keys[11] => $this->getDiscount(),
            $keys[12] => $this->getPostage(),
            $keys[13] => $this->getPostageTax(),
            $keys[14] => $this->getPostageTaxRuleTitle(),
            $keys[15] => $this->getPaymentModuleId(),
            $keys[16] => $this->getDeliveryModuleId(),
            $keys[17] => $this->getStatusId(),
            $keys[18] => $this->getLangId(),
            $keys[19] => $this->getCartId(),
            $keys[20] => $this->getCreatedAt(),
            $keys[21] => $this->getUpdatedAt(),
            $keys[22] => $this->getVersion(),
            $keys[23] => $this->getVersionCreatedAt(),
            $keys[24] => $this->getVersionCreatedBy(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aCurrency) {
                $result['Currency'] = $this->aCurrency->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aCustomer) {
                $result['Customer'] = $this->aCustomer->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aOrderAddressRelatedByInvoiceOrderAddressId) {
                $result['OrderAddressRelatedByInvoiceOrderAddressId'] = $this->aOrderAddressRelatedByInvoiceOrderAddressId->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aOrderAddressRelatedByDeliveryOrderAddressId) {
                $result['OrderAddressRelatedByDeliveryOrderAddressId'] = $this->aOrderAddressRelatedByDeliveryOrderAddressId->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aOrderStatus) {
                $result['OrderStatus'] = $this->aOrderStatus->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aModuleRelatedByPaymentModuleId) {
                $result['ModuleRelatedByPaymentModuleId'] = $this->aModuleRelatedByPaymentModuleId->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aModuleRelatedByDeliveryModuleId) {
                $result['ModuleRelatedByDeliveryModuleId'] = $this->aModuleRelatedByDeliveryModuleId->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aLang) {
                $result['Lang'] = $this->aLang->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collOrderProducts) {
                $result['OrderProducts'] = $this->collOrderProducts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrderCoupons) {
                $result['OrderCoupons'] = $this->collOrderCoupons->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrderVersions) {
                $result['OrderVersions'] = $this->collOrderVersions->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = OrderTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setRef($value);
                break;
            case 2:
                $this->setCustomerId($value);
                break;
            case 3:
                $this->setInvoiceOrderAddressId($value);
                break;
            case 4:
                $this->setDeliveryOrderAddressId($value);
                break;
            case 5:
                $this->setInvoiceDate($value);
                break;
            case 6:
                $this->setCurrencyId($value);
                break;
            case 7:
                $this->setCurrencyRate($value);
                break;
            case 8:
                $this->setTransactionRef($value);
                break;
            case 9:
                $this->setDeliveryRef($value);
                break;
            case 10:
                $this->setInvoiceRef($value);
                break;
            case 11:
                $this->setDiscount($value);
                break;
            case 12:
                $this->setPostage($value);
                break;
            case 13:
                $this->setPostageTax($value);
                break;
            case 14:
                $this->setPostageTaxRuleTitle($value);
                break;
            case 15:
                $this->setPaymentModuleId($value);
                break;
            case 16:
                $this->setDeliveryModuleId($value);
                break;
            case 17:
                $this->setStatusId($value);
                break;
            case 18:
                $this->setLangId($value);
                break;
            case 19:
                $this->setCartId($value);
                break;
            case 20:
                $this->setCreatedAt($value);
                break;
            case 21:
                $this->setUpdatedAt($value);
                break;
            case 22:
                $this->setVersion($value);
                break;
            case 23:
                $this->setVersionCreatedAt($value);
                break;
            case 24:
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
        $keys = OrderTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setRef($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCustomerId($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setInvoiceOrderAddressId($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setDeliveryOrderAddressId($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setInvoiceDate($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setCurrencyId($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setCurrencyRate($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setTransactionRef($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setDeliveryRef($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setInvoiceRef($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setDiscount($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setPostage($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setPostageTax($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setPostageTaxRuleTitle($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setPaymentModuleId($arr[$keys[15]]);
        if (array_key_exists($keys[16], $arr)) $this->setDeliveryModuleId($arr[$keys[16]]);
        if (array_key_exists($keys[17], $arr)) $this->setStatusId($arr[$keys[17]]);
        if (array_key_exists($keys[18], $arr)) $this->setLangId($arr[$keys[18]]);
        if (array_key_exists($keys[19], $arr)) $this->setCartId($arr[$keys[19]]);
        if (array_key_exists($keys[20], $arr)) $this->setCreatedAt($arr[$keys[20]]);
        if (array_key_exists($keys[21], $arr)) $this->setUpdatedAt($arr[$keys[21]]);
        if (array_key_exists($keys[22], $arr)) $this->setVersion($arr[$keys[22]]);
        if (array_key_exists($keys[23], $arr)) $this->setVersionCreatedAt($arr[$keys[23]]);
        if (array_key_exists($keys[24], $arr)) $this->setVersionCreatedBy($arr[$keys[24]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(OrderTableMap::DATABASE_NAME);

        if ($this->isColumnModified(OrderTableMap::ID)) $criteria->add(OrderTableMap::ID, $this->id);
        if ($this->isColumnModified(OrderTableMap::REF)) $criteria->add(OrderTableMap::REF, $this->ref);
        if ($this->isColumnModified(OrderTableMap::CUSTOMER_ID)) $criteria->add(OrderTableMap::CUSTOMER_ID, $this->customer_id);
        if ($this->isColumnModified(OrderTableMap::INVOICE_ORDER_ADDRESS_ID)) $criteria->add(OrderTableMap::INVOICE_ORDER_ADDRESS_ID, $this->invoice_order_address_id);
        if ($this->isColumnModified(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID)) $criteria->add(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID, $this->delivery_order_address_id);
        if ($this->isColumnModified(OrderTableMap::INVOICE_DATE)) $criteria->add(OrderTableMap::INVOICE_DATE, $this->invoice_date);
        if ($this->isColumnModified(OrderTableMap::CURRENCY_ID)) $criteria->add(OrderTableMap::CURRENCY_ID, $this->currency_id);
        if ($this->isColumnModified(OrderTableMap::CURRENCY_RATE)) $criteria->add(OrderTableMap::CURRENCY_RATE, $this->currency_rate);
        if ($this->isColumnModified(OrderTableMap::TRANSACTION_REF)) $criteria->add(OrderTableMap::TRANSACTION_REF, $this->transaction_ref);
        if ($this->isColumnModified(OrderTableMap::DELIVERY_REF)) $criteria->add(OrderTableMap::DELIVERY_REF, $this->delivery_ref);
        if ($this->isColumnModified(OrderTableMap::INVOICE_REF)) $criteria->add(OrderTableMap::INVOICE_REF, $this->invoice_ref);
        if ($this->isColumnModified(OrderTableMap::DISCOUNT)) $criteria->add(OrderTableMap::DISCOUNT, $this->discount);
        if ($this->isColumnModified(OrderTableMap::POSTAGE)) $criteria->add(OrderTableMap::POSTAGE, $this->postage);
        if ($this->isColumnModified(OrderTableMap::POSTAGE_TAX)) $criteria->add(OrderTableMap::POSTAGE_TAX, $this->postage_tax);
        if ($this->isColumnModified(OrderTableMap::POSTAGE_TAX_RULE_TITLE)) $criteria->add(OrderTableMap::POSTAGE_TAX_RULE_TITLE, $this->postage_tax_rule_title);
        if ($this->isColumnModified(OrderTableMap::PAYMENT_MODULE_ID)) $criteria->add(OrderTableMap::PAYMENT_MODULE_ID, $this->payment_module_id);
        if ($this->isColumnModified(OrderTableMap::DELIVERY_MODULE_ID)) $criteria->add(OrderTableMap::DELIVERY_MODULE_ID, $this->delivery_module_id);
        if ($this->isColumnModified(OrderTableMap::STATUS_ID)) $criteria->add(OrderTableMap::STATUS_ID, $this->status_id);
        if ($this->isColumnModified(OrderTableMap::LANG_ID)) $criteria->add(OrderTableMap::LANG_ID, $this->lang_id);
        if ($this->isColumnModified(OrderTableMap::CART_ID)) $criteria->add(OrderTableMap::CART_ID, $this->cart_id);
        if ($this->isColumnModified(OrderTableMap::CREATED_AT)) $criteria->add(OrderTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(OrderTableMap::UPDATED_AT)) $criteria->add(OrderTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(OrderTableMap::VERSION)) $criteria->add(OrderTableMap::VERSION, $this->version);
        if ($this->isColumnModified(OrderTableMap::VERSION_CREATED_AT)) $criteria->add(OrderTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(OrderTableMap::VERSION_CREATED_BY)) $criteria->add(OrderTableMap::VERSION_CREATED_BY, $this->version_created_by);

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
        $criteria = new Criteria(OrderTableMap::DATABASE_NAME);
        $criteria->add(OrderTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Order (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setRef($this->getRef());
        $copyObj->setCustomerId($this->getCustomerId());
        $copyObj->setInvoiceOrderAddressId($this->getInvoiceOrderAddressId());
        $copyObj->setDeliveryOrderAddressId($this->getDeliveryOrderAddressId());
        $copyObj->setInvoiceDate($this->getInvoiceDate());
        $copyObj->setCurrencyId($this->getCurrencyId());
        $copyObj->setCurrencyRate($this->getCurrencyRate());
        $copyObj->setTransactionRef($this->getTransactionRef());
        $copyObj->setDeliveryRef($this->getDeliveryRef());
        $copyObj->setInvoiceRef($this->getInvoiceRef());
        $copyObj->setDiscount($this->getDiscount());
        $copyObj->setPostage($this->getPostage());
        $copyObj->setPostageTax($this->getPostageTax());
        $copyObj->setPostageTaxRuleTitle($this->getPostageTaxRuleTitle());
        $copyObj->setPaymentModuleId($this->getPaymentModuleId());
        $copyObj->setDeliveryModuleId($this->getDeliveryModuleId());
        $copyObj->setStatusId($this->getStatusId());
        $copyObj->setLangId($this->getLangId());
        $copyObj->setCartId($this->getCartId());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getOrderProducts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderProduct($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrderCoupons() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderCoupon($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrderVersions() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderVersion($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Order Clone of current object.
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
     * Declares an association between this object and a ChildCurrency object.
     *
     * @param                  ChildCurrency $v
     * @return                 \Thelia\Model\Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCurrency(ChildCurrency $v = null)
    {
        if ($v === null) {
            $this->setCurrencyId(NULL);
        } else {
            $this->setCurrencyId($v->getId());
        }

        $this->aCurrency = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildCurrency object, it will not be re-added.
        if ($v !== null) {
            $v->addOrder($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildCurrency object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildCurrency The associated ChildCurrency object.
     * @throws PropelException
     */
    public function getCurrency(ConnectionInterface $con = null)
    {
        if ($this->aCurrency === null && ($this->currency_id !== null)) {
            $this->aCurrency = ChildCurrencyQuery::create()->findPk($this->currency_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCurrency->addOrders($this);
             */
        }

        return $this->aCurrency;
    }

    /**
     * Declares an association between this object and a ChildCustomer object.
     *
     * @param                  ChildCustomer $v
     * @return                 \Thelia\Model\Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCustomer(ChildCustomer $v = null)
    {
        if ($v === null) {
            $this->setCustomerId(NULL);
        } else {
            $this->setCustomerId($v->getId());
        }

        $this->aCustomer = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildCustomer object, it will not be re-added.
        if ($v !== null) {
            $v->addOrder($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildCustomer object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildCustomer The associated ChildCustomer object.
     * @throws PropelException
     */
    public function getCustomer(ConnectionInterface $con = null)
    {
        if ($this->aCustomer === null && ($this->customer_id !== null)) {
            $this->aCustomer = ChildCustomerQuery::create()->findPk($this->customer_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCustomer->addOrders($this);
             */
        }

        return $this->aCustomer;
    }

    /**
     * Declares an association between this object and a ChildOrderAddress object.
     *
     * @param                  ChildOrderAddress $v
     * @return                 \Thelia\Model\Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrderAddressRelatedByInvoiceOrderAddressId(ChildOrderAddress $v = null)
    {
        if ($v === null) {
            $this->setInvoiceOrderAddressId(NULL);
        } else {
            $this->setInvoiceOrderAddressId($v->getId());
        }

        $this->aOrderAddressRelatedByInvoiceOrderAddressId = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildOrderAddress object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderRelatedByInvoiceOrderAddressId($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildOrderAddress object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildOrderAddress The associated ChildOrderAddress object.
     * @throws PropelException
     */
    public function getOrderAddressRelatedByInvoiceOrderAddressId(ConnectionInterface $con = null)
    {
        if ($this->aOrderAddressRelatedByInvoiceOrderAddressId === null && ($this->invoice_order_address_id !== null)) {
            $this->aOrderAddressRelatedByInvoiceOrderAddressId = ChildOrderAddressQuery::create()->findPk($this->invoice_order_address_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aOrderAddressRelatedByInvoiceOrderAddressId->addOrdersRelatedByInvoiceOrderAddressId($this);
             */
        }

        return $this->aOrderAddressRelatedByInvoiceOrderAddressId;
    }

    /**
     * Declares an association between this object and a ChildOrderAddress object.
     *
     * @param                  ChildOrderAddress $v
     * @return                 \Thelia\Model\Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrderAddressRelatedByDeliveryOrderAddressId(ChildOrderAddress $v = null)
    {
        if ($v === null) {
            $this->setDeliveryOrderAddressId(NULL);
        } else {
            $this->setDeliveryOrderAddressId($v->getId());
        }

        $this->aOrderAddressRelatedByDeliveryOrderAddressId = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildOrderAddress object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderRelatedByDeliveryOrderAddressId($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildOrderAddress object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildOrderAddress The associated ChildOrderAddress object.
     * @throws PropelException
     */
    public function getOrderAddressRelatedByDeliveryOrderAddressId(ConnectionInterface $con = null)
    {
        if ($this->aOrderAddressRelatedByDeliveryOrderAddressId === null && ($this->delivery_order_address_id !== null)) {
            $this->aOrderAddressRelatedByDeliveryOrderAddressId = ChildOrderAddressQuery::create()->findPk($this->delivery_order_address_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aOrderAddressRelatedByDeliveryOrderAddressId->addOrdersRelatedByDeliveryOrderAddressId($this);
             */
        }

        return $this->aOrderAddressRelatedByDeliveryOrderAddressId;
    }

    /**
     * Declares an association between this object and a ChildOrderStatus object.
     *
     * @param                  ChildOrderStatus $v
     * @return                 \Thelia\Model\Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrderStatus(ChildOrderStatus $v = null)
    {
        if ($v === null) {
            $this->setStatusId(NULL);
        } else {
            $this->setStatusId($v->getId());
        }

        $this->aOrderStatus = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildOrderStatus object, it will not be re-added.
        if ($v !== null) {
            $v->addOrder($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildOrderStatus object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildOrderStatus The associated ChildOrderStatus object.
     * @throws PropelException
     */
    public function getOrderStatus(ConnectionInterface $con = null)
    {
        if ($this->aOrderStatus === null && ($this->status_id !== null)) {
            $this->aOrderStatus = ChildOrderStatusQuery::create()->findPk($this->status_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aOrderStatus->addOrders($this);
             */
        }

        return $this->aOrderStatus;
    }

    /**
     * Declares an association between this object and a ChildModule object.
     *
     * @param                  ChildModule $v
     * @return                 \Thelia\Model\Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setModuleRelatedByPaymentModuleId(ChildModule $v = null)
    {
        if ($v === null) {
            $this->setPaymentModuleId(NULL);
        } else {
            $this->setPaymentModuleId($v->getId());
        }

        $this->aModuleRelatedByPaymentModuleId = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildModule object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderRelatedByPaymentModuleId($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildModule object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildModule The associated ChildModule object.
     * @throws PropelException
     */
    public function getModuleRelatedByPaymentModuleId(ConnectionInterface $con = null)
    {
        if ($this->aModuleRelatedByPaymentModuleId === null && ($this->payment_module_id !== null)) {
            $this->aModuleRelatedByPaymentModuleId = ChildModuleQuery::create()->findPk($this->payment_module_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aModuleRelatedByPaymentModuleId->addOrdersRelatedByPaymentModuleId($this);
             */
        }

        return $this->aModuleRelatedByPaymentModuleId;
    }

    /**
     * Declares an association between this object and a ChildModule object.
     *
     * @param                  ChildModule $v
     * @return                 \Thelia\Model\Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setModuleRelatedByDeliveryModuleId(ChildModule $v = null)
    {
        if ($v === null) {
            $this->setDeliveryModuleId(NULL);
        } else {
            $this->setDeliveryModuleId($v->getId());
        }

        $this->aModuleRelatedByDeliveryModuleId = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildModule object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderRelatedByDeliveryModuleId($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildModule object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildModule The associated ChildModule object.
     * @throws PropelException
     */
    public function getModuleRelatedByDeliveryModuleId(ConnectionInterface $con = null)
    {
        if ($this->aModuleRelatedByDeliveryModuleId === null && ($this->delivery_module_id !== null)) {
            $this->aModuleRelatedByDeliveryModuleId = ChildModuleQuery::create()->findPk($this->delivery_module_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aModuleRelatedByDeliveryModuleId->addOrdersRelatedByDeliveryModuleId($this);
             */
        }

        return $this->aModuleRelatedByDeliveryModuleId;
    }

    /**
     * Declares an association between this object and a ChildLang object.
     *
     * @param                  ChildLang $v
     * @return                 \Thelia\Model\Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setLang(ChildLang $v = null)
    {
        if ($v === null) {
            $this->setLangId(NULL);
        } else {
            $this->setLangId($v->getId());
        }

        $this->aLang = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildLang object, it will not be re-added.
        if ($v !== null) {
            $v->addOrder($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildLang object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildLang The associated ChildLang object.
     * @throws PropelException
     */
    public function getLang(ConnectionInterface $con = null)
    {
        if ($this->aLang === null && ($this->lang_id !== null)) {
            $this->aLang = ChildLangQuery::create()->findPk($this->lang_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aLang->addOrders($this);
             */
        }

        return $this->aLang;
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
        if ('OrderProduct' == $relationName) {
            return $this->initOrderProducts();
        }
        if ('OrderCoupon' == $relationName) {
            return $this->initOrderCoupons();
        }
        if ('OrderVersion' == $relationName) {
            return $this->initOrderVersions();
        }
    }

    /**
     * Clears out the collOrderProducts collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderProducts()
     */
    public function clearOrderProducts()
    {
        $this->collOrderProducts = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderProducts collection loaded partially.
     */
    public function resetPartialOrderProducts($v = true)
    {
        $this->collOrderProductsPartial = $v;
    }

    /**
     * Initializes the collOrderProducts collection.
     *
     * By default this just sets the collOrderProducts collection to an empty array (like clearcollOrderProducts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderProducts($overrideExisting = true)
    {
        if (null !== $this->collOrderProducts && !$overrideExisting) {
            return;
        }
        $this->collOrderProducts = new ObjectCollection();
        $this->collOrderProducts->setModel('\Thelia\Model\OrderProduct');
    }

    /**
     * Gets an array of ChildOrderProduct objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderProduct[] List of ChildOrderProduct objects
     * @throws PropelException
     */
    public function getOrderProducts($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderProductsPartial && !$this->isNew();
        if (null === $this->collOrderProducts || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderProducts) {
                // return empty collection
                $this->initOrderProducts();
            } else {
                $collOrderProducts = ChildOrderProductQuery::create(null, $criteria)
                    ->filterByOrder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderProductsPartial && count($collOrderProducts)) {
                        $this->initOrderProducts(false);

                        foreach ($collOrderProducts as $obj) {
                            if (false == $this->collOrderProducts->contains($obj)) {
                                $this->collOrderProducts->append($obj);
                            }
                        }

                        $this->collOrderProductsPartial = true;
                    }

                    reset($collOrderProducts);

                    return $collOrderProducts;
                }

                if ($partial && $this->collOrderProducts) {
                    foreach ($this->collOrderProducts as $obj) {
                        if ($obj->isNew()) {
                            $collOrderProducts[] = $obj;
                        }
                    }
                }

                $this->collOrderProducts = $collOrderProducts;
                $this->collOrderProductsPartial = false;
            }
        }

        return $this->collOrderProducts;
    }

    /**
     * Sets a collection of OrderProduct objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderProducts A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildOrder The current object (for fluent API support)
     */
    public function setOrderProducts(Collection $orderProducts, ConnectionInterface $con = null)
    {
        $orderProductsToDelete = $this->getOrderProducts(new Criteria(), $con)->diff($orderProducts);


        $this->orderProductsScheduledForDeletion = $orderProductsToDelete;

        foreach ($orderProductsToDelete as $orderProductRemoved) {
            $orderProductRemoved->setOrder(null);
        }

        $this->collOrderProducts = null;
        foreach ($orderProducts as $orderProduct) {
            $this->addOrderProduct($orderProduct);
        }

        $this->collOrderProducts = $orderProducts;
        $this->collOrderProductsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderProduct objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderProduct objects.
     * @throws PropelException
     */
    public function countOrderProducts(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderProductsPartial && !$this->isNew();
        if (null === $this->collOrderProducts || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderProducts) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderProducts());
            }

            $query = ChildOrderProductQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByOrder($this)
                ->count($con);
        }

        return count($this->collOrderProducts);
    }

    /**
     * Method called to associate a ChildOrderProduct object to this object
     * through the ChildOrderProduct foreign key attribute.
     *
     * @param    ChildOrderProduct $l ChildOrderProduct
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function addOrderProduct(ChildOrderProduct $l)
    {
        if ($this->collOrderProducts === null) {
            $this->initOrderProducts();
            $this->collOrderProductsPartial = true;
        }

        if (!in_array($l, $this->collOrderProducts->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderProduct($l);
        }

        return $this;
    }

    /**
     * @param OrderProduct $orderProduct The orderProduct object to add.
     */
    protected function doAddOrderProduct($orderProduct)
    {
        $this->collOrderProducts[]= $orderProduct;
        $orderProduct->setOrder($this);
    }

    /**
     * @param  OrderProduct $orderProduct The orderProduct object to remove.
     * @return ChildOrder The current object (for fluent API support)
     */
    public function removeOrderProduct($orderProduct)
    {
        if ($this->getOrderProducts()->contains($orderProduct)) {
            $this->collOrderProducts->remove($this->collOrderProducts->search($orderProduct));
            if (null === $this->orderProductsScheduledForDeletion) {
                $this->orderProductsScheduledForDeletion = clone $this->collOrderProducts;
                $this->orderProductsScheduledForDeletion->clear();
            }
            $this->orderProductsScheduledForDeletion[]= clone $orderProduct;
            $orderProduct->setOrder(null);
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
    }

    /**
     * Reset is the collOrderCoupons collection loaded partially.
     */
    public function resetPartialOrderCoupons($v = true)
    {
        $this->collOrderCouponsPartial = $v;
    }

    /**
     * Initializes the collOrderCoupons collection.
     *
     * By default this just sets the collOrderCoupons collection to an empty array (like clearcollOrderCoupons());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderCoupons($overrideExisting = true)
    {
        if (null !== $this->collOrderCoupons && !$overrideExisting) {
            return;
        }
        $this->collOrderCoupons = new ObjectCollection();
        $this->collOrderCoupons->setModel('\Thelia\Model\OrderCoupon');
    }

    /**
     * Gets an array of ChildOrderCoupon objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderCoupon[] List of ChildOrderCoupon objects
     * @throws PropelException
     */
    public function getOrderCoupons($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponsPartial && !$this->isNew();
        if (null === $this->collOrderCoupons || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderCoupons) {
                // return empty collection
                $this->initOrderCoupons();
            } else {
                $collOrderCoupons = ChildOrderCouponQuery::create(null, $criteria)
                    ->filterByOrder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderCouponsPartial && count($collOrderCoupons)) {
                        $this->initOrderCoupons(false);

                        foreach ($collOrderCoupons as $obj) {
                            if (false == $this->collOrderCoupons->contains($obj)) {
                                $this->collOrderCoupons->append($obj);
                            }
                        }

                        $this->collOrderCouponsPartial = true;
                    }

                    reset($collOrderCoupons);

                    return $collOrderCoupons;
                }

                if ($partial && $this->collOrderCoupons) {
                    foreach ($this->collOrderCoupons as $obj) {
                        if ($obj->isNew()) {
                            $collOrderCoupons[] = $obj;
                        }
                    }
                }

                $this->collOrderCoupons = $collOrderCoupons;
                $this->collOrderCouponsPartial = false;
            }
        }

        return $this->collOrderCoupons;
    }

    /**
     * Sets a collection of OrderCoupon objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderCoupons A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildOrder The current object (for fluent API support)
     */
    public function setOrderCoupons(Collection $orderCoupons, ConnectionInterface $con = null)
    {
        $orderCouponsToDelete = $this->getOrderCoupons(new Criteria(), $con)->diff($orderCoupons);


        $this->orderCouponsScheduledForDeletion = $orderCouponsToDelete;

        foreach ($orderCouponsToDelete as $orderCouponRemoved) {
            $orderCouponRemoved->setOrder(null);
        }

        $this->collOrderCoupons = null;
        foreach ($orderCoupons as $orderCoupon) {
            $this->addOrderCoupon($orderCoupon);
        }

        $this->collOrderCoupons = $orderCoupons;
        $this->collOrderCouponsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderCoupon objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderCoupon objects.
     * @throws PropelException
     */
    public function countOrderCoupons(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderCouponsPartial && !$this->isNew();
        if (null === $this->collOrderCoupons || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderCoupons) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderCoupons());
            }

            $query = ChildOrderCouponQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByOrder($this)
                ->count($con);
        }

        return count($this->collOrderCoupons);
    }

    /**
     * Method called to associate a ChildOrderCoupon object to this object
     * through the ChildOrderCoupon foreign key attribute.
     *
     * @param    ChildOrderCoupon $l ChildOrderCoupon
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function addOrderCoupon(ChildOrderCoupon $l)
    {
        if ($this->collOrderCoupons === null) {
            $this->initOrderCoupons();
            $this->collOrderCouponsPartial = true;
        }

        if (!in_array($l, $this->collOrderCoupons->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderCoupon($l);
        }

        return $this;
    }

    /**
     * @param OrderCoupon $orderCoupon The orderCoupon object to add.
     */
    protected function doAddOrderCoupon($orderCoupon)
    {
        $this->collOrderCoupons[]= $orderCoupon;
        $orderCoupon->setOrder($this);
    }

    /**
     * @param  OrderCoupon $orderCoupon The orderCoupon object to remove.
     * @return ChildOrder The current object (for fluent API support)
     */
    public function removeOrderCoupon($orderCoupon)
    {
        if ($this->getOrderCoupons()->contains($orderCoupon)) {
            $this->collOrderCoupons->remove($this->collOrderCoupons->search($orderCoupon));
            if (null === $this->orderCouponsScheduledForDeletion) {
                $this->orderCouponsScheduledForDeletion = clone $this->collOrderCoupons;
                $this->orderCouponsScheduledForDeletion->clear();
            }
            $this->orderCouponsScheduledForDeletion[]= clone $orderCoupon;
            $orderCoupon->setOrder(null);
        }

        return $this;
    }

    /**
     * Clears out the collOrderVersions collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderVersions()
     */
    public function clearOrderVersions()
    {
        $this->collOrderVersions = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderVersions collection loaded partially.
     */
    public function resetPartialOrderVersions($v = true)
    {
        $this->collOrderVersionsPartial = $v;
    }

    /**
     * Initializes the collOrderVersions collection.
     *
     * By default this just sets the collOrderVersions collection to an empty array (like clearcollOrderVersions());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderVersions($overrideExisting = true)
    {
        if (null !== $this->collOrderVersions && !$overrideExisting) {
            return;
        }
        $this->collOrderVersions = new ObjectCollection();
        $this->collOrderVersions->setModel('\Thelia\Model\OrderVersion');
    }

    /**
     * Gets an array of ChildOrderVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrder is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderVersion[] List of ChildOrderVersion objects
     * @throws PropelException
     */
    public function getOrderVersions($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderVersionsPartial && !$this->isNew();
        if (null === $this->collOrderVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderVersions) {
                // return empty collection
                $this->initOrderVersions();
            } else {
                $collOrderVersions = ChildOrderVersionQuery::create(null, $criteria)
                    ->filterByOrder($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderVersionsPartial && count($collOrderVersions)) {
                        $this->initOrderVersions(false);

                        foreach ($collOrderVersions as $obj) {
                            if (false == $this->collOrderVersions->contains($obj)) {
                                $this->collOrderVersions->append($obj);
                            }
                        }

                        $this->collOrderVersionsPartial = true;
                    }

                    reset($collOrderVersions);

                    return $collOrderVersions;
                }

                if ($partial && $this->collOrderVersions) {
                    foreach ($this->collOrderVersions as $obj) {
                        if ($obj->isNew()) {
                            $collOrderVersions[] = $obj;
                        }
                    }
                }

                $this->collOrderVersions = $collOrderVersions;
                $this->collOrderVersionsPartial = false;
            }
        }

        return $this->collOrderVersions;
    }

    /**
     * Sets a collection of OrderVersion objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderVersions A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildOrder The current object (for fluent API support)
     */
    public function setOrderVersions(Collection $orderVersions, ConnectionInterface $con = null)
    {
        $orderVersionsToDelete = $this->getOrderVersions(new Criteria(), $con)->diff($orderVersions);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->orderVersionsScheduledForDeletion = clone $orderVersionsToDelete;

        foreach ($orderVersionsToDelete as $orderVersionRemoved) {
            $orderVersionRemoved->setOrder(null);
        }

        $this->collOrderVersions = null;
        foreach ($orderVersions as $orderVersion) {
            $this->addOrderVersion($orderVersion);
        }

        $this->collOrderVersions = $orderVersions;
        $this->collOrderVersionsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderVersion objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderVersion objects.
     * @throws PropelException
     */
    public function countOrderVersions(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderVersionsPartial && !$this->isNew();
        if (null === $this->collOrderVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderVersions) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderVersions());
            }

            $query = ChildOrderVersionQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByOrder($this)
                ->count($con);
        }

        return count($this->collOrderVersions);
    }

    /**
     * Method called to associate a ChildOrderVersion object to this object
     * through the ChildOrderVersion foreign key attribute.
     *
     * @param    ChildOrderVersion $l ChildOrderVersion
     * @return   \Thelia\Model\Order The current object (for fluent API support)
     */
    public function addOrderVersion(ChildOrderVersion $l)
    {
        if ($this->collOrderVersions === null) {
            $this->initOrderVersions();
            $this->collOrderVersionsPartial = true;
        }

        if (!in_array($l, $this->collOrderVersions->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderVersion($l);
        }

        return $this;
    }

    /**
     * @param OrderVersion $orderVersion The orderVersion object to add.
     */
    protected function doAddOrderVersion($orderVersion)
    {
        $this->collOrderVersions[]= $orderVersion;
        $orderVersion->setOrder($this);
    }

    /**
     * @param  OrderVersion $orderVersion The orderVersion object to remove.
     * @return ChildOrder The current object (for fluent API support)
     */
    public function removeOrderVersion($orderVersion)
    {
        if ($this->getOrderVersions()->contains($orderVersion)) {
            $this->collOrderVersions->remove($this->collOrderVersions->search($orderVersion));
            if (null === $this->orderVersionsScheduledForDeletion) {
                $this->orderVersionsScheduledForDeletion = clone $this->collOrderVersions;
                $this->orderVersionsScheduledForDeletion->clear();
            }
            $this->orderVersionsScheduledForDeletion[]= clone $orderVersion;
            $orderVersion->setOrder(null);
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->ref = null;
        $this->customer_id = null;
        $this->invoice_order_address_id = null;
        $this->delivery_order_address_id = null;
        $this->invoice_date = null;
        $this->currency_id = null;
        $this->currency_rate = null;
        $this->transaction_ref = null;
        $this->delivery_ref = null;
        $this->invoice_ref = null;
        $this->discount = null;
        $this->postage = null;
        $this->postage_tax = null;
        $this->postage_tax_rule_title = null;
        $this->payment_module_id = null;
        $this->delivery_module_id = null;
        $this->status_id = null;
        $this->lang_id = null;
        $this->cart_id = null;
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
            if ($this->collOrderProducts) {
                foreach ($this->collOrderProducts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrderCoupons) {
                foreach ($this->collOrderCoupons as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrderVersions) {
                foreach ($this->collOrderVersions as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collOrderProducts = null;
        $this->collOrderCoupons = null;
        $this->collOrderVersions = null;
        $this->aCurrency = null;
        $this->aCustomer = null;
        $this->aOrderAddressRelatedByInvoiceOrderAddressId = null;
        $this->aOrderAddressRelatedByDeliveryOrderAddressId = null;
        $this->aOrderStatus = null;
        $this->aModuleRelatedByPaymentModuleId = null;
        $this->aModuleRelatedByDeliveryModuleId = null;
        $this->aLang = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(OrderTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildOrder The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[OrderTableMap::UPDATED_AT] = true;

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return \Thelia\Model\Order
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

        if (ChildOrderQuery::isVersioningEnabled() && ($this->isNew() || $this->isModified()) || $this->isDeleted()) {
            return true;
        }
        if (null !== ($object = $this->getCustomer($con)) && $object->isVersioningNecessary($con)) {
            return true;
        }


        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildOrderVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new ChildOrderVersion();
        $version->setId($this->getId());
        $version->setRef($this->getRef());
        $version->setCustomerId($this->getCustomerId());
        $version->setInvoiceOrderAddressId($this->getInvoiceOrderAddressId());
        $version->setDeliveryOrderAddressId($this->getDeliveryOrderAddressId());
        $version->setInvoiceDate($this->getInvoiceDate());
        $version->setCurrencyId($this->getCurrencyId());
        $version->setCurrencyRate($this->getCurrencyRate());
        $version->setTransactionRef($this->getTransactionRef());
        $version->setDeliveryRef($this->getDeliveryRef());
        $version->setInvoiceRef($this->getInvoiceRef());
        $version->setDiscount($this->getDiscount());
        $version->setPostage($this->getPostage());
        $version->setPostageTax($this->getPostageTax());
        $version->setPostageTaxRuleTitle($this->getPostageTaxRuleTitle());
        $version->setPaymentModuleId($this->getPaymentModuleId());
        $version->setDeliveryModuleId($this->getDeliveryModuleId());
        $version->setStatusId($this->getStatusId());
        $version->setLangId($this->getLangId());
        $version->setCartId($this->getCartId());
        $version->setCreatedAt($this->getCreatedAt());
        $version->setUpdatedAt($this->getUpdatedAt());
        $version->setVersion($this->getVersion());
        $version->setVersionCreatedAt($this->getVersionCreatedAt());
        $version->setVersionCreatedBy($this->getVersionCreatedBy());
        $version->setOrder($this);
        if (($related = $this->getCustomer($con)) && $related->getVersion()) {
            $version->setCustomerIdVersion($related->getVersion());
        }
        $version->save($con);

        return $version;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con The connection to use
     *
     * @return  ChildOrder The current object (for fluent API support)
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No ChildOrder object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param ChildOrderVersion $version The version object to use
     * @param ConnectionInterface   $con the connection to use
     * @param array                 $loadedObjects objects that been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return ChildOrder The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {
        $loadedObjects['ChildOrder'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
        $this->setRef($version->getRef());
        $this->setCustomerId($version->getCustomerId());
        $this->setInvoiceOrderAddressId($version->getInvoiceOrderAddressId());
        $this->setDeliveryOrderAddressId($version->getDeliveryOrderAddressId());
        $this->setInvoiceDate($version->getInvoiceDate());
        $this->setCurrencyId($version->getCurrencyId());
        $this->setCurrencyRate($version->getCurrencyRate());
        $this->setTransactionRef($version->getTransactionRef());
        $this->setDeliveryRef($version->getDeliveryRef());
        $this->setInvoiceRef($version->getInvoiceRef());
        $this->setDiscount($version->getDiscount());
        $this->setPostage($version->getPostage());
        $this->setPostageTax($version->getPostageTax());
        $this->setPostageTaxRuleTitle($version->getPostageTaxRuleTitle());
        $this->setPaymentModuleId($version->getPaymentModuleId());
        $this->setDeliveryModuleId($version->getDeliveryModuleId());
        $this->setStatusId($version->getStatusId());
        $this->setLangId($version->getLangId());
        $this->setCartId($version->getCartId());
        $this->setCreatedAt($version->getCreatedAt());
        $this->setUpdatedAt($version->getUpdatedAt());
        $this->setVersion($version->getVersion());
        $this->setVersionCreatedAt($version->getVersionCreatedAt());
        $this->setVersionCreatedBy($version->getVersionCreatedBy());
        if ($fkValue = $version->getCustomerId()) {
            if (isset($loadedObjects['ChildCustomer']) && isset($loadedObjects['ChildCustomer'][$fkValue]) && isset($loadedObjects['ChildCustomer'][$fkValue][$version->getCustomerIdVersion()])) {
                $related = $loadedObjects['ChildCustomer'][$fkValue][$version->getCustomerIdVersion()];
            } else {
                $related = new ChildCustomer();
                $relatedVersion = ChildCustomerVersionQuery::create()
                    ->filterById($fkValue)
                    ->filterByVersion($version->getCustomerIdVersion())
                    ->findOne($con);
                $related->populateFromVersion($relatedVersion, $con, $loadedObjects);
                $related->setNew(false);
            }
            $this->setCustomer($related);
        }

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
        $v = ChildOrderVersionQuery::create()
            ->filterByOrder($this)
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
     * @return  ChildOrderVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return ChildOrderVersionQuery::create()
            ->filterByOrder($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ObjectCollection A list of ChildOrderVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(OrderVersionTableMap::VERSION);

        return $this->getOrderVersions($criteria, $con);
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
     * @return PropelCollection|array \Thelia\Model\OrderVersion[] List of \Thelia\Model\OrderVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, $con = null)
    {
        $criteria = ChildOrderVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(OrderVersionTableMap::VERSION);
        $criteria->limit($number);

        return $this->getOrderVersions($criteria, $con);
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
