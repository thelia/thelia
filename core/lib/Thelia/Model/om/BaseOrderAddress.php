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
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressPeer;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;

/**
 * Base class that represents a row from the 'order_address' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseOrderAddress extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\OrderAddressPeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        OrderAddressPeer
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
     * The value for the customer_title_id field.
     * @var        int
     */
    protected $customer_title_id;

    /**
     * The value for the company field.
     * @var        string
     */
    protected $company;

    /**
     * The value for the firstname field.
     * @var        string
     */
    protected $firstname;

    /**
     * The value for the lastname field.
     * @var        string
     */
    protected $lastname;

    /**
     * The value for the address1 field.
     * @var        string
     */
    protected $address1;

    /**
     * The value for the address2 field.
     * @var        string
     */
    protected $address2;

    /**
     * The value for the address3 field.
     * @var        string
     */
    protected $address3;

    /**
     * The value for the zipcode field.
     * @var        string
     */
    protected $zipcode;

    /**
     * The value for the city field.
     * @var        string
     */
    protected $city;

    /**
     * The value for the phone field.
     * @var        string
     */
    protected $phone;

    /**
     * The value for the country_id field.
     * @var        int
     */
    protected $country_id;

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
     * @var        PropelObjectCollection|Order[] Collection to store aggregation of Order objects.
     */
    protected $collOrdersRelatedByAddressInvoice;
    protected $collOrdersRelatedByAddressInvoicePartial;

    /**
     * @var        PropelObjectCollection|Order[] Collection to store aggregation of Order objects.
     */
    protected $collOrdersRelatedByAddressDelivery;
    protected $collOrdersRelatedByAddressDeliveryPartial;

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
    protected $ordersRelatedByAddressInvoiceScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $ordersRelatedByAddressDeliveryScheduledForDeletion = null;

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
     * Get the [customer_title_id] column value.
     *
     * @return int
     */
    public function getCustomerTitleId()
    {
        return $this->customer_title_id;
    }

    /**
     * Get the [company] column value.
     *
     * @return string
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * Get the [firstname] column value.
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Get the [lastname] column value.
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Get the [address1] column value.
     *
     * @return string
     */
    public function getAddress1()
    {
        return $this->address1;
    }

    /**
     * Get the [address2] column value.
     *
     * @return string
     */
    public function getAddress2()
    {
        return $this->address2;
    }

    /**
     * Get the [address3] column value.
     *
     * @return string
     */
    public function getAddress3()
    {
        return $this->address3;
    }

    /**
     * Get the [zipcode] column value.
     *
     * @return string
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * Get the [city] column value.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Get the [phone] column value.
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Get the [country_id] column value.
     *
     * @return int
     */
    public function getCountryId()
    {
        return $this->country_id;
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
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = OrderAddressPeer::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [customer_title_id] column.
     *
     * @param int $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setCustomerTitleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->customer_title_id !== $v) {
            $this->customer_title_id = $v;
            $this->modifiedColumns[] = OrderAddressPeer::CUSTOMER_TITLE_ID;
        }


        return $this;
    } // setCustomerTitleId()

    /**
     * Set the value of [company] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setCompany($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->company !== $v) {
            $this->company = $v;
            $this->modifiedColumns[] = OrderAddressPeer::COMPANY;
        }


        return $this;
    } // setCompany()

    /**
     * Set the value of [firstname] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setFirstname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->firstname !== $v) {
            $this->firstname = $v;
            $this->modifiedColumns[] = OrderAddressPeer::FIRSTNAME;
        }


        return $this;
    } // setFirstname()

    /**
     * Set the value of [lastname] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setLastname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lastname !== $v) {
            $this->lastname = $v;
            $this->modifiedColumns[] = OrderAddressPeer::LASTNAME;
        }


        return $this;
    } // setLastname()

    /**
     * Set the value of [address1] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setAddress1($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address1 !== $v) {
            $this->address1 = $v;
            $this->modifiedColumns[] = OrderAddressPeer::ADDRESS1;
        }


        return $this;
    } // setAddress1()

    /**
     * Set the value of [address2] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setAddress2($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address2 !== $v) {
            $this->address2 = $v;
            $this->modifiedColumns[] = OrderAddressPeer::ADDRESS2;
        }


        return $this;
    } // setAddress2()

    /**
     * Set the value of [address3] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setAddress3($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address3 !== $v) {
            $this->address3 = $v;
            $this->modifiedColumns[] = OrderAddressPeer::ADDRESS3;
        }


        return $this;
    } // setAddress3()

    /**
     * Set the value of [zipcode] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setZipcode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->zipcode !== $v) {
            $this->zipcode = $v;
            $this->modifiedColumns[] = OrderAddressPeer::ZIPCODE;
        }


        return $this;
    } // setZipcode()

    /**
     * Set the value of [city] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setCity($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->city !== $v) {
            $this->city = $v;
            $this->modifiedColumns[] = OrderAddressPeer::CITY;
        }


        return $this;
    } // setCity()

    /**
     * Set the value of [phone] column.
     *
     * @param string $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setPhone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->phone !== $v) {
            $this->phone = $v;
            $this->modifiedColumns[] = OrderAddressPeer::PHONE;
        }


        return $this;
    } // setPhone()

    /**
     * Set the value of [country_id] column.
     *
     * @param int $v new value
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setCountryId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->country_id !== $v) {
            $this->country_id = $v;
            $this->modifiedColumns[] = OrderAddressPeer::COUNTRY_ID;
        }


        return $this;
    } // setCountryId()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = OrderAddressPeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return OrderAddress The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = OrderAddressPeer::UPDATED_AT;
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
            $this->customer_title_id = ($row[$startcol + 1] !== null) ? (int) $row[$startcol + 1] : null;
            $this->company = ($row[$startcol + 2] !== null) ? (string) $row[$startcol + 2] : null;
            $this->firstname = ($row[$startcol + 3] !== null) ? (string) $row[$startcol + 3] : null;
            $this->lastname = ($row[$startcol + 4] !== null) ? (string) $row[$startcol + 4] : null;
            $this->address1 = ($row[$startcol + 5] !== null) ? (string) $row[$startcol + 5] : null;
            $this->address2 = ($row[$startcol + 6] !== null) ? (string) $row[$startcol + 6] : null;
            $this->address3 = ($row[$startcol + 7] !== null) ? (string) $row[$startcol + 7] : null;
            $this->zipcode = ($row[$startcol + 8] !== null) ? (string) $row[$startcol + 8] : null;
            $this->city = ($row[$startcol + 9] !== null) ? (string) $row[$startcol + 9] : null;
            $this->phone = ($row[$startcol + 10] !== null) ? (string) $row[$startcol + 10] : null;
            $this->country_id = ($row[$startcol + 11] !== null) ? (int) $row[$startcol + 11] : null;
            $this->created_at = ($row[$startcol + 12] !== null) ? (string) $row[$startcol + 12] : null;
            $this->updated_at = ($row[$startcol + 13] !== null) ? (string) $row[$startcol + 13] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 14; // 14 = OrderAddressPeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating OrderAddress object", $e);
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
            $con = Propel::getConnection(OrderAddressPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = OrderAddressPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collOrdersRelatedByAddressInvoice = null;

            $this->collOrdersRelatedByAddressDelivery = null;

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
            $con = Propel::getConnection(OrderAddressPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = OrderAddressQuery::create()
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
            $con = Propel::getConnection(OrderAddressPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(OrderAddressPeer::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(OrderAddressPeer::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(OrderAddressPeer::UPDATED_AT)) {
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
                OrderAddressPeer::addInstanceToPool($this);
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

            if ($this->ordersRelatedByAddressInvoiceScheduledForDeletion !== null) {
                if (!$this->ordersRelatedByAddressInvoiceScheduledForDeletion->isEmpty()) {
                    foreach ($this->ordersRelatedByAddressInvoiceScheduledForDeletion as $orderRelatedByAddressInvoice) {
                        // need to save related object because we set the relation to null
                        $orderRelatedByAddressInvoice->save($con);
                    }
                    $this->ordersRelatedByAddressInvoiceScheduledForDeletion = null;
                }
            }

            if ($this->collOrdersRelatedByAddressInvoice !== null) {
                foreach ($this->collOrdersRelatedByAddressInvoice as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->ordersRelatedByAddressDeliveryScheduledForDeletion !== null) {
                if (!$this->ordersRelatedByAddressDeliveryScheduledForDeletion->isEmpty()) {
                    foreach ($this->ordersRelatedByAddressDeliveryScheduledForDeletion as $orderRelatedByAddressDelivery) {
                        // need to save related object because we set the relation to null
                        $orderRelatedByAddressDelivery->save($con);
                    }
                    $this->ordersRelatedByAddressDeliveryScheduledForDeletion = null;
                }
            }

            if ($this->collOrdersRelatedByAddressDelivery !== null) {
                foreach ($this->collOrdersRelatedByAddressDelivery as $referrerFK) {
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

        $this->modifiedColumns[] = OrderAddressPeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . OrderAddressPeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(OrderAddressPeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(OrderAddressPeer::CUSTOMER_TITLE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CUSTOMER_TITLE_ID`';
        }
        if ($this->isColumnModified(OrderAddressPeer::COMPANY)) {
            $modifiedColumns[':p' . $index++]  = '`COMPANY`';
        }
        if ($this->isColumnModified(OrderAddressPeer::FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`FIRSTNAME`';
        }
        if ($this->isColumnModified(OrderAddressPeer::LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`LASTNAME`';
        }
        if ($this->isColumnModified(OrderAddressPeer::ADDRESS1)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS1`';
        }
        if ($this->isColumnModified(OrderAddressPeer::ADDRESS2)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS2`';
        }
        if ($this->isColumnModified(OrderAddressPeer::ADDRESS3)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS3`';
        }
        if ($this->isColumnModified(OrderAddressPeer::ZIPCODE)) {
            $modifiedColumns[':p' . $index++]  = '`ZIPCODE`';
        }
        if ($this->isColumnModified(OrderAddressPeer::CITY)) {
            $modifiedColumns[':p' . $index++]  = '`CITY`';
        }
        if ($this->isColumnModified(OrderAddressPeer::PHONE)) {
            $modifiedColumns[':p' . $index++]  = '`PHONE`';
        }
        if ($this->isColumnModified(OrderAddressPeer::COUNTRY_ID)) {
            $modifiedColumns[':p' . $index++]  = '`COUNTRY_ID`';
        }
        if ($this->isColumnModified(OrderAddressPeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(OrderAddressPeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `order_address` (%s) VALUES (%s)',
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
                    case '`CUSTOMER_TITLE_ID`':
                        $stmt->bindValue($identifier, $this->customer_title_id, PDO::PARAM_INT);
                        break;
                    case '`COMPANY`':
                        $stmt->bindValue($identifier, $this->company, PDO::PARAM_STR);
                        break;
                    case '`FIRSTNAME`':
                        $stmt->bindValue($identifier, $this->firstname, PDO::PARAM_STR);
                        break;
                    case '`LASTNAME`':
                        $stmt->bindValue($identifier, $this->lastname, PDO::PARAM_STR);
                        break;
                    case '`ADDRESS1`':
                        $stmt->bindValue($identifier, $this->address1, PDO::PARAM_STR);
                        break;
                    case '`ADDRESS2`':
                        $stmt->bindValue($identifier, $this->address2, PDO::PARAM_STR);
                        break;
                    case '`ADDRESS3`':
                        $stmt->bindValue($identifier, $this->address3, PDO::PARAM_STR);
                        break;
                    case '`ZIPCODE`':
                        $stmt->bindValue($identifier, $this->zipcode, PDO::PARAM_STR);
                        break;
                    case '`CITY`':
                        $stmt->bindValue($identifier, $this->city, PDO::PARAM_STR);
                        break;
                    case '`PHONE`':
                        $stmt->bindValue($identifier, $this->phone, PDO::PARAM_STR);
                        break;
                    case '`COUNTRY_ID`':
                        $stmt->bindValue($identifier, $this->country_id, PDO::PARAM_INT);
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


            if (($retval = OrderAddressPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collOrdersRelatedByAddressInvoice !== null) {
                    foreach ($this->collOrdersRelatedByAddressInvoice as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collOrdersRelatedByAddressDelivery !== null) {
                    foreach ($this->collOrdersRelatedByAddressDelivery as $referrerFK) {
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
        $pos = OrderAddressPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
                return $this->getCustomerTitleId();
                break;
            case 2:
                return $this->getCompany();
                break;
            case 3:
                return $this->getFirstname();
                break;
            case 4:
                return $this->getLastname();
                break;
            case 5:
                return $this->getAddress1();
                break;
            case 6:
                return $this->getAddress2();
                break;
            case 7:
                return $this->getAddress3();
                break;
            case 8:
                return $this->getZipcode();
                break;
            case 9:
                return $this->getCity();
                break;
            case 10:
                return $this->getPhone();
                break;
            case 11:
                return $this->getCountryId();
                break;
            case 12:
                return $this->getCreatedAt();
                break;
            case 13:
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
        if (isset($alreadyDumpedObjects['OrderAddress'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['OrderAddress'][$this->getPrimaryKey()] = true;
        $keys = OrderAddressPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getCustomerTitleId(),
            $keys[2] => $this->getCompany(),
            $keys[3] => $this->getFirstname(),
            $keys[4] => $this->getLastname(),
            $keys[5] => $this->getAddress1(),
            $keys[6] => $this->getAddress2(),
            $keys[7] => $this->getAddress3(),
            $keys[8] => $this->getZipcode(),
            $keys[9] => $this->getCity(),
            $keys[10] => $this->getPhone(),
            $keys[11] => $this->getCountryId(),
            $keys[12] => $this->getCreatedAt(),
            $keys[13] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->collOrdersRelatedByAddressInvoice) {
                $result['OrdersRelatedByAddressInvoice'] = $this->collOrdersRelatedByAddressInvoice->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrdersRelatedByAddressDelivery) {
                $result['OrdersRelatedByAddressDelivery'] = $this->collOrdersRelatedByAddressDelivery->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = OrderAddressPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
                $this->setCustomerTitleId($value);
                break;
            case 2:
                $this->setCompany($value);
                break;
            case 3:
                $this->setFirstname($value);
                break;
            case 4:
                $this->setLastname($value);
                break;
            case 5:
                $this->setAddress1($value);
                break;
            case 6:
                $this->setAddress2($value);
                break;
            case 7:
                $this->setAddress3($value);
                break;
            case 8:
                $this->setZipcode($value);
                break;
            case 9:
                $this->setCity($value);
                break;
            case 10:
                $this->setPhone($value);
                break;
            case 11:
                $this->setCountryId($value);
                break;
            case 12:
                $this->setCreatedAt($value);
                break;
            case 13:
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
        $keys = OrderAddressPeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setCustomerTitleId($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCompany($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setFirstname($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setLastname($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setAddress1($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setAddress2($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setAddress3($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setZipcode($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setCity($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setPhone($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setCountryId($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setCreatedAt($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setUpdatedAt($arr[$keys[13]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(OrderAddressPeer::DATABASE_NAME);

        if ($this->isColumnModified(OrderAddressPeer::ID)) $criteria->add(OrderAddressPeer::ID, $this->id);
        if ($this->isColumnModified(OrderAddressPeer::CUSTOMER_TITLE_ID)) $criteria->add(OrderAddressPeer::CUSTOMER_TITLE_ID, $this->customer_title_id);
        if ($this->isColumnModified(OrderAddressPeer::COMPANY)) $criteria->add(OrderAddressPeer::COMPANY, $this->company);
        if ($this->isColumnModified(OrderAddressPeer::FIRSTNAME)) $criteria->add(OrderAddressPeer::FIRSTNAME, $this->firstname);
        if ($this->isColumnModified(OrderAddressPeer::LASTNAME)) $criteria->add(OrderAddressPeer::LASTNAME, $this->lastname);
        if ($this->isColumnModified(OrderAddressPeer::ADDRESS1)) $criteria->add(OrderAddressPeer::ADDRESS1, $this->address1);
        if ($this->isColumnModified(OrderAddressPeer::ADDRESS2)) $criteria->add(OrderAddressPeer::ADDRESS2, $this->address2);
        if ($this->isColumnModified(OrderAddressPeer::ADDRESS3)) $criteria->add(OrderAddressPeer::ADDRESS3, $this->address3);
        if ($this->isColumnModified(OrderAddressPeer::ZIPCODE)) $criteria->add(OrderAddressPeer::ZIPCODE, $this->zipcode);
        if ($this->isColumnModified(OrderAddressPeer::CITY)) $criteria->add(OrderAddressPeer::CITY, $this->city);
        if ($this->isColumnModified(OrderAddressPeer::PHONE)) $criteria->add(OrderAddressPeer::PHONE, $this->phone);
        if ($this->isColumnModified(OrderAddressPeer::COUNTRY_ID)) $criteria->add(OrderAddressPeer::COUNTRY_ID, $this->country_id);
        if ($this->isColumnModified(OrderAddressPeer::CREATED_AT)) $criteria->add(OrderAddressPeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(OrderAddressPeer::UPDATED_AT)) $criteria->add(OrderAddressPeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(OrderAddressPeer::DATABASE_NAME);
        $criteria->add(OrderAddressPeer::ID, $this->id);

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
     * @param object $copyObj An object of OrderAddress (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setCustomerTitleId($this->getCustomerTitleId());
        $copyObj->setCompany($this->getCompany());
        $copyObj->setFirstname($this->getFirstname());
        $copyObj->setLastname($this->getLastname());
        $copyObj->setAddress1($this->getAddress1());
        $copyObj->setAddress2($this->getAddress2());
        $copyObj->setAddress3($this->getAddress3());
        $copyObj->setZipcode($this->getZipcode());
        $copyObj->setCity($this->getCity());
        $copyObj->setPhone($this->getPhone());
        $copyObj->setCountryId($this->getCountryId());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            foreach ($this->getOrdersRelatedByAddressInvoice() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderRelatedByAddressInvoice($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrdersRelatedByAddressDelivery() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderRelatedByAddressDelivery($relObj->copy($deepCopy));
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
     * @return OrderAddress Clone of current object.
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
     * @return OrderAddressPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new OrderAddressPeer();
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
        if ('OrderRelatedByAddressInvoice' == $relationName) {
            $this->initOrdersRelatedByAddressInvoice();
        }
        if ('OrderRelatedByAddressDelivery' == $relationName) {
            $this->initOrdersRelatedByAddressDelivery();
        }
    }

    /**
     * Clears out the collOrdersRelatedByAddressInvoice collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrdersRelatedByAddressInvoice()
     */
    public function clearOrdersRelatedByAddressInvoice()
    {
        $this->collOrdersRelatedByAddressInvoice = null; // important to set this to null since that means it is uninitialized
        $this->collOrdersRelatedByAddressInvoicePartial = null;
    }

    /**
     * reset is the collOrdersRelatedByAddressInvoice collection loaded partially
     *
     * @return void
     */
    public function resetPartialOrdersRelatedByAddressInvoice($v = true)
    {
        $this->collOrdersRelatedByAddressInvoicePartial = $v;
    }

    /**
     * Initializes the collOrdersRelatedByAddressInvoice collection.
     *
     * By default this just sets the collOrdersRelatedByAddressInvoice collection to an empty array (like clearcollOrdersRelatedByAddressInvoice());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrdersRelatedByAddressInvoice($overrideExisting = true)
    {
        if (null !== $this->collOrdersRelatedByAddressInvoice && !$overrideExisting) {
            return;
        }
        $this->collOrdersRelatedByAddressInvoice = new PropelObjectCollection();
        $this->collOrdersRelatedByAddressInvoice->setModel('Order');
    }

    /**
     * Gets an array of Order objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this OrderAddress is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Order[] List of Order objects
     * @throws PropelException
     */
    public function getOrdersRelatedByAddressInvoice($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collOrdersRelatedByAddressInvoicePartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByAddressInvoice || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByAddressInvoice) {
                // return empty collection
                $this->initOrdersRelatedByAddressInvoice();
            } else {
                $collOrdersRelatedByAddressInvoice = OrderQuery::create(null, $criteria)
                    ->filterByOrderAddressRelatedByAddressInvoice($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collOrdersRelatedByAddressInvoicePartial && count($collOrdersRelatedByAddressInvoice)) {
                      $this->initOrdersRelatedByAddressInvoice(false);

                      foreach($collOrdersRelatedByAddressInvoice as $obj) {
                        if (false == $this->collOrdersRelatedByAddressInvoice->contains($obj)) {
                          $this->collOrdersRelatedByAddressInvoice->append($obj);
                        }
                      }

                      $this->collOrdersRelatedByAddressInvoicePartial = true;
                    }

                    return $collOrdersRelatedByAddressInvoice;
                }

                if($partial && $this->collOrdersRelatedByAddressInvoice) {
                    foreach($this->collOrdersRelatedByAddressInvoice as $obj) {
                        if($obj->isNew()) {
                            $collOrdersRelatedByAddressInvoice[] = $obj;
                        }
                    }
                }

                $this->collOrdersRelatedByAddressInvoice = $collOrdersRelatedByAddressInvoice;
                $this->collOrdersRelatedByAddressInvoicePartial = false;
            }
        }

        return $this->collOrdersRelatedByAddressInvoice;
    }

    /**
     * Sets a collection of OrderRelatedByAddressInvoice objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $ordersRelatedByAddressInvoice A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setOrdersRelatedByAddressInvoice(PropelCollection $ordersRelatedByAddressInvoice, PropelPDO $con = null)
    {
        $this->ordersRelatedByAddressInvoiceScheduledForDeletion = $this->getOrdersRelatedByAddressInvoice(new Criteria(), $con)->diff($ordersRelatedByAddressInvoice);

        foreach ($this->ordersRelatedByAddressInvoiceScheduledForDeletion as $orderRelatedByAddressInvoiceRemoved) {
            $orderRelatedByAddressInvoiceRemoved->setOrderAddressRelatedByAddressInvoice(null);
        }

        $this->collOrdersRelatedByAddressInvoice = null;
        foreach ($ordersRelatedByAddressInvoice as $orderRelatedByAddressInvoice) {
            $this->addOrderRelatedByAddressInvoice($orderRelatedByAddressInvoice);
        }

        $this->collOrdersRelatedByAddressInvoice = $ordersRelatedByAddressInvoice;
        $this->collOrdersRelatedByAddressInvoicePartial = false;
    }

    /**
     * Returns the number of related Order objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Order objects.
     * @throws PropelException
     */
    public function countOrdersRelatedByAddressInvoice(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collOrdersRelatedByAddressInvoicePartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByAddressInvoice || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByAddressInvoice) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getOrdersRelatedByAddressInvoice());
                }
                $query = OrderQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByOrderAddressRelatedByAddressInvoice($this)
                    ->count($con);
            }
        } else {
            return count($this->collOrdersRelatedByAddressInvoice);
        }
    }

    /**
     * Method called to associate a Order object to this object
     * through the Order foreign key attribute.
     *
     * @param    Order $l Order
     * @return OrderAddress The current object (for fluent API support)
     */
    public function addOrderRelatedByAddressInvoice(Order $l)
    {
        if ($this->collOrdersRelatedByAddressInvoice === null) {
            $this->initOrdersRelatedByAddressInvoice();
            $this->collOrdersRelatedByAddressInvoicePartial = true;
        }
        if (!$this->collOrdersRelatedByAddressInvoice->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddOrderRelatedByAddressInvoice($l);
        }

        return $this;
    }

    /**
     * @param	OrderRelatedByAddressInvoice $orderRelatedByAddressInvoice The orderRelatedByAddressInvoice object to add.
     */
    protected function doAddOrderRelatedByAddressInvoice($orderRelatedByAddressInvoice)
    {
        $this->collOrdersRelatedByAddressInvoice[]= $orderRelatedByAddressInvoice;
        $orderRelatedByAddressInvoice->setOrderAddressRelatedByAddressInvoice($this);
    }

    /**
     * @param	OrderRelatedByAddressInvoice $orderRelatedByAddressInvoice The orderRelatedByAddressInvoice object to remove.
     */
    public function removeOrderRelatedByAddressInvoice($orderRelatedByAddressInvoice)
    {
        if ($this->getOrdersRelatedByAddressInvoice()->contains($orderRelatedByAddressInvoice)) {
            $this->collOrdersRelatedByAddressInvoice->remove($this->collOrdersRelatedByAddressInvoice->search($orderRelatedByAddressInvoice));
            if (null === $this->ordersRelatedByAddressInvoiceScheduledForDeletion) {
                $this->ordersRelatedByAddressInvoiceScheduledForDeletion = clone $this->collOrdersRelatedByAddressInvoice;
                $this->ordersRelatedByAddressInvoiceScheduledForDeletion->clear();
            }
            $this->ordersRelatedByAddressInvoiceScheduledForDeletion[]= $orderRelatedByAddressInvoice;
            $orderRelatedByAddressInvoice->setOrderAddressRelatedByAddressInvoice(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByAddressInvoice from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersRelatedByAddressInvoiceJoinCurrency($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('Currency', $join_behavior);

        return $this->getOrdersRelatedByAddressInvoice($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByAddressInvoice from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersRelatedByAddressInvoiceJoinCustomer($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('Customer', $join_behavior);

        return $this->getOrdersRelatedByAddressInvoice($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByAddressInvoice from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersRelatedByAddressInvoiceJoinOrderStatus($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('OrderStatus', $join_behavior);

        return $this->getOrdersRelatedByAddressInvoice($query, $con);
    }

    /**
     * Clears out the collOrdersRelatedByAddressDelivery collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrdersRelatedByAddressDelivery()
     */
    public function clearOrdersRelatedByAddressDelivery()
    {
        $this->collOrdersRelatedByAddressDelivery = null; // important to set this to null since that means it is uninitialized
        $this->collOrdersRelatedByAddressDeliveryPartial = null;
    }

    /**
     * reset is the collOrdersRelatedByAddressDelivery collection loaded partially
     *
     * @return void
     */
    public function resetPartialOrdersRelatedByAddressDelivery($v = true)
    {
        $this->collOrdersRelatedByAddressDeliveryPartial = $v;
    }

    /**
     * Initializes the collOrdersRelatedByAddressDelivery collection.
     *
     * By default this just sets the collOrdersRelatedByAddressDelivery collection to an empty array (like clearcollOrdersRelatedByAddressDelivery());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrdersRelatedByAddressDelivery($overrideExisting = true)
    {
        if (null !== $this->collOrdersRelatedByAddressDelivery && !$overrideExisting) {
            return;
        }
        $this->collOrdersRelatedByAddressDelivery = new PropelObjectCollection();
        $this->collOrdersRelatedByAddressDelivery->setModel('Order');
    }

    /**
     * Gets an array of Order objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this OrderAddress is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Order[] List of Order objects
     * @throws PropelException
     */
    public function getOrdersRelatedByAddressDelivery($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collOrdersRelatedByAddressDeliveryPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByAddressDelivery || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByAddressDelivery) {
                // return empty collection
                $this->initOrdersRelatedByAddressDelivery();
            } else {
                $collOrdersRelatedByAddressDelivery = OrderQuery::create(null, $criteria)
                    ->filterByOrderAddressRelatedByAddressDelivery($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collOrdersRelatedByAddressDeliveryPartial && count($collOrdersRelatedByAddressDelivery)) {
                      $this->initOrdersRelatedByAddressDelivery(false);

                      foreach($collOrdersRelatedByAddressDelivery as $obj) {
                        if (false == $this->collOrdersRelatedByAddressDelivery->contains($obj)) {
                          $this->collOrdersRelatedByAddressDelivery->append($obj);
                        }
                      }

                      $this->collOrdersRelatedByAddressDeliveryPartial = true;
                    }

                    return $collOrdersRelatedByAddressDelivery;
                }

                if($partial && $this->collOrdersRelatedByAddressDelivery) {
                    foreach($this->collOrdersRelatedByAddressDelivery as $obj) {
                        if($obj->isNew()) {
                            $collOrdersRelatedByAddressDelivery[] = $obj;
                        }
                    }
                }

                $this->collOrdersRelatedByAddressDelivery = $collOrdersRelatedByAddressDelivery;
                $this->collOrdersRelatedByAddressDeliveryPartial = false;
            }
        }

        return $this->collOrdersRelatedByAddressDelivery;
    }

    /**
     * Sets a collection of OrderRelatedByAddressDelivery objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $ordersRelatedByAddressDelivery A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setOrdersRelatedByAddressDelivery(PropelCollection $ordersRelatedByAddressDelivery, PropelPDO $con = null)
    {
        $this->ordersRelatedByAddressDeliveryScheduledForDeletion = $this->getOrdersRelatedByAddressDelivery(new Criteria(), $con)->diff($ordersRelatedByAddressDelivery);

        foreach ($this->ordersRelatedByAddressDeliveryScheduledForDeletion as $orderRelatedByAddressDeliveryRemoved) {
            $orderRelatedByAddressDeliveryRemoved->setOrderAddressRelatedByAddressDelivery(null);
        }

        $this->collOrdersRelatedByAddressDelivery = null;
        foreach ($ordersRelatedByAddressDelivery as $orderRelatedByAddressDelivery) {
            $this->addOrderRelatedByAddressDelivery($orderRelatedByAddressDelivery);
        }

        $this->collOrdersRelatedByAddressDelivery = $ordersRelatedByAddressDelivery;
        $this->collOrdersRelatedByAddressDeliveryPartial = false;
    }

    /**
     * Returns the number of related Order objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Order objects.
     * @throws PropelException
     */
    public function countOrdersRelatedByAddressDelivery(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collOrdersRelatedByAddressDeliveryPartial && !$this->isNew();
        if (null === $this->collOrdersRelatedByAddressDelivery || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrdersRelatedByAddressDelivery) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getOrdersRelatedByAddressDelivery());
                }
                $query = OrderQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByOrderAddressRelatedByAddressDelivery($this)
                    ->count($con);
            }
        } else {
            return count($this->collOrdersRelatedByAddressDelivery);
        }
    }

    /**
     * Method called to associate a Order object to this object
     * through the Order foreign key attribute.
     *
     * @param    Order $l Order
     * @return OrderAddress The current object (for fluent API support)
     */
    public function addOrderRelatedByAddressDelivery(Order $l)
    {
        if ($this->collOrdersRelatedByAddressDelivery === null) {
            $this->initOrdersRelatedByAddressDelivery();
            $this->collOrdersRelatedByAddressDeliveryPartial = true;
        }
        if (!$this->collOrdersRelatedByAddressDelivery->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddOrderRelatedByAddressDelivery($l);
        }

        return $this;
    }

    /**
     * @param	OrderRelatedByAddressDelivery $orderRelatedByAddressDelivery The orderRelatedByAddressDelivery object to add.
     */
    protected function doAddOrderRelatedByAddressDelivery($orderRelatedByAddressDelivery)
    {
        $this->collOrdersRelatedByAddressDelivery[]= $orderRelatedByAddressDelivery;
        $orderRelatedByAddressDelivery->setOrderAddressRelatedByAddressDelivery($this);
    }

    /**
     * @param	OrderRelatedByAddressDelivery $orderRelatedByAddressDelivery The orderRelatedByAddressDelivery object to remove.
     */
    public function removeOrderRelatedByAddressDelivery($orderRelatedByAddressDelivery)
    {
        if ($this->getOrdersRelatedByAddressDelivery()->contains($orderRelatedByAddressDelivery)) {
            $this->collOrdersRelatedByAddressDelivery->remove($this->collOrdersRelatedByAddressDelivery->search($orderRelatedByAddressDelivery));
            if (null === $this->ordersRelatedByAddressDeliveryScheduledForDeletion) {
                $this->ordersRelatedByAddressDeliveryScheduledForDeletion = clone $this->collOrdersRelatedByAddressDelivery;
                $this->ordersRelatedByAddressDeliveryScheduledForDeletion->clear();
            }
            $this->ordersRelatedByAddressDeliveryScheduledForDeletion[]= $orderRelatedByAddressDelivery;
            $orderRelatedByAddressDelivery->setOrderAddressRelatedByAddressDelivery(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByAddressDelivery from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersRelatedByAddressDeliveryJoinCurrency($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('Currency', $join_behavior);

        return $this->getOrdersRelatedByAddressDelivery($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByAddressDelivery from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersRelatedByAddressDeliveryJoinCustomer($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('Customer', $join_behavior);

        return $this->getOrdersRelatedByAddressDelivery($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this OrderAddress is new, it will return
     * an empty collection; or if this OrderAddress has previously
     * been saved, it will retrieve related OrdersRelatedByAddressDelivery from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in OrderAddress.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersRelatedByAddressDeliveryJoinOrderStatus($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('OrderStatus', $join_behavior);

        return $this->getOrdersRelatedByAddressDelivery($query, $con);
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->customer_title_id = null;
        $this->company = null;
        $this->firstname = null;
        $this->lastname = null;
        $this->address1 = null;
        $this->address2 = null;
        $this->address3 = null;
        $this->zipcode = null;
        $this->city = null;
        $this->phone = null;
        $this->country_id = null;
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
            if ($this->collOrdersRelatedByAddressInvoice) {
                foreach ($this->collOrdersRelatedByAddressInvoice as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrdersRelatedByAddressDelivery) {
                foreach ($this->collOrdersRelatedByAddressDelivery as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        if ($this->collOrdersRelatedByAddressInvoice instanceof PropelCollection) {
            $this->collOrdersRelatedByAddressInvoice->clearIterator();
        }
        $this->collOrdersRelatedByAddressInvoice = null;
        if ($this->collOrdersRelatedByAddressDelivery instanceof PropelCollection) {
            $this->collOrdersRelatedByAddressDelivery->clearIterator();
        }
        $this->collOrdersRelatedByAddressDelivery = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(OrderAddressPeer::DEFAULT_STRING_FORMAT);
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
     * @return     OrderAddress The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = OrderAddressPeer::UPDATED_AT;

        return $this;
    }

}
