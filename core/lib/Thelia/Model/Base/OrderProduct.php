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
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderAttributeCombination as ChildOrderAttributeCombination;
use Thelia\Model\OrderAttributeCombinationQuery as ChildOrderAttributeCombinationQuery;
use Thelia\Model\OrderProduct as ChildOrderProduct;
use Thelia\Model\OrderProductQuery as ChildOrderProductQuery;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\Map\OrderProductTableMap;

abstract class OrderProduct implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\OrderProductTableMap';


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
     * The value for the order_id field.
     * @var        int
     */
    protected $order_id;

    /**
     * The value for the product_ref field.
     * @var        string
     */
    protected $product_ref;

    /**
     * The value for the product_sale_elements_ref field.
     * @var        string
     */
    protected $product_sale_elements_ref;

    /**
     * The value for the title field.
     * @var        string
     */
    protected $title;

    /**
     * The value for the chapo field.
     * @var        string
     */
    protected $chapo;

    /**
     * The value for the description field.
     * @var        string
     */
    protected $description;

    /**
     * The value for the postscriptum field.
     * @var        string
     */
    protected $postscriptum;

    /**
     * The value for the quantity field.
     * @var        double
     */
    protected $quantity;

    /**
     * The value for the price field.
     * @var        double
     */
    protected $price;

    /**
     * The value for the promo_price field.
     * @var        string
     */
    protected $promo_price;

    /**
     * The value for the was_new field.
     * @var        int
     */
    protected $was_new;

    /**
     * The value for the was_in_promo field.
     * @var        int
     */
    protected $was_in_promo;

    /**
     * The value for the weight field.
     * @var        string
     */
    protected $weight;

    /**
     * The value for the tax field.
     * @var        double
     */
    protected $tax;

    /**
     * The value for the parent field.
     * @var        int
     */
    protected $parent;

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
     * @var        Order
     */
    protected $aOrder;

    /**
     * @var        ObjectCollection|ChildOrderAttributeCombination[] Collection to store aggregation of ChildOrderAttributeCombination objects.
     */
    protected $collOrderAttributeCombinations;
    protected $collOrderAttributeCombinationsPartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $orderAttributeCombinationsScheduledForDeletion = null;

    /**
     * Initializes internal state of Thelia\Model\Base\OrderProduct object.
     */
    public function __construct()
    {
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !empty($this->modifiedColumns);
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return in_array($col, $this->modifiedColumns);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return array_unique($this->modifiedColumns);
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return true, if the object has never been persisted.
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
            while (false !== ($offset = array_search($col, $this->modifiedColumns))) {
                array_splice($this->modifiedColumns, $offset, 1);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>OrderProduct</code> instance.  If
     * <code>obj</code> is an instance of <code>OrderProduct</code>, delegates to
     * <code>equals(OrderProduct)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param      obj The object to compare to.
     * @return Whether equal to the object specified.
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
     * @param string $name The virtual column name
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
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return array_key_exists($name, $this->virtualColumns);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @return mixed
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
     * @return OrderProduct The current object, for fluid interface
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
     * @return OrderProduct The current object, for fluid interface
     */
    public function importFrom($parser, $data)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $this->fromArray($parser->toArray($data), TableMap::TYPE_PHPNAME);
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
     * Get the [order_id] column value.
     *
     * @return   int
     */
    public function getOrderId()
    {

        return $this->order_id;
    }

    /**
     * Get the [product_ref] column value.
     *
     * @return   string
     */
    public function getProductRef()
    {

        return $this->product_ref;
    }

    /**
     * Get the [product_sale_elements_ref] column value.
     *
     * @return   string
     */
    public function getProductSaleElementsRef()
    {

        return $this->product_sale_elements_ref;
    }

    /**
     * Get the [title] column value.
     *
     * @return   string
     */
    public function getTitle()
    {

        return $this->title;
    }

    /**
     * Get the [chapo] column value.
     *
     * @return   string
     */
    public function getChapo()
    {

        return $this->chapo;
    }

    /**
     * Get the [description] column value.
     *
     * @return   string
     */
    public function getDescription()
    {

        return $this->description;
    }

    /**
     * Get the [postscriptum] column value.
     *
     * @return   string
     */
    public function getPostscriptum()
    {

        return $this->postscriptum;
    }

    /**
     * Get the [quantity] column value.
     *
     * @return   double
     */
    public function getQuantity()
    {

        return $this->quantity;
    }

    /**
     * Get the [price] column value.
     *
     * @return   double
     */
    public function getPrice()
    {

        return $this->price;
    }

    /**
     * Get the [promo_price] column value.
     *
     * @return   string
     */
    public function getPromoPrice()
    {

        return $this->promo_price;
    }

    /**
     * Get the [was_new] column value.
     *
     * @return   int
     */
    public function getWasNew()
    {

        return $this->was_new;
    }

    /**
     * Get the [was_in_promo] column value.
     *
     * @return   int
     */
    public function getWasInPromo()
    {

        return $this->was_in_promo;
    }

    /**
     * Get the [weight] column value.
     *
     * @return   string
     */
    public function getWeight()
    {

        return $this->weight;
    }

    /**
     * Get the [tax] column value.
     *
     * @return   double
     */
    public function getTax()
    {

        return $this->tax;
    }

    /**
     * Get the [parent] column value.
     * not managed yet
     * @return   int
     */
    public function getParent()
    {

        return $this->parent;
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
            return $this->created_at !== null ? $this->created_at->format($format) : null;
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
            return $this->updated_at !== null ? $this->updated_at->format($format) : null;
        }
    }

    /**
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = OrderProductTableMap::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [order_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setOrderId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->order_id !== $v) {
            $this->order_id = $v;
            $this->modifiedColumns[] = OrderProductTableMap::ORDER_ID;
        }

        if ($this->aOrder !== null && $this->aOrder->getId() !== $v) {
            $this->aOrder = null;
        }


        return $this;
    } // setOrderId()

    /**
     * Set the value of [product_ref] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setProductRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->product_ref !== $v) {
            $this->product_ref = $v;
            $this->modifiedColumns[] = OrderProductTableMap::PRODUCT_REF;
        }


        return $this;
    } // setProductRef()

    /**
     * Set the value of [product_sale_elements_ref] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setProductSaleElementsRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->product_sale_elements_ref !== $v) {
            $this->product_sale_elements_ref = $v;
            $this->modifiedColumns[] = OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF;
        }


        return $this;
    } // setProductSaleElementsRef()

    /**
     * Set the value of [title] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setTitle($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->title !== $v) {
            $this->title = $v;
            $this->modifiedColumns[] = OrderProductTableMap::TITLE;
        }


        return $this;
    } // setTitle()

    /**
     * Set the value of [chapo] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setChapo($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->chapo !== $v) {
            $this->chapo = $v;
            $this->modifiedColumns[] = OrderProductTableMap::CHAPO;
        }


        return $this;
    } // setChapo()

    /**
     * Set the value of [description] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setDescription($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->description !== $v) {
            $this->description = $v;
            $this->modifiedColumns[] = OrderProductTableMap::DESCRIPTION;
        }


        return $this;
    } // setDescription()

    /**
     * Set the value of [postscriptum] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setPostscriptum($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->postscriptum !== $v) {
            $this->postscriptum = $v;
            $this->modifiedColumns[] = OrderProductTableMap::POSTSCRIPTUM;
        }


        return $this;
    } // setPostscriptum()

    /**
     * Set the value of [quantity] column.
     *
     * @param      double $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setQuantity($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->quantity !== $v) {
            $this->quantity = $v;
            $this->modifiedColumns[] = OrderProductTableMap::QUANTITY;
        }


        return $this;
    } // setQuantity()

    /**
     * Set the value of [price] column.
     *
     * @param      double $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setPrice($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->price !== $v) {
            $this->price = $v;
            $this->modifiedColumns[] = OrderProductTableMap::PRICE;
        }


        return $this;
    } // setPrice()

    /**
     * Set the value of [promo_price] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setPromoPrice($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->promo_price !== $v) {
            $this->promo_price = $v;
            $this->modifiedColumns[] = OrderProductTableMap::PROMO_PRICE;
        }


        return $this;
    } // setPromoPrice()

    /**
     * Set the value of [was_new] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setWasNew($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->was_new !== $v) {
            $this->was_new = $v;
            $this->modifiedColumns[] = OrderProductTableMap::WAS_NEW;
        }


        return $this;
    } // setWasNew()

    /**
     * Set the value of [was_in_promo] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setWasInPromo($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->was_in_promo !== $v) {
            $this->was_in_promo = $v;
            $this->modifiedColumns[] = OrderProductTableMap::WAS_IN_PROMO;
        }


        return $this;
    } // setWasInPromo()

    /**
     * Set the value of [weight] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setWeight($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->weight !== $v) {
            $this->weight = $v;
            $this->modifiedColumns[] = OrderProductTableMap::WEIGHT;
        }


        return $this;
    } // setWeight()

    /**
     * Set the value of [tax] column.
     *
     * @param      double $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setTax($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->tax !== $v) {
            $this->tax = $v;
            $this->modifiedColumns[] = OrderProductTableMap::TAX;
        }


        return $this;
    } // setTax()

    /**
     * Set the value of [parent] column.
     * not managed yet
     * @param      int $v new value
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setParent($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->parent !== $v) {
            $this->parent = $v;
            $this->modifiedColumns[] = OrderProductTableMap::PARENT;
        }


        return $this;
    } // setParent()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[] = OrderProductTableMap::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[] = OrderProductTableMap::UPDATED_AT;
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : OrderProductTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : OrderProductTableMap::translateFieldName('OrderId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->order_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : OrderProductTableMap::translateFieldName('ProductRef', TableMap::TYPE_PHPNAME, $indexType)];
            $this->product_ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : OrderProductTableMap::translateFieldName('ProductSaleElementsRef', TableMap::TYPE_PHPNAME, $indexType)];
            $this->product_sale_elements_ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : OrderProductTableMap::translateFieldName('Title', TableMap::TYPE_PHPNAME, $indexType)];
            $this->title = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : OrderProductTableMap::translateFieldName('Chapo', TableMap::TYPE_PHPNAME, $indexType)];
            $this->chapo = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : OrderProductTableMap::translateFieldName('Description', TableMap::TYPE_PHPNAME, $indexType)];
            $this->description = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : OrderProductTableMap::translateFieldName('Postscriptum', TableMap::TYPE_PHPNAME, $indexType)];
            $this->postscriptum = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : OrderProductTableMap::translateFieldName('Quantity', TableMap::TYPE_PHPNAME, $indexType)];
            $this->quantity = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : OrderProductTableMap::translateFieldName('Price', TableMap::TYPE_PHPNAME, $indexType)];
            $this->price = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : OrderProductTableMap::translateFieldName('PromoPrice', TableMap::TYPE_PHPNAME, $indexType)];
            $this->promo_price = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : OrderProductTableMap::translateFieldName('WasNew', TableMap::TYPE_PHPNAME, $indexType)];
            $this->was_new = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : OrderProductTableMap::translateFieldName('WasInPromo', TableMap::TYPE_PHPNAME, $indexType)];
            $this->was_in_promo = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : OrderProductTableMap::translateFieldName('Weight', TableMap::TYPE_PHPNAME, $indexType)];
            $this->weight = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : OrderProductTableMap::translateFieldName('Tax', TableMap::TYPE_PHPNAME, $indexType)];
            $this->tax = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : OrderProductTableMap::translateFieldName('Parent', TableMap::TYPE_PHPNAME, $indexType)];
            $this->parent = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : OrderProductTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : OrderProductTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 18; // 18 = OrderProductTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\OrderProduct object", 0, $e);
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
        if ($this->aOrder !== null && $this->order_id !== $this->aOrder->getId()) {
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
            $con = Propel::getServiceContainer()->getReadConnection(OrderProductTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildOrderProductQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aOrder = null;
            $this->collOrderAttributeCombinations = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see OrderProduct::setDeleted()
     * @see OrderProduct::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderProductTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildOrderProductQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderProductTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(OrderProductTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(OrderProductTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(OrderProductTableMap::UPDATED_AT)) {
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
                OrderProductTableMap::addInstanceToPool($this);
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

            if ($this->orderAttributeCombinationsScheduledForDeletion !== null) {
                if (!$this->orderAttributeCombinationsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\OrderAttributeCombinationQuery::create()
                        ->filterByPrimaryKeys($this->orderAttributeCombinationsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->orderAttributeCombinationsScheduledForDeletion = null;
                }
            }

                if ($this->collOrderAttributeCombinations !== null) {
            foreach ($this->collOrderAttributeCombinations as $referrerFK) {
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

        $this->modifiedColumns[] = OrderProductTableMap::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . OrderProductTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(OrderProductTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = 'ID';
        }
        if ($this->isColumnModified(OrderProductTableMap::ORDER_ID)) {
            $modifiedColumns[':p' . $index++]  = 'ORDER_ID';
        }
        if ($this->isColumnModified(OrderProductTableMap::PRODUCT_REF)) {
            $modifiedColumns[':p' . $index++]  = 'PRODUCT_REF';
        }
        if ($this->isColumnModified(OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF)) {
            $modifiedColumns[':p' . $index++]  = 'PRODUCT_SALE_ELEMENTS_REF';
        }
        if ($this->isColumnModified(OrderProductTableMap::TITLE)) {
            $modifiedColumns[':p' . $index++]  = 'TITLE';
        }
        if ($this->isColumnModified(OrderProductTableMap::CHAPO)) {
            $modifiedColumns[':p' . $index++]  = 'CHAPO';
        }
        if ($this->isColumnModified(OrderProductTableMap::DESCRIPTION)) {
            $modifiedColumns[':p' . $index++]  = 'DESCRIPTION';
        }
        if ($this->isColumnModified(OrderProductTableMap::POSTSCRIPTUM)) {
            $modifiedColumns[':p' . $index++]  = 'POSTSCRIPTUM';
        }
        if ($this->isColumnModified(OrderProductTableMap::QUANTITY)) {
            $modifiedColumns[':p' . $index++]  = 'QUANTITY';
        }
        if ($this->isColumnModified(OrderProductTableMap::PRICE)) {
            $modifiedColumns[':p' . $index++]  = 'PRICE';
        }
        if ($this->isColumnModified(OrderProductTableMap::PROMO_PRICE)) {
            $modifiedColumns[':p' . $index++]  = 'PROMO_PRICE';
        }
        if ($this->isColumnModified(OrderProductTableMap::WAS_NEW)) {
            $modifiedColumns[':p' . $index++]  = 'WAS_NEW';
        }
        if ($this->isColumnModified(OrderProductTableMap::WAS_IN_PROMO)) {
            $modifiedColumns[':p' . $index++]  = 'WAS_IN_PROMO';
        }
        if ($this->isColumnModified(OrderProductTableMap::WEIGHT)) {
            $modifiedColumns[':p' . $index++]  = 'WEIGHT';
        }
        if ($this->isColumnModified(OrderProductTableMap::TAX)) {
            $modifiedColumns[':p' . $index++]  = 'TAX';
        }
        if ($this->isColumnModified(OrderProductTableMap::PARENT)) {
            $modifiedColumns[':p' . $index++]  = 'PARENT';
        }
        if ($this->isColumnModified(OrderProductTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'CREATED_AT';
        }
        if ($this->isColumnModified(OrderProductTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'UPDATED_AT';
        }

        $sql = sprintf(
            'INSERT INTO order_product (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'ID':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case 'ORDER_ID':
                        $stmt->bindValue($identifier, $this->order_id, PDO::PARAM_INT);
                        break;
                    case 'PRODUCT_REF':
                        $stmt->bindValue($identifier, $this->product_ref, PDO::PARAM_STR);
                        break;
                    case 'PRODUCT_SALE_ELEMENTS_REF':
                        $stmt->bindValue($identifier, $this->product_sale_elements_ref, PDO::PARAM_STR);
                        break;
                    case 'TITLE':
                        $stmt->bindValue($identifier, $this->title, PDO::PARAM_STR);
                        break;
                    case 'CHAPO':
                        $stmt->bindValue($identifier, $this->chapo, PDO::PARAM_STR);
                        break;
                    case 'DESCRIPTION':
                        $stmt->bindValue($identifier, $this->description, PDO::PARAM_STR);
                        break;
                    case 'POSTSCRIPTUM':
                        $stmt->bindValue($identifier, $this->postscriptum, PDO::PARAM_STR);
                        break;
                    case 'QUANTITY':
                        $stmt->bindValue($identifier, $this->quantity, PDO::PARAM_STR);
                        break;
                    case 'PRICE':
                        $stmt->bindValue($identifier, $this->price, PDO::PARAM_STR);
                        break;
                    case 'PROMO_PRICE':
                        $stmt->bindValue($identifier, $this->promo_price, PDO::PARAM_STR);
                        break;
                    case 'WAS_NEW':
                        $stmt->bindValue($identifier, $this->was_new, PDO::PARAM_INT);
                        break;
                    case 'WAS_IN_PROMO':
                        $stmt->bindValue($identifier, $this->was_in_promo, PDO::PARAM_INT);
                        break;
                    case 'WEIGHT':
                        $stmt->bindValue($identifier, $this->weight, PDO::PARAM_STR);
                        break;
                    case 'TAX':
                        $stmt->bindValue($identifier, $this->tax, PDO::PARAM_STR);
                        break;
                    case 'PARENT':
                        $stmt->bindValue($identifier, $this->parent, PDO::PARAM_INT);
                        break;
                    case 'CREATED_AT':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'UPDATED_AT':
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
        $pos = OrderProductTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getOrderId();
                break;
            case 2:
                return $this->getProductRef();
                break;
            case 3:
                return $this->getProductSaleElementsRef();
                break;
            case 4:
                return $this->getTitle();
                break;
            case 5:
                return $this->getChapo();
                break;
            case 6:
                return $this->getDescription();
                break;
            case 7:
                return $this->getPostscriptum();
                break;
            case 8:
                return $this->getQuantity();
                break;
            case 9:
                return $this->getPrice();
                break;
            case 10:
                return $this->getPromoPrice();
                break;
            case 11:
                return $this->getWasNew();
                break;
            case 12:
                return $this->getWasInPromo();
                break;
            case 13:
                return $this->getWeight();
                break;
            case 14:
                return $this->getTax();
                break;
            case 15:
                return $this->getParent();
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
        if (isset($alreadyDumpedObjects['OrderProduct'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['OrderProduct'][$this->getPrimaryKey()] = true;
        $keys = OrderProductTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getOrderId(),
            $keys[2] => $this->getProductRef(),
            $keys[3] => $this->getProductSaleElementsRef(),
            $keys[4] => $this->getTitle(),
            $keys[5] => $this->getChapo(),
            $keys[6] => $this->getDescription(),
            $keys[7] => $this->getPostscriptum(),
            $keys[8] => $this->getQuantity(),
            $keys[9] => $this->getPrice(),
            $keys[10] => $this->getPromoPrice(),
            $keys[11] => $this->getWasNew(),
            $keys[12] => $this->getWasInPromo(),
            $keys[13] => $this->getWeight(),
            $keys[14] => $this->getTax(),
            $keys[15] => $this->getParent(),
            $keys[16] => $this->getCreatedAt(),
            $keys[17] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach($virtualColumns as $key => $virtualColumn)
        {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aOrder) {
                $result['Order'] = $this->aOrder->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collOrderAttributeCombinations) {
                $result['OrderAttributeCombinations'] = $this->collOrderAttributeCombinations->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = OrderProductTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setOrderId($value);
                break;
            case 2:
                $this->setProductRef($value);
                break;
            case 3:
                $this->setProductSaleElementsRef($value);
                break;
            case 4:
                $this->setTitle($value);
                break;
            case 5:
                $this->setChapo($value);
                break;
            case 6:
                $this->setDescription($value);
                break;
            case 7:
                $this->setPostscriptum($value);
                break;
            case 8:
                $this->setQuantity($value);
                break;
            case 9:
                $this->setPrice($value);
                break;
            case 10:
                $this->setPromoPrice($value);
                break;
            case 11:
                $this->setWasNew($value);
                break;
            case 12:
                $this->setWasInPromo($value);
                break;
            case 13:
                $this->setWeight($value);
                break;
            case 14:
                $this->setTax($value);
                break;
            case 15:
                $this->setParent($value);
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
        $keys = OrderProductTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setOrderId($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setProductRef($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setProductSaleElementsRef($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setTitle($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setChapo($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setDescription($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setPostscriptum($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setQuantity($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setPrice($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setPromoPrice($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setWasNew($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setWasInPromo($arr[$keys[12]]);
        if (array_key_exists($keys[13], $arr)) $this->setWeight($arr[$keys[13]]);
        if (array_key_exists($keys[14], $arr)) $this->setTax($arr[$keys[14]]);
        if (array_key_exists($keys[15], $arr)) $this->setParent($arr[$keys[15]]);
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
        $criteria = new Criteria(OrderProductTableMap::DATABASE_NAME);

        if ($this->isColumnModified(OrderProductTableMap::ID)) $criteria->add(OrderProductTableMap::ID, $this->id);
        if ($this->isColumnModified(OrderProductTableMap::ORDER_ID)) $criteria->add(OrderProductTableMap::ORDER_ID, $this->order_id);
        if ($this->isColumnModified(OrderProductTableMap::PRODUCT_REF)) $criteria->add(OrderProductTableMap::PRODUCT_REF, $this->product_ref);
        if ($this->isColumnModified(OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF)) $criteria->add(OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF, $this->product_sale_elements_ref);
        if ($this->isColumnModified(OrderProductTableMap::TITLE)) $criteria->add(OrderProductTableMap::TITLE, $this->title);
        if ($this->isColumnModified(OrderProductTableMap::CHAPO)) $criteria->add(OrderProductTableMap::CHAPO, $this->chapo);
        if ($this->isColumnModified(OrderProductTableMap::DESCRIPTION)) $criteria->add(OrderProductTableMap::DESCRIPTION, $this->description);
        if ($this->isColumnModified(OrderProductTableMap::POSTSCRIPTUM)) $criteria->add(OrderProductTableMap::POSTSCRIPTUM, $this->postscriptum);
        if ($this->isColumnModified(OrderProductTableMap::QUANTITY)) $criteria->add(OrderProductTableMap::QUANTITY, $this->quantity);
        if ($this->isColumnModified(OrderProductTableMap::PRICE)) $criteria->add(OrderProductTableMap::PRICE, $this->price);
        if ($this->isColumnModified(OrderProductTableMap::PROMO_PRICE)) $criteria->add(OrderProductTableMap::PROMO_PRICE, $this->promo_price);
        if ($this->isColumnModified(OrderProductTableMap::WAS_NEW)) $criteria->add(OrderProductTableMap::WAS_NEW, $this->was_new);
        if ($this->isColumnModified(OrderProductTableMap::WAS_IN_PROMO)) $criteria->add(OrderProductTableMap::WAS_IN_PROMO, $this->was_in_promo);
        if ($this->isColumnModified(OrderProductTableMap::WEIGHT)) $criteria->add(OrderProductTableMap::WEIGHT, $this->weight);
        if ($this->isColumnModified(OrderProductTableMap::TAX)) $criteria->add(OrderProductTableMap::TAX, $this->tax);
        if ($this->isColumnModified(OrderProductTableMap::PARENT)) $criteria->add(OrderProductTableMap::PARENT, $this->parent);
        if ($this->isColumnModified(OrderProductTableMap::CREATED_AT)) $criteria->add(OrderProductTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(OrderProductTableMap::UPDATED_AT)) $criteria->add(OrderProductTableMap::UPDATED_AT, $this->updated_at);

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
        $criteria = new Criteria(OrderProductTableMap::DATABASE_NAME);
        $criteria->add(OrderProductTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\OrderProduct (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setOrderId($this->getOrderId());
        $copyObj->setProductRef($this->getProductRef());
        $copyObj->setProductSaleElementsRef($this->getProductSaleElementsRef());
        $copyObj->setTitle($this->getTitle());
        $copyObj->setChapo($this->getChapo());
        $copyObj->setDescription($this->getDescription());
        $copyObj->setPostscriptum($this->getPostscriptum());
        $copyObj->setQuantity($this->getQuantity());
        $copyObj->setPrice($this->getPrice());
        $copyObj->setPromoPrice($this->getPromoPrice());
        $copyObj->setWasNew($this->getWasNew());
        $copyObj->setWasInPromo($this->getWasInPromo());
        $copyObj->setWeight($this->getWeight());
        $copyObj->setTax($this->getTax());
        $copyObj->setParent($this->getParent());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getOrderAttributeCombinations() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addOrderAttributeCombination($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\OrderProduct Clone of current object.
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
     * @return                 \Thelia\Model\OrderProduct The current object (for fluent API support)
     * @throws PropelException
     */
    public function setOrder(ChildOrder $v = null)
    {
        if ($v === null) {
            $this->setOrderId(NULL);
        } else {
            $this->setOrderId($v->getId());
        }

        $this->aOrder = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildOrder object, it will not be re-added.
        if ($v !== null) {
            $v->addOrderProduct($this);
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
        if ($this->aOrder === null && ($this->order_id !== null)) {
            $this->aOrder = ChildOrderQuery::create()->findPk($this->order_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aOrder->addOrderProducts($this);
             */
        }

        return $this->aOrder;
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
        if ('OrderAttributeCombination' == $relationName) {
            return $this->initOrderAttributeCombinations();
        }
    }

    /**
     * Clears out the collOrderAttributeCombinations collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addOrderAttributeCombinations()
     */
    public function clearOrderAttributeCombinations()
    {
        $this->collOrderAttributeCombinations = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collOrderAttributeCombinations collection loaded partially.
     */
    public function resetPartialOrderAttributeCombinations($v = true)
    {
        $this->collOrderAttributeCombinationsPartial = $v;
    }

    /**
     * Initializes the collOrderAttributeCombinations collection.
     *
     * By default this just sets the collOrderAttributeCombinations collection to an empty array (like clearcollOrderAttributeCombinations());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initOrderAttributeCombinations($overrideExisting = true)
    {
        if (null !== $this->collOrderAttributeCombinations && !$overrideExisting) {
            return;
        }
        $this->collOrderAttributeCombinations = new ObjectCollection();
        $this->collOrderAttributeCombinations->setModel('\Thelia\Model\OrderAttributeCombination');
    }

    /**
     * Gets an array of ChildOrderAttributeCombination objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildOrderProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildOrderAttributeCombination[] List of ChildOrderAttributeCombination objects
     * @throws PropelException
     */
    public function getOrderAttributeCombinations($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderAttributeCombinationsPartial && !$this->isNew();
        if (null === $this->collOrderAttributeCombinations || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collOrderAttributeCombinations) {
                // return empty collection
                $this->initOrderAttributeCombinations();
            } else {
                $collOrderAttributeCombinations = ChildOrderAttributeCombinationQuery::create(null, $criteria)
                    ->filterByOrderProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collOrderAttributeCombinationsPartial && count($collOrderAttributeCombinations)) {
                        $this->initOrderAttributeCombinations(false);

                        foreach ($collOrderAttributeCombinations as $obj) {
                            if (false == $this->collOrderAttributeCombinations->contains($obj)) {
                                $this->collOrderAttributeCombinations->append($obj);
                            }
                        }

                        $this->collOrderAttributeCombinationsPartial = true;
                    }

                    $collOrderAttributeCombinations->getInternalIterator()->rewind();

                    return $collOrderAttributeCombinations;
                }

                if ($partial && $this->collOrderAttributeCombinations) {
                    foreach ($this->collOrderAttributeCombinations as $obj) {
                        if ($obj->isNew()) {
                            $collOrderAttributeCombinations[] = $obj;
                        }
                    }
                }

                $this->collOrderAttributeCombinations = $collOrderAttributeCombinations;
                $this->collOrderAttributeCombinationsPartial = false;
            }
        }

        return $this->collOrderAttributeCombinations;
    }

    /**
     * Sets a collection of OrderAttributeCombination objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $orderAttributeCombinations A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildOrderProduct The current object (for fluent API support)
     */
    public function setOrderAttributeCombinations(Collection $orderAttributeCombinations, ConnectionInterface $con = null)
    {
        $orderAttributeCombinationsToDelete = $this->getOrderAttributeCombinations(new Criteria(), $con)->diff($orderAttributeCombinations);


        $this->orderAttributeCombinationsScheduledForDeletion = $orderAttributeCombinationsToDelete;

        foreach ($orderAttributeCombinationsToDelete as $orderAttributeCombinationRemoved) {
            $orderAttributeCombinationRemoved->setOrderProduct(null);
        }

        $this->collOrderAttributeCombinations = null;
        foreach ($orderAttributeCombinations as $orderAttributeCombination) {
            $this->addOrderAttributeCombination($orderAttributeCombination);
        }

        $this->collOrderAttributeCombinations = $orderAttributeCombinations;
        $this->collOrderAttributeCombinationsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related OrderAttributeCombination objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related OrderAttributeCombination objects.
     * @throws PropelException
     */
    public function countOrderAttributeCombinations(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collOrderAttributeCombinationsPartial && !$this->isNew();
        if (null === $this->collOrderAttributeCombinations || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collOrderAttributeCombinations) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getOrderAttributeCombinations());
            }

            $query = ChildOrderAttributeCombinationQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByOrderProduct($this)
                ->count($con);
        }

        return count($this->collOrderAttributeCombinations);
    }

    /**
     * Method called to associate a ChildOrderAttributeCombination object to this object
     * through the ChildOrderAttributeCombination foreign key attribute.
     *
     * @param    ChildOrderAttributeCombination $l ChildOrderAttributeCombination
     * @return   \Thelia\Model\OrderProduct The current object (for fluent API support)
     */
    public function addOrderAttributeCombination(ChildOrderAttributeCombination $l)
    {
        if ($this->collOrderAttributeCombinations === null) {
            $this->initOrderAttributeCombinations();
            $this->collOrderAttributeCombinationsPartial = true;
        }

        if (!in_array($l, $this->collOrderAttributeCombinations->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddOrderAttributeCombination($l);
        }

        return $this;
    }

    /**
     * @param OrderAttributeCombination $orderAttributeCombination The orderAttributeCombination object to add.
     */
    protected function doAddOrderAttributeCombination($orderAttributeCombination)
    {
        $this->collOrderAttributeCombinations[]= $orderAttributeCombination;
        $orderAttributeCombination->setOrderProduct($this);
    }

    /**
     * @param  OrderAttributeCombination $orderAttributeCombination The orderAttributeCombination object to remove.
     * @return ChildOrderProduct The current object (for fluent API support)
     */
    public function removeOrderAttributeCombination($orderAttributeCombination)
    {
        if ($this->getOrderAttributeCombinations()->contains($orderAttributeCombination)) {
            $this->collOrderAttributeCombinations->remove($this->collOrderAttributeCombinations->search($orderAttributeCombination));
            if (null === $this->orderAttributeCombinationsScheduledForDeletion) {
                $this->orderAttributeCombinationsScheduledForDeletion = clone $this->collOrderAttributeCombinations;
                $this->orderAttributeCombinationsScheduledForDeletion->clear();
            }
            $this->orderAttributeCombinationsScheduledForDeletion[]= clone $orderAttributeCombination;
            $orderAttributeCombination->setOrderProduct(null);
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->order_id = null;
        $this->product_ref = null;
        $this->product_sale_elements_ref = null;
        $this->title = null;
        $this->chapo = null;
        $this->description = null;
        $this->postscriptum = null;
        $this->quantity = null;
        $this->price = null;
        $this->promo_price = null;
        $this->was_new = null;
        $this->was_in_promo = null;
        $this->weight = null;
        $this->tax = null;
        $this->parent = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->alreadyInSave = false;
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
     * when using Propel in certain daemon or large-volume/high-memory operations.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
            if ($this->collOrderAttributeCombinations) {
                foreach ($this->collOrderAttributeCombinations as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        if ($this->collOrderAttributeCombinations instanceof Collection) {
            $this->collOrderAttributeCombinations->clearIterator();
        }
        $this->collOrderAttributeCombinations = null;
        $this->aOrder = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(OrderProductTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildOrderProduct The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = OrderProductTableMap::UPDATED_AT;

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
