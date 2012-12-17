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
     * @var        TaxRule
     */
    protected $aTaxRule;

    /**
     * @var        PropelObjectCollection|Accessory[] Collection to store aggregation of Accessory objects.
     */
    protected $collAccessorysRelatedByProductId;
    protected $collAccessorysRelatedByProductIdPartial;

    /**
     * @var        PropelObjectCollection|Accessory[] Collection to store aggregation of Accessory objects.
     */
    protected $collAccessorysRelatedByAccessory;
    protected $collAccessorysRelatedByAccessoryPartial;

    /**
     * @var        PropelObjectCollection|ContentAssoc[] Collection to store aggregation of ContentAssoc objects.
     */
    protected $collContentAssocs;
    protected $collContentAssocsPartial;

    /**
     * @var        PropelObjectCollection|Document[] Collection to store aggregation of Document objects.
     */
    protected $collDocuments;
    protected $collDocumentsPartial;

    /**
     * @var        PropelObjectCollection|FeatureProd[] Collection to store aggregation of FeatureProd objects.
     */
    protected $collFeatureProds;
    protected $collFeatureProdsPartial;

    /**
     * @var        PropelObjectCollection|Image[] Collection to store aggregation of Image objects.
     */
    protected $collImages;
    protected $collImagesPartial;

    /**
     * @var        PropelObjectCollection|ProductCategory[] Collection to store aggregation of ProductCategory objects.
     */
    protected $collProductCategorys;
    protected $collProductCategorysPartial;

    /**
     * @var        PropelObjectCollection|ProductDesc[] Collection to store aggregation of ProductDesc objects.
     */
    protected $collProductDescs;
    protected $collProductDescsPartial;

    /**
     * @var        PropelObjectCollection|Rewriting[] Collection to store aggregation of Rewriting objects.
     */
    protected $collRewritings;
    protected $collRewritingsPartial;

    /**
     * @var        PropelObjectCollection|Stock[] Collection to store aggregation of Stock objects.
     */
    protected $collStocks;
    protected $collStocksPartial;

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
    protected $accessorysRelatedByProductIdScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $accessorysRelatedByAccessoryScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $contentAssocsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $documentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $featureProdsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $imagesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $productCategorysScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $productDescsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $rewritingsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var		PropelObjectCollection
     */
    protected $stocksScheduledForDeletion = null;

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

        if ($this->aTaxRule !== null && $this->aTaxRule->getId() !== $v) {
            $this->aTaxRule = null;
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

        if ($this->aTaxRule !== null && $this->tax_rule_id !== $this->aTaxRule->getId()) {
            $this->aTaxRule = null;
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

            $this->aTaxRule = null;
            $this->collAccessorysRelatedByProductId = null;

            $this->collAccessorysRelatedByAccessory = null;

            $this->collContentAssocs = null;

            $this->collDocuments = null;

            $this->collFeatureProds = null;

            $this->collImages = null;

            $this->collProductCategorys = null;

            $this->collProductDescs = null;

            $this->collRewritings = null;

            $this->collStocks = null;

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

            if ($this->aTaxRule !== null) {
                if ($this->aTaxRule->isModified() || $this->aTaxRule->isNew()) {
                    $affectedRows += $this->aTaxRule->save($con);
                }
                $this->setTaxRule($this->aTaxRule);
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

            if ($this->accessorysRelatedByProductIdScheduledForDeletion !== null) {
                if (!$this->accessorysRelatedByProductIdScheduledForDeletion->isEmpty()) {
                    AccessoryQuery::create()
                        ->filterByPrimaryKeys($this->accessorysRelatedByProductIdScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->accessorysRelatedByProductIdScheduledForDeletion = null;
                }
            }

            if ($this->collAccessorysRelatedByProductId !== null) {
                foreach ($this->collAccessorysRelatedByProductId as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->accessorysRelatedByAccessoryScheduledForDeletion !== null) {
                if (!$this->accessorysRelatedByAccessoryScheduledForDeletion->isEmpty()) {
                    AccessoryQuery::create()
                        ->filterByPrimaryKeys($this->accessorysRelatedByAccessoryScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->accessorysRelatedByAccessoryScheduledForDeletion = null;
                }
            }

            if ($this->collAccessorysRelatedByAccessory !== null) {
                foreach ($this->collAccessorysRelatedByAccessory as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->contentAssocsScheduledForDeletion !== null) {
                if (!$this->contentAssocsScheduledForDeletion->isEmpty()) {
                    foreach ($this->contentAssocsScheduledForDeletion as $contentAssoc) {
                        // need to save related object because we set the relation to null
                        $contentAssoc->save($con);
                    }
                    $this->contentAssocsScheduledForDeletion = null;
                }
            }

            if ($this->collContentAssocs !== null) {
                foreach ($this->collContentAssocs as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->documentsScheduledForDeletion !== null) {
                if (!$this->documentsScheduledForDeletion->isEmpty()) {
                    foreach ($this->documentsScheduledForDeletion as $document) {
                        // need to save related object because we set the relation to null
                        $document->save($con);
                    }
                    $this->documentsScheduledForDeletion = null;
                }
            }

            if ($this->collDocuments !== null) {
                foreach ($this->collDocuments as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureProdsScheduledForDeletion !== null) {
                if (!$this->featureProdsScheduledForDeletion->isEmpty()) {
                    FeatureProdQuery::create()
                        ->filterByPrimaryKeys($this->featureProdsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureProdsScheduledForDeletion = null;
                }
            }

            if ($this->collFeatureProds !== null) {
                foreach ($this->collFeatureProds as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->imagesScheduledForDeletion !== null) {
                if (!$this->imagesScheduledForDeletion->isEmpty()) {
                    foreach ($this->imagesScheduledForDeletion as $image) {
                        // need to save related object because we set the relation to null
                        $image->save($con);
                    }
                    $this->imagesScheduledForDeletion = null;
                }
            }

            if ($this->collImages !== null) {
                foreach ($this->collImages as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productCategorysScheduledForDeletion !== null) {
                if (!$this->productCategorysScheduledForDeletion->isEmpty()) {
                    ProductCategoryQuery::create()
                        ->filterByPrimaryKeys($this->productCategorysScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productCategorysScheduledForDeletion = null;
                }
            }

            if ($this->collProductCategorys !== null) {
                foreach ($this->collProductCategorys as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productDescsScheduledForDeletion !== null) {
                if (!$this->productDescsScheduledForDeletion->isEmpty()) {
                    ProductDescQuery::create()
                        ->filterByPrimaryKeys($this->productDescsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productDescsScheduledForDeletion = null;
                }
            }

            if ($this->collProductDescs !== null) {
                foreach ($this->collProductDescs as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->rewritingsScheduledForDeletion !== null) {
                if (!$this->rewritingsScheduledForDeletion->isEmpty()) {
                    foreach ($this->rewritingsScheduledForDeletion as $rewriting) {
                        // need to save related object because we set the relation to null
                        $rewriting->save($con);
                    }
                    $this->rewritingsScheduledForDeletion = null;
                }
            }

            if ($this->collRewritings !== null) {
                foreach ($this->collRewritings as $referrerFK) {
                    if (!$referrerFK->isDeleted()) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->stocksScheduledForDeletion !== null) {
                if (!$this->stocksScheduledForDeletion->isEmpty()) {
                    StockQuery::create()
                        ->filterByPrimaryKeys($this->stocksScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->stocksScheduledForDeletion = null;
                }
            }

            if ($this->collStocks !== null) {
                foreach ($this->collStocks as $referrerFK) {
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

            if ($this->aTaxRule !== null) {
                if (!$this->aTaxRule->validate($columns)) {
                    $failureMap = array_merge($failureMap, $this->aTaxRule->getValidationFailures());
                }
            }


            if (($retval = ProductPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }


                if ($this->collAccessorysRelatedByProductId !== null) {
                    foreach ($this->collAccessorysRelatedByProductId as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collAccessorysRelatedByAccessory !== null) {
                    foreach ($this->collAccessorysRelatedByAccessory as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collContentAssocs !== null) {
                    foreach ($this->collContentAssocs as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collDocuments !== null) {
                    foreach ($this->collDocuments as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collFeatureProds !== null) {
                    foreach ($this->collFeatureProds as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collImages !== null) {
                    foreach ($this->collImages as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collProductCategorys !== null) {
                    foreach ($this->collProductCategorys as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collProductDescs !== null) {
                    foreach ($this->collProductDescs as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collRewritings !== null) {
                    foreach ($this->collRewritings as $referrerFK) {
                        if (!$referrerFK->validate($columns)) {
                            $failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
                        }
                    }
                }

                if ($this->collStocks !== null) {
                    foreach ($this->collStocks as $referrerFK) {
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
            if (null !== $this->aTaxRule) {
                $result['TaxRule'] = $this->aTaxRule->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collAccessorysRelatedByProductId) {
                $result['AccessorysRelatedByProductId'] = $this->collAccessorysRelatedByProductId->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAccessorysRelatedByAccessory) {
                $result['AccessorysRelatedByAccessory'] = $this->collAccessorysRelatedByAccessory->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collContentAssocs) {
                $result['ContentAssocs'] = $this->collContentAssocs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collDocuments) {
                $result['Documents'] = $this->collDocuments->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureProds) {
                $result['FeatureProds'] = $this->collFeatureProds->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collImages) {
                $result['Images'] = $this->collImages->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductCategorys) {
                $result['ProductCategorys'] = $this->collProductCategorys->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductDescs) {
                $result['ProductDescs'] = $this->collProductDescs->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collRewritings) {
                $result['Rewritings'] = $this->collRewritings->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collStocks) {
                $result['Stocks'] = $this->collStocks->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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

            foreach ($this->getAccessorysRelatedByProductId() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAccessoryRelatedByProductId($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAccessorysRelatedByAccessory() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAccessoryRelatedByAccessory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getContentAssocs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addContentAssoc($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getDocuments() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addDocument($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureProds() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureProd($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getImages() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addImage($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductCategorys() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductCategory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductDescs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductDesc($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getRewritings() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addRewriting($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getStocks() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addStock($relObj->copy($deepCopy));
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
     * Declares an association between this object and a TaxRule object.
     *
     * @param             TaxRule $v
     * @return Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setTaxRule(TaxRule $v = null)
    {
        if ($v === null) {
            $this->setTaxRuleId(NULL);
        } else {
            $this->setTaxRuleId($v->getId());
        }

        $this->aTaxRule = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the TaxRule object, it will not be re-added.
        if ($v !== null) {
            $v->addProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated TaxRule object
     *
     * @param PropelPDO $con Optional Connection object.
     * @return TaxRule The associated TaxRule object.
     * @throws PropelException
     */
    public function getTaxRule(PropelPDO $con = null)
    {
        if ($this->aTaxRule === null && ($this->tax_rule_id !== null)) {
            $this->aTaxRule = TaxRuleQuery::create()->findPk($this->tax_rule_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aTaxRule->addProducts($this);
             */
        }

        return $this->aTaxRule;
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
        if ('AccessoryRelatedByProductId' == $relationName) {
            $this->initAccessorysRelatedByProductId();
        }
        if ('AccessoryRelatedByAccessory' == $relationName) {
            $this->initAccessorysRelatedByAccessory();
        }
        if ('ContentAssoc' == $relationName) {
            $this->initContentAssocs();
        }
        if ('Document' == $relationName) {
            $this->initDocuments();
        }
        if ('FeatureProd' == $relationName) {
            $this->initFeatureProds();
        }
        if ('Image' == $relationName) {
            $this->initImages();
        }
        if ('ProductCategory' == $relationName) {
            $this->initProductCategorys();
        }
        if ('ProductDesc' == $relationName) {
            $this->initProductDescs();
        }
        if ('Rewriting' == $relationName) {
            $this->initRewritings();
        }
        if ('Stock' == $relationName) {
            $this->initStocks();
        }
    }

    /**
     * Clears out the collAccessorysRelatedByProductId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAccessorysRelatedByProductId()
     */
    public function clearAccessorysRelatedByProductId()
    {
        $this->collAccessorysRelatedByProductId = null; // important to set this to null since that means it is uninitialized
        $this->collAccessorysRelatedByProductIdPartial = null;
    }

    /**
     * reset is the collAccessorysRelatedByProductId collection loaded partially
     *
     * @return void
     */
    public function resetPartialAccessorysRelatedByProductId($v = true)
    {
        $this->collAccessorysRelatedByProductIdPartial = $v;
    }

    /**
     * Initializes the collAccessorysRelatedByProductId collection.
     *
     * By default this just sets the collAccessorysRelatedByProductId collection to an empty array (like clearcollAccessorysRelatedByProductId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAccessorysRelatedByProductId($overrideExisting = true)
    {
        if (null !== $this->collAccessorysRelatedByProductId && !$overrideExisting) {
            return;
        }
        $this->collAccessorysRelatedByProductId = new PropelObjectCollection();
        $this->collAccessorysRelatedByProductId->setModel('Accessory');
    }

    /**
     * Gets an array of Accessory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Accessory[] List of Accessory objects
     * @throws PropelException
     */
    public function getAccessorysRelatedByProductId($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collAccessorysRelatedByProductIdPartial && !$this->isNew();
        if (null === $this->collAccessorysRelatedByProductId || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAccessorysRelatedByProductId) {
                // return empty collection
                $this->initAccessorysRelatedByProductId();
            } else {
                $collAccessorysRelatedByProductId = AccessoryQuery::create(null, $criteria)
                    ->filterByProductRelatedByProductId($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collAccessorysRelatedByProductIdPartial && count($collAccessorysRelatedByProductId)) {
                      $this->initAccessorysRelatedByProductId(false);

                      foreach($collAccessorysRelatedByProductId as $obj) {
                        if (false == $this->collAccessorysRelatedByProductId->contains($obj)) {
                          $this->collAccessorysRelatedByProductId->append($obj);
                        }
                      }

                      $this->collAccessorysRelatedByProductIdPartial = true;
                    }

                    return $collAccessorysRelatedByProductId;
                }

                if($partial && $this->collAccessorysRelatedByProductId) {
                    foreach($this->collAccessorysRelatedByProductId as $obj) {
                        if($obj->isNew()) {
                            $collAccessorysRelatedByProductId[] = $obj;
                        }
                    }
                }

                $this->collAccessorysRelatedByProductId = $collAccessorysRelatedByProductId;
                $this->collAccessorysRelatedByProductIdPartial = false;
            }
        }

        return $this->collAccessorysRelatedByProductId;
    }

    /**
     * Sets a collection of AccessoryRelatedByProductId objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $accessorysRelatedByProductId A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setAccessorysRelatedByProductId(PropelCollection $accessorysRelatedByProductId, PropelPDO $con = null)
    {
        $this->accessorysRelatedByProductIdScheduledForDeletion = $this->getAccessorysRelatedByProductId(new Criteria(), $con)->diff($accessorysRelatedByProductId);

        foreach ($this->accessorysRelatedByProductIdScheduledForDeletion as $accessoryRelatedByProductIdRemoved) {
            $accessoryRelatedByProductIdRemoved->setProductRelatedByProductId(null);
        }

        $this->collAccessorysRelatedByProductId = null;
        foreach ($accessorysRelatedByProductId as $accessoryRelatedByProductId) {
            $this->addAccessoryRelatedByProductId($accessoryRelatedByProductId);
        }

        $this->collAccessorysRelatedByProductId = $accessorysRelatedByProductId;
        $this->collAccessorysRelatedByProductIdPartial = false;
    }

    /**
     * Returns the number of related Accessory objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Accessory objects.
     * @throws PropelException
     */
    public function countAccessorysRelatedByProductId(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collAccessorysRelatedByProductIdPartial && !$this->isNew();
        if (null === $this->collAccessorysRelatedByProductId || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAccessorysRelatedByProductId) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getAccessorysRelatedByProductId());
                }
                $query = AccessoryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProductRelatedByProductId($this)
                    ->count($con);
            }
        } else {
            return count($this->collAccessorysRelatedByProductId);
        }
    }

    /**
     * Method called to associate a Accessory object to this object
     * through the Accessory foreign key attribute.
     *
     * @param    Accessory $l Accessory
     * @return Product The current object (for fluent API support)
     */
    public function addAccessoryRelatedByProductId(Accessory $l)
    {
        if ($this->collAccessorysRelatedByProductId === null) {
            $this->initAccessorysRelatedByProductId();
            $this->collAccessorysRelatedByProductIdPartial = true;
        }
        if (!$this->collAccessorysRelatedByProductId->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddAccessoryRelatedByProductId($l);
        }

        return $this;
    }

    /**
     * @param	AccessoryRelatedByProductId $accessoryRelatedByProductId The accessoryRelatedByProductId object to add.
     */
    protected function doAddAccessoryRelatedByProductId($accessoryRelatedByProductId)
    {
        $this->collAccessorysRelatedByProductId[]= $accessoryRelatedByProductId;
        $accessoryRelatedByProductId->setProductRelatedByProductId($this);
    }

    /**
     * @param	AccessoryRelatedByProductId $accessoryRelatedByProductId The accessoryRelatedByProductId object to remove.
     */
    public function removeAccessoryRelatedByProductId($accessoryRelatedByProductId)
    {
        if ($this->getAccessorysRelatedByProductId()->contains($accessoryRelatedByProductId)) {
            $this->collAccessorysRelatedByProductId->remove($this->collAccessorysRelatedByProductId->search($accessoryRelatedByProductId));
            if (null === $this->accessorysRelatedByProductIdScheduledForDeletion) {
                $this->accessorysRelatedByProductIdScheduledForDeletion = clone $this->collAccessorysRelatedByProductId;
                $this->accessorysRelatedByProductIdScheduledForDeletion->clear();
            }
            $this->accessorysRelatedByProductIdScheduledForDeletion[]= $accessoryRelatedByProductId;
            $accessoryRelatedByProductId->setProductRelatedByProductId(null);
        }
    }

    /**
     * Clears out the collAccessorysRelatedByAccessory collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAccessorysRelatedByAccessory()
     */
    public function clearAccessorysRelatedByAccessory()
    {
        $this->collAccessorysRelatedByAccessory = null; // important to set this to null since that means it is uninitialized
        $this->collAccessorysRelatedByAccessoryPartial = null;
    }

    /**
     * reset is the collAccessorysRelatedByAccessory collection loaded partially
     *
     * @return void
     */
    public function resetPartialAccessorysRelatedByAccessory($v = true)
    {
        $this->collAccessorysRelatedByAccessoryPartial = $v;
    }

    /**
     * Initializes the collAccessorysRelatedByAccessory collection.
     *
     * By default this just sets the collAccessorysRelatedByAccessory collection to an empty array (like clearcollAccessorysRelatedByAccessory());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAccessorysRelatedByAccessory($overrideExisting = true)
    {
        if (null !== $this->collAccessorysRelatedByAccessory && !$overrideExisting) {
            return;
        }
        $this->collAccessorysRelatedByAccessory = new PropelObjectCollection();
        $this->collAccessorysRelatedByAccessory->setModel('Accessory');
    }

    /**
     * Gets an array of Accessory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Accessory[] List of Accessory objects
     * @throws PropelException
     */
    public function getAccessorysRelatedByAccessory($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collAccessorysRelatedByAccessoryPartial && !$this->isNew();
        if (null === $this->collAccessorysRelatedByAccessory || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAccessorysRelatedByAccessory) {
                // return empty collection
                $this->initAccessorysRelatedByAccessory();
            } else {
                $collAccessorysRelatedByAccessory = AccessoryQuery::create(null, $criteria)
                    ->filterByProductRelatedByAccessory($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collAccessorysRelatedByAccessoryPartial && count($collAccessorysRelatedByAccessory)) {
                      $this->initAccessorysRelatedByAccessory(false);

                      foreach($collAccessorysRelatedByAccessory as $obj) {
                        if (false == $this->collAccessorysRelatedByAccessory->contains($obj)) {
                          $this->collAccessorysRelatedByAccessory->append($obj);
                        }
                      }

                      $this->collAccessorysRelatedByAccessoryPartial = true;
                    }

                    return $collAccessorysRelatedByAccessory;
                }

                if($partial && $this->collAccessorysRelatedByAccessory) {
                    foreach($this->collAccessorysRelatedByAccessory as $obj) {
                        if($obj->isNew()) {
                            $collAccessorysRelatedByAccessory[] = $obj;
                        }
                    }
                }

                $this->collAccessorysRelatedByAccessory = $collAccessorysRelatedByAccessory;
                $this->collAccessorysRelatedByAccessoryPartial = false;
            }
        }

        return $this->collAccessorysRelatedByAccessory;
    }

    /**
     * Sets a collection of AccessoryRelatedByAccessory objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $accessorysRelatedByAccessory A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setAccessorysRelatedByAccessory(PropelCollection $accessorysRelatedByAccessory, PropelPDO $con = null)
    {
        $this->accessorysRelatedByAccessoryScheduledForDeletion = $this->getAccessorysRelatedByAccessory(new Criteria(), $con)->diff($accessorysRelatedByAccessory);

        foreach ($this->accessorysRelatedByAccessoryScheduledForDeletion as $accessoryRelatedByAccessoryRemoved) {
            $accessoryRelatedByAccessoryRemoved->setProductRelatedByAccessory(null);
        }

        $this->collAccessorysRelatedByAccessory = null;
        foreach ($accessorysRelatedByAccessory as $accessoryRelatedByAccessory) {
            $this->addAccessoryRelatedByAccessory($accessoryRelatedByAccessory);
        }

        $this->collAccessorysRelatedByAccessory = $accessorysRelatedByAccessory;
        $this->collAccessorysRelatedByAccessoryPartial = false;
    }

    /**
     * Returns the number of related Accessory objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Accessory objects.
     * @throws PropelException
     */
    public function countAccessorysRelatedByAccessory(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collAccessorysRelatedByAccessoryPartial && !$this->isNew();
        if (null === $this->collAccessorysRelatedByAccessory || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAccessorysRelatedByAccessory) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getAccessorysRelatedByAccessory());
                }
                $query = AccessoryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProductRelatedByAccessory($this)
                    ->count($con);
            }
        } else {
            return count($this->collAccessorysRelatedByAccessory);
        }
    }

    /**
     * Method called to associate a Accessory object to this object
     * through the Accessory foreign key attribute.
     *
     * @param    Accessory $l Accessory
     * @return Product The current object (for fluent API support)
     */
    public function addAccessoryRelatedByAccessory(Accessory $l)
    {
        if ($this->collAccessorysRelatedByAccessory === null) {
            $this->initAccessorysRelatedByAccessory();
            $this->collAccessorysRelatedByAccessoryPartial = true;
        }
        if (!$this->collAccessorysRelatedByAccessory->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddAccessoryRelatedByAccessory($l);
        }

        return $this;
    }

    /**
     * @param	AccessoryRelatedByAccessory $accessoryRelatedByAccessory The accessoryRelatedByAccessory object to add.
     */
    protected function doAddAccessoryRelatedByAccessory($accessoryRelatedByAccessory)
    {
        $this->collAccessorysRelatedByAccessory[]= $accessoryRelatedByAccessory;
        $accessoryRelatedByAccessory->setProductRelatedByAccessory($this);
    }

    /**
     * @param	AccessoryRelatedByAccessory $accessoryRelatedByAccessory The accessoryRelatedByAccessory object to remove.
     */
    public function removeAccessoryRelatedByAccessory($accessoryRelatedByAccessory)
    {
        if ($this->getAccessorysRelatedByAccessory()->contains($accessoryRelatedByAccessory)) {
            $this->collAccessorysRelatedByAccessory->remove($this->collAccessorysRelatedByAccessory->search($accessoryRelatedByAccessory));
            if (null === $this->accessorysRelatedByAccessoryScheduledForDeletion) {
                $this->accessorysRelatedByAccessoryScheduledForDeletion = clone $this->collAccessorysRelatedByAccessory;
                $this->accessorysRelatedByAccessoryScheduledForDeletion->clear();
            }
            $this->accessorysRelatedByAccessoryScheduledForDeletion[]= $accessoryRelatedByAccessory;
            $accessoryRelatedByAccessory->setProductRelatedByAccessory(null);
        }
    }

    /**
     * Clears out the collContentAssocs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addContentAssocs()
     */
    public function clearContentAssocs()
    {
        $this->collContentAssocs = null; // important to set this to null since that means it is uninitialized
        $this->collContentAssocsPartial = null;
    }

    /**
     * reset is the collContentAssocs collection loaded partially
     *
     * @return void
     */
    public function resetPartialContentAssocs($v = true)
    {
        $this->collContentAssocsPartial = $v;
    }

    /**
     * Initializes the collContentAssocs collection.
     *
     * By default this just sets the collContentAssocs collection to an empty array (like clearcollContentAssocs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initContentAssocs($overrideExisting = true)
    {
        if (null !== $this->collContentAssocs && !$overrideExisting) {
            return;
        }
        $this->collContentAssocs = new PropelObjectCollection();
        $this->collContentAssocs->setModel('ContentAssoc');
    }

    /**
     * Gets an array of ContentAssoc objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|ContentAssoc[] List of ContentAssoc objects
     * @throws PropelException
     */
    public function getContentAssocs($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collContentAssocsPartial && !$this->isNew();
        if (null === $this->collContentAssocs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collContentAssocs) {
                // return empty collection
                $this->initContentAssocs();
            } else {
                $collContentAssocs = ContentAssocQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collContentAssocsPartial && count($collContentAssocs)) {
                      $this->initContentAssocs(false);

                      foreach($collContentAssocs as $obj) {
                        if (false == $this->collContentAssocs->contains($obj)) {
                          $this->collContentAssocs->append($obj);
                        }
                      }

                      $this->collContentAssocsPartial = true;
                    }

                    return $collContentAssocs;
                }

                if($partial && $this->collContentAssocs) {
                    foreach($this->collContentAssocs as $obj) {
                        if($obj->isNew()) {
                            $collContentAssocs[] = $obj;
                        }
                    }
                }

                $this->collContentAssocs = $collContentAssocs;
                $this->collContentAssocsPartial = false;
            }
        }

        return $this->collContentAssocs;
    }

    /**
     * Sets a collection of ContentAssoc objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $contentAssocs A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setContentAssocs(PropelCollection $contentAssocs, PropelPDO $con = null)
    {
        $this->contentAssocsScheduledForDeletion = $this->getContentAssocs(new Criteria(), $con)->diff($contentAssocs);

        foreach ($this->contentAssocsScheduledForDeletion as $contentAssocRemoved) {
            $contentAssocRemoved->setProduct(null);
        }

        $this->collContentAssocs = null;
        foreach ($contentAssocs as $contentAssoc) {
            $this->addContentAssoc($contentAssoc);
        }

        $this->collContentAssocs = $contentAssocs;
        $this->collContentAssocsPartial = false;
    }

    /**
     * Returns the number of related ContentAssoc objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related ContentAssoc objects.
     * @throws PropelException
     */
    public function countContentAssocs(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collContentAssocsPartial && !$this->isNew();
        if (null === $this->collContentAssocs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collContentAssocs) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getContentAssocs());
                }
                $query = ContentAssocQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collContentAssocs);
        }
    }

    /**
     * Method called to associate a ContentAssoc object to this object
     * through the ContentAssoc foreign key attribute.
     *
     * @param    ContentAssoc $l ContentAssoc
     * @return Product The current object (for fluent API support)
     */
    public function addContentAssoc(ContentAssoc $l)
    {
        if ($this->collContentAssocs === null) {
            $this->initContentAssocs();
            $this->collContentAssocsPartial = true;
        }
        if (!$this->collContentAssocs->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddContentAssoc($l);
        }

        return $this;
    }

    /**
     * @param	ContentAssoc $contentAssoc The contentAssoc object to add.
     */
    protected function doAddContentAssoc($contentAssoc)
    {
        $this->collContentAssocs[]= $contentAssoc;
        $contentAssoc->setProduct($this);
    }

    /**
     * @param	ContentAssoc $contentAssoc The contentAssoc object to remove.
     */
    public function removeContentAssoc($contentAssoc)
    {
        if ($this->getContentAssocs()->contains($contentAssoc)) {
            $this->collContentAssocs->remove($this->collContentAssocs->search($contentAssoc));
            if (null === $this->contentAssocsScheduledForDeletion) {
                $this->contentAssocsScheduledForDeletion = clone $this->collContentAssocs;
                $this->contentAssocsScheduledForDeletion->clear();
            }
            $this->contentAssocsScheduledForDeletion[]= $contentAssoc;
            $contentAssoc->setProduct(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related ContentAssocs from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|ContentAssoc[] List of ContentAssoc objects
     */
    public function getContentAssocsJoinCategory($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ContentAssocQuery::create(null, $criteria);
        $query->joinWith('Category', $join_behavior);

        return $this->getContentAssocs($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related ContentAssocs from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|ContentAssoc[] List of ContentAssoc objects
     */
    public function getContentAssocsJoinContent($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ContentAssocQuery::create(null, $criteria);
        $query->joinWith('Content', $join_behavior);

        return $this->getContentAssocs($query, $con);
    }

    /**
     * Clears out the collDocuments collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addDocuments()
     */
    public function clearDocuments()
    {
        $this->collDocuments = null; // important to set this to null since that means it is uninitialized
        $this->collDocumentsPartial = null;
    }

    /**
     * reset is the collDocuments collection loaded partially
     *
     * @return void
     */
    public function resetPartialDocuments($v = true)
    {
        $this->collDocumentsPartial = $v;
    }

    /**
     * Initializes the collDocuments collection.
     *
     * By default this just sets the collDocuments collection to an empty array (like clearcollDocuments());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initDocuments($overrideExisting = true)
    {
        if (null !== $this->collDocuments && !$overrideExisting) {
            return;
        }
        $this->collDocuments = new PropelObjectCollection();
        $this->collDocuments->setModel('Document');
    }

    /**
     * Gets an array of Document objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Document[] List of Document objects
     * @throws PropelException
     */
    public function getDocuments($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collDocumentsPartial && !$this->isNew();
        if (null === $this->collDocuments || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collDocuments) {
                // return empty collection
                $this->initDocuments();
            } else {
                $collDocuments = DocumentQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collDocumentsPartial && count($collDocuments)) {
                      $this->initDocuments(false);

                      foreach($collDocuments as $obj) {
                        if (false == $this->collDocuments->contains($obj)) {
                          $this->collDocuments->append($obj);
                        }
                      }

                      $this->collDocumentsPartial = true;
                    }

                    return $collDocuments;
                }

                if($partial && $this->collDocuments) {
                    foreach($this->collDocuments as $obj) {
                        if($obj->isNew()) {
                            $collDocuments[] = $obj;
                        }
                    }
                }

                $this->collDocuments = $collDocuments;
                $this->collDocumentsPartial = false;
            }
        }

        return $this->collDocuments;
    }

    /**
     * Sets a collection of Document objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $documents A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setDocuments(PropelCollection $documents, PropelPDO $con = null)
    {
        $this->documentsScheduledForDeletion = $this->getDocuments(new Criteria(), $con)->diff($documents);

        foreach ($this->documentsScheduledForDeletion as $documentRemoved) {
            $documentRemoved->setProduct(null);
        }

        $this->collDocuments = null;
        foreach ($documents as $document) {
            $this->addDocument($document);
        }

        $this->collDocuments = $documents;
        $this->collDocumentsPartial = false;
    }

    /**
     * Returns the number of related Document objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Document objects.
     * @throws PropelException
     */
    public function countDocuments(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collDocumentsPartial && !$this->isNew();
        if (null === $this->collDocuments || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collDocuments) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getDocuments());
                }
                $query = DocumentQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collDocuments);
        }
    }

    /**
     * Method called to associate a Document object to this object
     * through the Document foreign key attribute.
     *
     * @param    Document $l Document
     * @return Product The current object (for fluent API support)
     */
    public function addDocument(Document $l)
    {
        if ($this->collDocuments === null) {
            $this->initDocuments();
            $this->collDocumentsPartial = true;
        }
        if (!$this->collDocuments->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddDocument($l);
        }

        return $this;
    }

    /**
     * @param	Document $document The document object to add.
     */
    protected function doAddDocument($document)
    {
        $this->collDocuments[]= $document;
        $document->setProduct($this);
    }

    /**
     * @param	Document $document The document object to remove.
     */
    public function removeDocument($document)
    {
        if ($this->getDocuments()->contains($document)) {
            $this->collDocuments->remove($this->collDocuments->search($document));
            if (null === $this->documentsScheduledForDeletion) {
                $this->documentsScheduledForDeletion = clone $this->collDocuments;
                $this->documentsScheduledForDeletion->clear();
            }
            $this->documentsScheduledForDeletion[]= $document;
            $document->setProduct(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Document[] List of Document objects
     */
    public function getDocumentsJoinCategory($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = DocumentQuery::create(null, $criteria);
        $query->joinWith('Category', $join_behavior);

        return $this->getDocuments($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Document[] List of Document objects
     */
    public function getDocumentsJoinContent($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = DocumentQuery::create(null, $criteria);
        $query->joinWith('Content', $join_behavior);

        return $this->getDocuments($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Documents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Document[] List of Document objects
     */
    public function getDocumentsJoinFolder($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = DocumentQuery::create(null, $criteria);
        $query->joinWith('Folder', $join_behavior);

        return $this->getDocuments($query, $con);
    }

    /**
     * Clears out the collFeatureProds collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureProds()
     */
    public function clearFeatureProds()
    {
        $this->collFeatureProds = null; // important to set this to null since that means it is uninitialized
        $this->collFeatureProdsPartial = null;
    }

    /**
     * reset is the collFeatureProds collection loaded partially
     *
     * @return void
     */
    public function resetPartialFeatureProds($v = true)
    {
        $this->collFeatureProdsPartial = $v;
    }

    /**
     * Initializes the collFeatureProds collection.
     *
     * By default this just sets the collFeatureProds collection to an empty array (like clearcollFeatureProds());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureProds($overrideExisting = true)
    {
        if (null !== $this->collFeatureProds && !$overrideExisting) {
            return;
        }
        $this->collFeatureProds = new PropelObjectCollection();
        $this->collFeatureProds->setModel('FeatureProd');
    }

    /**
     * Gets an array of FeatureProd objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|FeatureProd[] List of FeatureProd objects
     * @throws PropelException
     */
    public function getFeatureProds($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collFeatureProdsPartial && !$this->isNew();
        if (null === $this->collFeatureProds || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureProds) {
                // return empty collection
                $this->initFeatureProds();
            } else {
                $collFeatureProds = FeatureProdQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collFeatureProdsPartial && count($collFeatureProds)) {
                      $this->initFeatureProds(false);

                      foreach($collFeatureProds as $obj) {
                        if (false == $this->collFeatureProds->contains($obj)) {
                          $this->collFeatureProds->append($obj);
                        }
                      }

                      $this->collFeatureProdsPartial = true;
                    }

                    return $collFeatureProds;
                }

                if($partial && $this->collFeatureProds) {
                    foreach($this->collFeatureProds as $obj) {
                        if($obj->isNew()) {
                            $collFeatureProds[] = $obj;
                        }
                    }
                }

                $this->collFeatureProds = $collFeatureProds;
                $this->collFeatureProdsPartial = false;
            }
        }

        return $this->collFeatureProds;
    }

    /**
     * Sets a collection of FeatureProd objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $featureProds A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setFeatureProds(PropelCollection $featureProds, PropelPDO $con = null)
    {
        $this->featureProdsScheduledForDeletion = $this->getFeatureProds(new Criteria(), $con)->diff($featureProds);

        foreach ($this->featureProdsScheduledForDeletion as $featureProdRemoved) {
            $featureProdRemoved->setProduct(null);
        }

        $this->collFeatureProds = null;
        foreach ($featureProds as $featureProd) {
            $this->addFeatureProd($featureProd);
        }

        $this->collFeatureProds = $featureProds;
        $this->collFeatureProdsPartial = false;
    }

    /**
     * Returns the number of related FeatureProd objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related FeatureProd objects.
     * @throws PropelException
     */
    public function countFeatureProds(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collFeatureProdsPartial && !$this->isNew();
        if (null === $this->collFeatureProds || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureProds) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getFeatureProds());
                }
                $query = FeatureProdQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collFeatureProds);
        }
    }

    /**
     * Method called to associate a FeatureProd object to this object
     * through the FeatureProd foreign key attribute.
     *
     * @param    FeatureProd $l FeatureProd
     * @return Product The current object (for fluent API support)
     */
    public function addFeatureProd(FeatureProd $l)
    {
        if ($this->collFeatureProds === null) {
            $this->initFeatureProds();
            $this->collFeatureProdsPartial = true;
        }
        if (!$this->collFeatureProds->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureProd($l);
        }

        return $this;
    }

    /**
     * @param	FeatureProd $featureProd The featureProd object to add.
     */
    protected function doAddFeatureProd($featureProd)
    {
        $this->collFeatureProds[]= $featureProd;
        $featureProd->setProduct($this);
    }

    /**
     * @param	FeatureProd $featureProd The featureProd object to remove.
     */
    public function removeFeatureProd($featureProd)
    {
        if ($this->getFeatureProds()->contains($featureProd)) {
            $this->collFeatureProds->remove($this->collFeatureProds->search($featureProd));
            if (null === $this->featureProdsScheduledForDeletion) {
                $this->featureProdsScheduledForDeletion = clone $this->collFeatureProds;
                $this->featureProdsScheduledForDeletion->clear();
            }
            $this->featureProdsScheduledForDeletion[]= $featureProd;
            $featureProd->setProduct(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related FeatureProds from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|FeatureProd[] List of FeatureProd objects
     */
    public function getFeatureProdsJoinFeatureAv($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = FeatureProdQuery::create(null, $criteria);
        $query->joinWith('FeatureAv', $join_behavior);

        return $this->getFeatureProds($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related FeatureProds from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|FeatureProd[] List of FeatureProd objects
     */
    public function getFeatureProdsJoinFeature($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = FeatureProdQuery::create(null, $criteria);
        $query->joinWith('Feature', $join_behavior);

        return $this->getFeatureProds($query, $con);
    }

    /**
     * Clears out the collImages collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addImages()
     */
    public function clearImages()
    {
        $this->collImages = null; // important to set this to null since that means it is uninitialized
        $this->collImagesPartial = null;
    }

    /**
     * reset is the collImages collection loaded partially
     *
     * @return void
     */
    public function resetPartialImages($v = true)
    {
        $this->collImagesPartial = $v;
    }

    /**
     * Initializes the collImages collection.
     *
     * By default this just sets the collImages collection to an empty array (like clearcollImages());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initImages($overrideExisting = true)
    {
        if (null !== $this->collImages && !$overrideExisting) {
            return;
        }
        $this->collImages = new PropelObjectCollection();
        $this->collImages->setModel('Image');
    }

    /**
     * Gets an array of Image objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Image[] List of Image objects
     * @throws PropelException
     */
    public function getImages($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collImagesPartial && !$this->isNew();
        if (null === $this->collImages || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collImages) {
                // return empty collection
                $this->initImages();
            } else {
                $collImages = ImageQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collImagesPartial && count($collImages)) {
                      $this->initImages(false);

                      foreach($collImages as $obj) {
                        if (false == $this->collImages->contains($obj)) {
                          $this->collImages->append($obj);
                        }
                      }

                      $this->collImagesPartial = true;
                    }

                    return $collImages;
                }

                if($partial && $this->collImages) {
                    foreach($this->collImages as $obj) {
                        if($obj->isNew()) {
                            $collImages[] = $obj;
                        }
                    }
                }

                $this->collImages = $collImages;
                $this->collImagesPartial = false;
            }
        }

        return $this->collImages;
    }

    /**
     * Sets a collection of Image objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $images A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setImages(PropelCollection $images, PropelPDO $con = null)
    {
        $this->imagesScheduledForDeletion = $this->getImages(new Criteria(), $con)->diff($images);

        foreach ($this->imagesScheduledForDeletion as $imageRemoved) {
            $imageRemoved->setProduct(null);
        }

        $this->collImages = null;
        foreach ($images as $image) {
            $this->addImage($image);
        }

        $this->collImages = $images;
        $this->collImagesPartial = false;
    }

    /**
     * Returns the number of related Image objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Image objects.
     * @throws PropelException
     */
    public function countImages(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collImagesPartial && !$this->isNew();
        if (null === $this->collImages || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collImages) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getImages());
                }
                $query = ImageQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collImages);
        }
    }

    /**
     * Method called to associate a Image object to this object
     * through the Image foreign key attribute.
     *
     * @param    Image $l Image
     * @return Product The current object (for fluent API support)
     */
    public function addImage(Image $l)
    {
        if ($this->collImages === null) {
            $this->initImages();
            $this->collImagesPartial = true;
        }
        if (!$this->collImages->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddImage($l);
        }

        return $this;
    }

    /**
     * @param	Image $image The image object to add.
     */
    protected function doAddImage($image)
    {
        $this->collImages[]= $image;
        $image->setProduct($this);
    }

    /**
     * @param	Image $image The image object to remove.
     */
    public function removeImage($image)
    {
        if ($this->getImages()->contains($image)) {
            $this->collImages->remove($this->collImages->search($image));
            if (null === $this->imagesScheduledForDeletion) {
                $this->imagesScheduledForDeletion = clone $this->collImages;
                $this->imagesScheduledForDeletion->clear();
            }
            $this->imagesScheduledForDeletion[]= $image;
            $image->setProduct(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Image[] List of Image objects
     */
    public function getImagesJoinCategory($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ImageQuery::create(null, $criteria);
        $query->joinWith('Category', $join_behavior);

        return $this->getImages($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Image[] List of Image objects
     */
    public function getImagesJoinContent($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ImageQuery::create(null, $criteria);
        $query->joinWith('Content', $join_behavior);

        return $this->getImages($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Images from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Image[] List of Image objects
     */
    public function getImagesJoinFolder($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ImageQuery::create(null, $criteria);
        $query->joinWith('Folder', $join_behavior);

        return $this->getImages($query, $con);
    }

    /**
     * Clears out the collProductCategorys collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductCategorys()
     */
    public function clearProductCategorys()
    {
        $this->collProductCategorys = null; // important to set this to null since that means it is uninitialized
        $this->collProductCategorysPartial = null;
    }

    /**
     * reset is the collProductCategorys collection loaded partially
     *
     * @return void
     */
    public function resetPartialProductCategorys($v = true)
    {
        $this->collProductCategorysPartial = $v;
    }

    /**
     * Initializes the collProductCategorys collection.
     *
     * By default this just sets the collProductCategorys collection to an empty array (like clearcollProductCategorys());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductCategorys($overrideExisting = true)
    {
        if (null !== $this->collProductCategorys && !$overrideExisting) {
            return;
        }
        $this->collProductCategorys = new PropelObjectCollection();
        $this->collProductCategorys->setModel('ProductCategory');
    }

    /**
     * Gets an array of ProductCategory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|ProductCategory[] List of ProductCategory objects
     * @throws PropelException
     */
    public function getProductCategorys($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collProductCategorysPartial && !$this->isNew();
        if (null === $this->collProductCategorys || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductCategorys) {
                // return empty collection
                $this->initProductCategorys();
            } else {
                $collProductCategorys = ProductCategoryQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collProductCategorysPartial && count($collProductCategorys)) {
                      $this->initProductCategorys(false);

                      foreach($collProductCategorys as $obj) {
                        if (false == $this->collProductCategorys->contains($obj)) {
                          $this->collProductCategorys->append($obj);
                        }
                      }

                      $this->collProductCategorysPartial = true;
                    }

                    return $collProductCategorys;
                }

                if($partial && $this->collProductCategorys) {
                    foreach($this->collProductCategorys as $obj) {
                        if($obj->isNew()) {
                            $collProductCategorys[] = $obj;
                        }
                    }
                }

                $this->collProductCategorys = $collProductCategorys;
                $this->collProductCategorysPartial = false;
            }
        }

        return $this->collProductCategorys;
    }

    /**
     * Sets a collection of ProductCategory objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $productCategorys A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setProductCategorys(PropelCollection $productCategorys, PropelPDO $con = null)
    {
        $this->productCategorysScheduledForDeletion = $this->getProductCategorys(new Criteria(), $con)->diff($productCategorys);

        foreach ($this->productCategorysScheduledForDeletion as $productCategoryRemoved) {
            $productCategoryRemoved->setProduct(null);
        }

        $this->collProductCategorys = null;
        foreach ($productCategorys as $productCategory) {
            $this->addProductCategory($productCategory);
        }

        $this->collProductCategorys = $productCategorys;
        $this->collProductCategorysPartial = false;
    }

    /**
     * Returns the number of related ProductCategory objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related ProductCategory objects.
     * @throws PropelException
     */
    public function countProductCategorys(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collProductCategorysPartial && !$this->isNew();
        if (null === $this->collProductCategorys || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductCategorys) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getProductCategorys());
                }
                $query = ProductCategoryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collProductCategorys);
        }
    }

    /**
     * Method called to associate a ProductCategory object to this object
     * through the ProductCategory foreign key attribute.
     *
     * @param    ProductCategory $l ProductCategory
     * @return Product The current object (for fluent API support)
     */
    public function addProductCategory(ProductCategory $l)
    {
        if ($this->collProductCategorys === null) {
            $this->initProductCategorys();
            $this->collProductCategorysPartial = true;
        }
        if (!$this->collProductCategorys->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddProductCategory($l);
        }

        return $this;
    }

    /**
     * @param	ProductCategory $productCategory The productCategory object to add.
     */
    protected function doAddProductCategory($productCategory)
    {
        $this->collProductCategorys[]= $productCategory;
        $productCategory->setProduct($this);
    }

    /**
     * @param	ProductCategory $productCategory The productCategory object to remove.
     */
    public function removeProductCategory($productCategory)
    {
        if ($this->getProductCategorys()->contains($productCategory)) {
            $this->collProductCategorys->remove($this->collProductCategorys->search($productCategory));
            if (null === $this->productCategorysScheduledForDeletion) {
                $this->productCategorysScheduledForDeletion = clone $this->collProductCategorys;
                $this->productCategorysScheduledForDeletion->clear();
            }
            $this->productCategorysScheduledForDeletion[]= $productCategory;
            $productCategory->setProduct(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related ProductCategorys from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|ProductCategory[] List of ProductCategory objects
     */
    public function getProductCategorysJoinCategory($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = ProductCategoryQuery::create(null, $criteria);
        $query->joinWith('Category', $join_behavior);

        return $this->getProductCategorys($query, $con);
    }

    /**
     * Clears out the collProductDescs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductDescs()
     */
    public function clearProductDescs()
    {
        $this->collProductDescs = null; // important to set this to null since that means it is uninitialized
        $this->collProductDescsPartial = null;
    }

    /**
     * reset is the collProductDescs collection loaded partially
     *
     * @return void
     */
    public function resetPartialProductDescs($v = true)
    {
        $this->collProductDescsPartial = $v;
    }

    /**
     * Initializes the collProductDescs collection.
     *
     * By default this just sets the collProductDescs collection to an empty array (like clearcollProductDescs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductDescs($overrideExisting = true)
    {
        if (null !== $this->collProductDescs && !$overrideExisting) {
            return;
        }
        $this->collProductDescs = new PropelObjectCollection();
        $this->collProductDescs->setModel('ProductDesc');
    }

    /**
     * Gets an array of ProductDesc objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|ProductDesc[] List of ProductDesc objects
     * @throws PropelException
     */
    public function getProductDescs($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collProductDescsPartial && !$this->isNew();
        if (null === $this->collProductDescs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductDescs) {
                // return empty collection
                $this->initProductDescs();
            } else {
                $collProductDescs = ProductDescQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collProductDescsPartial && count($collProductDescs)) {
                      $this->initProductDescs(false);

                      foreach($collProductDescs as $obj) {
                        if (false == $this->collProductDescs->contains($obj)) {
                          $this->collProductDescs->append($obj);
                        }
                      }

                      $this->collProductDescsPartial = true;
                    }

                    return $collProductDescs;
                }

                if($partial && $this->collProductDescs) {
                    foreach($this->collProductDescs as $obj) {
                        if($obj->isNew()) {
                            $collProductDescs[] = $obj;
                        }
                    }
                }

                $this->collProductDescs = $collProductDescs;
                $this->collProductDescsPartial = false;
            }
        }

        return $this->collProductDescs;
    }

    /**
     * Sets a collection of ProductDesc objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $productDescs A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setProductDescs(PropelCollection $productDescs, PropelPDO $con = null)
    {
        $this->productDescsScheduledForDeletion = $this->getProductDescs(new Criteria(), $con)->diff($productDescs);

        foreach ($this->productDescsScheduledForDeletion as $productDescRemoved) {
            $productDescRemoved->setProduct(null);
        }

        $this->collProductDescs = null;
        foreach ($productDescs as $productDesc) {
            $this->addProductDesc($productDesc);
        }

        $this->collProductDescs = $productDescs;
        $this->collProductDescsPartial = false;
    }

    /**
     * Returns the number of related ProductDesc objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related ProductDesc objects.
     * @throws PropelException
     */
    public function countProductDescs(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collProductDescsPartial && !$this->isNew();
        if (null === $this->collProductDescs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductDescs) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getProductDescs());
                }
                $query = ProductDescQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collProductDescs);
        }
    }

    /**
     * Method called to associate a ProductDesc object to this object
     * through the ProductDesc foreign key attribute.
     *
     * @param    ProductDesc $l ProductDesc
     * @return Product The current object (for fluent API support)
     */
    public function addProductDesc(ProductDesc $l)
    {
        if ($this->collProductDescs === null) {
            $this->initProductDescs();
            $this->collProductDescsPartial = true;
        }
        if (!$this->collProductDescs->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddProductDesc($l);
        }

        return $this;
    }

    /**
     * @param	ProductDesc $productDesc The productDesc object to add.
     */
    protected function doAddProductDesc($productDesc)
    {
        $this->collProductDescs[]= $productDesc;
        $productDesc->setProduct($this);
    }

    /**
     * @param	ProductDesc $productDesc The productDesc object to remove.
     */
    public function removeProductDesc($productDesc)
    {
        if ($this->getProductDescs()->contains($productDesc)) {
            $this->collProductDescs->remove($this->collProductDescs->search($productDesc));
            if (null === $this->productDescsScheduledForDeletion) {
                $this->productDescsScheduledForDeletion = clone $this->collProductDescs;
                $this->productDescsScheduledForDeletion->clear();
            }
            $this->productDescsScheduledForDeletion[]= $productDesc;
            $productDesc->setProduct(null);
        }
    }

    /**
     * Clears out the collRewritings collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addRewritings()
     */
    public function clearRewritings()
    {
        $this->collRewritings = null; // important to set this to null since that means it is uninitialized
        $this->collRewritingsPartial = null;
    }

    /**
     * reset is the collRewritings collection loaded partially
     *
     * @return void
     */
    public function resetPartialRewritings($v = true)
    {
        $this->collRewritingsPartial = $v;
    }

    /**
     * Initializes the collRewritings collection.
     *
     * By default this just sets the collRewritings collection to an empty array (like clearcollRewritings());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initRewritings($overrideExisting = true)
    {
        if (null !== $this->collRewritings && !$overrideExisting) {
            return;
        }
        $this->collRewritings = new PropelObjectCollection();
        $this->collRewritings->setModel('Rewriting');
    }

    /**
     * Gets an array of Rewriting objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Rewriting[] List of Rewriting objects
     * @throws PropelException
     */
    public function getRewritings($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collRewritingsPartial && !$this->isNew();
        if (null === $this->collRewritings || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collRewritings) {
                // return empty collection
                $this->initRewritings();
            } else {
                $collRewritings = RewritingQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collRewritingsPartial && count($collRewritings)) {
                      $this->initRewritings(false);

                      foreach($collRewritings as $obj) {
                        if (false == $this->collRewritings->contains($obj)) {
                          $this->collRewritings->append($obj);
                        }
                      }

                      $this->collRewritingsPartial = true;
                    }

                    return $collRewritings;
                }

                if($partial && $this->collRewritings) {
                    foreach($this->collRewritings as $obj) {
                        if($obj->isNew()) {
                            $collRewritings[] = $obj;
                        }
                    }
                }

                $this->collRewritings = $collRewritings;
                $this->collRewritingsPartial = false;
            }
        }

        return $this->collRewritings;
    }

    /**
     * Sets a collection of Rewriting objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $rewritings A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setRewritings(PropelCollection $rewritings, PropelPDO $con = null)
    {
        $this->rewritingsScheduledForDeletion = $this->getRewritings(new Criteria(), $con)->diff($rewritings);

        foreach ($this->rewritingsScheduledForDeletion as $rewritingRemoved) {
            $rewritingRemoved->setProduct(null);
        }

        $this->collRewritings = null;
        foreach ($rewritings as $rewriting) {
            $this->addRewriting($rewriting);
        }

        $this->collRewritings = $rewritings;
        $this->collRewritingsPartial = false;
    }

    /**
     * Returns the number of related Rewriting objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Rewriting objects.
     * @throws PropelException
     */
    public function countRewritings(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collRewritingsPartial && !$this->isNew();
        if (null === $this->collRewritings || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collRewritings) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getRewritings());
                }
                $query = RewritingQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collRewritings);
        }
    }

    /**
     * Method called to associate a Rewriting object to this object
     * through the Rewriting foreign key attribute.
     *
     * @param    Rewriting $l Rewriting
     * @return Product The current object (for fluent API support)
     */
    public function addRewriting(Rewriting $l)
    {
        if ($this->collRewritings === null) {
            $this->initRewritings();
            $this->collRewritingsPartial = true;
        }
        if (!$this->collRewritings->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddRewriting($l);
        }

        return $this;
    }

    /**
     * @param	Rewriting $rewriting The rewriting object to add.
     */
    protected function doAddRewriting($rewriting)
    {
        $this->collRewritings[]= $rewriting;
        $rewriting->setProduct($this);
    }

    /**
     * @param	Rewriting $rewriting The rewriting object to remove.
     */
    public function removeRewriting($rewriting)
    {
        if ($this->getRewritings()->contains($rewriting)) {
            $this->collRewritings->remove($this->collRewritings->search($rewriting));
            if (null === $this->rewritingsScheduledForDeletion) {
                $this->rewritingsScheduledForDeletion = clone $this->collRewritings;
                $this->rewritingsScheduledForDeletion->clear();
            }
            $this->rewritingsScheduledForDeletion[]= $rewriting;
            $rewriting->setProduct(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Rewriting[] List of Rewriting objects
     */
    public function getRewritingsJoinCategory($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = RewritingQuery::create(null, $criteria);
        $query->joinWith('Category', $join_behavior);

        return $this->getRewritings($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Rewriting[] List of Rewriting objects
     */
    public function getRewritingsJoinFolder($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = RewritingQuery::create(null, $criteria);
        $query->joinWith('Folder', $join_behavior);

        return $this->getRewritings($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Rewritings from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Rewriting[] List of Rewriting objects
     */
    public function getRewritingsJoinContent($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = RewritingQuery::create(null, $criteria);
        $query->joinWith('Content', $join_behavior);

        return $this->getRewritings($query, $con);
    }

    /**
     * Clears out the collStocks collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addStocks()
     */
    public function clearStocks()
    {
        $this->collStocks = null; // important to set this to null since that means it is uninitialized
        $this->collStocksPartial = null;
    }

    /**
     * reset is the collStocks collection loaded partially
     *
     * @return void
     */
    public function resetPartialStocks($v = true)
    {
        $this->collStocksPartial = $v;
    }

    /**
     * Initializes the collStocks collection.
     *
     * By default this just sets the collStocks collection to an empty array (like clearcollStocks());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initStocks($overrideExisting = true)
    {
        if (null !== $this->collStocks && !$overrideExisting) {
            return;
        }
        $this->collStocks = new PropelObjectCollection();
        $this->collStocks->setModel('Stock');
    }

    /**
     * Gets an array of Stock objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this Product is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @return PropelObjectCollection|Stock[] List of Stock objects
     * @throws PropelException
     */
    public function getStocks($criteria = null, PropelPDO $con = null)
    {
        $partial = $this->collStocksPartial && !$this->isNew();
        if (null === $this->collStocks || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collStocks) {
                // return empty collection
                $this->initStocks();
            } else {
                $collStocks = StockQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    if (false !== $this->collStocksPartial && count($collStocks)) {
                      $this->initStocks(false);

                      foreach($collStocks as $obj) {
                        if (false == $this->collStocks->contains($obj)) {
                          $this->collStocks->append($obj);
                        }
                      }

                      $this->collStocksPartial = true;
                    }

                    return $collStocks;
                }

                if($partial && $this->collStocks) {
                    foreach($this->collStocks as $obj) {
                        if($obj->isNew()) {
                            $collStocks[] = $obj;
                        }
                    }
                }

                $this->collStocks = $collStocks;
                $this->collStocksPartial = false;
            }
        }

        return $this->collStocks;
    }

    /**
     * Sets a collection of Stock objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param PropelCollection $stocks A Propel collection.
     * @param PropelPDO $con Optional connection object
     */
    public function setStocks(PropelCollection $stocks, PropelPDO $con = null)
    {
        $this->stocksScheduledForDeletion = $this->getStocks(new Criteria(), $con)->diff($stocks);

        foreach ($this->stocksScheduledForDeletion as $stockRemoved) {
            $stockRemoved->setProduct(null);
        }

        $this->collStocks = null;
        foreach ($stocks as $stock) {
            $this->addStock($stock);
        }

        $this->collStocks = $stocks;
        $this->collStocksPartial = false;
    }

    /**
     * Returns the number of related Stock objects.
     *
     * @param Criteria $criteria
     * @param boolean $distinct
     * @param PropelPDO $con
     * @return int             Count of related Stock objects.
     * @throws PropelException
     */
    public function countStocks(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
    {
        $partial = $this->collStocksPartial && !$this->isNew();
        if (null === $this->collStocks || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collStocks) {
                return 0;
            } else {
                if($partial && !$criteria) {
                    return count($this->getStocks());
                }
                $query = StockQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collStocks);
        }
    }

    /**
     * Method called to associate a Stock object to this object
     * through the Stock foreign key attribute.
     *
     * @param    Stock $l Stock
     * @return Product The current object (for fluent API support)
     */
    public function addStock(Stock $l)
    {
        if ($this->collStocks === null) {
            $this->initStocks();
            $this->collStocksPartial = true;
        }
        if (!$this->collStocks->contains($l)) { // only add it if the **same** object is not already associated
            $this->doAddStock($l);
        }

        return $this;
    }

    /**
     * @param	Stock $stock The stock object to add.
     */
    protected function doAddStock($stock)
    {
        $this->collStocks[]= $stock;
        $stock->setProduct($this);
    }

    /**
     * @param	Stock $stock The stock object to remove.
     */
    public function removeStock($stock)
    {
        if ($this->getStocks()->contains($stock)) {
            $this->collStocks->remove($this->collStocks->search($stock));
            if (null === $this->stocksScheduledForDeletion) {
                $this->stocksScheduledForDeletion = clone $this->collStocks;
                $this->stocksScheduledForDeletion->clear();
            }
            $this->stocksScheduledForDeletion[]= $stock;
            $stock->setProduct(null);
        }
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related Stocks from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param Criteria $criteria optional Criteria object to narrow the query
     * @param PropelPDO $con optional connection object
     * @param string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return PropelObjectCollection|Stock[] List of Stock objects
     */
    public function getStocksJoinCombination($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $query = StockQuery::create(null, $criteria);
        $query->joinWith('Combination', $join_behavior);

        return $this->getStocks($query, $con);
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
            if ($this->collAccessorysRelatedByProductId) {
                foreach ($this->collAccessorysRelatedByProductId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAccessorysRelatedByAccessory) {
                foreach ($this->collAccessorysRelatedByAccessory as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collContentAssocs) {
                foreach ($this->collContentAssocs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collDocuments) {
                foreach ($this->collDocuments as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureProds) {
                foreach ($this->collFeatureProds as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collImages) {
                foreach ($this->collImages as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductCategorys) {
                foreach ($this->collProductCategorys as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductDescs) {
                foreach ($this->collProductDescs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collRewritings) {
                foreach ($this->collRewritings as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collStocks) {
                foreach ($this->collStocks as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        if ($this->collAccessorysRelatedByProductId instanceof PropelCollection) {
            $this->collAccessorysRelatedByProductId->clearIterator();
        }
        $this->collAccessorysRelatedByProductId = null;
        if ($this->collAccessorysRelatedByAccessory instanceof PropelCollection) {
            $this->collAccessorysRelatedByAccessory->clearIterator();
        }
        $this->collAccessorysRelatedByAccessory = null;
        if ($this->collContentAssocs instanceof PropelCollection) {
            $this->collContentAssocs->clearIterator();
        }
        $this->collContentAssocs = null;
        if ($this->collDocuments instanceof PropelCollection) {
            $this->collDocuments->clearIterator();
        }
        $this->collDocuments = null;
        if ($this->collFeatureProds instanceof PropelCollection) {
            $this->collFeatureProds->clearIterator();
        }
        $this->collFeatureProds = null;
        if ($this->collImages instanceof PropelCollection) {
            $this->collImages->clearIterator();
        }
        $this->collImages = null;
        if ($this->collProductCategorys instanceof PropelCollection) {
            $this->collProductCategorys->clearIterator();
        }
        $this->collProductCategorys = null;
        if ($this->collProductDescs instanceof PropelCollection) {
            $this->collProductDescs->clearIterator();
        }
        $this->collProductDescs = null;
        if ($this->collRewritings instanceof PropelCollection) {
            $this->collRewritings->clearIterator();
        }
        $this->collRewritings = null;
        if ($this->collStocks instanceof PropelCollection) {
            $this->collStocks->clearIterator();
        }
        $this->collStocks = null;
        $this->aTaxRule = null;
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
