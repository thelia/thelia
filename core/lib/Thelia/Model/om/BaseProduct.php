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
use Thelia\Model\Accessory;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\ContentAssoc;
use Thelia\Model\ContentAssocQuery;
use Thelia\Model\Document;
use Thelia\Model\DocumentQuery;
use Thelia\Model\FeatureProd;
use Thelia\Model\FeatureProdQuery;
use Thelia\Model\Image;
use Thelia\Model\ImageQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductDesc;
use Thelia\Model\ProductDescQuery;
use Thelia\Model\ProductPeer;
use Thelia\Model\ProductQuery;
use Thelia\Model\Rewriting;
use Thelia\Model\RewritingQuery;
use Thelia\Model\Stock;
use Thelia\Model\StockQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

/**
 * Base class that represents a row from the 'product' table.
 *
 *
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseProduct extends BaseObject implements Persistent
{
    /**
     * Peer class name
     */
    const PEER = 'Thelia\\Model\\ProductPeer';

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        ProductPeer
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
     * The value for the tax_rule_id field.
     * @var        int
     */
    protected $tax_rule_id;

    /**
     * The value for the ref field.
     * @var        string
     */
    protected $ref;

    /**
     * The value for the price field.
     * @var        double
     */
    protected $price;

    /**
     * The value for the price2 field.
     * @var        double
     */
    protected $price2;

    /**
     * The value for the ecotax field.
     * @var        double
     */
    protected $ecotax;

    /**
     * The value for the newness field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $newness;

    /**
     * The value for the promo field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $promo;

    /**
     * The value for the quantity field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $quantity;

    /**
     * The value for the visible field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $visible;

    /**
     * The value for the weight field.
     * @var        double
     */
    protected $weight;

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
     * @var        Accessory
     */
    protected $aAccessory;

    /**
     * @var        Accessory
     */
    protected $aAccessory;

    /**
     * @var        ContentAssoc
     */
    protected $aContentAssoc;

    /**
     * @var        Document
     */
    protected $aDocument;

    /**
     * @var        FeatureProd
     */
    protected $aFeatureProd;

    /**
     * @var        Image
     */
    protected $aImage;

    /**
     * @var        ProductCategory
     */
    protected $aProductCategory;

    /**
     * @var        ProductDesc
     */
    protected $aProductDesc;

    /**
     * @var        Rewriting
     */
    protected $aRewriting;

    /**
     * @var        Stock
     */
    protected $aStock;

    /**
     * @var        TaxRule one-to-one related TaxRule object
     */
    protected $singleTaxRule;

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
    protected $taxRulesScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see        __construct()
     */
    public function applyDefaultValues()
    {
        $this->newness = 0;
        $this->promo = 0;
        $this->quantity = 0;
        $this->visible = 0;
    }

    /**
     * Initializes internal state of BaseProduct object.
     * @see        applyDefaults()
     */
    public function __construct()
    {
        parent::__construct();
        $this->applyDefaultValues();
    }

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
     * Get the [tax_rule_id] column value.
     *
     * @return int
     */
    public function getTaxRuleId()
    {
        return $this->tax_rule_id;
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
     * Get the [price] column value.
     *
     * @return double
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Get the [price2] column value.
     *
     * @return double
     */
    public function getPrice2()
    {
        return $this->price2;
    }

    /**
     * Get the [ecotax] column value.
     *
     * @return double
     */
    public function getEcotax()
    {
        return $this->ecotax;
    }

    /**
     * Get the [newness] column value.
     *
     * @return int
     */
    public function getNewness()
    {
        return $this->newness;
    }

    /**
     * Get the [promo] column value.
     *
     * @return int
     */
    public function getPromo()
    {
        return $this->promo;
    }

    /**
     * Get the [quantity] column value.
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Get the [visible] column value.
     *
     * @return int
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Get the [weight] column value.
     *
     * @return double
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * Get the [position] column value.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
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
     * @return Product The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = ProductPeer::ID;
        }

        if ($this->aAccessory !== null && $this->aAccessory->getProductId() !== $v) {
            $this->aAccessory = null;
        }

        if ($this->aAccessory !== null && $this->aAccessory->getAccessory() !== $v) {
            $this->aAccessory = null;
        }

        if ($this->aContentAssoc !== null && $this->aContentAssoc->getProductId() !== $v) {
            $this->aContentAssoc = null;
        }

        if ($this->aDocument !== null && $this->aDocument->getProductId() !== $v) {
            $this->aDocument = null;
        }

        if ($this->aFeatureProd !== null && $this->aFeatureProd->getProductId() !== $v) {
            $this->aFeatureProd = null;
        }

        if ($this->aImage !== null && $this->aImage->getProductId() !== $v) {
            $this->aImage = null;
        }

        if ($this->aProductCategory !== null && $this->aProductCategory->getProductId() !== $v) {
            $this->aProductCategory = null;
        }

        if ($this->aProductDesc !== null && $this->aProductDesc->getProductId() !== $v) {
            $this->aProductDesc = null;
        }

        if ($this->aRewriting !== null && $this->aRewriting->getProductId() !== $v) {
            $this->aRewriting = null;
        }

        if ($this->aStock !== null && $this->aStock->getProductId() !== $v) {
            $this->aStock = null;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [tax_rule_id] column.
     *
     * @param int $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setTaxRuleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->tax_rule_id !== $v) {
            $this->tax_rule_id = $v;
            $this->modifiedColumns[] = ProductPeer::TAX_RULE_ID;
        }


        return $this;
    } // setTaxRuleId()

    /**
     * Set the value of [ref] column.
     *
     * @param string $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->ref !== $v) {
            $this->ref = $v;
            $this->modifiedColumns[] = ProductPeer::REF;
        }


        return $this;
    } // setRef()

    /**
     * Set the value of [price] column.
     *
     * @param double $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setPrice($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->price !== $v) {
            $this->price = $v;
            $this->modifiedColumns[] = ProductPeer::PRICE;
        }


        return $this;
    } // setPrice()

    /**
     * Set the value of [price2] column.
     *
     * @param double $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setPrice2($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->price2 !== $v) {
            $this->price2 = $v;
            $this->modifiedColumns[] = ProductPeer::PRICE2;
        }


        return $this;
    } // setPrice2()

    /**
     * Set the value of [ecotax] column.
     *
     * @param double $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setEcotax($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->ecotax !== $v) {
            $this->ecotax = $v;
            $this->modifiedColumns[] = ProductPeer::ECOTAX;
        }


        return $this;
    } // setEcotax()

    /**
     * Set the value of [newness] column.
     *
     * @param int $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setNewness($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->newness !== $v) {
            $this->newness = $v;
            $this->modifiedColumns[] = ProductPeer::NEWNESS;
        }


        return $this;
    } // setNewness()

    /**
     * Set the value of [promo] column.
     *
     * @param int $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setPromo($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->promo !== $v) {
            $this->promo = $v;
            $this->modifiedColumns[] = ProductPeer::PROMO;
        }


        return $this;
    } // setPromo()

    /**
     * Set the value of [quantity] column.
     *
     * @param int $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setQuantity($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->quantity !== $v) {
            $this->quantity = $v;
            $this->modifiedColumns[] = ProductPeer::QUANTITY;
        }


        return $this;
    } // setQuantity()

    /**
     * Set the value of [visible] column.
     *
     * @param int $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[] = ProductPeer::VISIBLE;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [weight] column.
     *
     * @param double $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setWeight($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->weight !== $v) {
            $this->weight = $v;
            $this->modifiedColumns[] = ProductPeer::WEIGHT;
        }


        return $this;
    } // setWeight()

    /**
     * Set the value of [position] column.
     *
     * @param int $v new value
     * @return Product The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[] = ProductPeer::POSITION;
        }


        return $this;
    } // setPosition()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Product The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->created_at !== null || $dt !== null) {
            $currentDateAsString = ($this->created_at !== null && $tmpDt = new DateTime($this->created_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->created_at = $newDateAsString;
                $this->modifiedColumns[] = ProductPeer::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param mixed $v string, integer (timestamp), or DateTime value.
     *               Empty strings are treated as null.
     * @return Product The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            $currentDateAsString = ($this->updated_at !== null && $tmpDt = new DateTime($this->updated_at)) ? $tmpDt->format('Y-m-d H:i:s') : null;
            $newDateAsString = $dt ? $dt->format('Y-m-d H:i:s') : null;
            if ($currentDateAsString !== $newDateAsString) {
                $this->updated_at = $newDateAsString;
                $this->modifiedColumns[] = ProductPeer::UPDATED_AT;
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
            if ($this->newness !== 0) {
                return false;
            }

            if ($this->promo !== 0) {
                return false;
            }

            if ($this->quantity !== 0) {
                return false;
            }

            if ($this->visible !== 0) {
                return false;
            }

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
            $this->tax_rule_id = ($row[$startcol + 1] !== null) ? (int) $row[$startcol + 1] : null;
            $this->ref = ($row[$startcol + 2] !== null) ? (string) $row[$startcol + 2] : null;
            $this->price = ($row[$startcol + 3] !== null) ? (double) $row[$startcol + 3] : null;
            $this->price2 = ($row[$startcol + 4] !== null) ? (double) $row[$startcol + 4] : null;
            $this->ecotax = ($row[$startcol + 5] !== null) ? (double) $row[$startcol + 5] : null;
            $this->newness = ($row[$startcol + 6] !== null) ? (int) $row[$startcol + 6] : null;
            $this->promo = ($row[$startcol + 7] !== null) ? (int) $row[$startcol + 7] : null;
            $this->quantity = ($row[$startcol + 8] !== null) ? (int) $row[$startcol + 8] : null;
            $this->visible = ($row[$startcol + 9] !== null) ? (int) $row[$startcol + 9] : null;
            $this->weight = ($row[$startcol + 10] !== null) ? (double) $row[$startcol + 10] : null;
            $this->position = ($row[$startcol + 11] !== null) ? (int) $row[$startcol + 11] : null;
            $this->created_at = ($row[$startcol + 12] !== null) ? (string) $row[$startcol + 12] : null;
            $this->updated_at = ($row[$startcol + 13] !== null) ? (string) $row[$startcol + 13] : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 14; // 14 = ProductPeer::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating Product object", $e);
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

        if ($this->aAccessory !== null && $this->id !== $this->aAccessory->getProductId()) {
            $this->aAccessory = null;
        }
        if ($this->aAccessory !== null && $this->id !== $this->aAccessory->getAccessory()) {
            $this->aAccessory = null;
        }
        if ($this->aContentAssoc !== null && $this->id !== $this->aContentAssoc->getProductId()) {
            $this->aContentAssoc = null;
        }
        if ($this->aDocument !== null && $this->id !== $this->aDocument->getProductId()) {
            $this->aDocument = null;
        }
        if ($this->aFeatureProd !== null && $this->id !== $this->aFeatureProd->getProductId()) {
            $this->aFeatureProd = null;
        }
        if ($this->aImage !== null && $this->id !== $this->aImage->getProductId()) {
            $this->aImage = null;
        }
        if ($this->aProductCategory !== null && $this->id !== $this->aProductCategory->getProductId()) {
            $this->aProductCategory = null;
        }
        if ($this->aProductDesc !== null && $this->id !== $this->aProductDesc->getProductId()) {
            $this->aProductDesc = null;
        }
        if ($this->aRewriting !== null && $this->id !== $this->aRewriting->getProductId()) {
            $this->aRewriting = null;
        }
        if ($this->aStock !== null && $this->id !== $this->aStock->getProductId()) {
            $this->aStock = null;
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
            $con = Propel::getConnection(ProductPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $stmt = ProductPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aAccessory = null;
            $this->aAccessory = null;
            $this->aContentAssoc = null;
            $this->aDocument = null;
            $this->aFeatureProd = null;
            $this->aImage = null;
            $this->aProductCategory = null;
            $this->aProductDesc = null;
            $this->aRewriting = null;
            $this->aStock = null;
            $this->singleTaxRule = null;

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
            $con = Propel::getConnection(ProductPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ProductQuery::create()
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
            $con = Propel::getConnection(ProductPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
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
                ProductPeer::addInstanceToPool($this);
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

            if ($this->aAccessory !== null) {
                if ($this->aAccessory->isModified() || $this->aAccessory->isNew()) {
                    $affectedRows += $this->aAccessory->save($con);
                }
                $this->setAccessory($this->aAccessory);
            }

            if ($this->aAccessory !== null) {
                if ($this->aAccessory->isModified() || $this->aAccessory->isNew()) {
                    $affectedRows += $this->aAccessory->save($con);
                }
                $this->setAccessory($this->aAccessory);
            }

            if ($this->aContentAssoc !== null) {
                if ($this->aContentAssoc->isModified() || $this->aContentAssoc->isNew()) {
                    $affectedRows += $this->aContentAssoc->save($con);
                }
                $this->setContentAssoc($this->aContentAssoc);
            }

            if ($this->aDocument !== null) {
                if ($this->aDocument->isModified() || $this->aDocument->isNew()) {
                    $affectedRows += $this->aDocument->save($con);
                }
                $this->setDocument($this->aDocument);
            }

            if ($this->aFeatureProd !== null) {
                if ($this->aFeatureProd->isModified() || $this->aFeatureProd->isNew()) {
                    $affectedRows += $this->aFeatureProd->save($con);
                }
                $this->setFeatureProd($this->aFeatureProd);
            }

            if ($this->aImage !== null) {
                if ($this->aImage->isModified() || $this->aImage->isNew()) {
                    $affectedRows += $this->aImage->save($con);
                }
                $this->setImage($this->aImage);
            }

            if ($this->aProductCategory !== null) {
                if ($this->aProductCategory->isModified() || $this->aProductCategory->isNew()) {
                    $affectedRows += $this->aProductCategory->save($con);
                }
                $this->setProductCategory($this->aProductCategory);
            }

            if ($this->aProductDesc !== null) {
                if ($this->aProductDesc->isModified() || $this->aProductDesc->isNew()) {
                    $affectedRows += $this->aProductDesc->save($con);
                }
                $this->setProductDesc($this->aProductDesc);
            }

            if ($this->aRewriting !== null) {
                if ($this->aRewriting->isModified() || $this->aRewriting->isNew()) {
                    $affectedRows += $this->aRewriting->save($con);
                }
                $this->setRewriting($this->aRewriting);
            }

            if ($this->aStock !== null) {
                if ($this->aStock->isModified() || $this->aStock->isNew()) {
                    $affectedRows += $this->aStock->save($con);
                }
                $this->setStock($this->aStock);
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

            if ($this->taxRulesScheduledForDeletion !== null) {
                if (!$this->taxRulesScheduledForDeletion->isEmpty()) {
                    TaxRuleQuery::create()
                        ->filterByPrimaryKeys($this->taxRulesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->taxRulesScheduledForDeletion = null;
                }
            }

            if ($this->singleTaxRule !== null) {
                if (!$this->singleTaxRule->isDeleted()) {
                        $affectedRows += $this->singleTaxRule->save($con);
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

        $this->modifiedColumns[] = ProductPeer::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . ProductPeer::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(ProductPeer::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(ProductPeer::TAX_RULE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`TAX_RULE_ID`';
        }
        if ($this->isColumnModified(ProductPeer::REF)) {
            $modifiedColumns[':p' . $index++]  = '`REF`';
        }
        if ($this->isColumnModified(ProductPeer::PRICE)) {
            $modifiedColumns[':p' . $index++]  = '`PRICE`';
        }
        if ($this->isColumnModified(ProductPeer::PRICE2)) {
            $modifiedColumns[':p' . $index++]  = '`PRICE2`';
        }
        if ($this->isColumnModified(ProductPeer::ECOTAX)) {
            $modifiedColumns[':p' . $index++]  = '`ECOTAX`';
        }
        if ($this->isColumnModified(ProductPeer::NEWNESS)) {
            $modifiedColumns[':p' . $index++]  = '`NEWNESS`';
        }
        if ($this->isColumnModified(ProductPeer::PROMO)) {
            $modifiedColumns[':p' . $index++]  = '`PROMO`';
        }
        if ($this->isColumnModified(ProductPeer::QUANTITY)) {
            $modifiedColumns[':p' . $index++]  = '`QUANTITY`';
        }
        if ($this->isColumnModified(ProductPeer::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`VISIBLE`';
        }
        if ($this->isColumnModified(ProductPeer::WEIGHT)) {
            $modifiedColumns[':p' . $index++]  = '`WEIGHT`';
        }
        if ($this->isColumnModified(ProductPeer::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(ProductPeer::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(ProductPeer::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }

        $sql = sprintf(
            'INSERT INTO `product` (%s) VALUES (%s)',
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
                    case '`TAX_RULE_ID`':
                        $stmt->bindValue($identifier, $this->tax_rule_id, PDO::PARAM_INT);
                        break;
                    case '`REF`':
                        $stmt->bindValue($identifier, $this->ref, PDO::PARAM_STR);
                        break;
                    case '`PRICE`':
                        $stmt->bindValue($identifier, $this->price, PDO::PARAM_STR);
                        break;
                    case '`PRICE2`':
                        $stmt->bindValue($identifier, $this->price2, PDO::PARAM_STR);
                        break;
                    case '`ECOTAX`':
                        $stmt->bindValue($identifier, $this->ecotax, PDO::PARAM_STR);
                        break;
                    case '`NEWNESS`':
                        $stmt->bindValue($identifier, $this->newness, PDO::PARAM_INT);
                        break;
                    case '`PROMO`':
                        $stmt->bindValue($identifier, $this->promo, PDO::PARAM_INT);
                        break;
                    case '`QUANTITY`':
                        $stmt->bindValue($identifier, $this->quantity, PDO::PARAM_INT);
                        break;
                    case '`VISIBLE`':
                        $stmt->bindValue($identifier, $this->visible, PDO::PARAM_INT);
                        break;
                    case '`WEIGHT`':
                        $stmt->bindValue($identifier, $this->weight, PDO::PARAM_STR);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
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

            if ($this->aAccessory !== null) {
                if (!$this->aAccessory->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aAccessory->getValidationFailures());
                }
            }

            if ($this->aAccessory !== null) {
                if (!$this->aAccessory->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aAccessory->getValidationFailures());
                }
            }

            if ($this->aContentAssoc !== null) {
                if (!$this->aContentAssoc->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aContentAssoc->getValidationFailures());
                }
            }

            if ($this->aDocument !== null) {
                if (!$this->aDocument->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aDocument->getValidationFailures());
                }
            }

            if ($this->aFeatureProd !== null) {
                if (!$this->aFeatureProd->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aFeatureProd->getValidationFailures());
                }
            }

            if ($this->aImage !== null) {
                if (!$this->aImage->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aImage->getValidationFailures());
                }
            }

            if ($this->aProductCategory !== null) {
                if (!$this->aProductCategory->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aProductCategory->getValidationFailures());
                }
            }

            if ($this->aProductDesc !== null) {
                if (!$this->aProductDesc->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aProductDesc->getValidationFailures());
                }
            }

            if ($this->aRewriting !== null) {
                if (!$this->aRewriting->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aRewriting->getValidationFailures());
                }
            }

            if ($this->aStock !== null) {
                if (!$this->aStock->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aStock->getValidationFailures());
                }
            }


            if (($retval = ProductPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->singleTaxRule !== null) {
                    if (!$this->singleTaxRule->validate($columns)) {
                        $failureMap = array_merge($failureMap, $this->singleTaxRule->getValidationFailures());
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
        $pos = ProductPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
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
                return $this->getTaxRuleId();
                break;
            case 2:
                return $this->getRef();
                break;
            case 3:
                return $this->getPrice();
                break;
            case 4:
                return $this->getPrice2();
                break;
            case 5:
                return $this->getEcotax();
                break;
            case 6:
                return $this->getNewness();
                break;
            case 7:
                return $this->getPromo();
                break;
            case 8:
                return $this->getQuantity();
                break;
            case 9:
                return $this->getVisible();
                break;
            case 10:
                return $this->getWeight();
                break;
            case 11:
                return $this->getPosition();
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
        if (isset($alreadyDumpedObjects['Product'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Product'][$this->getPrimaryKey()] = true;
        $keys = ProductPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getTaxRuleId(),
            $keys[2] => $this->getRef(),
            $keys[3] => $this->getPrice(),
            $keys[4] => $this->getPrice2(),
            $keys[5] => $this->getEcotax(),
            $keys[6] => $this->getNewness(),
            $keys[7] => $this->getPromo(),
            $keys[8] => $this->getQuantity(),
            $keys[9] => $this->getVisible(),
            $keys[10] => $this->getWeight(),
            $keys[11] => $this->getPosition(),
            $keys[12] => $this->getCreatedAt(),
            $keys[13] => $this->getUpdatedAt(),
        );
        if ($includeForeignObjects) {
            if (null !== $this->aAccessory) {
                $result['Accessory'] = $this->aAccessory->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aAccessory) {
                $result['Accessory'] = $this->aAccessory->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aContentAssoc) {
                $result['ContentAssoc'] = $this->aContentAssoc->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aDocument) {
                $result['Document'] = $this->aDocument->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aFeatureProd) {
                $result['FeatureProd'] = $this->aFeatureProd->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aImage) {
                $result['Image'] = $this->aImage->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aProductCategory) {
                $result['ProductCategory'] = $this->aProductCategory->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aProductDesc) {
                $result['ProductDesc'] = $this->aProductDesc->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aRewriting) {
                $result['Rewriting'] = $this->aRewriting->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aStock) {
                $result['Stock'] = $this->aStock->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->singleTaxRule) {
                $result['TaxRule'] = $this->singleTaxRule->toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, true);
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
        $pos = ProductPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);

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
                $this->setTaxRuleId($value);
                break;
            case 2:
                $this->setRef($value);
                break;
            case 3:
                $this->setPrice($value);
                break;
            case 4:
                $this->setPrice2($value);
                break;
            case 5:
                $this->setEcotax($value);
                break;
            case 6:
                $this->setNewness($value);
                break;
            case 7:
                $this->setPromo($value);
                break;
            case 8:
                $this->setQuantity($value);
                break;
            case 9:
                $this->setVisible($value);
                break;
            case 10:
                $this->setWeight($value);
                break;
            case 11:
                $this->setPosition($value);
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
        $keys = ProductPeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setTaxRuleId($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setRef($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setPrice($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setPrice2($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setEcotax($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setNewness($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setPromo($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setQuantity($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setVisible($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setWeight($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setPosition($arr[$keys[11]]);
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
        $criteria = new Criteria(ProductPeer::DATABASE_NAME);

        if ($this->isColumnModified(ProductPeer::ID)) $criteria->add(ProductPeer::ID, $this->id);
        if ($this->isColumnModified(ProductPeer::TAX_RULE_ID)) $criteria->add(ProductPeer::TAX_RULE_ID, $this->tax_rule_id);
        if ($this->isColumnModified(ProductPeer::REF)) $criteria->add(ProductPeer::REF, $this->ref);
        if ($this->isColumnModified(ProductPeer::PRICE)) $criteria->add(ProductPeer::PRICE, $this->price);
        if ($this->isColumnModified(ProductPeer::PRICE2)) $criteria->add(ProductPeer::PRICE2, $this->price2);
        if ($this->isColumnModified(ProductPeer::ECOTAX)) $criteria->add(ProductPeer::ECOTAX, $this->ecotax);
        if ($this->isColumnModified(ProductPeer::NEWNESS)) $criteria->add(ProductPeer::NEWNESS, $this->newness);
        if ($this->isColumnModified(ProductPeer::PROMO)) $criteria->add(ProductPeer::PROMO, $this->promo);
        if ($this->isColumnModified(ProductPeer::QUANTITY)) $criteria->add(ProductPeer::QUANTITY, $this->quantity);
        if ($this->isColumnModified(ProductPeer::VISIBLE)) $criteria->add(ProductPeer::VISIBLE, $this->visible);
        if ($this->isColumnModified(ProductPeer::WEIGHT)) $criteria->add(ProductPeer::WEIGHT, $this->weight);
        if ($this->isColumnModified(ProductPeer::POSITION)) $criteria->add(ProductPeer::POSITION, $this->position);
        if ($this->isColumnModified(ProductPeer::CREATED_AT)) $criteria->add(ProductPeer::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(ProductPeer::UPDATED_AT)) $criteria->add(ProductPeer::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(ProductPeer::DATABASE_NAME);
        $criteria->add(ProductPeer::ID, $this->id);

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
     * @param object $copyObj An object of Product (or compatible) type.
     * @param boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setTaxRuleId($this->getTaxRuleId());
        $copyObj->setRef($this->getRef());
        $copyObj->setPrice($this->getPrice());
        $copyObj->setPrice2($this->getPrice2());
        $copyObj->setEcotax($this->getEcotax());
        $copyObj->setNewness($this->getNewness());
        $copyObj->setPromo($this->getPromo());
        $copyObj->setQuantity($this->getQuantity());
        $copyObj->setVisible($this->getVisible());
        $copyObj->setWeight($this->getWeight());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy && !$this->startCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);
            // store object hash to prevent cycle
            $this->startCopy = true;

            $relObj = $this->getTaxRule();
            if ($relObj) {
                $copyObj->setTaxRule($relObj->copy($deepCopy));
            }

            $relObj = $this->getAccessory();
            if ($relObj) {
                $copyObj->setAccessory($relObj->copy($deepCopy));
            }

            $relObj = $this->getAccessory();
            if ($relObj) {
                $copyObj->setAccessory($relObj->copy($deepCopy));
            }

            $relObj = $this->getContentAssoc();
            if ($relObj) {
                $copyObj->setContentAssoc($relObj->copy($deepCopy));
            }

            $relObj = $this->getDocument();
            if ($relObj) {
                $copyObj->setDocument($relObj->copy($deepCopy));
            }

            $relObj = $this->getFeatureProd();
            if ($relObj) {
                $copyObj->setFeatureProd($relObj->copy($deepCopy));
            }

            $relObj = $this->getImage();
            if ($relObj) {
                $copyObj->setImage($relObj->copy($deepCopy));
            }

            $relObj = $this->getProductCategory();
            if ($relObj) {
                $copyObj->setProductCategory($relObj->copy($deepCopy));
            }

            $relObj = $this->getProductDesc();
            if ($relObj) {
                $copyObj->setProductDesc($relObj->copy($deepCopy));
            }

            $relObj = $this->getRewriting();
            if ($relObj) {
                $copyObj->setRewriting($relObj->copy($deepCopy));
            }

            $relObj = $this->getStock();
            if ($relObj) {
                $copyObj->setStock($relObj->copy($deepCopy));
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
     * @return Product Clone of current object.
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
     * @return ProductPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new ProductPeer();
        }

        return self::$peer;
    }

    /**
     * Declares an association between this object and a Accessory object.
     *
     * @param             Accessory $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setAccessory(Accessory $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aAccessory = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated Accessory object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Accessory The associated Accessory object.
     * @throws PropelException
     */
    public function getAccessory(PropelPDO $con = null)
    {
        if ($this->aAccessory === null && ($this->id !== null)) {
            $this->aAccessory = AccessoryQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aAccessory->setProduct($this);
        }

        return $this->aAccessory;
    }

    /**
     * Declares an association between this object and a Accessory object.
     *
     * @param             Accessory $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setAccessory(Accessory $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getAccessory());
        }

        $this->aAccessory = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated Accessory object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Accessory The associated Accessory object.
     * @throws PropelException
     */
    public function getAccessory(PropelPDO $con = null)
    {
        if ($this->aAccessory === null && ($this->id !== null)) {
            $this->aAccessory = AccessoryQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aAccessory->setProduct($this);
        }

        return $this->aAccessory;
    }

    /**
     * Declares an association between this object and a ContentAssoc object.
     *
     * @param             ContentAssoc $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setContentAssoc(ContentAssoc $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aContentAssoc = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated ContentAssoc object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return ContentAssoc The associated ContentAssoc object.
     * @throws PropelException
     */
    public function getContentAssoc(PropelPDO $con = null)
    {
        if ($this->aContentAssoc === null && ($this->id !== null)) {
            $this->aContentAssoc = ContentAssocQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aContentAssoc->setProduct($this);
        }

        return $this->aContentAssoc;
    }

    /**
     * Declares an association between this object and a Document object.
     *
     * @param             Document $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setDocument(Document $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aDocument = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated Document object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Document The associated Document object.
     * @throws PropelException
     */
    public function getDocument(PropelPDO $con = null)
    {
        if ($this->aDocument === null && ($this->id !== null)) {
            $this->aDocument = DocumentQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aDocument->setProduct($this);
        }

        return $this->aDocument;
    }

    /**
     * Declares an association between this object and a FeatureProd object.
     *
     * @param             FeatureProd $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setFeatureProd(FeatureProd $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aFeatureProd = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated FeatureProd object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return FeatureProd The associated FeatureProd object.
     * @throws PropelException
     */
    public function getFeatureProd(PropelPDO $con = null)
    {
        if ($this->aFeatureProd === null && ($this->id !== null)) {
            $this->aFeatureProd = FeatureProdQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aFeatureProd->setProduct($this);
        }

        return $this->aFeatureProd;
    }

    /**
     * Declares an association between this object and a Image object.
     *
     * @param             Image $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setImage(Image $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aImage = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated Image object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Image The associated Image object.
     * @throws PropelException
     */
    public function getImage(PropelPDO $con = null)
    {
        if ($this->aImage === null && ($this->id !== null)) {
            $this->aImage = ImageQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aImage->setProduct($this);
        }

        return $this->aImage;
    }

    /**
     * Declares an association between this object and a ProductCategory object.
     *
     * @param             ProductCategory $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setProductCategory(ProductCategory $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aProductCategory = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated ProductCategory object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return ProductCategory The associated ProductCategory object.
     * @throws PropelException
     */
    public function getProductCategory(PropelPDO $con = null)
    {
        if ($this->aProductCategory === null && ($this->id !== null)) {
            $this->aProductCategory = ProductCategoryQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aProductCategory->setProduct($this);
        }

        return $this->aProductCategory;
    }

    /**
     * Declares an association between this object and a ProductDesc object.
     *
     * @param             ProductDesc $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setProductDesc(ProductDesc $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aProductDesc = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated ProductDesc object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return ProductDesc The associated ProductDesc object.
     * @throws PropelException
     */
    public function getProductDesc(PropelPDO $con = null)
    {
        if ($this->aProductDesc === null && ($this->id !== null)) {
            $this->aProductDesc = ProductDescQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aProductDesc->setProduct($this);
        }

        return $this->aProductDesc;
    }

    /**
     * Declares an association between this object and a Rewriting object.
     *
     * @param             Rewriting $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setRewriting(Rewriting $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aRewriting = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated Rewriting object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Rewriting The associated Rewriting object.
     * @throws PropelException
     */
    public function getRewriting(PropelPDO $con = null)
    {
        if ($this->aRewriting === null && ($this->id !== null)) {
            $this->aRewriting = RewritingQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aRewriting->setProduct($this);
        }

        return $this->aRewriting;
    }

    /**
     * Declares an association between this object and a Stock object.
     *
     * @param             Stock $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setStock(Stock $v = null)
    {
        if ($v === null) {
            $this->setId(NULL);
        } else {
            $this->setId($v->getProductId());
        }

        $this->aStock = $v;

        // Add binding for other direction of this 1:1 relationship.
        if ($v !== null) {
            $v->setProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated Stock object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return Stock The associated Stock object.
     * @throws PropelException
     */
    public function getStock(PropelPDO $con = null)
    {
        if ($this->aStock === null && ($this->id !== null)) {
            $this->aStock = StockQuery::create()
                ->filterByProduct($this) // here
                ->findOne($con);
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            $this->aStock->setProduct($this);
        }

        return $this->aStock;
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
     * Gets a single TaxRule object, which is related to this object by a one-to-one relationship.
     *
     * @param PropelPDO $con optional connection object
     * @return TaxRule
     * @throws PropelException
     */
    public function getTaxRule(PropelPDO $con = null)
    {

        if ($this->singleTaxRule === null && !$this->isNew()) {
            $this->singleTaxRule = TaxRuleQuery::create()->findPk($this->getPrimaryKey(), $con);
        }

        return $this->singleTaxRule;
    }

    /**
     * Sets a single TaxRule object as related to this object by a one-to-one relationship.
     *
     * @param             TaxRule $v TaxRule
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setTaxRule(TaxRule $v = null)
    {
        $this->singleTaxRule = $v;

        // Make sure that that the passed-in TaxRule isn't already associated with this object
        if ($v !== null && $v->getProduct() === null) {
            $v->setProduct($this);
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->tax_rule_id = null;
        $this->ref = null;
        $this->price = null;
        $this->price2 = null;
        $this->ecotax = null;
        $this->newness = null;
        $this->promo = null;
        $this->quantity = null;
        $this->visible = null;
        $this->weight = null;
        $this->position = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->alreadyInSave = false;
        $this->alreadyInValidation = false;
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
     * when using Propel in certain daemon or large-volumne/high-memory operations.
     *
     * @param boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
            if ($this->singleTaxRule) {
                $this->singleTaxRule->clearAllReferences($deep);
            }
        } // if ($deep)

        if ($this->singleTaxRule instanceof PropelCollection) {
            $this->singleTaxRule->clearIterator();
        }
        $this->singleTaxRule = null;
        $this->aAccessory = null;
        $this->aAccessory = null;
        $this->aContentAssoc = null;
        $this->aDocument = null;
        $this->aFeatureProd = null;
        $this->aImage = null;
        $this->aProductCategory = null;
        $this->aProductDesc = null;
        $this->aRewriting = null;
        $this->aStock = null;
    }

    /**
     * return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(ProductPeer::DEFAULT_STRING_FORMAT);
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
