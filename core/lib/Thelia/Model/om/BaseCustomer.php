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
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPeer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

/**
 * Base class that represents a row from the 'customer' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseCustomer extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\CustomerPeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        CustomerPeer
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
     * The value for the country_id field.
     * @var        int
     */
    protected $country_id;

    /**
     * The value for the phone field.
     * @var        string
     */
    protected $phone;

    /**
     * The value for the cellphone field.
     * @var        string
     */
    protected $cellphone;

    /**
     * The value for the email field.
     * @var        string
     */
    protected $email;

    /**
     * The value for the password field.
     * @var        string
     */
    protected $password;

    /**
     * The value for the algo field.
     * @var        string
     */
    protected $algo;

    /**
     * The value for the salt field.
     * @var        string
     */
    protected $salt;

    /**
     * The value for the reseller field.
     * @var        int
     */
    protected $reseller;

    /**
     * The value for the lang field.
     * @var        string
     */
    protected $lang;

    /**
     * The value for the sponsor field.
     * @var        string
     */
    protected $sponsor;

    /**
     * The value for the discount field.
     * @var        double
     */
    protected $discount;

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
     * @var        CustomerTitle
     */
    protected $aCustomerTitle;

    /**
     * @var        PropelObjectCollection|Address[] Collection to store aggregation of Address objects.
     */
    protected $collAddresss;
    protected $collAddresssPartial;

    /**
     * @var        PropelObjectCollection|Order[] Collection to store aggregation of Order objects.
     */
    protected $collOrders;
    protected $collOrdersPartial;

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
    protected $addresssScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $ordersScheduledForDeletion = null;

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
     * Get the [country_id] column value.
     *
     * @return int
     */
    public function getCountryId()
    {
        return $this->country_id;
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
     * Get the [cellphone] column value.
     *
     * @return string
     */
    public function getCellphone()
    {
        return $this->cellphone;
    }

    /**
     * Get the [email] column value.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Get the [password] column value.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Get the [algo] column value.
     *
     * @return string
     */
    public function getAlgo()
    {
        return $this->algo;
    }

    /**
     * Get the [salt] column value.
     *
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Get the [reseller] column value.
     *
     * @return int
     */
    public function getReseller()
    {
        return $this->reseller;
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
     * Get the [sponsor] column value.
     *
     * @return string
     */
    public function getSponsor()
    {
        return $this->sponsor;
    }

    /**
     * Get the [discount] column value.
     *
     * @return double
     */
    public function getDiscount()
    {
        return $this->discount;
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
     * @return Customer The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = CustomerPeer::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [ref] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->ref !== $v) {
            $this->ref = $v;
            $this->modifiedColumns[] = CustomerPeer::REF;
        }


        return $this;
    } // setRef()

    /**
     * Set the value of [customer_title_id] column.
     *
     * @param int $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setCustomerTitleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->customer_title_id !== $v) {
            $this->customer_title_id = $v;
            $this->modifiedColumns[] = CustomerPeer::CUSTOMER_TITLE_ID;
        }

        if ($this->aCustomerTitle !== null && $this->aCustomerTitle->getId() !== $v) {
            $this->aCustomerTitle = null;
        }


        return $this;
    } // setCustomerTitleId()

    /**
     * Set the value of [company] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setCompany($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->company !== $v) {
            $this->company = $v;
            $this->modifiedColumns[] = CustomerPeer::COMPANY;
        }


        return $this;
    } // setCompany()

    /**
     * Set the value of [firstname] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setFirstname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->firstname !== $v) {
            $this->firstname = $v;
            $this->modifiedColumns[] = CustomerPeer::FIRSTNAME;
        }


        return $this;
    } // setFirstname()

    /**
     * Set the value of [lastname] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setLastname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lastname !== $v) {
            $this->lastname = $v;
            $this->modifiedColumns[] = CustomerPeer::LASTNAME;
        }


        return $this;
    } // setLastname()

    /**
     * Set the value of [address1] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setAddress1($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address1 !== $v) {
            $this->address1 = $v;
            $this->modifiedColumns[] = CustomerPeer::ADDRESS1;
        }


        return $this;
    } // setAddress1()

    /**
     * Set the value of [address2] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setAddress2($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address2 !== $v) {
            $this->address2 = $v;
            $this->modifiedColumns[] = CustomerPeer::ADDRESS2;
        }


        return $this;
    } // setAddress2()

    /**
     * Set the value of [address3] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setAddress3($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->address3 !== $v) {
            $this->address3 = $v;
            $this->modifiedColumns[] = CustomerPeer::ADDRESS3;
        }


        return $this;
    } // setAddress3()

    /**
     * Set the value of [zipcode] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setZipcode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->zipcode !== $v) {
            $this->zipcode = $v;
            $this->modifiedColumns[] = CustomerPeer::ZIPCODE;
        }


        return $this;
    } // setZipcode()

    /**
     * Set the value of [city] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setCity($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->city !== $v) {
            $this->city = $v;
            $this->modifiedColumns[] = CustomerPeer::CITY;
        }


        return $this;
    } // setCity()

    /**
     * Set the value of [country_id] column.
     *
     * @param int $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setCountryId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->country_id !== $v) {
            $this->country_id = $v;
            $this->modifiedColumns[] = CustomerPeer::COUNTRY_ID;
        }


        return $this;
    } // setCountryId()

    /**
     * Set the value of [phone] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setPhone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->phone !== $v) {
            $this->phone = $v;
            $this->modifiedColumns[] = CustomerPeer::PHONE;
        }


        return $this;
    } // setPhone()

    /**
     * Set the value of [cellphone] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setCellphone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cellphone !== $v) {
            $this->cellphone = $v;
            $this->modifiedColumns[] = CustomerPeer::CELLPHONE;
        }


        return $this;
    } // setCellphone()

    /**
     * Set the value of [email] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setEmail($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->email !== $v) {
            $this->email = $v;
            $this->modifiedColumns[] = CustomerPeer::EMAIL;
        }


        return $this;
    } // setEmail()

    /**
     * Set the value of [password] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setPassword($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->password !== $v) {
            $this->password = $v;
            $this->modifiedColumns[] = CustomerPeer::PASSWORD;
        }


        return $this;
    } // setPassword()

    /**
     * Set the value of [algo] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setAlgo($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->algo !== $v) {
            $this->algo = $v;
            $this->modifiedColumns[] = CustomerPeer::ALGO;
        }


        return $this;
    } // setAlgo()

    /**
     * Set the value of [salt] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setSalt($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->salt !== $v) {
            $this->salt = $v;
            $this->modifiedColumns[] = CustomerPeer::SALT;
        }


        return $this;
    } // setSalt()

    /**
     * Set the value of [reseller] column.
     *
     * @param int $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setReseller($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->reseller !== $v) {
            $this->reseller = $v;
            $this->modifiedColumns[] = CustomerPeer::RESELLER;
        }


        return $this;
    } // setReseller()

    /**
     * Set the value of [lang] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setLang($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lang !== $v) {
            $this->lang = $v;
            $this->modifiedColumns[] = CustomerPeer::LANG;
        }


        return $this;
    } // setLang()

    /**
     * Set the value of [sponsor] column.
     *
     * @param string $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setSponsor($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->sponsor !== $v) {
            $this->sponsor = $v;
            $this->modifiedColumns[] = CustomerPeer::SPONSOR;
        }


        return $this;
    } // setSponsor()

    /**
     * Set the value of [discount] column.
     *
     * @param double $v new value
     * @return Customer The current object (for fluent API support)
     */
    public function setDiscount($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->discount !== $v) {
            $this->discount = $v;
            $this->modifiedColumns[] = CustomerPeer::DISCOUNT;
        }


        return $this;
    } // setDiscount()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Customer The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = CustomerPeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Customer The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = CustomerPeer::UPDATED_AT;
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
            $this->customer_title_id = ($row[$startcol + 2] !== null) ? (int) $row[$startcol + 2] : null;
            $this->company = ($row[$startcol + 3] !== null) ? (string) $row[$startcol + 3] : null;
            $this->firstname = ($row[$startcol + 4] !== null) ? (string) $row[$startcol + 4] : null;
            $this->lastname = ($row[$startcol + 5] !== null) ? (string) $row[$startcol + 5] : null;
            $this->address1 = ($row[$startcol + 6] !== null) ? (string) $row[$startcol + 6] : null;
            $this->address2 = ($row[$startcol + 7] !== null) ? (string) $row[$startcol + 7] : null;
            $this->address3 = ($row[$startcol + 8] !== null) ? (string) $row[$startcol + 8] : null;
            $this->zipcode = ($row[$startcol + 9] !== null) ? (string) $row[$startcol + 9] : null;
            $this->city = ($row[$startcol + 10] !== null) ? (string) $row[$startcol + 10] : null;
            $this->country_id = ($row[$startcol + 11] !== null) ? (int) $row[$startcol + 11] : null;
            $this->phone = ($row[$startcol + 12] !== null) ? (string) $row[$startcol + 12] : null;
            $this->cellphone = ($row[$startcol + 13] !== null) ? (string) $row[$startcol + 13] : null;
            $this->email = ($row[$startcol + 14] !== null) ? (string) $row[$startcol + 14] : null;
            $this->password = ($row[$startcol + 15] !== null) ? (string) $row[$startcol + 15] : null;
            $this->algo = ($row[$startcol + 16] !== null) ? (string) $row[$startcol + 16] : null;
            $this->salt = ($row[$startcol + 17] !== null) ? (string) $row[$startcol + 17] : null;
            $this->reseller = ($row[$startcol + 18] !== null) ? (int) $row[$startcol + 18] : null;
            $this->lang = ($row[$startcol + 19] !== null) ? (string) $row[$startcol + 19] : null;
            $this->sponsor = ($row[$startcol + 20] !== null) ? (string) $row[$startcol + 20] : null;
            $this->discount = ($row[$startcol + 21] !== null) ? (double) $row[$startcol + 21] : null;
            $this->created_at = ($row[$startcol + 22] !== null) ? (string) $row[$startcol + 22] : null;
            $this->updated_at = ($row[$startcol + 23] !== null) ? (string) $row[$startcol + 23] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 24; // 24 = CustomerPeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Customer object", $e);
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

        if ($this->aCustomerTitle !== null && $this->customer_title_id !== $this->aCustomerTitle->getId()) {
            $this->aCustomerTitle = null;
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
            $con = Propel::getConnection(CustomerPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = CustomerPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aCustomerTitle = null;
            $this->collAddresss = null;

            $this->collOrders = null;

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
            $con = Propel::getConnection(CustomerPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = CustomerQuery::create()
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
            $con = Propel::getConnection(CustomerPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
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
                CustomerPeer::addInstanceToPool($this);
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

            if ($this->aCustomerTitle !== null) {
                if ($this->aCustomerTitle->isModified() || $this->aCustomerTitle->isNew()) {
                    $affectedRows += $this->aCustomerTitle->save($con);
                }
                $this->setCustomerTitle($this->aCustomerTitle);
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

            if ($this->addresssScheduledForDeletion !== null) {
                if (!$this->addresssScheduledForDeletion->isEmpty()) {
                    AddressQuery::create()
                        ->filterByPrimaryKeys($this->addresssScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->addresssScheduledForDeletion = null;
                }
            }

            if ($this->collAddresss !== null) {
                foreach ($this->collAddresss as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->ordersScheduledForDeletion !== null) {
                if (!$this->ordersScheduledForDeletion->isEmpty()) {
                    OrderQuery::create()
                        ->filterByPrimaryKeys($this->ordersScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->ordersScheduledForDeletion = null;
                }
            }

            if ($this->collOrders !== null) {
                foreach ($this->collOrders as $referrerFK) {
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

        $this->modifiedColumns[] = CustomerPeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . CustomerPeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(CustomerPeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(CustomerPeer::REF)) {
            $modifiedColumns[':p' . $index++]  = '`REF`';
        }
        if ($this->isColumnModified(CustomerPeer::CUSTOMER_TITLE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`CUSTOMER_TITLE_ID`';
        }
        if ($this->isColumnModified(CustomerPeer::COMPANY)) {
            $modifiedColumns[':p' . $index++]  = '`COMPANY`';
        }
        if ($this->isColumnModified(CustomerPeer::FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`FIRSTNAME`';
        }
        if ($this->isColumnModified(CustomerPeer::LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = '`LASTNAME`';
        }
        if ($this->isColumnModified(CustomerPeer::ADDRESS1)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS1`';
        }
        if ($this->isColumnModified(CustomerPeer::ADDRESS2)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS2`';
        }
        if ($this->isColumnModified(CustomerPeer::ADDRESS3)) {
            $modifiedColumns[':p' . $index++]  = '`ADDRESS3`';
        }
        if ($this->isColumnModified(CustomerPeer::ZIPCODE)) {
            $modifiedColumns[':p' . $index++]  = '`ZIPCODE`';
        }
        if ($this->isColumnModified(CustomerPeer::CITY)) {
            $modifiedColumns[':p' . $index++]  = '`CITY`';
        }
        if ($this->isColumnModified(CustomerPeer::COUNTRY_ID)) {
            $modifiedColumns[':p' . $index++]  = '`COUNTRY_ID`';
        }
        if ($this->isColumnModified(CustomerPeer::PHONE)) {
            $modifiedColumns[':p' . $index++]  = '`PHONE`';
        }
        if ($this->isColumnModified(CustomerPeer::CELLPHONE)) {
            $modifiedColumns[':p' . $index++]  = '`CELLPHONE`';
        }
        if ($this->isColumnModified(CustomerPeer::EMAIL)) {
            $modifiedColumns[':p' . $index++]  = '`EMAIL`';
        }
        if ($this->isColumnModified(CustomerPeer::PASSWORD)) {
            $modifiedColumns[':p' . $index++]  = '`PASSWORD`';
        }
        if ($this->isColumnModified(CustomerPeer::ALGO)) {
            $modifiedColumns[':p' . $index++]  = '`ALGO`';
        }
        if ($this->isColumnModified(CustomerPeer::SALT)) {
            $modifiedColumns[':p' . $index++]  = '`SALT`';
        }
        if ($this->isColumnModified(CustomerPeer::RESELLER)) {
            $modifiedColumns[':p' . $index++]  = '`RESELLER`';
        }
        if ($this->isColumnModified(CustomerPeer::LANG)) {
            $modifiedColumns[':p' . $index++]  = '`LANG`';
        }
        if ($this->isColumnModified(CustomerPeer::SPONSOR)) {
            $modifiedColumns[':p' . $index++]  = '`SPONSOR`';
        }
        if ($this->isColumnModified(CustomerPeer::DISCOUNT)) {
            $modifiedColumns[':p' . $index++]  = '`DISCOUNT`';
        }
        if ($this->isColumnModified(CustomerPeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(CustomerPeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `customer` (%s) VALUES (%s)',
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
                    case '`COUNTRY_ID`':
                        $stmt->bindValue($identifier, $this->country_id, PDO::PARAM_INT);
                        break;
                    case '`PHONE`':
                        $stmt->bindValue($identifier, $this->phone, PDO::PARAM_STR);
                        break;
                    case '`CELLPHONE`':
                        $stmt->bindValue($identifier, $this->cellphone, PDO::PARAM_STR);
                        break;
                    case '`EMAIL`':
                        $stmt->bindValue($identifier, $this->email, PDO::PARAM_STR);
                        break;
                    case '`PASSWORD`':
                        $stmt->bindValue($identifier, $this->password, PDO::PARAM_STR);
                        break;
                    case '`ALGO`':
                        $stmt->bindValue($identifier, $this->algo, PDO::PARAM_STR);
                        break;
                    case '`SALT`':
                        $stmt->bindValue($identifier, $this->salt, PDO::PARAM_STR);
                        break;
                    case '`RESELLER`':
                        $stmt->bindValue($identifier, $this->reseller, PDO::PARAM_INT);
                        break;
                    case '`LANG`':
                        $stmt->bindValue($identifier, $this->lang, PDO::PARAM_STR);
                        break;
                    case '`SPONSOR`':
                        $stmt->bindValue($identifier, $this->sponsor, PDO::PARAM_STR);
                        break;
                    case '`DISCOUNT`':
                        $stmt->bindValue($identifier, $this->discount, PDO::PARAM_STR);
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

            if ($this->aCustomerTitle !== null) {
                if (!$this->aCustomerTitle->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aCustomerTitle->getValidationFailures());
                }
            }


            if (($retval = CustomerPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collAddresss !== null) {
                    foreach ($this->collAddresss as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collOrders !== null) {
                    foreach ($this->collOrders as $referrerFK) {
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
        $pos = CustomerPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
                return $this->getCustomerTitleId();
                break;
            case 3:
                return $this->getCompany();
                break;
            case 4:
                return $this->getFirstname();
                break;
            case 5:
                return $this->getLastname();
                break;
            case 6:
                return $this->getAddress1();
                break;
            case 7:
                return $this->getAddress2();
                break;
            case 8:
                return $this->getAddress3();
                break;
            case 9:
                return $this->getZipcode();
                break;
            case 10:
                return $this->getCity();
                break;
            case 11:
                return $this->getCountryId();
                break;
            case 12:
                return $this->getPhone();
                break;
            case 13:
                return $this->getCellphone();
                break;
            case 14:
                return $this->getEmail();
                break;
            case 15:
                return $this->getPassword();
                break;
            case 16:
                return $this->getAlgo();
                break;
            case 17:
                return $this->getSalt();
                break;
            case 18:
                return $this->getReseller();
                break;
            case 19:
                return $this->getLang();
                break;
            case 20:
                return $this->getSponsor();
                break;
            case 21:
                return $this->getDiscount();
                break;
            case 22:
                return $this->getCreatedAt();
                break;
            case 23:
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
        if (isset($alreadyDumpedObjects['Customer'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Customer'][$this->getPrimaryKey()] = true;
        $keys = CustomerPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getRef(),
            $keys[2] => $this->getCustomerTitleId(),
            $keys[3] => $this->getCompany(),
            $keys[4] => $this->getFirstname(),
            $keys[5] => $this->getLastname(),
            $keys[6] => $this->getAddress1(),
            $keys[7] => $this->getAddress2(),
            $keys[8] => $this->getAddress3(),
            $keys[9] => $this->getZipcode(),
            $keys[10] => $this->getCity(),
            $keys[11] => $this->getCountryId(),
            $keys[12] => $this->getPhone(),
            $keys[13] => $this->getCellphone(),
            $keys[14] => $this->getEmail(),
            $keys[15] => $this->getPassword(),
            $keys[16] => $this->getAlgo(),
            $keys[17] => $this->getSalt(),
            $keys[18] => $this->getReseller(),
            $keys[19] => $this->getLang(),
            $keys[20] => $this->getSponsor(),
            $keys[21] => $this->getDiscount(),
            $keys[22] => $this->getCreatedAt(),
            $keys[23] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->aCustomerTitle) {
                $result['CustomerTitle'] = $this->aCustomerTitle->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collAddresss) {
                $result['Addresss'] = $this->collAddresss->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collOrders) {
                $result['Orders'] = $this->collOrders->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = CustomerPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
                $this->setCustomerTitleId($value);
                break;
            case 3:
                $this->setCompany($value);
                break;
            case 4:
                $this->setFirstname($value);
                break;
            case 5:
                $this->setLastname($value);
                break;
            case 6:
                $this->setAddress1($value);
                break;
            case 7:
                $this->setAddress2($value);
                break;
            case 8:
                $this->setAddress3($value);
                break;
            case 9:
                $this->setZipcode($value);
                break;
            case 10:
                $this->setCity($value);
                break;
            case 11:
                $this->setCountryId($value);
                break;
            case 12:
                $this->setPhone($value);
                break;
            case 13:
                $this->setCellphone($value);
                break;
            case 14:
                $this->setEmail($value);
                break;
            case 15:
                $this->setPassword($value);
                break;
            case 16:
                $this->setAlgo($value);
                break;
            case 17:
                $this->setSalt($value);
                break;
            case 18:
                $this->setReseller($value);
                break;
            case 19:
                $this->setLang($value);
                break;
            case 20:
                $this->setSponsor($value);
                break;
            case 21:
                $this->setDiscount($value);
                break;
            case 22:
                $this->setCreatedAt($value);
                break;
            case 23:
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
        $keys = CustomerPeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setRef($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCustomerTitleId($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setCompany($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setFirstname($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setLastname($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setAddress1($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setAddress2($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setAddress3($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setZipcode($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setCity($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setCountryId($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setPhone($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setCellphone($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setEmail($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setPassword($arr[$keys[15]]);
        if (array_key_exists($keys[16], $arr)) $this->setAlgo($arr[$keys[16]]);
        if (array_key_exists($keys[17], $arr)) $this->setSalt($arr[$keys[17]]);
        if (array_key_exists($keys[18], $arr)) $this->setReseller($arr[$keys[18]]);
        if (array_key_exists($keys[19], $arr)) $this->setLang($arr[$keys[19]]);
        if (array_key_exists($keys[20], $arr)) $this->setSponsor($arr[$keys[20]]);
        if (array_key_exists($keys[21], $arr)) $this->setDiscount($arr[$keys[21]]);
        if (array_key_exists($keys[22], $arr)) $this->setCreatedAt($arr[$keys[22]]);
        if (array_key_exists($keys[23], $arr)) $this->setUpdatedAt($arr[$keys[23]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(CustomerPeer::DATABASE_NAME);

        if ($this->isColumnModified(CustomerPeer::ID)) $criteria->add(CustomerPeer::ID, $this->id);
        if ($this->isColumnModified(CustomerPeer::REF)) $criteria->add(CustomerPeer::REF, $this->ref);
        if ($this->isColumnModified(CustomerPeer::CUSTOMER_TITLE_ID)) $criteria->add(CustomerPeer::CUSTOMER_TITLE_ID, $this->customer_title_id);
        if ($this->isColumnModified(CustomerPeer::COMPANY)) $criteria->add(CustomerPeer::COMPANY, $this->company);
        if ($this->isColumnModified(CustomerPeer::FIRSTNAME)) $criteria->add(CustomerPeer::FIRSTNAME, $this->firstname);
        if ($this->isColumnModified(CustomerPeer::LASTNAME)) $criteria->add(CustomerPeer::LASTNAME, $this->lastname);
        if ($this->isColumnModified(CustomerPeer::ADDRESS1)) $criteria->add(CustomerPeer::ADDRESS1, $this->address1);
        if ($this->isColumnModified(CustomerPeer::ADDRESS2)) $criteria->add(CustomerPeer::ADDRESS2, $this->address2);
        if ($this->isColumnModified(CustomerPeer::ADDRESS3)) $criteria->add(CustomerPeer::ADDRESS3, $this->address3);
        if ($this->isColumnModified(CustomerPeer::ZIPCODE)) $criteria->add(CustomerPeer::ZIPCODE, $this->zipcode);
        if ($this->isColumnModified(CustomerPeer::CITY)) $criteria->add(CustomerPeer::CITY, $this->city);
        if ($this->isColumnModified(CustomerPeer::COUNTRY_ID)) $criteria->add(CustomerPeer::COUNTRY_ID, $this->country_id);
        if ($this->isColumnModified(CustomerPeer::PHONE)) $criteria->add(CustomerPeer::PHONE, $this->phone);
        if ($this->isColumnModified(CustomerPeer::CELLPHONE)) $criteria->add(CustomerPeer::CELLPHONE, $this->cellphone);
        if ($this->isColumnModified(CustomerPeer::EMAIL)) $criteria->add(CustomerPeer::EMAIL, $this->email);
        if ($this->isColumnModified(CustomerPeer::PASSWORD)) $criteria->add(CustomerPeer::PASSWORD, $this->password);
        if ($this->isColumnModified(CustomerPeer::ALGO)) $criteria->add(CustomerPeer::ALGO, $this->algo);
        if ($this->isColumnModified(CustomerPeer::SALT)) $criteria->add(CustomerPeer::SALT, $this->salt);
        if ($this->isColumnModified(CustomerPeer::RESELLER)) $criteria->add(CustomerPeer::RESELLER, $this->reseller);
        if ($this->isColumnModified(CustomerPeer::LANG)) $criteria->add(CustomerPeer::LANG, $this->lang);
        if ($this->isColumnModified(CustomerPeer::SPONSOR)) $criteria->add(CustomerPeer::SPONSOR, $this->sponsor);
        if ($this->isColumnModified(CustomerPeer::DISCOUNT)) $criteria->add(CustomerPeer::DISCOUNT, $this->discount);
        if ($this->isColumnModified(CustomerPeer::CREATED_AT)) $criteria->add(CustomerPeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(CustomerPeer::UPDATED_AT)) $criteria->add(CustomerPeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(CustomerPeer::DATABASE_NAME);
        $criteria->add(CustomerPeer::ID, $this->id);

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
     * @param object $copyObj An object of Customer (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setRef($this->getRef());
        $copyObj->setCustomerTitleId($this->getCustomerTitleId());
        $copyObj->setCompany($this->getCompany());
        $copyObj->setFirstname($this->getFirstname());
        $copyObj->setLastname($this->getLastname());
        $copyObj->setAddress1($this->getAddress1());
        $copyObj->setAddress2($this->getAddress2());
        $copyObj->setAddress3($this->getAddress3());
        $copyObj->setZipcode($this->getZipcode());
        $copyObj->setCity($this->getCity());
        $copyObj->setCountryId($this->getCountryId());
        $copyObj->setPhone($this->getPhone());
        $copyObj->setCellphone($this->getCellphone());
        $copyObj->setEmail($this->getEmail());
        $copyObj->setPassword($this->getPassword());
        $copyObj->setAlgo($this->getAlgo());
        $copyObj->setSalt($this->getSalt());
        $copyObj->setReseller($this->getReseller());
        $copyObj->setLang($this->getLang());
        $copyObj->setSponsor($this->getSponsor());
        $copyObj->setDiscount($this->getDiscount());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            foreach ($this->getAddresss() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAddress($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getOrders() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrder($relObj->copy($deepCopy));
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
     * @return Customer Clone of current object.
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
     * @return CustomerPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new CustomerPeer();
        }

        return self::$peer;
    }

    /**
     * Declares an association between this object and a CustomerTitle object.
     *
     * @param             CustomerTitle $v
     * @return Customer The current object (for fluent API support)
     * @throws PropelException
     */
    public function setCustomerTitle(CustomerTitle $v = null)
    {
        if ($v === null) {
            $this->setCustomerTitleId(NULL);
        } else {
            $this->setCustomerTitleId($v->getId());
        }

        $this->aCustomerTitle = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the CustomerTitle object, it will not be re-added.
        if ($v !== null) {
            $v->addCustomer($this);
        }


        return $this;
    }


    /**
     * Get the associated CustomerTitle object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return CustomerTitle The associated CustomerTitle object.
     * @throws PropelException
     */
    public function getCustomerTitle(PropelPDO $con = null)
    {
        if ($this->aCustomerTitle === null && ($this->customer_title_id !== null)) {
            $this->aCustomerTitle = CustomerTitleQuery::create()->findPk($this->customer_title_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aCustomerTitle->addCustomers($this);
             */
        }

        return $this->aCustomerTitle;
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
        if ('Address' == $relationName) {
            $this->initAddresss();
        }
        if ('Order' == $relationName) {
            $this->initOrders();
        }
    }

    /**
     * Clears out the collAddresss collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAddresss()
     */
    public function clearAddresss()
    {
        $this->collAddresss = null; // important to set this to null since that means it is uninitialized
        $this->collAddresssPartial = null;
    }

    /**
     * reset is the collAddresss collection loaded partially
     *
     * @return void
     */
    public function resetPartialAddresss($v = true)
    {
        $this->collAddresssPartial = $v;
    }

    /**
     * Initializes the collAddresss collection.
     *
     * By default this just sets the collAddresss collection to an empty array (like clearcollAddresss());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAddresss($overrideExisting = true)
    {
        if (null !== $this->collAddresss && !$overrideExisting) {
            return;
        }
        $this->collAddresss = new PropelObjectCollection();
        $this->collAddresss->setModel('Address');
    }

    /**
     * Gets an array of Address objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Customer is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Address[] List of Address objects
     * @throws PropelException
     */
    public function getAddresss($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collAddresssPartial && !$this->isNew();
        if (null === $this->collAddresss || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAddresss) {
                // return empty collection
                $this->initAddresss();
            } else {
                $collAddresss = AddressQuery::create(null, $criteria)
                    ->filterByCustomer($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collAddresssPartial && count($collAddresss)) {
                      $this->initAddresss(false);

                      foreach($collAddresss as $obj) {
                        if (false == $this->collAddresss->contains($obj)) {
                          $this->collAddresss->append($obj);
                        }
                      }

                      $this->collAddresssPartial = true;
                    }

                    return $collAddresss;
                }

                if($partial && $this->collAddresss) {
                    foreach($this->collAddresss as $obj) {
                        if($obj->isNew()) {
                            $collAddresss[] = $obj;
                        }
                    }
                }

                $this->collAddresss = $collAddresss;
                $this->collAddresssPartial = false;
            }
        }

        return $this->collAddresss;
    }

    /**
     * Sets a collection of Address objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $addresss A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setAddresss(PropelCollection $addresss, PropelPDO $con = null)
    {
        $this->addresssScheduledForDeletion = $this->getAddresss(new Criteria(), $con)->diff($addresss);

        foreach ($this->addresssScheduledForDeletion as $addressRemoved) {
            $addressRemoved->setCustomer(null);
        }

        $this->collAddresss = null;
        foreach ($addresss as $address) {
            $this->addAddress($address);
        }

        $this->collAddresss = $addresss;
        $this->collAddresssPartial = false;
    }

    /**
     * Returns the number of related Address objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Address objects.
     * @throws PropelException
     */
    public function countAddresss(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collAddresssPartial && !$this->isNew();
        if (null === $this->collAddresss || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAddresss) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getAddresss());
                }
                $query = AddressQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCustomer($this)
                    ->count($con);
            }
        } else {
            return count($this->collAddresss);
        }
    }

    /**
     * Method called to associate a Address object to this object
     * through the Address foreign key attribute.
     *
     * @param    Address $l Address
     * @return Customer The current object (for fluent API support)
     */
    public function addAddress(Address $l)
    {
        if ($this->collAddresss === null) {
            $this->initAddresss();
            $this->collAddresssPartial = true;
        }
        if (!$this->collAddresss->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddAddress($l);
        }

        return $this;
    }

    /**
     * @param	Address $address The address object to add.
     */
    protected function doAddAddress($address)
    {
        $this->collAddresss[]= $address;
        $address->setCustomer($this);
    }

    /**
     * @param	Address $address The address object to remove.
     */
    public function removeAddress($address)
    {
        if ($this->getAddresss()->contains($address)) {
            $this->collAddresss->remove($this->collAddresss->search($address));
            if (null === $this->addresssScheduledForDeletion) {
                $this->addresssScheduledForDeletion = clone $this->collAddresss;
                $this->addresssScheduledForDeletion->clear();
            }
            $this->addresssScheduledForDeletion[]= $address;
            $address->setCustomer(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Addresss from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Address[] List of Address objects
     */
    public function getAddresssJoinCustomerTitle($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = AddressQuery::create(null, $criteria);
        $query->joinWith('CustomerTitle', $join_behavior);

        return $this->getAddresss($query, $con);
    }

    /**
     * Clears out the collOrders collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrders()
     */
    public function clearOrders()
    {
        $this->collOrders = null; // important to set this to null since that means it is uninitialized
        $this->collOrdersPartial = null;
    }

    /**
     * reset is the collOrders collection loaded partially
     *
     * @return void
     */
    public function resetPartialOrders($v = true)
    {
        $this->collOrdersPartial = $v;
    }

    /**
     * Initializes the collOrders collection.
     *
     * By default this just sets the collOrders collection to an empty array (like clearcollOrders());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrders($overrideExisting = true)
    {
        if (null !== $this->collOrders && !$overrideExisting) {
            return;
        }
        $this->collOrders = new PropelObjectCollection();
        $this->collOrders->setModel('Order');
    }

    /**
     * Gets an array of Order objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Customer is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Order[] List of Order objects
     * @throws PropelException
     */
    public function getOrders($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collOrdersPartial && !$this->isNew();
        if (null === $this->collOrders || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrders) {
                // return empty collection
                $this->initOrders();
            } else {
                $collOrders = OrderQuery::create(null, $criteria)
                    ->filterByCustomer($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collOrdersPartial && count($collOrders)) {
                      $this->initOrders(false);

                      foreach($collOrders as $obj) {
                        if (false == $this->collOrders->contains($obj)) {
                          $this->collOrders->append($obj);
                        }
                      }

                      $this->collOrdersPartial = true;
                    }

                    return $collOrders;
                }

                if($partial && $this->collOrders) {
                    foreach($this->collOrders as $obj) {
                        if($obj->isNew()) {
                            $collOrders[] = $obj;
                        }
                    }
                }

                $this->collOrders = $collOrders;
                $this->collOrdersPartial = false;
            }
        }

        return $this->collOrders;
    }

    /**
     * Sets a collection of Order objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $orders A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setOrders(PropelCollection $orders, PropelPDO $con = null)
    {
        $this->ordersScheduledForDeletion = $this->getOrders(new Criteria(), $con)->diff($orders);

        foreach ($this->ordersScheduledForDeletion as $orderRemoved) {
            $orderRemoved->setCustomer(null);
        }

        $this->collOrders = null;
        foreach ($orders as $order) {
            $this->addOrder($order);
        }

        $this->collOrders = $orders;
        $this->collOrdersPartial = false;
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
    public function countOrders(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collOrdersPartial && !$this->isNew();
        if (null === $this->collOrders || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrders) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getOrders());
                }
                $query = OrderQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByCustomer($this)
                    ->count($con);
            }
        } else {
            return count($this->collOrders);
        }
    }

    /**
     * Method called to associate a Order object to this object
     * through the Order foreign key attribute.
     *
     * @param    Order $l Order
     * @return Customer The current object (for fluent API support)
     */
    public function addOrder(Order $l)
    {
        if ($this->collOrders === null) {
            $this->initOrders();
            $this->collOrdersPartial = true;
        }
        if (!$this->collOrders->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddOrder($l);
        }

        return $this;
    }

    /**
     * @param	Order $order The order object to add.
     */
    protected function doAddOrder($order)
    {
        $this->collOrders[]= $order;
        $order->setCustomer($this);
    }

    /**
     * @param	Order $order The order object to remove.
     */
    public function removeOrder($order)
    {
        if ($this->getOrders()->contains($order)) {
            $this->collOrders->remove($this->collOrders->search($order));
            if (null === $this->ordersScheduledForDeletion) {
                $this->ordersScheduledForDeletion = clone $this->collOrders;
                $this->ordersScheduledForDeletion->clear();
            }
            $this->ordersScheduledForDeletion[]= $order;
            $order->setCustomer(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersJoinCurrency($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('Currency', $join_behavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersJoinOrderAddressRelatedByAddressInvoice($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('OrderAddressRelatedByAddressInvoice', $join_behavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersJoinOrderAddressRelatedByAddressDelivery($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('OrderAddressRelatedByAddressDelivery', $join_behavior);

        return $this->getOrders($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Customer is new, it will return
     * an empty collection; or if this Customer has previously
     * been saved, it will retrieve related Orders from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Customer.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Order[] List of Order objects
     */
    public function getOrdersJoinOrderStatus($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = OrderQuery::create(null, $criteria);
        $query->joinWith('OrderStatus', $join_behavior);

        return $this->getOrders($query, $con);
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->ref = null;
        $this->customer_title_id = null;
        $this->company = null;
        $this->firstname = null;
        $this->lastname = null;
        $this->address1 = null;
        $this->address2 = null;
        $this->address3 = null;
        $this->zipcode = null;
        $this->city = null;
        $this->country_id = null;
        $this->phone = null;
        $this->cellphone = null;
        $this->email = null;
        $this->password = null;
        $this->algo = null;
        $this->salt = null;
        $this->reseller = null;
        $this->lang = null;
        $this->sponsor = null;
        $this->discount = null;
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
            if ($this->collAddresss) {
                foreach ($this->collAddresss as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collOrders) {
                foreach ($this->collOrders as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        if ($this->collAddresss instanceof PropelCollection) {
            $this->collAddresss->clearIterator();
        }
        $this->collAddresss = null;
        if ($this->collOrders instanceof PropelCollection) {
            $this->collOrders->clearIterator();
        }
        $this->collOrders = null;
        $this->aCustomerTitle = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(CustomerPeer::DEFAULT_STRING_FORMAT);
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
