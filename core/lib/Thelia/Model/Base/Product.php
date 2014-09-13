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
use Thelia\Model\Accessory as ChildAccessory;
use Thelia\Model\AccessoryQuery as ChildAccessoryQuery;
use Thelia\Model\Brand as ChildBrand;
use Thelia\Model\BrandQuery as ChildBrandQuery;
use Thelia\Model\CartItem as ChildCartItem;
use Thelia\Model\CartItemQuery as ChildCartItemQuery;
use Thelia\Model\Category as ChildCategory;
use Thelia\Model\CategoryQuery as ChildCategoryQuery;
use Thelia\Model\FeatureProduct as ChildFeatureProduct;
use Thelia\Model\FeatureProductQuery as ChildFeatureProductQuery;
use Thelia\Model\Product as ChildProduct;
use Thelia\Model\ProductAssociatedContent as ChildProductAssociatedContent;
use Thelia\Model\ProductAssociatedContentQuery as ChildProductAssociatedContentQuery;
use Thelia\Model\ProductCategory as ChildProductCategory;
use Thelia\Model\ProductCategoryQuery as ChildProductCategoryQuery;
use Thelia\Model\ProductDocument as ChildProductDocument;
use Thelia\Model\ProductDocumentQuery as ChildProductDocumentQuery;
use Thelia\Model\ProductI18n as ChildProductI18n;
use Thelia\Model\ProductI18nQuery as ChildProductI18nQuery;
use Thelia\Model\ProductImage as ChildProductImage;
use Thelia\Model\ProductImageQuery as ChildProductImageQuery;
use Thelia\Model\ProductQuery as ChildProductQuery;
use Thelia\Model\ProductSaleElements as ChildProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery as ChildProductSaleElementsQuery;
use Thelia\Model\ProductVersion as ChildProductVersion;
use Thelia\Model\ProductVersionQuery as ChildProductVersionQuery;
use Thelia\Model\SaleProduct as ChildSaleProduct;
use Thelia\Model\SaleProductQuery as ChildSaleProductQuery;
use Thelia\Model\TaxRule as ChildTaxRule;
use Thelia\Model\TaxRuleQuery as ChildTaxRuleQuery;
use Thelia\Model\Template as ChildTemplate;
use Thelia\Model\TemplateQuery as ChildTemplateQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\ProductVersionTableMap;

