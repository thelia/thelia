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
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\OrderVersionQuery as ChildOrderVersionQuery;
use Thelia\Model\Map\OrderVersionTableMap;

abstract class OrderVersion implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\OrderVersionTableMap';


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
     * The value for the customer_id_version field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $customer_id_version;

    /**
     * @var        Order
     */
    protected $aOrder;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

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
        $this->customer_id_version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\OrderVersion object.
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
     * Compares this with another <code>OrderVersion</code> instance.  If
     * <code>obj</code> is an instance of <code>OrderVersion</code>, delegates to
     * <code>equals(OrderVersion)</code>.  Otherwise, returns <code>false</code>.
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
     * @return OrderVersion The current object, for fluid interface
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
     * @return OrderVersion The current object, for fluid interface
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
     * Get the [customer_id_version] column value.
     *
     * @return   int
     */
    public function getCustomerIdVersion()
    {

        return $this->customer_id_version;
    }

    /**
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[OrderVersionTableMap::ID] = true;
        }

        if ($this->aOrder !== null && $this->aOrder->getId() !== $v) {
            $this->aOrder = null;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [ref] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->ref !== $v) {
            $this->ref = $v;
            $this->modifiedColumns[OrderVersionTableMap::REF] = true;
        }


        return $this;
    } // setRef()

    /**
     * Set the value of [customer_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setCustomerId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->customer_id !== $v) {
            $this->customer_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::CUSTOMER_ID] = true;
        }


        return $this;
    } // setCustomerId()

    /**
     * Set the value of [invoice_order_address_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setInvoiceOrderAddressId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->invoice_order_address_id !== $v) {
            $this->invoice_order_address_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::INVOICE_ORDER_ADDRESS_ID] = true;
        }


        return $this;
    } // setInvoiceOrderAddressId()

    /**
     * Set the value of [delivery_order_address_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setDeliveryOrderAddressId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->delivery_order_address_id !== $v) {
            $this->delivery_order_address_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::DELIVERY_ORDER_ADDRESS_ID] = true;
        }


        return $this;
    } // setDeliveryOrderAddressId()

    /**
     * Sets the value of [invoice_date] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setInvoiceDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->invoice_date !== null || $dt !== null) {
            if ($dt !== $this->invoice_date) {
                $this->invoice_date = $dt;
                $this->modifiedColumns[OrderVersionTableMap::INVOICE_DATE] = true;
            }
        } // if either are not null


        return $this;
    } // setInvoiceDate()

    /**
     * Set the value of [currency_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setCurrencyId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->currency_id !== $v) {
            $this->currency_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::CURRENCY_ID] = true;
        }


        return $this;
    } // setCurrencyId()

    /**
     * Set the value of [currency_rate] column.
     *
     * @param      double $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setCurrencyRate($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->currency_rate !== $v) {
            $this->currency_rate = $v;
            $this->modifiedColumns[OrderVersionTableMap::CURRENCY_RATE] = true;
        }


        return $this;
    } // setCurrencyRate()

    /**
     * Set the value of [transaction_ref] column.
     * transaction reference - usually use to identify a transaction with banking modules
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setTransactionRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->transaction_ref !== $v) {
            $this->transaction_ref = $v;
            $this->modifiedColumns[OrderVersionTableMap::TRANSACTION_REF] = true;
        }


        return $this;
    } // setTransactionRef()

    /**
     * Set the value of [delivery_ref] column.
     * delivery reference - usually use to identify a delivery progress on a distant delivery tracker website
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setDeliveryRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->delivery_ref !== $v) {
            $this->delivery_ref = $v;
            $this->modifiedColumns[OrderVersionTableMap::DELIVERY_REF] = true;
        }


        return $this;
    } // setDeliveryRef()

    /**
     * Set the value of [invoice_ref] column.
     * the invoice reference
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setInvoiceRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->invoice_ref !== $v) {
            $this->invoice_ref = $v;
            $this->modifiedColumns[OrderVersionTableMap::INVOICE_REF] = true;
        }


        return $this;
    } // setInvoiceRef()

    /**
     * Set the value of [discount] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setDiscount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->discount !== $v) {
            $this->discount = $v;
            $this->modifiedColumns[OrderVersionTableMap::DISCOUNT] = true;
        }


        return $this;
    } // setDiscount()

    /**
     * Set the value of [postage] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setPostage($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->postage !== $v) {
            $this->postage = $v;
            $this->modifiedColumns[OrderVersionTableMap::POSTAGE] = true;
        }


        return $this;
    } // setPostage()

    /**
     * Set the value of [postage_tax] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setPostageTax($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->postage_tax !== $v) {
            $this->postage_tax = $v;
            $this->modifiedColumns[OrderVersionTableMap::POSTAGE_TAX] = true;
        }


        return $this;
    } // setPostageTax()

    /**
     * Set the value of [postage_tax_rule_title] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setPostageTaxRuleTitle($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->postage_tax_rule_title !== $v) {
            $this->postage_tax_rule_title = $v;
            $this->modifiedColumns[OrderVersionTableMap::POSTAGE_TAX_RULE_TITLE] = true;
        }


        return $this;
    } // setPostageTaxRuleTitle()

    /**
     * Set the value of [payment_module_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setPaymentModuleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->payment_module_id !== $v) {
            $this->payment_module_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::PAYMENT_MODULE_ID] = true;
        }


        return $this;
    } // setPaymentModuleId()

    /**
     * Set the value of [delivery_module_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setDeliveryModuleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->delivery_module_id !== $v) {
            $this->delivery_module_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::DELIVERY_MODULE_ID] = true;
        }


        return $this;
    } // setDeliveryModuleId()

    /**
     * Set the value of [status_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setStatusId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->status_id !== $v) {
            $this->status_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::STATUS_ID] = true;
        }


        return $this;
    } // setStatusId()

    /**
     * Set the value of [lang_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setLangId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->lang_id !== $v) {
            $this->lang_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::LANG_ID] = true;
        }


        return $this;
    } // setLangId()

    /**
     * Set the value of [cart_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setCartId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->cart_id !== $v) {
            $this->cart_id = $v;
            $this->modifiedColumns[OrderVersionTableMap::CART_ID] = true;
        }


        return $this;
    } // setCartId()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[OrderVersionTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[OrderVersionTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[OrderVersionTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[OrderVersionTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[OrderVersionTableMap::VERSION_CREATED_BY] = true;
        }


        return $this;
    } // setVersionCreatedBy()

    /**
     * Set the value of [customer_id_version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderVersion The current object (for fluent API support)
     */
    public function setCustomerIdVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->customer_id_version !== $v) {
            $this->customer_id_version = $v;
            $this->modifiedColumns[OrderVersionTableMap::CUSTOMER_ID_VERSION] = true;
        }


        return $this;
    } // setCustomerIdVersion()

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

            if ($this->customer_id_version !== 0) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : OrderVersionTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : OrderVersionTableMap::translateFieldName('Ref', TableMap::TYPE_PHPNAME, $indexType)];
            $this->ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : OrderVersionTableMap::translateFieldName('CustomerId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->customer_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : OrderVersionTableMap::translateFieldName('InvoiceOrderAddressId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->invoice_order_address_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : OrderVersionTableMap::translateFieldName('DeliveryOrderAddressId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->delivery_order_address_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : OrderVersionTableMap::translateFieldName('InvoiceDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->invoice_date = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : OrderVersionTableMap::translateFieldName('CurrencyId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->currency_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : OrderVersionTableMap::translateFieldName('CurrencyRate', TableMap::TYPE_PHPNAME, $indexType)];
            $this->currency_rate = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : OrderVersionTableMap::translateFieldName('TransactionRef', TableMap::TYPE_PHPNAME, $indexType)];
            $this->transaction_ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : OrderVersionTableMap::translateFieldName('DeliveryRef', TableMap::TYPE_PHPNAME, $indexType)];
            $this->delivery_ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : OrderVersionTableMap::translateFieldName('InvoiceRef', TableMap::TYPE_PHPNAME, $indexType)];
            $this->invoice_ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : OrderVersionTableMap::translateFieldName('Discount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->discount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : OrderVersionTableMap::translateFieldName('Postage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->postage = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : OrderVersionTableMap::translateFieldName('PostageTax', TableMap::TYPE_PHPNAME, $indexType)];
            $this->postage_tax = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : OrderVersionTableMap::translateFieldName('PostageTaxRuleTitle', TableMap::TYPE_PHPNAME, $indexType)];
            $this->postage_tax_rule_title = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : OrderVersionTableMap::translateFieldName('PaymentModuleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->payment_module_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : OrderVersionTableMap::translateFieldName('DeliveryModuleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->delivery_module_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : OrderVersionTableMap::translateFieldName('StatusId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->status_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : OrderVersionTableMap::translateFieldName('LangId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lang_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : OrderVersionTableMap::translateFieldName('CartId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cart_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : OrderVersionTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : OrderVersionTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : OrderVersionTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 23 + $startcol : OrderVersionTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 24 + $startcol : OrderVersionTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 25 + $startcol : OrderVersionTableMap::translateFieldName('CustomerIdVersion', TableMap::TYPE_PHPNAME, $indexType)];
            $this->customer_id_version = (null !== $col) ? (int) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 26; // 26 = OrderVersionTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\OrderVersion object", 0, $e);
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
        if ($this->aOrder !== null && $this->id !== $this->aOrder->getId()) {
            $this->aOrder = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(OrderVersionTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildOrderVersionQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aOrder = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see OrderVersion::setDeleted()
     * @see OrderVersion::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderVersionTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildOrderVersionQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderVersionTableMap::DATABASE_NAME);
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
                OrderVersionTableMap::addInstanceToPool($this);
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

            if ($this->aOrder !== null) {
                if ($this->aOrder->isModified() || $this->aOrder->isNew()) {
                    $affectedRows += $this->aOrder->save($con);
                }
                $this->setOrder($this->aOrder);
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
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;


         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(OrderVersionTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::REF)) {
            $modifiedColumns[':p' . $index++]  = '`REF`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::CUSTOMER_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CUSTOMER_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::INVOICE_ORDER_ADDRESS_ID)) {
            $modifiedColumns[':p' . $index++]  = '`INVOICE_ORDER_ADDRESS_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::DELIVERY_ORDER_ADDRESS_ID)) {
            $modifiedColumns[':p' . $index++]  = '`DELIVERY_ORDER_ADDRESS_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::INVOICE_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`INVOICE_DATE`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::CURRENCY_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CURRENCY_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::CURRENCY_RATE)) {
            $modifiedColumns[':p' . $index++]  = '`CURRENCY_RATE`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::TRANSACTION_REF)) {
            $modifiedColumns[':p' . $index++]  = '`TRANSACTION_REF`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::DELIVERY_REF)) {
            $modifiedColumns[':p' . $index++]  = '`DELIVERY_REF`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::INVOICE_REF)) {
            $modifiedColumns[':p' . $index++]  = '`INVOICE_REF`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::DISCOUNT)) {
            $modifiedColumns[':p' . $index++]  = '`DISCOUNT`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::POSTAGE)) {
            $modifiedColumns[':p' . $index++]  = '`POSTAGE`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::POSTAGE_TAX)) {
            $modifiedColumns[':p' . $index++]  = '`POSTAGE_TAX`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::POSTAGE_TAX_RULE_TITLE)) {
            $modifiedColumns[':p' . $index++]  = '`POSTAGE_TAX_RULE_TITLE`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::PAYMENT_MODULE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`PAYMENT_MODULE_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::DELIVERY_MODULE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`DELIVERY_MODULE_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::STATUS_ID)) {
            $modifiedColumns[':p' . $index++]  = '`STATUS_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::LANG_ID)) {
            $modifiedColumns[':p' . $index++]  = '`LANG_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::CART_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CART_ID`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
        }
        if ($this->isColumnModified(OrderVersionTableMap::CUSTOMER_ID_VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`CUSTOMER_ID_VERSION`';
        }

        $sql = sprintf(
            'INSERT INTO `order_version` (%s) VALUES (%s)',
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
                    case '`CUSTOMER_ID_VERSION`':
                        $stmt->bindValue($identifier, $this->customer_id_version, PDO::PARAM_INT);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

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
        $pos = OrderVersionTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
            case 25:
                return $this->getCustomerIdVersion();
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
        if (isset($alreadyDumpedObjects['OrderVersion'][serialize($this->getPrimaryKey())])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['OrderVersion'][serialize($this->getPrimaryKey())] = true;
        $keys = OrderVersionTableMap::getFieldNames($keyType);
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
            $keys[25] => $this->getCustomerIdVersion(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aOrder) {
                $result['Order'] = $this->aOrder->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
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
        $pos = OrderVersionTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
            case 25:
                $this->setCustomerIdVersion($value);
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
        $keys = OrderVersionTableMap::getFieldNames($keyType);

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
        if (array_key_exists($keys[25], $arr)) $this->setCustomerIdVersion($arr[$keys[25]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(OrderVersionTableMap::DATABASE_NAME);

        if ($this->isColumnModified(OrderVersionTableMap::ID)) $criteria->add(OrderVersionTableMap::ID, $this->id);
        if ($this->isColumnModified(OrderVersionTableMap::REF)) $criteria->add(OrderVersionTableMap::REF, $this->ref);
        if ($this->isColumnModified(OrderVersionTableMap::CUSTOMER_ID)) $criteria->add(OrderVersionTableMap::CUSTOMER_ID, $this->customer_id);
        if ($this->isColumnModified(OrderVersionTableMap::INVOICE_ORDER_ADDRESS_ID)) $criteria->add(OrderVersionTableMap::INVOICE_ORDER_ADDRESS_ID, $this->invoice_order_address_id);
        if ($this->isColumnModified(OrderVersionTableMap::DELIVERY_ORDER_ADDRESS_ID)) $criteria->add(OrderVersionTableMap::DELIVERY_ORDER_ADDRESS_ID, $this->delivery_order_address_id);
        if ($this->isColumnModified(OrderVersionTableMap::INVOICE_DATE)) $criteria->add(OrderVersionTableMap::INVOICE_DATE, $this->invoice_date);
        if ($this->isColumnModified(OrderVersionTableMap::CURRENCY_ID)) $criteria->add(OrderVersionTableMap::CURRENCY_ID, $this->currency_id);
        if ($this->isColumnModified(OrderVersionTableMap::CURRENCY_RATE)) $criteria->add(OrderVersionTableMap::CURRENCY_RATE, $this->currency_rate);
        if ($this->isColumnModified(OrderVersionTableMap::TRANSACTION_REF)) $criteria->add(OrderVersionTableMap::TRANSACTION_REF, $this->transaction_ref);
        if ($this->isColumnModified(OrderVersionTableMap::DELIVERY_REF)) $criteria->add(OrderVersionTableMap::DELIVERY_REF, $this->delivery_ref);
        if ($this->isColumnModified(OrderVersionTableMap::INVOICE_REF)) $criteria->add(OrderVersionTableMap::INVOICE_REF, $this->invoice_ref);
        if ($this->isColumnModified(OrderVersionTableMap::DISCOUNT)) $criteria->add(OrderVersionTableMap::DISCOUNT, $this->discount);
        if ($this->isColumnModified(OrderVersionTableMap::POSTAGE)) $criteria->add(OrderVersionTableMap::POSTAGE, $this->postage);
        if ($this->isColumnModified(OrderVersionTableMap::POSTAGE_TAX)) $criteria->add(OrderVersionTableMap::POSTAGE_TAX, $this->postage_tax);
        if ($this->isColumnModified(OrderVersionTableMap::POSTAGE_TAX_RULE_TITLE)) $criteria->add(OrderVersionTableMap::POSTAGE_TAX_RULE_TITLE, $this->postage_tax_rule_title);
        if ($this->isColumnModified(OrderVersionTableMap::PAYMENT_MODULE_ID)) $criteria->add(OrderVersionTableMap::PAYMENT_MODULE_ID, $this->payment_module_id);
        if ($this->isColumnModified(OrderVersionTableMap::DELIVERY_MODULE_ID)) $criteria->add(OrderVersionTableMap::DELIVERY_MODULE_ID, $this->delivery_module_id);
        if ($this->isColumnModified(OrderVersionTableMap::STATUS_ID)) $criteria->add(OrderVersionTableMap::STATUS_ID, $this->status_id);
        if ($this->isColumnModified(OrderVersionTableMap::LANG_ID)) $criteria->add(OrderVersionTableMap::LANG_ID, $this->lang_id);
        if ($this->isColumnModified(OrderVersionTableMap::CART_ID)) $criteria->add(OrderVersionTableMap::CART_ID, $this->cart_id);
        if ($this->isColumnModified(OrderVersionTableMap::CREATED_AT)) $criteria->add(OrderVersionTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(OrderVersionTableMap::UPDATED_AT)) $criteria->add(OrderVersionTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(OrderVersionTableMap::VERSION)) $criteria->add(OrderVersionTableMap::VERSION, $this->version);
        if ($this->isColumnModified(OrderVersionTableMap::VERSION_CREATED_AT)) $criteria->add(OrderVersionTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(OrderVersionTableMap::VERSION_CREATED_BY)) $criteria->add(OrderVersionTableMap::VERSION_CREATED_BY, $this->version_created_by);
        if ($this->isColumnModified(OrderVersionTableMap::CUSTOMER_ID_VERSION)) $criteria->add(OrderVersionTableMap::CUSTOMER_ID_VERSION, $this->customer_id_version);

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
        $criteria = new Criteria(OrderVersionTableMap::DATABASE_NAME);
        $criteria->add(OrderVersionTableMap::ID, $this->id);
        $criteria->add(OrderVersionTableMap::VERSION, $this->version);

        return $criteria;
    }

    /**
     * Returns the composite primary key for this object.
     * The array elements will be in same order as specified in XML.
     * @return array
     */
    public function getPrimaryKey()
    {
        $pks = array();
        $pks[0] = $this->getId();
        $pks[1] = $this->getVersion();

        return $pks;
    }

    /**
     * Set the [composite] primary key.
     *
     * @param      array $keys The elements of the composite key (order must match the order in XML file).
     * @return void
     */
    public function setPrimaryKey($keys)
    {
        $this->setId($keys[0]);
        $this->setVersion($keys[1]);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {

        return (null === $this->getId()) && (null === $this->getVersion());
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \Thelia\Model\OrderVersion (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setId($this->getId());
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
        $copyObj->setCustomerIdVersion($this->getCustomerIdVersion());
        if ($makeNew) {
            $copyObj->setNew(true);
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
     * @return                 \Thelia\Model\OrderVersion Clone of current object.
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
     * Declares an association between this object and a ChildOrder object.
     *
     * @param                  ChildOrder $v
     * @return                 \Thelia\Model\OrderVersion The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrder(ChildOrder $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getId());
        }

        $this->aOrder = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildOrder object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderVersion($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildOrder object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildOrder The associated ChildOrder object.
     * @throws PropelException
     */
    public function getOrder(ConnectionInterface $con = null)
    {
        if ($this->aOrder === null && ($this->id !== null)) {
            $this->aOrder = ChildOrderQuery::create()->findPk($this->id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aOrder->addOrderVersions($this);
             */
        }

        return $this->aOrder;
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
        $this->customer_id_version = null;
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
        } // if ($deep)

        $this->aOrder = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(OrderVersionTableMap::DEFAULT_STRING_FORMAT);
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
