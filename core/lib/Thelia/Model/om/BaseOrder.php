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
use Thelia\Model\CouponOrder;
use Thelia\Model\CouponOrderQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderPeer;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Base class that represents a row from the 'order' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseOrder extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\OrderPeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        OrderPeer
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
     * The value for the address_invoice field.
     * @var        int
     */
    protected $address_invoice;

    /**
     * The value for the address_delivery field.
     * @var        int
     */
    protected $address_delivery;

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
     * The value for the transaction field.
     * @var        string
     */
    protected $transaction;

    /**
     * The value for the delivery_num field.
     * @var        string
     */
    protected $delivery_num;

    /**
     * The value for the invoice field.
     * @var        string
     */
    protected $invoice;

    /**
     * The value for the postage field.
     * @var        double
     */
    protected $postage;

    /**
     * The value for the payment field.
     * @var        string
     */
    protected $payment;

    /**
     * The value for the carrier field.
     * @var        string
     */
    protected $carrier;

    /**
     * The value for the status_id field.
     * @var        int
     */
    protected $status_id;

    /**
     * The value for the lang field.
     * @var        string
     */
    protected $lang;

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
     * @var        CouponOrder
     */
    protected $aCouponOrder;

    /**
     * @var        OrderProduct
     */
    protected $aOrderProduct;

    /**
     * @var        Currency one-to-one related Currency object
     */
    protected $singleCurrency;

    /**
     * @var        Customer one-to-one related Customer object
     */
    protected $singleCustomer;

    /**
     * @var        OrderAddress one-to-one related OrderAddress object
     */
    protected $singleOrderAddress;

    /**
     * @var        OrderAddress one-to-one related OrderAddress object
     */
    protected $singleOrderAddress;

    /**
     * @var        OrderStatus one-to-one related OrderStatus object
     */
    protected $singleOrderStatus;

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
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $currencysScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $customersScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $orderAddresssScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $orderAddresssScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $orderStatussScheduledForDeletion = null;

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
     * Get the [ref] column value.
     *
     * @return string
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * Get the [customer_id] column value.
     *
     * @return int
     */
    public function getCustomerId()
    {
        return $this->customer_id;
    }

    /**
     * Get the [address_invoice] column value.
     *
     * @return int
     */
    public function getAddressInvoice()
    {
        return $this->address_invoice;
    }

    /**
     * Get the [address_delivery] column value.
     *
     * @return int
     */
    public function getAddressDelivery()
    {
        return $this->address_delivery;
    }

    /**
     * Get the [optionally formatted] temporal [invoice_date] column value.
     *
     *
     * @param string $format The date/time format string (either date()-style or strftime()-style).
     *				 If format is null, then the raw DateTime object will be returned.
     * @return mixed Formatted date/time value as string or DateTime object (if format is null), null if column is null, and 0 if column value is 0000-00-00
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getInvoiceDate($format = '%x')
    {
        if ($this->invoice_date === null) {
            return null;
        }

        if ($this->invoice_date === '0000-00-00') {
            // while technically this is not a default value of null,
            // this seems to be closest in meaning.
            return null;
        } else {
            try {
                $dt = new DateTime($this->invoice_date);
            } catch (Exception $x) {
                throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($this->invoice_date, true), $x);
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
     * Get the [currency_id] column value.
     *
     * @return int
     */
    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    /**
     * Get the [currency_rate] column value.
     *
     * @return double
     */
    public function getCurrencyRate()
    {
        return $this->currency_rate;
    }

    /**
     * Get the [transaction] column value.
     *
     * @return string
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * Get the [delivery_num] column value.
     *
     * @return string
     */
    public function getDeliveryNum()
    {
        return $this->delivery_num;
    }

    /**
     * Get the [invoice] column value.
     *
     * @return string
     */
    public function getInvoice()
    {
        return $this->invoice;
    }

    /**
     * Get the [postage] column value.
     *
     * @return double
     */
    public function getPostage()
    {
        return $this->postage;
    }

    /**
     * Get the [payment] column value.
     *
     * @return string
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * Get the [carrier] column value.
     *
     * @return string
     */
    public function getCarrier()
    {
        return $this->carrier;
    }

    /**
     * Get the [status_id] column value.
     *
     * @return int
     */
    public function getStatusId()
    {
        return $this->status_id;
    }

    /**
     * Get the [lang] column value.
     *
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
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
     * Set the value of [id] column.
     *
     * @param int $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = OrderPeer::ID;
        }

        if ($this->aCouponOrder !== null && $this->aCouponOrder->getOrderId() !== $v) {
            $this->aCouponOrder = null;
        }

        if ($this->aOrderProduct !== null && $this->aOrderProduct->getOrderId() !== $v) {
            $this->aOrderProduct = null;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [ref] column.
     *
     * @param string $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->ref !== $v) {
            $this->ref = $v;
            $this->modifiedColumns[] = OrderPeer::REF;
        }


        return $this;
    } // setRef()

    /**
     * Set the value of [customer_id] column.
     *
     * @param int $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setCustomerId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->customer_id !== $v) {
            $this->customer_id = $v;
            $this->modifiedColumns[] = OrderPeer::CUSTOMER_ID;
        }


        return $this;
    } // setCustomerId()

    /**
     * Set the value of [address_invoice] column.
     *
     * @param int $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setAddressInvoice($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->address_invoice !== $v) {
            $this->address_invoice = $v;
            $this->modifiedColumns[] = OrderPeer::ADDRESS_INVOICE;
        }


        return $this;
    } // setAddressInvoice()

    /**
     * Set the value of [address_delivery] column.
     *
     * @param int $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setAddressDelivery($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->address_delivery !== $v) {
            $this->address_delivery = $v;
            $this->modifiedColumns[] = OrderPeer::ADDRESS_DELIVERY;
        }


        return $this;
    } // setAddressDelivery()

    /**
     * Sets the value of [invoice_date] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Order The current object (for fluent API support)
     */
    public function setInvoiceDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->invoice_date !== null || $dt !== null) {
            $currentDateAsString = ($this->invoice_date !== null && $tmpDt = new DateTime($this->invoice_date)) ? $tmpDt->format('Y-m-d') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->invoice_date = $newDateAsString;
                $this->modifiedColumns[] = OrderPeer::INVOICE_DATE;
            }
        } // if either are not null


        return $this;
    } // setInvoiceDate()

    /**
     * Set the value of [currency_id] column.
     *
     * @param int $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setCurrencyId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->currency_id !== $v) {
            $this->currency_id = $v;
            $this->modifiedColumns[] = OrderPeer::CURRENCY_ID;
        }


        return $this;
    } // setCurrencyId()

    /**
     * Set the value of [currency_rate] column.
     *
     * @param double $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setCurrencyRate($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->currency_rate !== $v) {
            $this->currency_rate = $v;
            $this->modifiedColumns[] = OrderPeer::CURRENCY_RATE;
        }


        return $this;
    } // setCurrencyRate()

    /**
     * Set the value of [transaction] column.
     *
     * @param string $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setTransaction($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->transaction !== $v) {
            $this->transaction = $v;
            $this->modifiedColumns[] = OrderPeer::TRANSACTION;
        }


        return $this;
    } // setTransaction()

    /**
     * Set the value of [delivery_num] column.
     *
     * @param string $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setDeliveryNum($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->delivery_num !== $v) {
            $this->delivery_num = $v;
            $this->modifiedColumns[] = OrderPeer::DELIVERY_NUM;
        }


        return $this;
    } // setDeliveryNum()

    /**
     * Set the value of [invoice] column.
     *
     * @param string $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setInvoice($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->invoice !== $v) {
            $this->invoice = $v;
            $this->modifiedColumns[] = OrderPeer::INVOICE;
        }


        return $this;
    } // setInvoice()

    /**
     * Set the value of [postage] column.
     *
     * @param double $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setPostage($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->postage !== $v) {
            $this->postage = $v;
            $this->modifiedColumns[] = OrderPeer::POSTAGE;
        }


        return $this;
    } // setPostage()

    /**
     * Set the value of [payment] column.
     *
     * @param string $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setPayment($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->payment !== $v) {
            $this->payment = $v;
            $this->modifiedColumns[] = OrderPeer::PAYMENT;
        }


        return $this;
    } // setPayment()

    /**
     * Set the value of [carrier] column.
     *
     * @param string $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setCarrier($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->carrier !== $v) {
            $this->carrier = $v;
            $this->modifiedColumns[] = OrderPeer::CARRIER;
        }


        return $this;
    } // setCarrier()

    /**
     * Set the value of [status_id] column.
     *
     * @param int $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setStatusId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->status_id !== $v) {
            $this->status_id = $v;
            $this->modifiedColumns[] = OrderPeer::STATUS_ID;
        }


        return $this;
    } // setStatusId()

    /**
     * Set the value of [lang] column.
     *
     * @param string $v new value
     * @return Order The current object (for fluent API support)
     */
    public function setLang($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lang !== $v) {
            $this->lang = $v;
            $this->modifiedColumns[] = OrderPeer::LANG;
        }


        return $this;
    } // setLang()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Order The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = OrderPeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Order The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = OrderPeer::UPDATED_AT;
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
            $this->ref = ($row[$startcol + 1] !== null) ? (string) $row[$startcol + 1] : null;
            $this->customer_id = ($row[$startcol + 2] !== null) ? (int) $row[$startcol + 2] : null;
            $this->address_invoice = ($row[$startcol + 3] !== null) ? (int) $row[$startcol + 3] : null;
            $this->address_delivery = ($row[$startcol + 4] !== null) ? (int) $row[$startcol + 4] : null;
            $this->invoice_date = ($row[$startcol + 5] !== null) ? (string) $row[$startcol + 5] : null;
            $this->currency_id = ($row[$startcol + 6] !== null) ? (int) $row[$startcol + 6] : null;
            $this->currency_rate = ($row[$startcol + 7] !== null) ? (double) $row[$startcol + 7] : null;
            $this->transaction = ($row[$startcol + 8] !== null) ? (string) $row[$startcol + 8] : null;
            $this->delivery_num = ($row[$startcol + 9] !== null) ? (string) $row[$startcol + 9] : null;
            $this->invoice = ($row[$startcol + 10] !== null) ? (string) $row[$startcol + 10] : null;
            $this->postage = ($row[$startcol + 11] !== null) ? (double) $row[$startcol + 11] : null;
            $this->payment = ($row[$startcol + 12] !== null) ? (string) $row[$startcol + 12] : null;
            $this->carrier = ($row[$startcol + 13] !== null) ? (string) $row[$startcol + 13] : null;
            $this->status_id = ($row[$startcol + 14] !== null) ? (int) $row[$startcol + 14] : null;
            $this->lang = ($row[$startcol + 15] !== null) ? (string) $row[$startcol + 15] : null;
            $this->created_at = ($row[$startcol + 16] !== null) ? (string) $row[$startcol + 16] : null;
            $this->updated_at = ($row[$startcol + 17] !== null) ? (string) $row[$startcol + 17] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 18; // 18 = OrderPeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Order object", $e);
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

        if ($this->aCouponOrder !== null && $this->id !== $this->aCouponOrder->getOrderId()) {
            $this->aCouponOrder = null;
        }
        if ($this->aOrderProduct !== null && $this->id !== $this->aOrderProduct->getOrderId()) {
            $this->aOrderProduct = null;
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
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = OrderPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aCouponOrder = null;
            $this->aOrderProduct = null;
            $this->singleCurrency = null;

            $this->singleCustomer = null;

            $this->singleOrderAddress = null;

            $this->singleOrderAddress = null;

            $this->singleOrderStatus = null;

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
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = OrderQuery::create()
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
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
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
                OrderPeer::addInstanceToPool($this);
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

            if ($this->aCouponOrder !== null) {
                if ($this->aCouponOrder->isModified() || $this->aCouponOrder->isNew()) {
                    $affectedRows += $this->aCouponOrder->save($con);
                }
                $this->setCouponOrder($this->aCouponOrder);
            }

            if ($this->aOrderProduct !== null) {
                if ($this->aOrderProduct->isModified() || $this->aOrderProduct->isNew()) {
                    $affectedRows += $this->aOrderProduct->save($con);
                }
                $this->setOrderProduct($this->aOrderProduct);
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

            if ($this->currencysScheduledForDeletion !== null) {
                if (!$this->currencysScheduledForDeletion->isEmpty()) {
                    CurrencyQuery::create()
                        ->filterByPrimaryKeys($this->currencysScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->currencysScheduledForDeletion = null;
                }
            }

            if ($this->singleCurrency !== null) {
                if (!$this->singleCurrency->isDeleted()) {
                        $affectedRows += $this->singleCurrency->save($con);
                }
            }

            if ($this->customersScheduledForDeletion !== null) {
                if (!$this->customersScheduledForDeletion->isEmpty()) {
                    CustomerQuery::create()
                        ->filterByPrimaryKeys($this->customersScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->customersScheduledForDeletion = null;
                }
            }

            if ($this->singleCustomer !== null) {
                if (!$this->singleCustomer->isDeleted()) {
                        $affectedRows += $this->singleCustomer->save($con);
                }
            }

            if ($this->orderAddresssScheduledForDeletion !== null) {
                if (!$this->orderAddresssScheduledForDeletion->isEmpty()) {
                    OrderAddressQuery::create()
                        ->filterByPrimaryKeys($this->orderAddresssScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderAddresssScheduledForDeletion = null;
                }
            }

            if ($this->singleOrderAddress !== null) {
                if (!$this->singleOrderAddress->isDeleted()) {
                        $affectedRows += $this->singleOrderAddress->save($con);
                }
            }

            if ($this->orderAddresssScheduledForDeletion !== null) {
                if (!$this->orderAddresssScheduledForDeletion->isEmpty()) {
                    OrderAddressQuery::create()
                        ->filterByPrimaryKeys($this->orderAddresssScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderAddresssScheduledForDeletion = null;
                }
            }

            if ($this->singleOrderAddress !== null) {
                if (!$this->singleOrderAddress->isDeleted()) {
                        $affectedRows += $this->singleOrderAddress->save($con);
                }
            }

            if ($this->orderStatussScheduledForDeletion !== null) {
                if (!$this->orderStatussScheduledForDeletion->isEmpty()) {
                    OrderStatusQuery::create()
                        ->filterByPrimaryKeys($this->orderStatussScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderStatussScheduledForDeletion = null;
                }
            }

            if ($this->singleOrderStatus !== null) {
                if (!$this->singleOrderStatus->isDeleted()) {
                        $affectedRows += $this->singleOrderStatus->save($con);
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

        $this->modifiedColumns[] = OrderPeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . OrderPeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(OrderPeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(OrderPeer::REF)) {
            $modifiedColumns[':p' . $index++]  = '`REF`';
        }
        if ($this->isColumnModified(OrderPeer::CUSTOMER_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CUSTOMER_ID`';
        }
        if ($this->isColumnModified(OrderPeer::ADDRESS_INVOICE)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS_INVOICE`';
        }
        if ($this->isColumnModified(OrderPeer::ADDRESS_DELIVERY)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS_DELIVERY`';
        }
        if ($this->isColumnModified(OrderPeer::INVOICE_DATE)) {
            $modifiedColumns[':p' . $index++]  = '`INVOICE_DATE`';
        }
        if ($this->isColumnModified(OrderPeer::CURRENCY_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CURRENCY_ID`';
        }
        if ($this->isColumnModified(OrderPeer::CURRENCY_RATE)) {
            $modifiedColumns[':p' . $index++]  = '`CURRENCY_RATE`';
        }
        if ($this->isColumnModified(OrderPeer::TRANSACTION)) {
            $modifiedColumns[':p' . $index++]  = '`TRANSACTION`';
        }
        if ($this->isColumnModified(OrderPeer::DELIVERY_NUM)) {
            $modifiedColumns[':p' . $index++]  = '`DELIVERY_NUM`';
        }
        if ($this->isColumnModified(OrderPeer::INVOICE)) {
            $modifiedColumns[':p' . $index++]  = '`INVOICE`';
        }
        if ($this->isColumnModified(OrderPeer::POSTAGE)) {
            $modifiedColumns[':p' . $index++]  = '`POSTAGE`';
        }
        if ($this->isColumnModified(OrderPeer::PAYMENT)) {
            $modifiedColumns[':p' . $index++]  = '`PAYMENT`';
        }
        if ($this->isColumnModified(OrderPeer::CARRIER)) {
            $modifiedColumns[':p' . $index++]  = '`CARRIER`';
        }
        if ($this->isColumnModified(OrderPeer::STATUS_ID)) {
            $modifiedColumns[':p' . $index++]  = '`STATUS_ID`';
        }
        if ($this->isColumnModified(OrderPeer::LANG)) {
            $modifiedColumns[':p' . $index++]  = '`LANG`';
        }
        if ($this->isColumnModified(OrderPeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(OrderPeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
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
                    case '`ADDRESS_INVOICE`':
                        $stmt->bindValue($identifier, $this->address_invoice, PDO::PARAM_INT);
                        break;
                    case '`ADDRESS_DELIVERY`':
                        $stmt->bindValue($identifier, $this->address_delivery, PDO::PARAM_INT);
                        break;
                    case '`INVOICE_DATE`':
                        $stmt->bindValue($identifier, $this->invoice_date, PDO::PARAM_STR);
                        break;
                    case '`CURRENCY_ID`':
                        $stmt->bindValue($identifier, $this->currency_id, PDO::PARAM_INT);
                        break;
                    case '`CURRENCY_RATE`':
                        $stmt->bindValue($identifier, $this->currency_rate, PDO::PARAM_STR);
                        break;
                    case '`TRANSACTION`':
                        $stmt->bindValue($identifier, $this->transaction, PDO::PARAM_STR);
                        break;
                    case '`DELIVERY_NUM`':
                        $stmt->bindValue($identifier, $this->delivery_num, PDO::PARAM_STR);
                        break;
                    case '`INVOICE`':
                        $stmt->bindValue($identifier, $this->invoice, PDO::PARAM_STR);
                        break;
                    case '`POSTAGE`':
                        $stmt->bindValue($identifier, $this->postage, PDO::PARAM_STR);
                        break;
                    case '`PAYMENT`':
                        $stmt->bindValue($identifier, $this->payment, PDO::PARAM_STR);
                        break;
                    case '`CARRIER`':
                        $stmt->bindValue($identifier, $this->carrier, PDO::PARAM_STR);
                        break;
                    case '`STATUS_ID`':
                        $stmt->bindValue($identifier, $this->status_id, PDO::PARAM_INT);
                        break;
                    case '`LANG`':
                        $stmt->bindValue($identifier, $this->lang, PDO::PARAM_STR);
                        break;
                    case '`CREATED_AT`':
                        $stmt->bindValue($identifier, $this->created_at, PDO::PARAM_STR);
                        break;
                    case '`UPDATED_AT`':
                        $stmt->bindValue($identifier, $this->updated_at, PDO::PARAM_STR);
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

            if ($this->aCouponOrder !== null) {
                if (!$this->aCouponOrder->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aCouponOrder->getValidationFailures());
                }
            }

            if ($this->aOrderProduct !== null) {
                if (!$this->aOrderProduct->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aOrderProduct->getValidationFailures());
                }
            }


            if (($retval = OrderPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->singleCurrency !== null) {
                    if (!$this->singleCurrency->validate($columns)) {
                        $failureMap = array_merge($failureMap, $this->singleCurrency->getValidationFailures());
                    }
                }

                if ($this->singleCustomer !== null) {
                    if (!$this->singleCustomer->validate($columns)) {
                        $failureMap = array_merge($failureMap, $this->singleCustomer->getValidationFailures());
                    }
                }

                if ($this->singleOrderAddress !== null) {
                    if (!$this->singleOrderAddress->validate($columns)) {
                        $failureMap = array_merge($failureMap, $this->singleOrderAddress->getValidationFailures());
                    }
                }

                if ($this->singleOrderAddress !== null) {
                    if (!$this->singleOrderAddress->validate($columns)) {
                        $failureMap = array_merge($failureMap, $this->singleOrderAddress->getValidationFailures());
                    }
                }

                if ($this->singleOrderStatus !== null) {
                    if (!$this->singleOrderStatus->validate($columns)) {
                        $failureMap = array_merge($failureMap, $this->singleOrderStatus->getValidationFailures());
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
        $pos = OrderPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
                return $this->getRef();
                break;
            case 2:
                return $this->getCustomerId();
                break;
            case 3:
                return $this->getAddressInvoice();
                break;
            case 4:
                return $this->getAddressDelivery();
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
                return $this->getTransaction();
                break;
            case 9:
                return $this->getDeliveryNum();
                break;
            case 10:
                return $this->getInvoice();
                break;
            case 11:
                return $this->getPostage();
                break;
            case 12:
                return $this->getPayment();
                break;
            case 13:
                return $this->getCarrier();
                break;
            case 14:
                return $this->getStatusId();
                break;
            case 15:
                return $this->getLang();
                break;
            case 16:
                return $this->getCreatedAt();
                break;
            case 17:
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
        if (isset($alreadyDumpedObjects['Order'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Order'][$this->getPrimaryKey()] = true;
        $keys = OrderPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getRef(),
            $keys[2] => $this->getCustomerId(),
            $keys[3] => $this->getAddressInvoice(),
            $keys[4] => $this->getAddressDelivery(),
            $keys[5] => $this->getInvoiceDate(),
            $keys[6] => $this->getCurrencyId(),
            $keys[7] => $this->getCurrencyRate(),
            $keys[8] => $this->getTransaction(),
            $keys[9] => $this->getDeliveryNum(),
            $keys[10] => $this->getInvoice(),
            $keys[11] => $this->getPostage(),
            $keys[12] => $this->getPayment(),
            $keys[13] => $this->getCarrier(),
            $keys[14] => $this->getStatusId(),
            $keys[15] => $this->getLang(),
            $keys[16] => $this->getCreatedAt(),
            $keys[17] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->aCouponOrder) {
                $result['CouponOrder'] = $this->aCouponOrder->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aOrderProduct) {
                $result['OrderProduct'] = $this->aOrderProduct->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->singleCurrency) {
                $result['Currency'] = $this->singleCurrency->toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, true);
            }
            if (null !== $this->singleCustomer) {
                $result['Customer'] = $this->singleCustomer->toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, true);
            }
            if (null !== $this->singleOrderAddress) {
                $result['OrderAddress'] = $this->singleOrderAddress->toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, true);
            }
            if (null !== $this->singleOrderAddress) {
                $result['OrderAddress'] = $this->singleOrderAddress->toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, true);
            }
            if (null !== $this->singleOrderStatus) {
                $result['OrderStatus'] = $this->singleOrderStatus->toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, true);
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
        $pos = OrderPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
                $this->setRef($value);
                break;
            case 2:
                $this->setCustomerId($value);
                break;
            case 3:
                $this->setAddressInvoice($value);
                break;
            case 4:
                $this->setAddressDelivery($value);
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
                $this->setTransaction($value);
                break;
            case 9:
                $this->setDeliveryNum($value);
                break;
            case 10:
                $this->setInvoice($value);
                break;
            case 11:
                $this->setPostage($value);
                break;
            case 12:
                $this->setPayment($value);
                break;
            case 13:
                $this->setCarrier($value);
                break;
            case 14:
                $this->setStatusId($value);
                break;
            case 15:
                $this->setLang($value);
                break;
            case 16:
                $this->setCreatedAt($value);
                break;
            case 17:
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
        $keys = OrderPeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setRef($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCustomerId($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setAddressInvoice($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setAddressDelivery($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setInvoiceDate($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setCurrencyId($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setCurrencyRate($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setTransaction($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setDeliveryNum($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setInvoice($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setPostage($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setPayment($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setCarrier($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setStatusId($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setLang($arr[$keys[15]]);
        if (array_key_exists($keys[16], $arr)) $this->setCreatedAt($arr[$keys[16]]);
        if (array_key_exists($keys[17], $arr)) $this->setUpdatedAt($arr[$keys[17]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(OrderPeer::DATABASE_NAME);

        if ($this->isColumnModified(OrderPeer::ID)) $criteria->add(OrderPeer::ID, $this->id);
        if ($this->isColumnModified(OrderPeer::REF)) $criteria->add(OrderPeer::REF, $this->ref);
        if ($this->isColumnModified(OrderPeer::CUSTOMER_ID)) $criteria->add(OrderPeer::CUSTOMER_ID, $this->customer_id);
        if ($this->isColumnModified(OrderPeer::ADDRESS_INVOICE)) $criteria->add(OrderPeer::ADDRESS_INVOICE, $this->address_invoice);
        if ($this->isColumnModified(OrderPeer::ADDRESS_DELIVERY)) $criteria->add(OrderPeer::ADDRESS_DELIVERY, $this->address_delivery);
        if ($this->isColumnModified(OrderPeer::INVOICE_DATE)) $criteria->add(OrderPeer::INVOICE_DATE, $this->invoice_date);
        if ($this->isColumnModified(OrderPeer::CURRENCY_ID)) $criteria->add(OrderPeer::CURRENCY_ID, $this->currency_id);
        if ($this->isColumnModified(OrderPeer::CURRENCY_RATE)) $criteria->add(OrderPeer::CURRENCY_RATE, $this->currency_rate);
        if ($this->isColumnModified(OrderPeer::TRANSACTION)) $criteria->add(OrderPeer::TRANSACTION, $this->transaction);
        if ($this->isColumnModified(OrderPeer::DELIVERY_NUM)) $criteria->add(OrderPeer::DELIVERY_NUM, $this->delivery_num);
        if ($this->isColumnModified(OrderPeer::INVOICE)) $criteria->add(OrderPeer::INVOICE, $this->invoice);
        if ($this->isColumnModified(OrderPeer::POSTAGE)) $criteria->add(OrderPeer::POSTAGE, $this->postage);
        if ($this->isColumnModified(OrderPeer::PAYMENT)) $criteria->add(OrderPeer::PAYMENT, $this->payment);
        if ($this->isColumnModified(OrderPeer::CARRIER)) $criteria->add(OrderPeer::CARRIER, $this->carrier);
        if ($this->isColumnModified(OrderPeer::STATUS_ID)) $criteria->add(OrderPeer::STATUS_ID, $this->status_id);
        if ($this->isColumnModified(OrderPeer::LANG)) $criteria->add(OrderPeer::LANG, $this->lang);
        if ($this->isColumnModified(OrderPeer::CREATED_AT)) $criteria->add(OrderPeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(OrderPeer::UPDATED_AT)) $criteria->add(OrderPeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(OrderPeer::DATABASE_NAME);
        $criteria->add(OrderPeer::ID, $this->id);

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
     * @param object $copyObj An object of Order (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setRef($this->getRef());
        $copyObj->setCustomerId($this->getCustomerId());
        $copyObj->setAddressInvoice($this->getAddressInvoice());
        $copyObj->setAddressDelivery($this->getAddressDelivery());
        $copyObj->setInvoiceDate($this->getInvoiceDate());
        $copyObj->setCurrencyId($this->getCurrencyId());
        $copyObj->setCurrencyRate($this->getCurrencyRate());
        $copyObj->setTransaction($this->getTransaction());
        $copyObj->setDeliveryNum($this->getDeliveryNum());
        $copyObj->setInvoice($this->getInvoice());
        $copyObj->setPostage($this->getPostage());
        $copyObj->setPayment($this->getPayment());
        $copyObj->setCarrier($this->getCarrier());
        $copyObj->setStatusId($this->getStatusId());
        $copyObj->setLang($this->getLang());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            $relObj = $this->getCurrency();
            if ($relObj) {
                $copyObj->setCurrency($relObj->copy($deepCopy));
            }

            $relObj = $this->getCustomer();
            if ($relObj) {
                $copyObj->setCustomer($relObj->copy($deepCopy));
            }

            $relObj = $this->getOrderAddress();
            if ($relObj) {
                $copyObj->setOrderAddress($relObj->copy($deepCopy));
            }

            $relObj = $this->getOrderAddress();
            if ($relObj) {
                $copyObj->setOrderAddress($relObj->copy($deepCopy));
            }

            $relObj = $this->getOrderStatus();
            if ($relObj) {
                $copyObj->setOrderStatus($relObj->copy($deepCopy));
            }

            $relObj = $this->getCouponOrder();
            if ($relObj) {
                $copyObj->setCouponOrder($relObj->copy($deepCopy));
            }

            $relObj = $this->getOrderProduct();
            if ($relObj) {
                $copyObj->setOrderProduct($relObj->copy($deepCopy));
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
     * @return Order Clone of current object.
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
     * @return OrderPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new OrderPeer();
        }

        return self::$peer;
    }

    /**
     * Declares an association between this object and a CouponOrder object.
     *
     * @param             CouponOrder $v
     * @return Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCouponOrder(CouponOrder $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getOrderId());
        }

        $this->aCouponOrder = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setOrder($this);
        }


        return $this;
    }


    /**
     * Get the associated CouponOrder object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return CouponOrder The associated CouponOrder object.
     * @throws PropelException
     */
    public function getCouponOrder(PropelPDO $con = null)
    {
        if ($this->aCouponOrder === null && ($this->id !== null)) {
            $this->aCouponOrder = CouponOrderQuery::create()
                ->filterByOrder($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aCouponOrder->setOrder($this);
        }

        return $this->aCouponOrder;
    }

    /**
     * Declares an association between this object and a OrderProduct object.
     *
     * @param             OrderProduct $v
     * @return Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrderProduct(OrderProduct $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getOrderId());
        }

        $this->aOrderProduct = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setOrder($this);
        }


        return $this;
    }


    /**
     * Get the associated OrderProduct object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return OrderProduct The associated OrderProduct object.
     * @throws PropelException
     */
    public function getOrderProduct(PropelPDO $con = null)
    {
        if ($this->aOrderProduct === null && ($this->id !== null)) {
            $this->aOrderProduct = OrderProductQuery::create()
                ->filterByOrder($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aOrderProduct->setOrder($this);
        }

        return $this->aOrderProduct;
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
    }

    /**
     * Gets a single Currency object, which is related to this object by a one-to-one relationship.
     *
     * @param PropelPDO $con optional connection object
     * @return Currency
     * @throws PropelException
     */
    public function getCurrency(PropelPDO $con = null)
    {

        if ($this->singleCurrency === null && !$this->isNew()) {
            $this->singleCurrency = CurrencyQuery::create()->findPk($this->getPrimaryKey(), $con);
        }

        return $this->singleCurrency;
    }

    /**
     * Sets a single Currency object as related to this object by a one-to-one relationship.
     *
     * @param             Currency $v Currency
     * @return Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCurrency(Currency $v = null)
    {
        $this->singleCurrency = $v;

        // Make sure that that the passed-in Currency isn't already associated with this object
        if ($v !== null && $v->getOrder() === null) {
            $v->setOrder($this);
        }

        return $this;
    }

    /**
     * Gets a single Customer object, which is related to this object by a one-to-one relationship.
     *
     * @param PropelPDO $con optional connection object
     * @return Customer
     * @throws PropelException
     */
    public function getCustomer(PropelPDO $con = null)
    {

        if ($this->singleCustomer === null && !$this->isNew()) {
            $this->singleCustomer = CustomerQuery::create()->findPk($this->getPrimaryKey(), $con);
        }

        return $this->singleCustomer;
    }

    /**
     * Sets a single Customer object as related to this object by a one-to-one relationship.
     *
     * @param             Customer $v Customer
     * @return Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCustomer(Customer $v = null)
    {
        $this->singleCustomer = $v;

        // Make sure that that the passed-in Customer isn't already associated with this object
        if ($v !== null && $v->getOrder() === null) {
            $v->setOrder($this);
        }

        return $this;
    }

    /**
     * Gets a single OrderAddress object, which is related to this object by a one-to-one relationship.
     *
     * @param PropelPDO $con optional connection object
     * @return OrderAddress
     * @throws PropelException
     */
    public function getOrderAddress(PropelPDO $con = null)
    {

        if ($this->singleOrderAddress === null && !$this->isNew()) {
            $this->singleOrderAddress = OrderAddressQuery::create()->findPk($this->getPrimaryKey(), $con);
        }

        return $this->singleOrderAddress;
    }

    /**
     * Sets a single OrderAddress object as related to this object by a one-to-one relationship.
     *
     * @param             OrderAddress $v OrderAddress
     * @return Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrderAddress(OrderAddress $v = null)
    {
        $this->singleOrderAddress = $v;

        // Make sure that that the passed-in OrderAddress isn't already associated with this object
        if ($v !== null && $v->getOrder() === null) {
            $v->setOrder($this);
        }

        return $this;
    }

    /**
     * Gets a single OrderAddress object, which is related to this object by a one-to-one relationship.
     *
     * @param PropelPDO $con optional connection object
     * @return OrderAddress
     * @throws PropelException
     */
    public function getOrderAddress(PropelPDO $con = null)
    {

        if ($this->singleOrderAddress === null && !$this->isNew()) {
            $this->singleOrderAddress = OrderAddressQuery::create()->findPk($this->getPrimaryKey(), $con);
        }

        return $this->singleOrderAddress;
    }

    /**
     * Sets a single OrderAddress object as related to this object by a one-to-one relationship.
     *
     * @param             OrderAddress $v OrderAddress
     * @return Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrderAddress(OrderAddress $v = null)
    {
        $this->singleOrderAddress = $v;

        // Make sure that that the passed-in OrderAddress isn't already associated with this object
        if ($v !== null && $v->getOrder() === null) {
            $v->setOrder($this);
        }

        return $this;
    }

    /**
     * Gets a single OrderStatus object, which is related to this object by a one-to-one relationship.
     *
     * @param PropelPDO $con optional connection object
     * @return OrderStatus
     * @throws PropelException
     */
    public function getOrderStatus(PropelPDO $con = null)
    {

        if ($this->singleOrderStatus === null && !$this->isNew()) {
            $this->singleOrderStatus = OrderStatusQuery::create()->findPk($this->getPrimaryKey(), $con);
        }

        return $this->singleOrderStatus;
    }

    /**
     * Sets a single OrderStatus object as related to this object by a one-to-one relationship.
     *
     * @param             OrderStatus $v OrderStatus
     * @return Order The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrderStatus(OrderStatus $v = null)
    {
        $this->singleOrderStatus = $v;

        // Make sure that that the passed-in OrderStatus isn't already associated with this object
        if ($v !== null && $v->getOrder() === null) {
            $v->setOrder($this);
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
        $this->address_invoice = null;
        $this->address_delivery = null;
        $this->invoice_date = null;
        $this->currency_id = null;
        $this->currency_rate = null;
        $this->transaction = null;
        $this->delivery_num = null;
        $this->invoice = null;
        $this->postage = null;
        $this->payment = null;
        $this->carrier = null;
        $this->status_id = null;
        $this->lang = null;
        $this->created_at = null;
        $this->updated_at = null;
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
            if ($this->singleCurrency) {
                $this->singleCurrency->clearAllReferences($deep);
            }
            if ($this->singleCustomer) {
                $this->singleCustomer->clearAllReferences($deep);
            }
            if ($this->singleOrderAddress) {
                $this->singleOrderAddress->clearAllReferences($deep);
            }
            if ($this->singleOrderAddress) {
                $this->singleOrderAddress->clearAllReferences($deep);
            }
            if ($this->singleOrderStatus) {
                $this->singleOrderStatus->clearAllReferences($deep);
            }
        } // if ($deep)

        if ($this->singleCurrency instanceof PropelCollection) {
            $this->singleCurrency->clearIterator();
        }
        $this->singleCurrency = null;
        if ($this->singleCustomer instanceof PropelCollection) {
            $this->singleCustomer->clearIterator();
        }
        $this->singleCustomer = null;
        if ($this->singleOrderAddress instanceof PropelCollection) {
            $this->singleOrderAddress->clearIterator();
        }
        $this->singleOrderAddress = null;
        if ($this->singleOrderAddress instanceof PropelCollection) {
            $this->singleOrderAddress->clearIterator();
        }
        $this->singleOrderAddress = null;
        if ($this->singleOrderStatus instanceof PropelCollection) {
            $this->singleOrderStatus->clearIterator();
        }
        $this->singleOrderStatus = null;
        $this->aCouponOrder = null;
        $this->aOrderProduct = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(OrderPeer::DEFAULT_STRING_FORMAT);
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