abstract class Product implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\ProductTableMap';


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
     * The value for the visible field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $visible;

    /**
     * The value for the position field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $position;

    /**
     * The value for the template_id field.
     * @var        int
     */
    protected $template_id;

    /**
     * The value for the brand_id field.
     * @var        int
     */
    protected $brand_id;

    /**
     * The value for the virtual field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $virtual;

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
     * @var        TaxRule
     */
    protected $aTaxRule;

    /**
     * @var        Template
     */
    protected $aTemplate;

    /**
     * @var        Brand
     */
    protected $aBrand;

    /**
     * @var        ObjectCollection|ChildProductCategory[] Collection to store aggregation of ChildProductCategory objects.
     */
    protected $collProductCategories;
    protected $collProductCategoriesPartial;

    /**
     * @var        ObjectCollection|ChildFeatureProduct[] Collection to store aggregation of ChildFeatureProduct objects.
     */
    protected $collFeatureProducts;
    protected $collFeatureProductsPartial;

    /**
     * @var        ObjectCollection|ChildProductSaleElements[] Collection to store aggregation of ChildProductSaleElements objects.
     */
    protected $collProductSaleElementss;
    protected $collProductSaleElementssPartial;

    /**
     * @var        ObjectCollection|ChildProductImage[] Collection to store aggregation of ChildProductImage objects.
     */
    protected $collProductImages;
    protected $collProductImagesPartial;

    /**
     * @var        ObjectCollection|ChildProductDocument[] Collection to store aggregation of ChildProductDocument objects.
     */
    protected $collProductDocuments;
    protected $collProductDocumentsPartial;

    /**
     * @var        ObjectCollection|ChildAccessory[] Collection to store aggregation of ChildAccessory objects.
     */
    protected $collAccessoriesRelatedByProductId;
    protected $collAccessoriesRelatedByProductIdPartial;

    /**
     * @var        ObjectCollection|ChildAccessory[] Collection to store aggregation of ChildAccessory objects.
     */
    protected $collAccessoriesRelatedByAccessory;
    protected $collAccessoriesRelatedByAccessoryPartial;

    /**
     * @var        ObjectCollection|ChildCartItem[] Collection to store aggregation of ChildCartItem objects.
     */
    protected $collCartItems;
    protected $collCartItemsPartial;

    /**
     * @var        ObjectCollection|ChildProductAssociatedContent[] Collection to store aggregation of ChildProductAssociatedContent objects.
     */
    protected $collProductAssociatedContents;
    protected $collProductAssociatedContentsPartial;

    /**
     * @var        ObjectCollection|ChildSaleProduct[] Collection to store aggregation of ChildSaleProduct objects.
     */
    protected $collSaleProducts;
    protected $collSaleProductsPartial;

    /**
     * @var        ObjectCollection|ChildProductI18n[] Collection to store aggregation of ChildProductI18n objects.
     */
    protected $collProductI18ns;
    protected $collProductI18nsPartial;

    /**
     * @var        ObjectCollection|ChildProductVersion[] Collection to store aggregation of ChildProductVersion objects.
     */
    protected $collProductVersions;
    protected $collProductVersionsPartial;

    /**
     * @var        ChildCategory[] Collection to store aggregation of ChildCategory objects.
     */
    protected $collCategories;

    /**
     * @var        ChildProduct[] Collection to store aggregation of ChildProduct objects.
     */
    protected $collProductsRelatedByAccessory;

    /**
     * @var        ChildProduct[] Collection to store aggregation of ChildProduct objects.
     */
    protected $collProductsRelatedByProductId;

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
     * @var        array[ChildProductI18n]
     */
    protected $currentTranslations;

    // versionable behavior


    /**
     * @var bool
     */
    protected $enforceVersion = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $categoriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productsRelatedByAccessoryScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productsRelatedByProductIdScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productCategoriesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $featureProductsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productSaleElementssScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productImagesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productDocumentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $accessoriesRelatedByProductIdScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $accessoriesRelatedByAccessoryScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $cartItemsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productAssociatedContentsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $saleProductsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productI18nsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $productVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->visible = 0;
        $this->position = 0;
        $this->virtual = 0;
        $this->version = 0;
    }

    /**
     * Initializes internal state of Thelia\Model\Base\Product object.
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
     * Compares this with another <code>Product</code> instance.  If
     * <code>obj</code> is an instance of <code>Product</code>, delegates to
     * <code>equals(Product)</code>.  Otherwise, returns <code>false</code>.
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
     * @return Product The current object, for fluid interface
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
     * @return Product The current object, for fluid interface
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
     * Get the [tax_rule_id] column value.
     *
     * @return   int
     */
    public function getTaxRuleId()
    {

        return $this->tax_rule_id;
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
     * Get the [visible] column value.
     *
     * @return   int
     */
    public function getVisible()
    {

        return $this->visible;
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
     * Get the [template_id] column value.
     *
     * @return   int
     */
    public function getTemplateId()
    {

        return $this->template_id;
    }

    /**
     * Get the [brand_id] column value.
     *
     * @return   int
     */
    public function getBrandId()
    {

        return $this->brand_id;
    }

    /**
     * Get the [virtual] column value.
     *
     * @return   int
     */
    public function getVirtual()
    {

        return $this->virtual;
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
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[ProductTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [tax_rule_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setTaxRuleId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->tax_rule_id !== $v) {
            $this->tax_rule_id = $v;
            $this->modifiedColumns[ProductTableMap::TAX_RULE_ID] = true;
        }

        if ($this->aTaxRule !== null && $this->aTaxRule->getId() !== $v) {
            $this->aTaxRule = null;
        }


        return $this;
    } // setTaxRuleId()

    /**
     * Set the value of [ref] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setRef($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->ref !== $v) {
            $this->ref = $v;
            $this->modifiedColumns[ProductTableMap::REF] = true;
        }


        return $this;
    } // setRef()

    /**
     * Set the value of [visible] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setVisible($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->visible !== $v) {
            $this->visible = $v;
            $this->modifiedColumns[ProductTableMap::VISIBLE] = true;
        }


        return $this;
    } // setVisible()

    /**
     * Set the value of [position] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setPosition($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->position !== $v) {
            $this->position = $v;
            $this->modifiedColumns[ProductTableMap::POSITION] = true;
        }


        return $this;
    } // setPosition()

    /**
     * Set the value of [template_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setTemplateId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->template_id !== $v) {
            $this->template_id = $v;
            $this->modifiedColumns[ProductTableMap::TEMPLATE_ID] = true;
        }

        if ($this->aTemplate !== null && $this->aTemplate->getId() !== $v) {
            $this->aTemplate = null;
        }


        return $this;
    } // setTemplateId()

    /**
     * Set the value of [brand_id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setBrandId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->brand_id !== $v) {
            $this->brand_id = $v;
            $this->modifiedColumns[ProductTableMap::BRAND_ID] = true;
        }

        if ($this->aBrand !== null && $this->aBrand->getId() !== $v) {
            $this->aBrand = null;
        }


        return $this;
    } // setBrandId()

    /**
     * Set the value of [virtual] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setVirtual($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->virtual !== $v) {
            $this->virtual = $v;
            $this->modifiedColumns[ProductTableMap::VIRTUAL] = true;
        }


        return $this;
    } // setVirtual()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[ProductTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[ProductTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[ProductTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[ProductTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[ProductTableMap::VERSION_CREATED_BY] = true;
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
            if ($this->visible !== 0) {
                return false;
            }

            if ($this->position !== 0) {
                return false;
            }

            if ($this->virtual !== 0) {
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


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : ProductTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : ProductTableMap::translateFieldName('TaxRuleId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->tax_rule_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : ProductTableMap::translateFieldName('Ref', TableMap::TYPE_PHPNAME, $indexType)];
            $this->ref = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : ProductTableMap::translateFieldName('Visible', TableMap::TYPE_PHPNAME, $indexType)];
            $this->visible = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : ProductTableMap::translateFieldName('Position', TableMap::TYPE_PHPNAME, $indexType)];
            $this->position = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : ProductTableMap::translateFieldName('TemplateId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->template_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : ProductTableMap::translateFieldName('BrandId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->brand_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : ProductTableMap::translateFieldName('Virtual', TableMap::TYPE_PHPNAME, $indexType)];
            $this->virtual = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : ProductTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : ProductTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : ProductTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : ProductTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : ProductTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 13; // 13 = ProductTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Product object", 0, $e);
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
        if ($this->aTemplate !== null && $this->template_id !== $this->aTemplate->getId()) {
            $this->aTemplate = null;
        }
        if ($this->aBrand !== null && $this->brand_id !== $this->aBrand->getId()) {
            $this->aBrand = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(ProductTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildProductQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aTaxRule = null;
            $this->aTemplate = null;
            $this->aBrand = null;
            $this->collProductCategories = null;

            $this->collFeatureProducts = null;

            $this->collProductSaleElementss = null;

            $this->collProductImages = null;

            $this->collProductDocuments = null;

            $this->collAccessoriesRelatedByProductId = null;

            $this->collAccessoriesRelatedByAccessory = null;

            $this->collCartItems = null;

            $this->collProductAssociatedContents = null;

            $this->collSaleProducts = null;

            $this->collProductI18ns = null;

            $this->collProductVersions = null;

            $this->collCategories = null;
            $this->collProductsRelatedByAccessory = null;
            $this->collProductsRelatedByProductId = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Product::setDeleted()
     * @see Product::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProductTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildProductQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProductTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(ProductTableMap::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(ProductTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(ProductTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(ProductTableMap::UPDATED_AT)) {
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
                ProductTableMap::addInstanceToPool($this);
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

            if ($this->aTaxRule !== null) {
                if ($this->aTaxRule->isModified() || $this->aTaxRule->isNew()) {
                    $affectedRows += $this->aTaxRule->save($con);
                }
                $this->setTaxRule($this->aTaxRule);
            }

            if ($this->aTemplate !== null) {
                if ($this->aTemplate->isModified() || $this->aTemplate->isNew()) {
                    $affectedRows += $this->aTemplate->save($con);
                }
                $this->setTemplate($this->aTemplate);
            }

            if ($this->aBrand !== null) {
                if ($this->aBrand->isModified() || $this->aBrand->isNew()) {
                    $affectedRows += $this->aBrand->save($con);
                }
                $this->setBrand($this->aBrand);
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

            if ($this->categoriesScheduledForDeletion !== null) {
                if (!$this->categoriesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->categoriesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    ProductCategoryQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->categoriesScheduledForDeletion = null;
                }

                foreach ($this->getCategories() as $category) {
                    if ($category->isModified()) {
                        $category->save($con);
                    }
                }
            } elseif ($this->collCategories) {
                foreach ($this->collCategories as $category) {
                    if ($category->isModified()) {
                        $category->save($con);
                    }
                }
            }

            if ($this->productsRelatedByAccessoryScheduledForDeletion !== null) {
                if (!$this->productsRelatedByAccessoryScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->productsRelatedByAccessoryScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    AccessoryRelatedByProductIdQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->productsRelatedByAccessoryScheduledForDeletion = null;
                }

                foreach ($this->getProductsRelatedByAccessory() as $productRelatedByAccessory) {
                    if ($productRelatedByAccessory->isModified()) {
                        $productRelatedByAccessory->save($con);
                    }
                }
            } elseif ($this->collProductsRelatedByAccessory) {
                foreach ($this->collProductsRelatedByAccessory as $productRelatedByAccessory) {
                    if ($productRelatedByAccessory->isModified()) {
                        $productRelatedByAccessory->save($con);
                    }
                }
            }

            if ($this->productsRelatedByProductIdScheduledForDeletion !== null) {
                if (!$this->productsRelatedByProductIdScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->productsRelatedByProductIdScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($pk, $remotePk);
                    }

                    AccessoryRelatedByAccessoryQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->productsRelatedByProductIdScheduledForDeletion = null;
                }

                foreach ($this->getProductsRelatedByProductId() as $productRelatedByProductId) {
                    if ($productRelatedByProductId->isModified()) {
                        $productRelatedByProductId->save($con);
                    }
                }
            } elseif ($this->collProductsRelatedByProductId) {
                foreach ($this->collProductsRelatedByProductId as $productRelatedByProductId) {
                    if ($productRelatedByProductId->isModified()) {
                        $productRelatedByProductId->save($con);
                    }
                }
            }

            if ($this->productCategoriesScheduledForDeletion !== null) {
                if (!$this->productCategoriesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductCategoryQuery::create()
                        ->filterByPrimaryKeys($this->productCategoriesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productCategoriesScheduledForDeletion = null;
                }
            }

                if ($this->collProductCategories !== null) {
            foreach ($this->collProductCategories as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->featureProductsScheduledForDeletion !== null) {
                if (!$this->featureProductsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\FeatureProductQuery::create()
                        ->filterByPrimaryKeys($this->featureProductsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->featureProductsScheduledForDeletion = null;
                }
            }

                if ($this->collFeatureProducts !== null) {
            foreach ($this->collFeatureProducts as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productSaleElementssScheduledForDeletion !== null) {
                if (!$this->productSaleElementssScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductSaleElementsQuery::create()
                        ->filterByPrimaryKeys($this->productSaleElementssScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productSaleElementssScheduledForDeletion = null;
                }
            }

                if ($this->collProductSaleElementss !== null) {
            foreach ($this->collProductSaleElementss as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productImagesScheduledForDeletion !== null) {
                if (!$this->productImagesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductImageQuery::create()
                        ->filterByPrimaryKeys($this->productImagesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productImagesScheduledForDeletion = null;
                }
            }

                if ($this->collProductImages !== null) {
            foreach ($this->collProductImages as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productDocumentsScheduledForDeletion !== null) {
                if (!$this->productDocumentsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductDocumentQuery::create()
                        ->filterByPrimaryKeys($this->productDocumentsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productDocumentsScheduledForDeletion = null;
                }
            }

                if ($this->collProductDocuments !== null) {
            foreach ($this->collProductDocuments as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->accessoriesRelatedByProductIdScheduledForDeletion !== null) {
                if (!$this->accessoriesRelatedByProductIdScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AccessoryQuery::create()
                        ->filterByPrimaryKeys($this->accessoriesRelatedByProductIdScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->accessoriesRelatedByProductIdScheduledForDeletion = null;
                }
            }

                if ($this->collAccessoriesRelatedByProductId !== null) {
            foreach ($this->collAccessoriesRelatedByProductId as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->accessoriesRelatedByAccessoryScheduledForDeletion !== null) {
                if (!$this->accessoriesRelatedByAccessoryScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AccessoryQuery::create()
                        ->filterByPrimaryKeys($this->accessoriesRelatedByAccessoryScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->accessoriesRelatedByAccessoryScheduledForDeletion = null;
                }
            }

                if ($this->collAccessoriesRelatedByAccessory !== null) {
            foreach ($this->collAccessoriesRelatedByAccessory as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->cartItemsScheduledForDeletion !== null) {
                if (!$this->cartItemsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\CartItemQuery::create()
                        ->filterByPrimaryKeys($this->cartItemsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->cartItemsScheduledForDeletion = null;
                }
            }

                if ($this->collCartItems !== null) {
            foreach ($this->collCartItems as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productAssociatedContentsScheduledForDeletion !== null) {
                if (!$this->productAssociatedContentsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductAssociatedContentQuery::create()
                        ->filterByPrimaryKeys($this->productAssociatedContentsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productAssociatedContentsScheduledForDeletion = null;
                }
            }

                if ($this->collProductAssociatedContents !== null) {
            foreach ($this->collProductAssociatedContents as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->saleProductsScheduledForDeletion !== null) {
                if (!$this->saleProductsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\SaleProductQuery::create()
                        ->filterByPrimaryKeys($this->saleProductsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->saleProductsScheduledForDeletion = null;
                }
            }

                if ($this->collSaleProducts !== null) {
            foreach ($this->collSaleProducts as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productI18nsScheduledForDeletion !== null) {
                if (!$this->productI18nsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductI18nQuery::create()
                        ->filterByPrimaryKeys($this->productI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collProductI18ns !== null) {
            foreach ($this->collProductI18ns as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->productVersionsScheduledForDeletion !== null) {
                if (!$this->productVersionsScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\ProductVersionQuery::create()
                        ->filterByPrimaryKeys($this->productVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->productVersionsScheduledForDeletion = null;
                }
            }

                if ($this->collProductVersions !== null) {
            foreach ($this->collProductVersions as $referrerFK) {
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

        $this->modifiedColumns[ProductTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . ProductTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(ProductTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = '`ID`';
        }
        if ($this->isColumnModified(ProductTableMap::TAX_RULE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`TAX_RULE_ID`';
        }
        if ($this->isColumnModified(ProductTableMap::REF)) {
            $modifiedColumns[':p' . $index++]  = '`REF`';
        }
        if ($this->isColumnModified(ProductTableMap::VISIBLE)) {
            $modifiedColumns[':p' . $index++]  = '`VISIBLE`';
        }
        if ($this->isColumnModified(ProductTableMap::POSITION)) {
            $modifiedColumns[':p' . $index++]  = '`POSITION`';
        }
        if ($this->isColumnModified(ProductTableMap::TEMPLATE_ID)) {
            $modifiedColumns[':p' . $index++]  = '`TEMPLATE_ID`';
        }
        if ($this->isColumnModified(ProductTableMap::BRAND_ID)) {
            $modifiedColumns[':p' . $index++]  = '`BRAND_ID`';
        }
        if ($this->isColumnModified(ProductTableMap::VIRTUAL)) {
            $modifiedColumns[':p' . $index++]  = '`VIRTUAL`';
        }
        if ($this->isColumnModified(ProductTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`CREATED_AT`';
        }
        if ($this->isColumnModified(ProductTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`UPDATED_AT`';
        }
        if ($this->isColumnModified(ProductTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION`';
        }
        if ($this->isColumnModified(ProductTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_AT`';
        }
        if ($this->isColumnModified(ProductTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = '`VERSION_CREATED_BY`';
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
                    case '`VISIBLE`':
                        $stmt->bindValue($identifier, $this->visible, PDO::PARAM_INT);
                        break;
                    case '`POSITION`':
                        $stmt->bindValue($identifier, $this->position, PDO::PARAM_INT);
                        break;
                    case '`TEMPLATE_ID`':
                        $stmt->bindValue($identifier, $this->template_id, PDO::PARAM_INT);
                        break;
                    case '`BRAND_ID`':
                        $stmt->bindValue($identifier, $this->brand_id, PDO::PARAM_INT);
                        break;
                    case '`VIRTUAL`':
                        $stmt->bindValue($identifier, $this->virtual, PDO::PARAM_INT);
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
        $pos = ProductTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getTaxRuleId();
                break;
            case 2:
                return $this->getRef();
                break;
            case 3:
                return $this->getVisible();
                break;
            case 4:
                return $this->getPosition();
                break;
            case 5:
                return $this->getTemplateId();
                break;
            case 6:
                return $this->getBrandId();
                break;
            case 7:
                return $this->getVirtual();
                break;
            case 8:
                return $this->getCreatedAt();
                break;
            case 9:
                return $this->getUpdatedAt();
                break;
            case 10:
                return $this->getVersion();
                break;
            case 11:
                return $this->getVersionCreatedAt();
                break;
            case 12:
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
        if (isset($alreadyDumpedObjects['Product'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Product'][$this->getPrimaryKey()] = true;
        $keys = ProductTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getTaxRuleId(),
            $keys[2] => $this->getRef(),
            $keys[3] => $this->getVisible(),
            $keys[4] => $this->getPosition(),
            $keys[5] => $this->getTemplateId(),
            $keys[6] => $this->getBrandId(),
            $keys[7] => $this->getVirtual(),
            $keys[8] => $this->getCreatedAt(),
            $keys[9] => $this->getUpdatedAt(),
            $keys[10] => $this->getVersion(),
            $keys[11] => $this->getVersionCreatedAt(),
            $keys[12] => $this->getVersionCreatedBy(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aTaxRule) {
                $result['TaxRule'] = $this->aTaxRule->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aTemplate) {
                $result['Template'] = $this->aTemplate->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aBrand) {
                $result['Brand'] = $this->aBrand->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collProductCategories) {
                $result['ProductCategories'] = $this->collProductCategories->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collFeatureProducts) {
                $result['FeatureProducts'] = $this->collFeatureProducts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductSaleElementss) {
                $result['ProductSaleElementss'] = $this->collProductSaleElementss->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductImages) {
                $result['ProductImages'] = $this->collProductImages->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductDocuments) {
                $result['ProductDocuments'] = $this->collProductDocuments->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAccessoriesRelatedByProductId) {
                $result['AccessoriesRelatedByProductId'] = $this->collAccessoriesRelatedByProductId->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collAccessoriesRelatedByAccessory) {
                $result['AccessoriesRelatedByAccessory'] = $this->collAccessoriesRelatedByAccessory->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collCartItems) {
                $result['CartItems'] = $this->collCartItems->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductAssociatedContents) {
                $result['ProductAssociatedContents'] = $this->collProductAssociatedContents->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collSaleProducts) {
                $result['SaleProducts'] = $this->collSaleProducts->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductI18ns) {
                $result['ProductI18ns'] = $this->collProductI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collProductVersions) {
                $result['ProductVersions'] = $this->collProductVersions->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
        $pos = ProductTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

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
                $this->setTaxRuleId($value);
                break;
            case 2:
                $this->setRef($value);
                break;
            case 3:
                $this->setVisible($value);
                break;
            case 4:
                $this->setPosition($value);
                break;
            case 5:
                $this->setTemplateId($value);
                break;
            case 6:
                $this->setBrandId($value);
                break;
            case 7:
                $this->setVirtual($value);
                break;
            case 8:
                $this->setCreatedAt($value);
                break;
            case 9:
                $this->setUpdatedAt($value);
                break;
            case 10:
                $this->setVersion($value);
                break;
            case 11:
                $this->setVersionCreatedAt($value);
                break;
            case 12:
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
        $keys = ProductTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setTaxRuleId($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setRef($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setVisible($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setPosition($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setTemplateId($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setBrandId($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setVirtual($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setCreatedAt($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setUpdatedAt($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setVersion($arr[$keys[10]]);
        if (array_key_exists($keys[11], $arr)) $this->setVersionCreatedAt($arr[$keys[11]]);
        if (array_key_exists($keys[12], $arr)) $this->setVersionCreatedBy($arr[$keys[12]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(ProductTableMap::DATABASE_NAME);

        if ($this->isColumnModified(ProductTableMap::ID)) $criteria->add(ProductTableMap::ID, $this->id);
        if ($this->isColumnModified(ProductTableMap::TAX_RULE_ID)) $criteria->add(ProductTableMap::TAX_RULE_ID, $this->tax_rule_id);
        if ($this->isColumnModified(ProductTableMap::REF)) $criteria->add(ProductTableMap::REF, $this->ref);
        if ($this->isColumnModified(ProductTableMap::VISIBLE)) $criteria->add(ProductTableMap::VISIBLE, $this->visible);
        if ($this->isColumnModified(ProductTableMap::POSITION)) $criteria->add(ProductTableMap::POSITION, $this->position);
        if ($this->isColumnModified(ProductTableMap::TEMPLATE_ID)) $criteria->add(ProductTableMap::TEMPLATE_ID, $this->template_id);
        if ($this->isColumnModified(ProductTableMap::BRAND_ID)) $criteria->add(ProductTableMap::BRAND_ID, $this->brand_id);
        if ($this->isColumnModified(ProductTableMap::VIRTUAL)) $criteria->add(ProductTableMap::VIRTUAL, $this->virtual);
        if ($this->isColumnModified(ProductTableMap::CREATED_AT)) $criteria->add(ProductTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(ProductTableMap::UPDATED_AT)) $criteria->add(ProductTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(ProductTableMap::VERSION)) $criteria->add(ProductTableMap::VERSION, $this->version);
        if ($this->isColumnModified(ProductTableMap::VERSION_CREATED_AT)) $criteria->add(ProductTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(ProductTableMap::VERSION_CREATED_BY)) $criteria->add(ProductTableMap::VERSION_CREATED_BY, $this->version_created_by);

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
        $criteria = new Criteria(ProductTableMap::DATABASE_NAME);
        $criteria->add(ProductTableMap::ID, $this->id);

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
     * @param      object $copyObj An object of \Thelia\Model\Product (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setTaxRuleId($this->getTaxRuleId());
        $copyObj->setRef($this->getRef());
        $copyObj->setVisible($this->getVisible());
        $copyObj->setPosition($this->getPosition());
        $copyObj->setTemplateId($this->getTemplateId());
        $copyObj->setBrandId($this->getBrandId());
        $copyObj->setVirtual($this->getVirtual());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getProductCategories() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductCategory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getFeatureProducts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addFeatureProduct($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductSaleElementss() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductSaleElements($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductImages() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductImage($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductDocuments() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductDocument($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAccessoriesRelatedByProductId() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAccessoryRelatedByProductId($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getAccessoriesRelatedByAccessory() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAccessoryRelatedByAccessory($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getCartItems() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addCartItem($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductAssociatedContents() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductAssociatedContent($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getSaleProducts() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addSaleProduct($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductI18n($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getProductVersions() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addProductVersion($relObj->copy($deepCopy));
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
     * @return                 \Thelia\Model\Product Clone of current object.
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
     * Declares an association between this object and a ChildTaxRule object.
     *
     * @param                  ChildTaxRule $v
     * @return                 \Thelia\Model\Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setTaxRule(ChildTaxRule $v = null)
    {
        if ($v === null) {
            $this->setTaxRuleId(NULL);
        } else {
            $this->setTaxRuleId($v->getId());
        }

        $this->aTaxRule = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildTaxRule object, it will not be re-added.
        if ($v !== null) {
            $v->addProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildTaxRule object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildTaxRule The associated ChildTaxRule object.
     * @throws PropelException
     */
    public function getTaxRule(ConnectionInterface $con = null)
    {
        if ($this->aTaxRule === null && ($this->tax_rule_id !== null)) {
            $this->aTaxRule = ChildTaxRuleQuery::create()->findPk($this->tax_rule_id, $con);
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
     * Declares an association between this object and a ChildTemplate object.
     *
     * @param                  ChildTemplate $v
     * @return                 \Thelia\Model\Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setTemplate(ChildTemplate $v = null)
    {
        if ($v === null) {
            $this->setTemplateId(NULL);
        } else {
            $this->setTemplateId($v->getId());
        }

        $this->aTemplate = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildTemplate object, it will not be re-added.
        if ($v !== null) {
            $v->addProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildTemplate object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildTemplate The associated ChildTemplate object.
     * @throws PropelException
     */
    public function getTemplate(ConnectionInterface $con = null)
    {
        if ($this->aTemplate === null && ($this->template_id !== null)) {
            $this->aTemplate = ChildTemplateQuery::create()->findPk($this->template_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aTemplate->addProducts($this);
             */
        }

        return $this->aTemplate;
    }

    /**
     * Declares an association between this object and a ChildBrand object.
     *
     * @param                  ChildBrand $v
     * @return                 \Thelia\Model\Product The current object (for fluent API support)
     * @throws PropelException
     */
    public function setBrand(ChildBrand $v = null)
    {
        if ($v === null) {
            $this->setBrandId(NULL);
        } else {
            $this->setBrandId($v->getId());
        }

        $this->aBrand = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildBrand object, it will not be re-added.
        if ($v !== null) {
            $v->addProduct($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildBrand object
     *
     * @param      ConnectionInterface $con Optional Connection object.
     * @return                 ChildBrand The associated ChildBrand object.
     * @throws PropelException
     */
    public function getBrand(ConnectionInterface $con = null)
    {
        if ($this->aBrand === null && ($this->brand_id !== null)) {
            $this->aBrand = ChildBrandQuery::create()->findPk($this->brand_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aBrand->addProducts($this);
             */
        }

        return $this->aBrand;
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
        if ('ProductCategory' == $relationName) {
            return $this->initProductCategories();
        }
        if ('FeatureProduct' == $relationName) {
            return $this->initFeatureProducts();
        }
        if ('ProductSaleElements' == $relationName) {
            return $this->initProductSaleElementss();
        }
        if ('ProductImage' == $relationName) {
            return $this->initProductImages();
        }
        if ('ProductDocument' == $relationName) {
            return $this->initProductDocuments();
        }
        if ('AccessoryRelatedByProductId' == $relationName) {
            return $this->initAccessoriesRelatedByProductId();
        }
        if ('AccessoryRelatedByAccessory' == $relationName) {
            return $this->initAccessoriesRelatedByAccessory();
        }
        if ('CartItem' == $relationName) {
            return $this->initCartItems();
        }
        if ('ProductAssociatedContent' == $relationName) {
            return $this->initProductAssociatedContents();
        }
        if ('SaleProduct' == $relationName) {
            return $this->initSaleProducts();
        }
        if ('ProductI18n' == $relationName) {
            return $this->initProductI18ns();
        }
        if ('ProductVersion' == $relationName) {
            return $this->initProductVersions();
        }
    }

    /**
     * Clears out the collProductCategories collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductCategories()
     */
    public function clearProductCategories()
    {
        $this->collProductCategories = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductCategories collection loaded partially.
     */
    public function resetPartialProductCategories($v = true)
    {
        $this->collProductCategoriesPartial = $v;
    }

    /**
     * Initializes the collProductCategories collection.
     *
     * By default this just sets the collProductCategories collection to an empty array (like clearcollProductCategories());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductCategories($overrideExisting = true)
    {
        if (null !== $this->collProductCategories && !$overrideExisting) {
            return;
        }
        $this->collProductCategories = new ObjectCollection();
        $this->collProductCategories->setModel('\Thelia\Model\ProductCategory');
    }

    /**
     * Gets an array of ChildProductCategory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductCategory[] List of ChildProductCategory objects
     * @throws PropelException
     */
    public function getProductCategories($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductCategoriesPartial && !$this->isNew();
        if (null === $this->collProductCategories || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductCategories) {
                // return empty collection
                $this->initProductCategories();
            } else {
                $collProductCategories = ChildProductCategoryQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductCategoriesPartial && count($collProductCategories)) {
                        $this->initProductCategories(false);

                        foreach ($collProductCategories as $obj) {
                            if (false == $this->collProductCategories->contains($obj)) {
                                $this->collProductCategories->append($obj);
                            }
                        }

                        $this->collProductCategoriesPartial = true;
                    }

                    reset($collProductCategories);

                    return $collProductCategories;
                }

                if ($partial && $this->collProductCategories) {
                    foreach ($this->collProductCategories as $obj) {
                        if ($obj->isNew()) {
                            $collProductCategories[] = $obj;
                        }
                    }
                }

                $this->collProductCategories = $collProductCategories;
                $this->collProductCategoriesPartial = false;
            }
        }

        return $this->collProductCategories;
    }

    /**
     * Sets a collection of ProductCategory objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productCategories A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setProductCategories(Collection $productCategories, ConnectionInterface $con = null)
    {
        $productCategoriesToDelete = $this->getProductCategories(new Criteria(), $con)->diff($productCategories);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->productCategoriesScheduledForDeletion = clone $productCategoriesToDelete;

        foreach ($productCategoriesToDelete as $productCategoryRemoved) {
            $productCategoryRemoved->setProduct(null);
        }

        $this->collProductCategories = null;
        foreach ($productCategories as $productCategory) {
            $this->addProductCategory($productCategory);
        }

        $this->collProductCategories = $productCategories;
        $this->collProductCategoriesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductCategory objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductCategory objects.
     * @throws PropelException
     */
    public function countProductCategories(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductCategoriesPartial && !$this->isNew();
        if (null === $this->collProductCategories || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductCategories) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductCategories());
            }

            $query = ChildProductCategoryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collProductCategories);
    }

    /**
     * Method called to associate a ChildProductCategory object to this object
     * through the ChildProductCategory foreign key attribute.
     *
     * @param    ChildProductCategory $l ChildProductCategory
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addProductCategory(ChildProductCategory $l)
    {
        if ($this->collProductCategories === null) {
            $this->initProductCategories();
            $this->collProductCategoriesPartial = true;
        }

        if (!in_array($l, $this->collProductCategories->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductCategory($l);
        }

        return $this;
    }

    /**
     * @param ProductCategory $productCategory The productCategory object to add.
     */
    protected function doAddProductCategory($productCategory)
    {
        $this->collProductCategories[]= $productCategory;
        $productCategory->setProduct($this);
    }

    /**
     * @param  ProductCategory $productCategory The productCategory object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductCategory($productCategory)
    {
        if ($this->getProductCategories()->contains($productCategory)) {
            $this->collProductCategories->remove($this->collProductCategories->search($productCategory));
            if (null === $this->productCategoriesScheduledForDeletion) {
                $this->productCategoriesScheduledForDeletion = clone $this->collProductCategories;
                $this->productCategoriesScheduledForDeletion->clear();
            }
            $this->productCategoriesScheduledForDeletion[]= clone $productCategory;
            $productCategory->setProduct(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related ProductCategories from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProductCategory[] List of ChildProductCategory objects
     */
    public function getProductCategoriesJoinCategory($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProductCategoryQuery::create(null, $criteria);
        $query->joinWith('Category', $joinBehavior);

        return $this->getProductCategories($query, $con);
    }

    /**
     * Clears out the collFeatureProducts collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addFeatureProducts()
     */
    public function clearFeatureProducts()
    {
        $this->collFeatureProducts = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collFeatureProducts collection loaded partially.
     */
    public function resetPartialFeatureProducts($v = true)
    {
        $this->collFeatureProductsPartial = $v;
    }

    /**
     * Initializes the collFeatureProducts collection.
     *
     * By default this just sets the collFeatureProducts collection to an empty array (like clearcollFeatureProducts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initFeatureProducts($overrideExisting = true)
    {
        if (null !== $this->collFeatureProducts && !$overrideExisting) {
            return;
        }
        $this->collFeatureProducts = new ObjectCollection();
        $this->collFeatureProducts->setModel('\Thelia\Model\FeatureProduct');
    }

    /**
     * Gets an array of ChildFeatureProduct objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildFeatureProduct[] List of ChildFeatureProduct objects
     * @throws PropelException
     */
    public function getFeatureProducts($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureProductsPartial && !$this->isNew();
        if (null === $this->collFeatureProducts || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collFeatureProducts) {
                // return empty collection
                $this->initFeatureProducts();
            } else {
                $collFeatureProducts = ChildFeatureProductQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collFeatureProductsPartial && count($collFeatureProducts)) {
                        $this->initFeatureProducts(false);

                        foreach ($collFeatureProducts as $obj) {
                            if (false == $this->collFeatureProducts->contains($obj)) {
                                $this->collFeatureProducts->append($obj);
                            }
                        }

                        $this->collFeatureProductsPartial = true;
                    }

                    reset($collFeatureProducts);

                    return $collFeatureProducts;
                }

                if ($partial && $this->collFeatureProducts) {
                    foreach ($this->collFeatureProducts as $obj) {
                        if ($obj->isNew()) {
                            $collFeatureProducts[] = $obj;
                        }
                    }
                }

                $this->collFeatureProducts = $collFeatureProducts;
                $this->collFeatureProductsPartial = false;
            }
        }

        return $this->collFeatureProducts;
    }

    /**
     * Sets a collection of FeatureProduct objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $featureProducts A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setFeatureProducts(Collection $featureProducts, ConnectionInterface $con = null)
    {
        $featureProductsToDelete = $this->getFeatureProducts(new Criteria(), $con)->diff($featureProducts);


        $this->featureProductsScheduledForDeletion = $featureProductsToDelete;

        foreach ($featureProductsToDelete as $featureProductRemoved) {
            $featureProductRemoved->setProduct(null);
        }

        $this->collFeatureProducts = null;
        foreach ($featureProducts as $featureProduct) {
            $this->addFeatureProduct($featureProduct);
        }

        $this->collFeatureProducts = $featureProducts;
        $this->collFeatureProductsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related FeatureProduct objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related FeatureProduct objects.
     * @throws PropelException
     */
    public function countFeatureProducts(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collFeatureProductsPartial && !$this->isNew();
        if (null === $this->collFeatureProducts || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collFeatureProducts) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getFeatureProducts());
            }

            $query = ChildFeatureProductQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collFeatureProducts);
    }

    /**
     * Method called to associate a ChildFeatureProduct object to this object
     * through the ChildFeatureProduct foreign key attribute.
     *
     * @param    ChildFeatureProduct $l ChildFeatureProduct
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addFeatureProduct(ChildFeatureProduct $l)
    {
        if ($this->collFeatureProducts === null) {
            $this->initFeatureProducts();
            $this->collFeatureProductsPartial = true;
        }

        if (!in_array($l, $this->collFeatureProducts->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddFeatureProduct($l);
        }

        return $this;
    }

    /**
     * @param FeatureProduct $featureProduct The featureProduct object to add.
     */
    protected function doAddFeatureProduct($featureProduct)
    {
        $this->collFeatureProducts[]= $featureProduct;
        $featureProduct->setProduct($this);
    }

    /**
     * @param  FeatureProduct $featureProduct The featureProduct object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeFeatureProduct($featureProduct)
    {
        if ($this->getFeatureProducts()->contains($featureProduct)) {
            $this->collFeatureProducts->remove($this->collFeatureProducts->search($featureProduct));
            if (null === $this->featureProductsScheduledForDeletion) {
                $this->featureProductsScheduledForDeletion = clone $this->collFeatureProducts;
                $this->featureProductsScheduledForDeletion->clear();
            }
            $this->featureProductsScheduledForDeletion[]= clone $featureProduct;
            $featureProduct->setProduct(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related FeatureProducts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildFeatureProduct[] List of ChildFeatureProduct objects
     */
    public function getFeatureProductsJoinFeature($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildFeatureProductQuery::create(null, $criteria);
        $query->joinWith('Feature', $joinBehavior);

        return $this->getFeatureProducts($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related FeatureProducts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildFeatureProduct[] List of ChildFeatureProduct objects
     */
    public function getFeatureProductsJoinFeatureAv($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildFeatureProductQuery::create(null, $criteria);
        $query->joinWith('FeatureAv', $joinBehavior);

        return $this->getFeatureProducts($query, $con);
    }

    /**
     * Clears out the collProductSaleElementss collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductSaleElementss()
     */
    public function clearProductSaleElementss()
    {
        $this->collProductSaleElementss = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductSaleElementss collection loaded partially.
     */
    public function resetPartialProductSaleElementss($v = true)
    {
        $this->collProductSaleElementssPartial = $v;
    }

    /**
     * Initializes the collProductSaleElementss collection.
     *
     * By default this just sets the collProductSaleElementss collection to an empty array (like clearcollProductSaleElementss());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductSaleElementss($overrideExisting = true)
    {
        if (null !== $this->collProductSaleElementss && !$overrideExisting) {
            return;
        }
        $this->collProductSaleElementss = new ObjectCollection();
        $this->collProductSaleElementss->setModel('\Thelia\Model\ProductSaleElements');
    }

    /**
     * Gets an array of ChildProductSaleElements objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductSaleElements[] List of ChildProductSaleElements objects
     * @throws PropelException
     */
    public function getProductSaleElementss($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductSaleElementssPartial && !$this->isNew();
        if (null === $this->collProductSaleElementss || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductSaleElementss) {
                // return empty collection
                $this->initProductSaleElementss();
            } else {
                $collProductSaleElementss = ChildProductSaleElementsQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductSaleElementssPartial && count($collProductSaleElementss)) {
                        $this->initProductSaleElementss(false);

                        foreach ($collProductSaleElementss as $obj) {
                            if (false == $this->collProductSaleElementss->contains($obj)) {
                                $this->collProductSaleElementss->append($obj);
                            }
                        }

                        $this->collProductSaleElementssPartial = true;
                    }

                    reset($collProductSaleElementss);

                    return $collProductSaleElementss;
                }

                if ($partial && $this->collProductSaleElementss) {
                    foreach ($this->collProductSaleElementss as $obj) {
                        if ($obj->isNew()) {
                            $collProductSaleElementss[] = $obj;
                        }
                    }
                }

                $this->collProductSaleElementss = $collProductSaleElementss;
                $this->collProductSaleElementssPartial = false;
            }
        }

        return $this->collProductSaleElementss;
    }

    /**
     * Sets a collection of ProductSaleElements objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productSaleElementss A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setProductSaleElementss(Collection $productSaleElementss, ConnectionInterface $con = null)
    {
        $productSaleElementssToDelete = $this->getProductSaleElementss(new Criteria(), $con)->diff($productSaleElementss);


        $this->productSaleElementssScheduledForDeletion = $productSaleElementssToDelete;

        foreach ($productSaleElementssToDelete as $productSaleElementsRemoved) {
            $productSaleElementsRemoved->setProduct(null);
        }

        $this->collProductSaleElementss = null;
        foreach ($productSaleElementss as $productSaleElements) {
            $this->addProductSaleElements($productSaleElements);
        }

        $this->collProductSaleElementss = $productSaleElementss;
        $this->collProductSaleElementssPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductSaleElements objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductSaleElements objects.
     * @throws PropelException
     */
    public function countProductSaleElementss(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductSaleElementssPartial && !$this->isNew();
        if (null === $this->collProductSaleElementss || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductSaleElementss) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductSaleElementss());
            }

            $query = ChildProductSaleElementsQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collProductSaleElementss);
    }

    /**
     * Method called to associate a ChildProductSaleElements object to this object
     * through the ChildProductSaleElements foreign key attribute.
     *
     * @param    ChildProductSaleElements $l ChildProductSaleElements
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addProductSaleElements(ChildProductSaleElements $l)
    {
        if ($this->collProductSaleElementss === null) {
            $this->initProductSaleElementss();
            $this->collProductSaleElementssPartial = true;
        }

        if (!in_array($l, $this->collProductSaleElementss->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductSaleElements($l);
        }

        return $this;
    }

    /**
     * @param ProductSaleElements $productSaleElements The productSaleElements object to add.
     */
    protected function doAddProductSaleElements($productSaleElements)
    {
        $this->collProductSaleElementss[]= $productSaleElements;
        $productSaleElements->setProduct($this);
    }

    /**
     * @param  ProductSaleElements $productSaleElements The productSaleElements object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductSaleElements($productSaleElements)
    {
        if ($this->getProductSaleElementss()->contains($productSaleElements)) {
            $this->collProductSaleElementss->remove($this->collProductSaleElementss->search($productSaleElements));
            if (null === $this->productSaleElementssScheduledForDeletion) {
                $this->productSaleElementssScheduledForDeletion = clone $this->collProductSaleElementss;
                $this->productSaleElementssScheduledForDeletion->clear();
            }
            $this->productSaleElementssScheduledForDeletion[]= clone $productSaleElements;
            $productSaleElements->setProduct(null);
        }

        return $this;
    }

    /**
     * Clears out the collProductImages collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductImages()
     */
    public function clearProductImages()
    {
        $this->collProductImages = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductImages collection loaded partially.
     */
    public function resetPartialProductImages($v = true)
    {
        $this->collProductImagesPartial = $v;
    }

    /**
     * Initializes the collProductImages collection.
     *
     * By default this just sets the collProductImages collection to an empty array (like clearcollProductImages());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductImages($overrideExisting = true)
    {
        if (null !== $this->collProductImages && !$overrideExisting) {
            return;
        }
        $this->collProductImages = new ObjectCollection();
        $this->collProductImages->setModel('\Thelia\Model\ProductImage');
    }

    /**
     * Gets an array of ChildProductImage objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductImage[] List of ChildProductImage objects
     * @throws PropelException
     */
    public function getProductImages($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductImagesPartial && !$this->isNew();
        if (null === $this->collProductImages || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductImages) {
                // return empty collection
                $this->initProductImages();
            } else {
                $collProductImages = ChildProductImageQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductImagesPartial && count($collProductImages)) {
                        $this->initProductImages(false);

                        foreach ($collProductImages as $obj) {
                            if (false == $this->collProductImages->contains($obj)) {
                                $this->collProductImages->append($obj);
                            }
                        }

                        $this->collProductImagesPartial = true;
                    }

                    reset($collProductImages);

                    return $collProductImages;
                }

                if ($partial && $this->collProductImages) {
                    foreach ($this->collProductImages as $obj) {
                        if ($obj->isNew()) {
                            $collProductImages[] = $obj;
                        }
                    }
                }

                $this->collProductImages = $collProductImages;
                $this->collProductImagesPartial = false;
            }
        }

        return $this->collProductImages;
    }

    /**
     * Sets a collection of ProductImage objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productImages A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setProductImages(Collection $productImages, ConnectionInterface $con = null)
    {
        $productImagesToDelete = $this->getProductImages(new Criteria(), $con)->diff($productImages);


        $this->productImagesScheduledForDeletion = $productImagesToDelete;

        foreach ($productImagesToDelete as $productImageRemoved) {
            $productImageRemoved->setProduct(null);
        }

        $this->collProductImages = null;
        foreach ($productImages as $productImage) {
            $this->addProductImage($productImage);
        }

        $this->collProductImages = $productImages;
        $this->collProductImagesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductImage objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductImage objects.
     * @throws PropelException
     */
    public function countProductImages(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductImagesPartial && !$this->isNew();
        if (null === $this->collProductImages || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductImages) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductImages());
            }

            $query = ChildProductImageQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collProductImages);
    }

    /**
     * Method called to associate a ChildProductImage object to this object
     * through the ChildProductImage foreign key attribute.
     *
     * @param    ChildProductImage $l ChildProductImage
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addProductImage(ChildProductImage $l)
    {
        if ($this->collProductImages === null) {
            $this->initProductImages();
            $this->collProductImagesPartial = true;
        }

        if (!in_array($l, $this->collProductImages->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductImage($l);
        }

        return $this;
    }

    /**
     * @param ProductImage $productImage The productImage object to add.
     */
    protected function doAddProductImage($productImage)
    {
        $this->collProductImages[]= $productImage;
        $productImage->setProduct($this);
    }

    /**
     * @param  ProductImage $productImage The productImage object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductImage($productImage)
    {
        if ($this->getProductImages()->contains($productImage)) {
            $this->collProductImages->remove($this->collProductImages->search($productImage));
            if (null === $this->productImagesScheduledForDeletion) {
                $this->productImagesScheduledForDeletion = clone $this->collProductImages;
                $this->productImagesScheduledForDeletion->clear();
            }
            $this->productImagesScheduledForDeletion[]= clone $productImage;
            $productImage->setProduct(null);
        }

        return $this;
    }

    /**
     * Clears out the collProductDocuments collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductDocuments()
     */
    public function clearProductDocuments()
    {
        $this->collProductDocuments = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductDocuments collection loaded partially.
     */
    public function resetPartialProductDocuments($v = true)
    {
        $this->collProductDocumentsPartial = $v;
    }

    /**
     * Initializes the collProductDocuments collection.
     *
     * By default this just sets the collProductDocuments collection to an empty array (like clearcollProductDocuments());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductDocuments($overrideExisting = true)
    {
        if (null !== $this->collProductDocuments && !$overrideExisting) {
            return;
        }
        $this->collProductDocuments = new ObjectCollection();
        $this->collProductDocuments->setModel('\Thelia\Model\ProductDocument');
    }

    /**
     * Gets an array of ChildProductDocument objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductDocument[] List of ChildProductDocument objects
     * @throws PropelException
     */
    public function getProductDocuments($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductDocumentsPartial && !$this->isNew();
        if (null === $this->collProductDocuments || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductDocuments) {
                // return empty collection
                $this->initProductDocuments();
            } else {
                $collProductDocuments = ChildProductDocumentQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductDocumentsPartial && count($collProductDocuments)) {
                        $this->initProductDocuments(false);

                        foreach ($collProductDocuments as $obj) {
                            if (false == $this->collProductDocuments->contains($obj)) {
                                $this->collProductDocuments->append($obj);
                            }
                        }

                        $this->collProductDocumentsPartial = true;
                    }

                    reset($collProductDocuments);

                    return $collProductDocuments;
                }

                if ($partial && $this->collProductDocuments) {
                    foreach ($this->collProductDocuments as $obj) {
                        if ($obj->isNew()) {
                            $collProductDocuments[] = $obj;
                        }
                    }
                }

                $this->collProductDocuments = $collProductDocuments;
                $this->collProductDocumentsPartial = false;
            }
        }

        return $this->collProductDocuments;
    }

    /**
     * Sets a collection of ProductDocument objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productDocuments A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setProductDocuments(Collection $productDocuments, ConnectionInterface $con = null)
    {
        $productDocumentsToDelete = $this->getProductDocuments(new Criteria(), $con)->diff($productDocuments);


        $this->productDocumentsScheduledForDeletion = $productDocumentsToDelete;

        foreach ($productDocumentsToDelete as $productDocumentRemoved) {
            $productDocumentRemoved->setProduct(null);
        }

        $this->collProductDocuments = null;
        foreach ($productDocuments as $productDocument) {
            $this->addProductDocument($productDocument);
        }

        $this->collProductDocuments = $productDocuments;
        $this->collProductDocumentsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductDocument objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductDocument objects.
     * @throws PropelException
     */
    public function countProductDocuments(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductDocumentsPartial && !$this->isNew();
        if (null === $this->collProductDocuments || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductDocuments) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductDocuments());
            }

            $query = ChildProductDocumentQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collProductDocuments);
    }

    /**
     * Method called to associate a ChildProductDocument object to this object
     * through the ChildProductDocument foreign key attribute.
     *
     * @param    ChildProductDocument $l ChildProductDocument
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addProductDocument(ChildProductDocument $l)
    {
        if ($this->collProductDocuments === null) {
            $this->initProductDocuments();
            $this->collProductDocumentsPartial = true;
        }

        if (!in_array($l, $this->collProductDocuments->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductDocument($l);
        }

        return $this;
    }

    /**
     * @param ProductDocument $productDocument The productDocument object to add.
     */
    protected function doAddProductDocument($productDocument)
    {
        $this->collProductDocuments[]= $productDocument;
        $productDocument->setProduct($this);
    }

    /**
     * @param  ProductDocument $productDocument The productDocument object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductDocument($productDocument)
    {
        if ($this->getProductDocuments()->contains($productDocument)) {
            $this->collProductDocuments->remove($this->collProductDocuments->search($productDocument));
            if (null === $this->productDocumentsScheduledForDeletion) {
                $this->productDocumentsScheduledForDeletion = clone $this->collProductDocuments;
                $this->productDocumentsScheduledForDeletion->clear();
            }
            $this->productDocumentsScheduledForDeletion[]= clone $productDocument;
            $productDocument->setProduct(null);
        }

        return $this;
    }

    /**
     * Clears out the collAccessoriesRelatedByProductId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAccessoriesRelatedByProductId()
     */
    public function clearAccessoriesRelatedByProductId()
    {
        $this->collAccessoriesRelatedByProductId = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAccessoriesRelatedByProductId collection loaded partially.
     */
    public function resetPartialAccessoriesRelatedByProductId($v = true)
    {
        $this->collAccessoriesRelatedByProductIdPartial = $v;
    }

    /**
     * Initializes the collAccessoriesRelatedByProductId collection.
     *
     * By default this just sets the collAccessoriesRelatedByProductId collection to an empty array (like clearcollAccessoriesRelatedByProductId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAccessoriesRelatedByProductId($overrideExisting = true)
    {
        if (null !== $this->collAccessoriesRelatedByProductId && !$overrideExisting) {
            return;
        }
        $this->collAccessoriesRelatedByProductId = new ObjectCollection();
        $this->collAccessoriesRelatedByProductId->setModel('\Thelia\Model\Accessory');
    }

    /**
     * Gets an array of ChildAccessory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAccessory[] List of ChildAccessory objects
     * @throws PropelException
     */
    public function getAccessoriesRelatedByProductId($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAccessoriesRelatedByProductIdPartial && !$this->isNew();
        if (null === $this->collAccessoriesRelatedByProductId || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAccessoriesRelatedByProductId) {
                // return empty collection
                $this->initAccessoriesRelatedByProductId();
            } else {
                $collAccessoriesRelatedByProductId = ChildAccessoryQuery::create(null, $criteria)
                    ->filterByProductRelatedByProductId($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAccessoriesRelatedByProductIdPartial && count($collAccessoriesRelatedByProductId)) {
                        $this->initAccessoriesRelatedByProductId(false);

                        foreach ($collAccessoriesRelatedByProductId as $obj) {
                            if (false == $this->collAccessoriesRelatedByProductId->contains($obj)) {
                                $this->collAccessoriesRelatedByProductId->append($obj);
                            }
                        }

                        $this->collAccessoriesRelatedByProductIdPartial = true;
                    }

                    reset($collAccessoriesRelatedByProductId);

                    return $collAccessoriesRelatedByProductId;
                }

                if ($partial && $this->collAccessoriesRelatedByProductId) {
                    foreach ($this->collAccessoriesRelatedByProductId as $obj) {
                        if ($obj->isNew()) {
                            $collAccessoriesRelatedByProductId[] = $obj;
                        }
                    }
                }

                $this->collAccessoriesRelatedByProductId = $collAccessoriesRelatedByProductId;
                $this->collAccessoriesRelatedByProductIdPartial = false;
            }
        }

        return $this->collAccessoriesRelatedByProductId;
    }

    /**
     * Sets a collection of AccessoryRelatedByProductId objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $accessoriesRelatedByProductId A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setAccessoriesRelatedByProductId(Collection $accessoriesRelatedByProductId, ConnectionInterface $con = null)
    {
        $accessoriesRelatedByProductIdToDelete = $this->getAccessoriesRelatedByProductId(new Criteria(), $con)->diff($accessoriesRelatedByProductId);


        $this->accessoriesRelatedByProductIdScheduledForDeletion = $accessoriesRelatedByProductIdToDelete;

        foreach ($accessoriesRelatedByProductIdToDelete as $accessoryRelatedByProductIdRemoved) {
            $accessoryRelatedByProductIdRemoved->setProductRelatedByProductId(null);
        }

        $this->collAccessoriesRelatedByProductId = null;
        foreach ($accessoriesRelatedByProductId as $accessoryRelatedByProductId) {
            $this->addAccessoryRelatedByProductId($accessoryRelatedByProductId);
        }

        $this->collAccessoriesRelatedByProductId = $accessoriesRelatedByProductId;
        $this->collAccessoriesRelatedByProductIdPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Accessory objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Accessory objects.
     * @throws PropelException
     */
    public function countAccessoriesRelatedByProductId(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAccessoriesRelatedByProductIdPartial && !$this->isNew();
        if (null === $this->collAccessoriesRelatedByProductId || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAccessoriesRelatedByProductId) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAccessoriesRelatedByProductId());
            }

            $query = ChildAccessoryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProductRelatedByProductId($this)
                ->count($con);
        }

        return count($this->collAccessoriesRelatedByProductId);
    }

    /**
     * Method called to associate a ChildAccessory object to this object
     * through the ChildAccessory foreign key attribute.
     *
     * @param    ChildAccessory $l ChildAccessory
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addAccessoryRelatedByProductId(ChildAccessory $l)
    {
        if ($this->collAccessoriesRelatedByProductId === null) {
            $this->initAccessoriesRelatedByProductId();
            $this->collAccessoriesRelatedByProductIdPartial = true;
        }

        if (!in_array($l, $this->collAccessoriesRelatedByProductId->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAccessoryRelatedByProductId($l);
        }

        return $this;
    }

    /**
     * @param AccessoryRelatedByProductId $accessoryRelatedByProductId The accessoryRelatedByProductId object to add.
     */
    protected function doAddAccessoryRelatedByProductId($accessoryRelatedByProductId)
    {
        $this->collAccessoriesRelatedByProductId[]= $accessoryRelatedByProductId;
        $accessoryRelatedByProductId->setProductRelatedByProductId($this);
    }

    /**
     * @param  AccessoryRelatedByProductId $accessoryRelatedByProductId The accessoryRelatedByProductId object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeAccessoryRelatedByProductId($accessoryRelatedByProductId)
    {
        if ($this->getAccessoriesRelatedByProductId()->contains($accessoryRelatedByProductId)) {
            $this->collAccessoriesRelatedByProductId->remove($this->collAccessoriesRelatedByProductId->search($accessoryRelatedByProductId));
            if (null === $this->accessoriesRelatedByProductIdScheduledForDeletion) {
                $this->accessoriesRelatedByProductIdScheduledForDeletion = clone $this->collAccessoriesRelatedByProductId;
                $this->accessoriesRelatedByProductIdScheduledForDeletion->clear();
            }
            $this->accessoriesRelatedByProductIdScheduledForDeletion[]= clone $accessoryRelatedByProductId;
            $accessoryRelatedByProductId->setProductRelatedByProductId(null);
        }

        return $this;
    }

    /**
     * Clears out the collAccessoriesRelatedByAccessory collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAccessoriesRelatedByAccessory()
     */
    public function clearAccessoriesRelatedByAccessory()
    {
        $this->collAccessoriesRelatedByAccessory = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAccessoriesRelatedByAccessory collection loaded partially.
     */
    public function resetPartialAccessoriesRelatedByAccessory($v = true)
    {
        $this->collAccessoriesRelatedByAccessoryPartial = $v;
    }

    /**
     * Initializes the collAccessoriesRelatedByAccessory collection.
     *
     * By default this just sets the collAccessoriesRelatedByAccessory collection to an empty array (like clearcollAccessoriesRelatedByAccessory());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAccessoriesRelatedByAccessory($overrideExisting = true)
    {
        if (null !== $this->collAccessoriesRelatedByAccessory && !$overrideExisting) {
            return;
        }
        $this->collAccessoriesRelatedByAccessory = new ObjectCollection();
        $this->collAccessoriesRelatedByAccessory->setModel('\Thelia\Model\Accessory');
    }

    /**
     * Gets an array of ChildAccessory objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAccessory[] List of ChildAccessory objects
     * @throws PropelException
     */
    public function getAccessoriesRelatedByAccessory($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAccessoriesRelatedByAccessoryPartial && !$this->isNew();
        if (null === $this->collAccessoriesRelatedByAccessory || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAccessoriesRelatedByAccessory) {
                // return empty collection
                $this->initAccessoriesRelatedByAccessory();
            } else {
                $collAccessoriesRelatedByAccessory = ChildAccessoryQuery::create(null, $criteria)
                    ->filterByProductRelatedByAccessory($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAccessoriesRelatedByAccessoryPartial && count($collAccessoriesRelatedByAccessory)) {
                        $this->initAccessoriesRelatedByAccessory(false);

                        foreach ($collAccessoriesRelatedByAccessory as $obj) {
                            if (false == $this->collAccessoriesRelatedByAccessory->contains($obj)) {
                                $this->collAccessoriesRelatedByAccessory->append($obj);
                            }
                        }

                        $this->collAccessoriesRelatedByAccessoryPartial = true;
                    }

                    reset($collAccessoriesRelatedByAccessory);

                    return $collAccessoriesRelatedByAccessory;
                }

                if ($partial && $this->collAccessoriesRelatedByAccessory) {
                    foreach ($this->collAccessoriesRelatedByAccessory as $obj) {
                        if ($obj->isNew()) {
                            $collAccessoriesRelatedByAccessory[] = $obj;
                        }
                    }
                }

                $this->collAccessoriesRelatedByAccessory = $collAccessoriesRelatedByAccessory;
                $this->collAccessoriesRelatedByAccessoryPartial = false;
            }
        }

        return $this->collAccessoriesRelatedByAccessory;
    }

    /**
     * Sets a collection of AccessoryRelatedByAccessory objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $accessoriesRelatedByAccessory A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setAccessoriesRelatedByAccessory(Collection $accessoriesRelatedByAccessory, ConnectionInterface $con = null)
    {
        $accessoriesRelatedByAccessoryToDelete = $this->getAccessoriesRelatedByAccessory(new Criteria(), $con)->diff($accessoriesRelatedByAccessory);


        $this->accessoriesRelatedByAccessoryScheduledForDeletion = $accessoriesRelatedByAccessoryToDelete;

        foreach ($accessoriesRelatedByAccessoryToDelete as $accessoryRelatedByAccessoryRemoved) {
            $accessoryRelatedByAccessoryRemoved->setProductRelatedByAccessory(null);
        }

        $this->collAccessoriesRelatedByAccessory = null;
        foreach ($accessoriesRelatedByAccessory as $accessoryRelatedByAccessory) {
            $this->addAccessoryRelatedByAccessory($accessoryRelatedByAccessory);
        }

        $this->collAccessoriesRelatedByAccessory = $accessoriesRelatedByAccessory;
        $this->collAccessoriesRelatedByAccessoryPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Accessory objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Accessory objects.
     * @throws PropelException
     */
    public function countAccessoriesRelatedByAccessory(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAccessoriesRelatedByAccessoryPartial && !$this->isNew();
        if (null === $this->collAccessoriesRelatedByAccessory || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAccessoriesRelatedByAccessory) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAccessoriesRelatedByAccessory());
            }

            $query = ChildAccessoryQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProductRelatedByAccessory($this)
                ->count($con);
        }

        return count($this->collAccessoriesRelatedByAccessory);
    }

    /**
     * Method called to associate a ChildAccessory object to this object
     * through the ChildAccessory foreign key attribute.
     *
     * @param    ChildAccessory $l ChildAccessory
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addAccessoryRelatedByAccessory(ChildAccessory $l)
    {
        if ($this->collAccessoriesRelatedByAccessory === null) {
            $this->initAccessoriesRelatedByAccessory();
            $this->collAccessoriesRelatedByAccessoryPartial = true;
        }

        if (!in_array($l, $this->collAccessoriesRelatedByAccessory->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAccessoryRelatedByAccessory($l);
        }

        return $this;
    }

    /**
     * @param AccessoryRelatedByAccessory $accessoryRelatedByAccessory The accessoryRelatedByAccessory object to add.
     */
    protected function doAddAccessoryRelatedByAccessory($accessoryRelatedByAccessory)
    {
        $this->collAccessoriesRelatedByAccessory[]= $accessoryRelatedByAccessory;
        $accessoryRelatedByAccessory->setProductRelatedByAccessory($this);
    }

    /**
     * @param  AccessoryRelatedByAccessory $accessoryRelatedByAccessory The accessoryRelatedByAccessory object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeAccessoryRelatedByAccessory($accessoryRelatedByAccessory)
    {
        if ($this->getAccessoriesRelatedByAccessory()->contains($accessoryRelatedByAccessory)) {
            $this->collAccessoriesRelatedByAccessory->remove($this->collAccessoriesRelatedByAccessory->search($accessoryRelatedByAccessory));
            if (null === $this->accessoriesRelatedByAccessoryScheduledForDeletion) {
                $this->accessoriesRelatedByAccessoryScheduledForDeletion = clone $this->collAccessoriesRelatedByAccessory;
                $this->accessoriesRelatedByAccessoryScheduledForDeletion->clear();
            }
            $this->accessoriesRelatedByAccessoryScheduledForDeletion[]= clone $accessoryRelatedByAccessory;
            $accessoryRelatedByAccessory->setProductRelatedByAccessory(null);
        }

        return $this;
    }

    /**
     * Clears out the collCartItems collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCartItems()
     */
    public function clearCartItems()
    {
        $this->collCartItems = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collCartItems collection loaded partially.
     */
    public function resetPartialCartItems($v = true)
    {
        $this->collCartItemsPartial = $v;
    }

    /**
     * Initializes the collCartItems collection.
     *
     * By default this just sets the collCartItems collection to an empty array (like clearcollCartItems());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initCartItems($overrideExisting = true)
    {
        if (null !== $this->collCartItems && !$overrideExisting) {
            return;
        }
        $this->collCartItems = new ObjectCollection();
        $this->collCartItems->setModel('\Thelia\Model\CartItem');
    }

    /**
     * Gets an array of ChildCartItem objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildCartItem[] List of ChildCartItem objects
     * @throws PropelException
     */
    public function getCartItems($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collCartItemsPartial && !$this->isNew();
        if (null === $this->collCartItems || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collCartItems) {
                // return empty collection
                $this->initCartItems();
            } else {
                $collCartItems = ChildCartItemQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collCartItemsPartial && count($collCartItems)) {
                        $this->initCartItems(false);

                        foreach ($collCartItems as $obj) {
                            if (false == $this->collCartItems->contains($obj)) {
                                $this->collCartItems->append($obj);
                            }
                        }

                        $this->collCartItemsPartial = true;
                    }

                    reset($collCartItems);

                    return $collCartItems;
                }

                if ($partial && $this->collCartItems) {
                    foreach ($this->collCartItems as $obj) {
                        if ($obj->isNew()) {
                            $collCartItems[] = $obj;
                        }
                    }
                }

                $this->collCartItems = $collCartItems;
                $this->collCartItemsPartial = false;
            }
        }

        return $this->collCartItems;
    }

    /**
     * Sets a collection of CartItem objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $cartItems A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setCartItems(Collection $cartItems, ConnectionInterface $con = null)
    {
        $cartItemsToDelete = $this->getCartItems(new Criteria(), $con)->diff($cartItems);


        $this->cartItemsScheduledForDeletion = $cartItemsToDelete;

        foreach ($cartItemsToDelete as $cartItemRemoved) {
            $cartItemRemoved->setProduct(null);
        }

        $this->collCartItems = null;
        foreach ($cartItems as $cartItem) {
            $this->addCartItem($cartItem);
        }

        $this->collCartItems = $cartItems;
        $this->collCartItemsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related CartItem objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related CartItem objects.
     * @throws PropelException
     */
    public function countCartItems(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collCartItemsPartial && !$this->isNew();
        if (null === $this->collCartItems || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collCartItems) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getCartItems());
            }

            $query = ChildCartItemQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collCartItems);
    }

    /**
     * Method called to associate a ChildCartItem object to this object
     * through the ChildCartItem foreign key attribute.
     *
     * @param    ChildCartItem $l ChildCartItem
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addCartItem(ChildCartItem $l)
    {
        if ($this->collCartItems === null) {
            $this->initCartItems();
            $this->collCartItemsPartial = true;
        }

        if (!in_array($l, $this->collCartItems->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddCartItem($l);
        }

        return $this;
    }

    /**
     * @param CartItem $cartItem The cartItem object to add.
     */
    protected function doAddCartItem($cartItem)
    {
        $this->collCartItems[]= $cartItem;
        $cartItem->setProduct($this);
    }

    /**
     * @param  CartItem $cartItem The cartItem object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeCartItem($cartItem)
    {
        if ($this->getCartItems()->contains($cartItem)) {
            $this->collCartItems->remove($this->collCartItems->search($cartItem));
            if (null === $this->cartItemsScheduledForDeletion) {
                $this->cartItemsScheduledForDeletion = clone $this->collCartItems;
                $this->cartItemsScheduledForDeletion->clear();
            }
            $this->cartItemsScheduledForDeletion[]= clone $cartItem;
            $cartItem->setProduct(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related CartItems from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCartItem[] List of ChildCartItem objects
     */
    public function getCartItemsJoinCart($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartItemQuery::create(null, $criteria);
        $query->joinWith('Cart', $joinBehavior);

        return $this->getCartItems($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related CartItems from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildCartItem[] List of ChildCartItem objects
     */
    public function getCartItemsJoinProductSaleElements($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildCartItemQuery::create(null, $criteria);
        $query->joinWith('ProductSaleElements', $joinBehavior);

        return $this->getCartItems($query, $con);
    }

    /**
     * Clears out the collProductAssociatedContents collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductAssociatedContents()
     */
    public function clearProductAssociatedContents()
    {
        $this->collProductAssociatedContents = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductAssociatedContents collection loaded partially.
     */
    public function resetPartialProductAssociatedContents($v = true)
    {
        $this->collProductAssociatedContentsPartial = $v;
    }

    /**
     * Initializes the collProductAssociatedContents collection.
     *
     * By default this just sets the collProductAssociatedContents collection to an empty array (like clearcollProductAssociatedContents());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductAssociatedContents($overrideExisting = true)
    {
        if (null !== $this->collProductAssociatedContents && !$overrideExisting) {
            return;
        }
        $this->collProductAssociatedContents = new ObjectCollection();
        $this->collProductAssociatedContents->setModel('\Thelia\Model\ProductAssociatedContent');
    }

    /**
     * Gets an array of ChildProductAssociatedContent objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductAssociatedContent[] List of ChildProductAssociatedContent objects
     * @throws PropelException
     */
    public function getProductAssociatedContents($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductAssociatedContentsPartial && !$this->isNew();
        if (null === $this->collProductAssociatedContents || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductAssociatedContents) {
                // return empty collection
                $this->initProductAssociatedContents();
            } else {
                $collProductAssociatedContents = ChildProductAssociatedContentQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductAssociatedContentsPartial && count($collProductAssociatedContents)) {
                        $this->initProductAssociatedContents(false);

                        foreach ($collProductAssociatedContents as $obj) {
                            if (false == $this->collProductAssociatedContents->contains($obj)) {
                                $this->collProductAssociatedContents->append($obj);
                            }
                        }

                        $this->collProductAssociatedContentsPartial = true;
                    }

                    reset($collProductAssociatedContents);

                    return $collProductAssociatedContents;
                }

                if ($partial && $this->collProductAssociatedContents) {
                    foreach ($this->collProductAssociatedContents as $obj) {
                        if ($obj->isNew()) {
                            $collProductAssociatedContents[] = $obj;
                        }
                    }
                }

                $this->collProductAssociatedContents = $collProductAssociatedContents;
                $this->collProductAssociatedContentsPartial = false;
            }
        }

        return $this->collProductAssociatedContents;
    }

    /**
     * Sets a collection of ProductAssociatedContent objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productAssociatedContents A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setProductAssociatedContents(Collection $productAssociatedContents, ConnectionInterface $con = null)
    {
        $productAssociatedContentsToDelete = $this->getProductAssociatedContents(new Criteria(), $con)->diff($productAssociatedContents);


        $this->productAssociatedContentsScheduledForDeletion = $productAssociatedContentsToDelete;

        foreach ($productAssociatedContentsToDelete as $productAssociatedContentRemoved) {
            $productAssociatedContentRemoved->setProduct(null);
        }

        $this->collProductAssociatedContents = null;
        foreach ($productAssociatedContents as $productAssociatedContent) {
            $this->addProductAssociatedContent($productAssociatedContent);
        }

        $this->collProductAssociatedContents = $productAssociatedContents;
        $this->collProductAssociatedContentsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductAssociatedContent objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductAssociatedContent objects.
     * @throws PropelException
     */
    public function countProductAssociatedContents(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductAssociatedContentsPartial && !$this->isNew();
        if (null === $this->collProductAssociatedContents || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductAssociatedContents) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductAssociatedContents());
            }

            $query = ChildProductAssociatedContentQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collProductAssociatedContents);
    }

    /**
     * Method called to associate a ChildProductAssociatedContent object to this object
     * through the ChildProductAssociatedContent foreign key attribute.
     *
     * @param    ChildProductAssociatedContent $l ChildProductAssociatedContent
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addProductAssociatedContent(ChildProductAssociatedContent $l)
    {
        if ($this->collProductAssociatedContents === null) {
            $this->initProductAssociatedContents();
            $this->collProductAssociatedContentsPartial = true;
        }

        if (!in_array($l, $this->collProductAssociatedContents->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductAssociatedContent($l);
        }

        return $this;
    }

    /**
     * @param ProductAssociatedContent $productAssociatedContent The productAssociatedContent object to add.
     */
    protected function doAddProductAssociatedContent($productAssociatedContent)
    {
        $this->collProductAssociatedContents[]= $productAssociatedContent;
        $productAssociatedContent->setProduct($this);
    }

    /**
     * @param  ProductAssociatedContent $productAssociatedContent The productAssociatedContent object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductAssociatedContent($productAssociatedContent)
    {
        if ($this->getProductAssociatedContents()->contains($productAssociatedContent)) {
            $this->collProductAssociatedContents->remove($this->collProductAssociatedContents->search($productAssociatedContent));
            if (null === $this->productAssociatedContentsScheduledForDeletion) {
                $this->productAssociatedContentsScheduledForDeletion = clone $this->collProductAssociatedContents;
                $this->productAssociatedContentsScheduledForDeletion->clear();
            }
            $this->productAssociatedContentsScheduledForDeletion[]= clone $productAssociatedContent;
            $productAssociatedContent->setProduct(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related ProductAssociatedContents from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildProductAssociatedContent[] List of ChildProductAssociatedContent objects
     */
    public function getProductAssociatedContentsJoinContent($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildProductAssociatedContentQuery::create(null, $criteria);
        $query->joinWith('Content', $joinBehavior);

        return $this->getProductAssociatedContents($query, $con);
    }

    /**
     * Clears out the collSaleProducts collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addSaleProducts()
     */
    public function clearSaleProducts()
    {
        $this->collSaleProducts = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collSaleProducts collection loaded partially.
     */
    public function resetPartialSaleProducts($v = true)
    {
        $this->collSaleProductsPartial = $v;
    }

    /**
     * Initializes the collSaleProducts collection.
     *
     * By default this just sets the collSaleProducts collection to an empty array (like clearcollSaleProducts());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initSaleProducts($overrideExisting = true)
    {
        if (null !== $this->collSaleProducts && !$overrideExisting) {
            return;
        }
        $this->collSaleProducts = new ObjectCollection();
        $this->collSaleProducts->setModel('\Thelia\Model\SaleProduct');
    }

    /**
     * Gets an array of ChildSaleProduct objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildSaleProduct[] List of ChildSaleProduct objects
     * @throws PropelException
     */
    public function getSaleProducts($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collSaleProductsPartial && !$this->isNew();
        if (null === $this->collSaleProducts || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collSaleProducts) {
                // return empty collection
                $this->initSaleProducts();
            } else {
                $collSaleProducts = ChildSaleProductQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collSaleProductsPartial && count($collSaleProducts)) {
                        $this->initSaleProducts(false);

                        foreach ($collSaleProducts as $obj) {
                            if (false == $this->collSaleProducts->contains($obj)) {
                                $this->collSaleProducts->append($obj);
                            }
                        }

                        $this->collSaleProductsPartial = true;
                    }

                    reset($collSaleProducts);

                    return $collSaleProducts;
                }

                if ($partial && $this->collSaleProducts) {
                    foreach ($this->collSaleProducts as $obj) {
                        if ($obj->isNew()) {
                            $collSaleProducts[] = $obj;
                        }
                    }
                }

                $this->collSaleProducts = $collSaleProducts;
                $this->collSaleProductsPartial = false;
            }
        }

        return $this->collSaleProducts;
    }

    /**
     * Sets a collection of SaleProduct objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $saleProducts A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setSaleProducts(Collection $saleProducts, ConnectionInterface $con = null)
    {
        $saleProductsToDelete = $this->getSaleProducts(new Criteria(), $con)->diff($saleProducts);


        $this->saleProductsScheduledForDeletion = $saleProductsToDelete;

        foreach ($saleProductsToDelete as $saleProductRemoved) {
            $saleProductRemoved->setProduct(null);
        }

        $this->collSaleProducts = null;
        foreach ($saleProducts as $saleProduct) {
            $this->addSaleProduct($saleProduct);
        }

        $this->collSaleProducts = $saleProducts;
        $this->collSaleProductsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related SaleProduct objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related SaleProduct objects.
     * @throws PropelException
     */
    public function countSaleProducts(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collSaleProductsPartial && !$this->isNew();
        if (null === $this->collSaleProducts || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collSaleProducts) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getSaleProducts());
            }

            $query = ChildSaleProductQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collSaleProducts);
    }

    /**
     * Method called to associate a ChildSaleProduct object to this object
     * through the ChildSaleProduct foreign key attribute.
     *
     * @param    ChildSaleProduct $l ChildSaleProduct
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addSaleProduct(ChildSaleProduct $l)
    {
        if ($this->collSaleProducts === null) {
            $this->initSaleProducts();
            $this->collSaleProductsPartial = true;
        }

        if (!in_array($l, $this->collSaleProducts->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddSaleProduct($l);
        }

        return $this;
    }

    /**
     * @param SaleProduct $saleProduct The saleProduct object to add.
     */
    protected function doAddSaleProduct($saleProduct)
    {
        $this->collSaleProducts[]= $saleProduct;
        $saleProduct->setProduct($this);
    }

    /**
     * @param  SaleProduct $saleProduct The saleProduct object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeSaleProduct($saleProduct)
    {
        if ($this->getSaleProducts()->contains($saleProduct)) {
            $this->collSaleProducts->remove($this->collSaleProducts->search($saleProduct));
            if (null === $this->saleProductsScheduledForDeletion) {
                $this->saleProductsScheduledForDeletion = clone $this->collSaleProducts;
                $this->saleProductsScheduledForDeletion->clear();
            }
            $this->saleProductsScheduledForDeletion[]= clone $saleProduct;
            $saleProduct->setProduct(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related SaleProducts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildSaleProduct[] List of ChildSaleProduct objects
     */
    public function getSaleProductsJoinSale($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildSaleProductQuery::create(null, $criteria);
        $query->joinWith('Sale', $joinBehavior);

        return $this->getSaleProducts($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Product is new, it will return
     * an empty collection; or if this Product has previously
     * been saved, it will retrieve related SaleProducts from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Product.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildSaleProduct[] List of ChildSaleProduct objects
     */
    public function getSaleProductsJoinAttributeAv($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildSaleProductQuery::create(null, $criteria);
        $query->joinWith('AttributeAv', $joinBehavior);

        return $this->getSaleProducts($query, $con);
    }

    /**
     * Clears out the collProductI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductI18ns()
     */
    public function clearProductI18ns()
    {
        $this->collProductI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductI18ns collection loaded partially.
     */
    public function resetPartialProductI18ns($v = true)
    {
        $this->collProductI18nsPartial = $v;
    }

    /**
     * Initializes the collProductI18ns collection.
     *
     * By default this just sets the collProductI18ns collection to an empty array (like clearcollProductI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductI18ns($overrideExisting = true)
    {
        if (null !== $this->collProductI18ns && !$overrideExisting) {
            return;
        }
        $this->collProductI18ns = new ObjectCollection();
        $this->collProductI18ns->setModel('\Thelia\Model\ProductI18n');
    }

    /**
     * Gets an array of ChildProductI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductI18n[] List of ChildProductI18n objects
     * @throws PropelException
     */
    public function getProductI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductI18nsPartial && !$this->isNew();
        if (null === $this->collProductI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductI18ns) {
                // return empty collection
                $this->initProductI18ns();
            } else {
                $collProductI18ns = ChildProductI18nQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductI18nsPartial && count($collProductI18ns)) {
                        $this->initProductI18ns(false);

                        foreach ($collProductI18ns as $obj) {
                            if (false == $this->collProductI18ns->contains($obj)) {
                                $this->collProductI18ns->append($obj);
                            }
                        }

                        $this->collProductI18nsPartial = true;
                    }

                    reset($collProductI18ns);

                    return $collProductI18ns;
                }

                if ($partial && $this->collProductI18ns) {
                    foreach ($this->collProductI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collProductI18ns[] = $obj;
                        }
                    }
                }

                $this->collProductI18ns = $collProductI18ns;
                $this->collProductI18nsPartial = false;
            }
        }

        return $this->collProductI18ns;
    }

    /**
     * Sets a collection of ProductI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setProductI18ns(Collection $productI18ns, ConnectionInterface $con = null)
    {
        $productI18nsToDelete = $this->getProductI18ns(new Criteria(), $con)->diff($productI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->productI18nsScheduledForDeletion = clone $productI18nsToDelete;

        foreach ($productI18nsToDelete as $productI18nRemoved) {
            $productI18nRemoved->setProduct(null);
        }

        $this->collProductI18ns = null;
        foreach ($productI18ns as $productI18n) {
            $this->addProductI18n($productI18n);
        }

        $this->collProductI18ns = $productI18ns;
        $this->collProductI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductI18n objects.
     * @throws PropelException
     */
    public function countProductI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductI18nsPartial && !$this->isNew();
        if (null === $this->collProductI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductI18ns());
            }

            $query = ChildProductI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collProductI18ns);
    }

    /**
     * Method called to associate a ChildProductI18n object to this object
     * through the ChildProductI18n foreign key attribute.
     *
     * @param    ChildProductI18n $l ChildProductI18n
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addProductI18n(ChildProductI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collProductI18ns === null) {
            $this->initProductI18ns();
            $this->collProductI18nsPartial = true;
        }

        if (!in_array($l, $this->collProductI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductI18n($l);
        }

        return $this;
    }

    /**
     * @param ProductI18n $productI18n The productI18n object to add.
     */
    protected function doAddProductI18n($productI18n)
    {
        $this->collProductI18ns[]= $productI18n;
        $productI18n->setProduct($this);
    }

    /**
     * @param  ProductI18n $productI18n The productI18n object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductI18n($productI18n)
    {
        if ($this->getProductI18ns()->contains($productI18n)) {
            $this->collProductI18ns->remove($this->collProductI18ns->search($productI18n));
            if (null === $this->productI18nsScheduledForDeletion) {
                $this->productI18nsScheduledForDeletion = clone $this->collProductI18ns;
                $this->productI18nsScheduledForDeletion->clear();
            }
            $this->productI18nsScheduledForDeletion[]= clone $productI18n;
            $productI18n->setProduct(null);
        }

        return $this;
    }

    /**
     * Clears out the collProductVersions collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductVersions()
     */
    public function clearProductVersions()
    {
        $this->collProductVersions = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collProductVersions collection loaded partially.
     */
    public function resetPartialProductVersions($v = true)
    {
        $this->collProductVersionsPartial = $v;
    }

    /**
     * Initializes the collProductVersions collection.
     *
     * By default this just sets the collProductVersions collection to an empty array (like clearcollProductVersions());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initProductVersions($overrideExisting = true)
    {
        if (null !== $this->collProductVersions && !$overrideExisting) {
            return;
        }
        $this->collProductVersions = new ObjectCollection();
        $this->collProductVersions->setModel('\Thelia\Model\ProductVersion');
    }

    /**
     * Gets an array of ChildProductVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildProductVersion[] List of ChildProductVersion objects
     * @throws PropelException
     */
    public function getProductVersions($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collProductVersionsPartial && !$this->isNew();
        if (null === $this->collProductVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collProductVersions) {
                // return empty collection
                $this->initProductVersions();
            } else {
                $collProductVersions = ChildProductVersionQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collProductVersionsPartial && count($collProductVersions)) {
                        $this->initProductVersions(false);

                        foreach ($collProductVersions as $obj) {
                            if (false == $this->collProductVersions->contains($obj)) {
                                $this->collProductVersions->append($obj);
                            }
                        }

                        $this->collProductVersionsPartial = true;
                    }

                    reset($collProductVersions);

                    return $collProductVersions;
                }

                if ($partial && $this->collProductVersions) {
                    foreach ($this->collProductVersions as $obj) {
                        if ($obj->isNew()) {
                            $collProductVersions[] = $obj;
                        }
                    }
                }

                $this->collProductVersions = $collProductVersions;
                $this->collProductVersionsPartial = false;
            }
        }

        return $this->collProductVersions;
    }

    /**
     * Sets a collection of ProductVersion objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $productVersions A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildProduct The current object (for fluent API support)
     */
    public function setProductVersions(Collection $productVersions, ConnectionInterface $con = null)
    {
        $productVersionsToDelete = $this->getProductVersions(new Criteria(), $con)->diff($productVersions);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->productVersionsScheduledForDeletion = clone $productVersionsToDelete;

        foreach ($productVersionsToDelete as $productVersionRemoved) {
            $productVersionRemoved->setProduct(null);
        }

        $this->collProductVersions = null;
        foreach ($productVersions as $productVersion) {
            $this->addProductVersion($productVersion);
        }

        $this->collProductVersions = $productVersions;
        $this->collProductVersionsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related ProductVersion objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related ProductVersion objects.
     * @throws PropelException
     */
    public function countProductVersions(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collProductVersionsPartial && !$this->isNew();
        if (null === $this->collProductVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collProductVersions) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getProductVersions());
            }

            $query = ChildProductVersionQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProduct($this)
                ->count($con);
        }

        return count($this->collProductVersions);
    }

    /**
     * Method called to associate a ChildProductVersion object to this object
     * through the ChildProductVersion foreign key attribute.
     *
     * @param    ChildProductVersion $l ChildProductVersion
     * @return   \Thelia\Model\Product The current object (for fluent API support)
     */
    public function addProductVersion(ChildProductVersion $l)
    {
        if ($this->collProductVersions === null) {
            $this->initProductVersions();
            $this->collProductVersionsPartial = true;
        }

        if (!in_array($l, $this->collProductVersions->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddProductVersion($l);
        }

        return $this;
    }

    /**
     * @param ProductVersion $productVersion The productVersion object to add.
     */
    protected function doAddProductVersion($productVersion)
    {
        $this->collProductVersions[]= $productVersion;
        $productVersion->setProduct($this);
    }

    /**
     * @param  ProductVersion $productVersion The productVersion object to remove.
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductVersion($productVersion)
    {
        if ($this->getProductVersions()->contains($productVersion)) {
            $this->collProductVersions->remove($this->collProductVersions->search($productVersion));
            if (null === $this->productVersionsScheduledForDeletion) {
                $this->productVersionsScheduledForDeletion = clone $this->collProductVersions;
                $this->productVersionsScheduledForDeletion->clear();
            }
            $this->productVersionsScheduledForDeletion[]= clone $productVersion;
            $productVersion->setProduct(null);
        }

        return $this;
    }

    /**
     * Clears out the collCategories collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addCategories()
     */
    public function clearCategories()
    {
        $this->collCategories = null; // important to set this to NULL since that means it is uninitialized
        $this->collCategoriesPartial = null;
    }

    /**
     * Initializes the collCategories collection.
     *
     * By default this just sets the collCategories collection to an empty collection (like clearCategories());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initCategories()
    {
        $this->collCategories = new ObjectCollection();
        $this->collCategories->setModel('\Thelia\Model\Category');
    }

    /**
     * Gets a collection of ChildCategory objects related by a many-to-many relationship
     * to the current object by way of the product_category cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildCategory[] List of ChildCategory objects
     */
    public function getCategories($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collCategories || null !== $criteria) {
            if ($this->isNew() && null === $this->collCategories) {
                // return empty collection
                $this->initCategories();
            } else {
                $collCategories = ChildCategoryQuery::create(null, $criteria)
                    ->filterByProduct($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collCategories;
                }
                $this->collCategories = $collCategories;
            }
        }

        return $this->collCategories;
    }

    /**
     * Sets a collection of Category objects related by a many-to-many relationship
     * to the current object by way of the product_category cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $categories A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildProduct The current object (for fluent API support)
     */
    public function setCategories(Collection $categories, ConnectionInterface $con = null)
    {
        $this->clearCategories();
        $currentCategories = $this->getCategories();

        $this->categoriesScheduledForDeletion = $currentCategories->diff($categories);

        foreach ($categories as $category) {
            if (!$currentCategories->contains($category)) {
                $this->doAddCategory($category);
            }
        }

        $this->collCategories = $categories;

        return $this;
    }

    /**
     * Gets the number of ChildCategory objects related by a many-to-many relationship
     * to the current object by way of the product_category cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildCategory objects
     */
    public function countCategories($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collCategories || null !== $criteria) {
            if ($this->isNew() && null === $this->collCategories) {
                return 0;
            } else {
                $query = ChildCategoryQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProduct($this)
                    ->count($con);
            }
        } else {
            return count($this->collCategories);
        }
    }

    /**
     * Associate a ChildCategory object to this object
     * through the product_category cross reference table.
     *
     * @param  ChildCategory $category The ChildProductCategory object to relate
     * @return ChildProduct The current object (for fluent API support)
     */
    public function addCategory(ChildCategory $category)
    {
        if ($this->collCategories === null) {
            $this->initCategories();
        }

        if (!$this->collCategories->contains($category)) { // only add it if the **same** object is not already associated
            $this->doAddCategory($category);
            $this->collCategories[] = $category;
        }

        return $this;
    }

    /**
     * @param    Category $category The category object to add.
     */
    protected function doAddCategory($category)
    {
        $productCategory = new ChildProductCategory();
        $productCategory->setCategory($category);
        $this->addProductCategory($productCategory);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$category->getProducts()->contains($this)) {
            $foreignCollection   = $category->getProducts();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildCategory object to this object
     * through the product_category cross reference table.
     *
     * @param ChildCategory $category The ChildProductCategory object to relate
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeCategory(ChildCategory $category)
    {
        if ($this->getCategories()->contains($category)) {
            $this->collCategories->remove($this->collCategories->search($category));

            if (null === $this->categoriesScheduledForDeletion) {
                $this->categoriesScheduledForDeletion = clone $this->collCategories;
                $this->categoriesScheduledForDeletion->clear();
            }

            $this->categoriesScheduledForDeletion[] = $category;
        }

        return $this;
    }

    /**
     * Clears out the collProductsRelatedByAccessory collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductsRelatedByAccessory()
     */
    public function clearProductsRelatedByAccessory()
    {
        $this->collProductsRelatedByAccessory = null; // important to set this to NULL since that means it is uninitialized
        $this->collProductsRelatedByAccessoryPartial = null;
    }

    /**
     * Initializes the collProductsRelatedByAccessory collection.
     *
     * By default this just sets the collProductsRelatedByAccessory collection to an empty collection (like clearProductsRelatedByAccessory());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initProductsRelatedByAccessory()
    {
        $this->collProductsRelatedByAccessory = new ObjectCollection();
        $this->collProductsRelatedByAccessory->setModel('\Thelia\Model\Product');
    }

    /**
     * Gets a collection of ChildProduct objects related by a many-to-many relationship
     * to the current object by way of the accessory cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildProduct[] List of ChildProduct objects
     */
    public function getProductsRelatedByAccessory($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collProductsRelatedByAccessory || null !== $criteria) {
            if ($this->isNew() && null === $this->collProductsRelatedByAccessory) {
                // return empty collection
                $this->initProductsRelatedByAccessory();
            } else {
                $collProductsRelatedByAccessory = ChildProductQuery::create(null, $criteria)
                    ->filterByProductRelatedByProductId($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collProductsRelatedByAccessory;
                }
                $this->collProductsRelatedByAccessory = $collProductsRelatedByAccessory;
            }
        }

        return $this->collProductsRelatedByAccessory;
    }

    /**
     * Sets a collection of Product objects related by a many-to-many relationship
     * to the current object by way of the accessory cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $productsRelatedByAccessory A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildProduct The current object (for fluent API support)
     */
    public function setProductsRelatedByAccessory(Collection $productsRelatedByAccessory, ConnectionInterface $con = null)
    {
        $this->clearProductsRelatedByAccessory();
        $currentProductsRelatedByAccessory = $this->getProductsRelatedByAccessory();

        $this->productsRelatedByAccessoryScheduledForDeletion = $currentProductsRelatedByAccessory->diff($productsRelatedByAccessory);

        foreach ($productsRelatedByAccessory as $productRelatedByAccessory) {
            if (!$currentProductsRelatedByAccessory->contains($productRelatedByAccessory)) {
                $this->doAddProductRelatedByAccessory($productRelatedByAccessory);
            }
        }

        $this->collProductsRelatedByAccessory = $productsRelatedByAccessory;

        return $this;
    }

    /**
     * Gets the number of ChildProduct objects related by a many-to-many relationship
     * to the current object by way of the accessory cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildProduct objects
     */
    public function countProductsRelatedByAccessory($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collProductsRelatedByAccessory || null !== $criteria) {
            if ($this->isNew() && null === $this->collProductsRelatedByAccessory) {
                return 0;
            } else {
                $query = ChildProductQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProductRelatedByProductId($this)
                    ->count($con);
            }
        } else {
            return count($this->collProductsRelatedByAccessory);
        }
    }

    /**
     * Associate a ChildProduct object to this object
     * through the accessory cross reference table.
     *
     * @param  ChildProduct $product The ChildAccessory object to relate
     * @return ChildProduct The current object (for fluent API support)
     */
    public function addProductRelatedByAccessory(ChildProduct $product)
    {
        if ($this->collProductsRelatedByAccessory === null) {
            $this->initProductsRelatedByAccessory();
        }

        if (!$this->collProductsRelatedByAccessory->contains($product)) { // only add it if the **same** object is not already associated
            $this->doAddProductRelatedByAccessory($product);
            $this->collProductsRelatedByAccessory[] = $product;
        }

        return $this;
    }

    /**
     * @param    ProductRelatedByAccessory $productRelatedByAccessory The productRelatedByAccessory object to add.
     */
    protected function doAddProductRelatedByAccessory($productRelatedByAccessory)
    {
        $accessory = new ChildAccessory();
        $accessory->setProductRelatedByAccessory($productRelatedByAccessory);
        $this->addAccessoryRelatedByProductId($accessory);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$productRelatedByAccessory->getProductsRelatedByProductId()->contains($this)) {
            $foreignCollection   = $productRelatedByAccessory->getProductsRelatedByProductId();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildProduct object to this object
     * through the accessory cross reference table.
     *
     * @param ChildProduct $product The ChildAccessory object to relate
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductRelatedByAccessory(ChildProduct $product)
    {
        if ($this->getProductsRelatedByAccessory()->contains($product)) {
            $this->collProductsRelatedByAccessory->remove($this->collProductsRelatedByAccessory->search($product));

            if (null === $this->productsRelatedByAccessoryScheduledForDeletion) {
                $this->productsRelatedByAccessoryScheduledForDeletion = clone $this->collProductsRelatedByAccessory;
                $this->productsRelatedByAccessoryScheduledForDeletion->clear();
            }

            $this->productsRelatedByAccessoryScheduledForDeletion[] = $product;
        }

        return $this;
    }

    /**
     * Clears out the collProductsRelatedByProductId collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProductsRelatedByProductId()
     */
    public function clearProductsRelatedByProductId()
    {
        $this->collProductsRelatedByProductId = null; // important to set this to NULL since that means it is uninitialized
        $this->collProductsRelatedByProductIdPartial = null;
    }

    /**
     * Initializes the collProductsRelatedByProductId collection.
     *
     * By default this just sets the collProductsRelatedByProductId collection to an empty collection (like clearProductsRelatedByProductId());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initProductsRelatedByProductId()
    {
        $this->collProductsRelatedByProductId = new ObjectCollection();
        $this->collProductsRelatedByProductId->setModel('\Thelia\Model\Product');
    }

    /**
     * Gets a collection of ChildProduct objects related by a many-to-many relationship
     * to the current object by way of the accessory cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProduct is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildProduct[] List of ChildProduct objects
     */
    public function getProductsRelatedByProductId($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collProductsRelatedByProductId || null !== $criteria) {
            if ($this->isNew() && null === $this->collProductsRelatedByProductId) {
                // return empty collection
                $this->initProductsRelatedByProductId();
            } else {
                $collProductsRelatedByProductId = ChildProductQuery::create(null, $criteria)
                    ->filterByProductRelatedByAccessory($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collProductsRelatedByProductId;
                }
                $this->collProductsRelatedByProductId = $collProductsRelatedByProductId;
            }
        }

        return $this->collProductsRelatedByProductId;
    }

    /**
     * Sets a collection of Product objects related by a many-to-many relationship
     * to the current object by way of the accessory cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $productsRelatedByProductId A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildProduct The current object (for fluent API support)
     */
    public function setProductsRelatedByProductId(Collection $productsRelatedByProductId, ConnectionInterface $con = null)
    {
        $this->clearProductsRelatedByProductId();
        $currentProductsRelatedByProductId = $this->getProductsRelatedByProductId();

        $this->productsRelatedByProductIdScheduledForDeletion = $currentProductsRelatedByProductId->diff($productsRelatedByProductId);

        foreach ($productsRelatedByProductId as $productRelatedByProductId) {
            if (!$currentProductsRelatedByProductId->contains($productRelatedByProductId)) {
                $this->doAddProductRelatedByProductId($productRelatedByProductId);
            }
        }

        $this->collProductsRelatedByProductId = $productsRelatedByProductId;

        return $this;
    }

    /**
     * Gets the number of ChildProduct objects related by a many-to-many relationship
     * to the current object by way of the accessory cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildProduct objects
     */
    public function countProductsRelatedByProductId($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collProductsRelatedByProductId || null !== $criteria) {
            if ($this->isNew() && null === $this->collProductsRelatedByProductId) {
                return 0;
            } else {
                $query = ChildProductQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProductRelatedByAccessory($this)
                    ->count($con);
            }
        } else {
            return count($this->collProductsRelatedByProductId);
        }
    }

    /**
     * Associate a ChildProduct object to this object
     * through the accessory cross reference table.
     *
     * @param  ChildProduct $product The ChildAccessory object to relate
     * @return ChildProduct The current object (for fluent API support)
     */
    public function addProductRelatedByProductId(ChildProduct $product)
    {
        if ($this->collProductsRelatedByProductId === null) {
            $this->initProductsRelatedByProductId();
        }

        if (!$this->collProductsRelatedByProductId->contains($product)) { // only add it if the **same** object is not already associated
            $this->doAddProductRelatedByProductId($product);
            $this->collProductsRelatedByProductId[] = $product;
        }

        return $this;
    }

    /**
     * @param    ProductRelatedByProductId $productRelatedByProductId The productRelatedByProductId object to add.
     */
    protected function doAddProductRelatedByProductId($productRelatedByProductId)
    {
        $accessory = new ChildAccessory();
        $accessory->setProductRelatedByProductId($productRelatedByProductId);
        $this->addAccessoryRelatedByAccessory($accessory);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$productRelatedByProductId->getProductsRelatedByAccessory()->contains($this)) {
            $foreignCollection   = $productRelatedByProductId->getProductsRelatedByAccessory();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildProduct object to this object
     * through the accessory cross reference table.
     *
     * @param ChildProduct $product The ChildAccessory object to relate
     * @return ChildProduct The current object (for fluent API support)
     */
    public function removeProductRelatedByProductId(ChildProduct $product)
    {
        if ($this->getProductsRelatedByProductId()->contains($product)) {
            $this->collProductsRelatedByProductId->remove($this->collProductsRelatedByProductId->search($product));

            if (null === $this->productsRelatedByProductIdScheduledForDeletion) {
                $this->productsRelatedByProductIdScheduledForDeletion = clone $this->collProductsRelatedByProductId;
                $this->productsRelatedByProductIdScheduledForDeletion->clear();
            }

            $this->productsRelatedByProductIdScheduledForDeletion[] = $product;
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
        $this->visible = null;
        $this->position = null;
        $this->template_id = null;
        $this->brand_id = null;
        $this->virtual = null;
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
            if ($this->collProductCategories) {
                foreach ($this->collProductCategories as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collFeatureProducts) {
                foreach ($this->collFeatureProducts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductSaleElementss) {
                foreach ($this->collProductSaleElementss as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductImages) {
                foreach ($this->collProductImages as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductDocuments) {
                foreach ($this->collProductDocuments as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAccessoriesRelatedByProductId) {
                foreach ($this->collAccessoriesRelatedByProductId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collAccessoriesRelatedByAccessory) {
                foreach ($this->collAccessoriesRelatedByAccessory as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCartItems) {
                foreach ($this->collCartItems as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductAssociatedContents) {
                foreach ($this->collProductAssociatedContents as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collSaleProducts) {
                foreach ($this->collSaleProducts as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductI18ns) {
                foreach ($this->collProductI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductVersions) {
                foreach ($this->collProductVersions as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collCategories) {
                foreach ($this->collCategories as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductsRelatedByAccessory) {
                foreach ($this->collProductsRelatedByAccessory as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProductsRelatedByProductId) {
                foreach ($this->collProductsRelatedByProductId as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collProductCategories = null;
        $this->collFeatureProducts = null;
        $this->collProductSaleElementss = null;
        $this->collProductImages = null;
        $this->collProductDocuments = null;
        $this->collAccessoriesRelatedByProductId = null;
        $this->collAccessoriesRelatedByAccessory = null;
        $this->collCartItems = null;
        $this->collProductAssociatedContents = null;
        $this->collSaleProducts = null;
        $this->collProductI18ns = null;
        $this->collProductVersions = null;
        $this->collCategories = null;
        $this->collProductsRelatedByAccessory = null;
        $this->collProductsRelatedByProductId = null;
        $this->aTaxRule = null;
        $this->aTemplate = null;
        $this->aBrand = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(ProductTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildProduct The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[ProductTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildProduct The current object (for fluent API support)
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
     * @return ChildProductI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collProductI18ns) {
                foreach ($this->collProductI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildProductI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildProductI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addProductI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildProduct The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildProductI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collProductI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collProductI18ns[$key]);
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
     * @return ChildProductI18n */
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
         * @return   \Thelia\Model\ProductI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProductI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProductI18n The current object (for fluent API support)
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
         * @return   \Thelia\Model\ProductI18n The current object (for fluent API support)
         */
        public function setPostscriptum($v)
        {    $this->getCurrentTranslation()->setPostscriptum($v);

        return $this;
    }


        /**
         * Get the [meta_title] column value.
         *
         * @return   string
         */
        public function getMetaTitle()
        {
        return $this->getCurrentTranslation()->getMetaTitle();
    }


        /**
         * Set the value of [meta_title] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\ProductI18n The current object (for fluent API support)
         */
        public function setMetaTitle($v)
        {    $this->getCurrentTranslation()->setMetaTitle($v);

        return $this;
    }


        /**
         * Get the [meta_description] column value.
         *
         * @return   string
         */
        public function getMetaDescription()
        {
        return $this->getCurrentTranslation()->getMetaDescription();
    }


        /**
         * Set the value of [meta_description] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\ProductI18n The current object (for fluent API support)
         */
        public function setMetaDescription($v)
        {    $this->getCurrentTranslation()->setMetaDescription($v);

        return $this;
    }


        /**
         * Get the [meta_keywords] column value.
         *
         * @return   string
         */
        public function getMetaKeywords()
        {
        return $this->getCurrentTranslation()->getMetaKeywords();
    }


        /**
         * Set the value of [meta_keywords] column.
         *
         * @param      string $v new value
         * @return   \Thelia\Model\ProductI18n The current object (for fluent API support)
         */
        public function setMetaKeywords($v)
        {    $this->getCurrentTranslation()->setMetaKeywords($v);

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return \Thelia\Model\Product
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

        if (ChildProductQuery::isVersioningEnabled() && ($this->isNew() || $this->isModified()) || $this->isDeleted()) {
            return true;
        }

        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildProductVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new ChildProductVersion();
        $version->setId($this->getId());
        $version->setTaxRuleId($this->getTaxRuleId());
        $version->setRef($this->getRef());
        $version->setVisible($this->getVisible());
        $version->setPosition($this->getPosition());
        $version->setTemplateId($this->getTemplateId());
        $version->setBrandId($this->getBrandId());
        $version->setVirtual($this->getVirtual());
        $version->setCreatedAt($this->getCreatedAt());
        $version->setUpdatedAt($this->getUpdatedAt());
        $version->setVersion($this->getVersion());
        $version->setVersionCreatedAt($this->getVersionCreatedAt());
        $version->setVersionCreatedBy($this->getVersionCreatedBy());
        $version->setProduct($this);
        $version->save($con);

        return $version;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con The connection to use
     *
     * @return  ChildProduct The current object (for fluent API support)
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No ChildProduct object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param ChildProductVersion $version The version object to use
     * @param ConnectionInterface   $con the connection to use
     * @param array                 $loadedObjects objects that been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return ChildProduct The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {
        $loadedObjects['ChildProduct'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
        $this->setTaxRuleId($version->getTaxRuleId());
        $this->setRef($version->getRef());
        $this->setVisible($version->getVisible());
        $this->setPosition($version->getPosition());
        $this->setTemplateId($version->getTemplateId());
        $this->setBrandId($version->getBrandId());
        $this->setVirtual($version->getVirtual());
        $this->setCreatedAt($version->getCreatedAt());
        $this->setUpdatedAt($version->getUpdatedAt());
        $this->setVersion($version->getVersion());
        $this->setVersionCreatedAt($version->getVersionCreatedAt());
        $this->setVersionCreatedBy($version->getVersionCreatedBy());

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
        $v = ChildProductVersionQuery::create()
            ->filterByProduct($this)
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
     * @return  ChildProductVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return ChildProductVersionQuery::create()
            ->filterByProduct($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ObjectCollection A list of ChildProductVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(ProductVersionTableMap::VERSION);

        return $this->getProductVersions($criteria, $con);
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
     * @return PropelCollection|array \Thelia\Model\ProductVersion[] List of \Thelia\Model\ProductVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, $con = null)
    {
        $criteria = ChildProductVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(ProductVersionTableMap::VERSION);
        $criteria->limit($number);

        return $this->getProductVersions($criteria, $con);
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
