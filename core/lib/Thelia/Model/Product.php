<?php

namespace Thelia\Model;

use Propel\Runtime\Exception\PropelException;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Product as BaseProduct;
use Thelia\Model\Tools\UrlRewritingTrait;
use Thelia\TaxEngine\Calculator;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Product\ProductEvent;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class Product extends BaseProduct implements FileModelParentInterface
{
    use ModelEventDispatcherTrait;

    use PositionManagementTrait;

    use UrlRewritingTrait;

    /**
     * {@inheritDoc}
     */
    public function getRewrittenUrlViewName()
    {
        return 'product';
    }

    public function getRealLowestPrice($virtualColumnName = 'real_lowest_price')
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);
        } catch (PropelException $e) {
            throw new PropelException("Virtual column `$virtualColumnName` does not exist in Product::getRealLowestPrice");
        }

        return $amount;
    }

    public function getTaxedPrice(Country $country, $price)
    {
        $taxCalculator = new Calculator();

        return round($taxCalculator->load($this, $country)->getTaxedPrice($price), 2);
    }

    public function getTaxedPromoPrice(Country $country, $price)
    {
        $taxCalculator = new Calculator();

        return round($taxCalculator->load($this, $country)->getTaxedPrice($price), 2);
    }

    /**
     * Return the default PSE for this product.
     *
     * @return ProductSaleElements
     */
    public function getDefaultSaleElements()
    {
        return ProductSaleElementsQuery::create()->filterByProductId($this->id)->filterByIsDefault(true)->findOne();
    }

    /**
     * Return PSE count fir this product.
     *
     * @param ConnectionInterface $con an optional connection object
     * @return int
     */
    public function countSaleElements($con = null)
    {
        return ProductSaleElementsQuery::create()->filterByProductId($this->id)->count($con);
    }

    /**
     * @return int the current default category ID for this product
     */
    public function getDefaultCategoryId()
    {
        // Find default category
        $default_category = ProductCategoryQuery::create()
            ->filterByProductId($this->getId())
            ->filterByDefaultCategory(true)
            ->findOne();

        return $default_category == null ? 0 : $default_category->getCategoryId();
    }

    /**
     * Set default category for this product
     *
     * @param int $defaultCategoryId the new default category id
     * @return $this
     */
    public function setDefaultCategory($defaultCategoryId)
    {
        // Allow uncategorized products (NULL instead of 0, to bypass delete cascade constraint)
        if ($defaultCategoryId <= 0) {
            $defaultCategoryId = null;
        }

        /** @var ProductCategory $productCategory */
        $productCategory = ProductCategoryQuery::create()
            ->filterByProductId($this->getId())
            ->filterByDefaultCategory(true)
            ->findOne()
        ;

        if ($productCategory !== null && (int) $productCategory->getCategoryId() === (int) $defaultCategoryId) {
            return $this;
        }

        if ($productCategory !== null) {
            $productCategory->delete();
        }

        // checks if the product is already associated with the category and but not default
        if (null !== $productCategory = ProductCategoryQuery::create()->filterByProduct($this)->filterByCategoryId($defaultCategoryId)->findOne()) {
            $productCategory->setDefaultCategory(true)->save();
        } else {
            $position = (new ProductCategory())->setCategoryId($defaultCategoryId)->getNextPosition();

            (new ProductCategory())
                ->setProduct($this)
                ->setCategoryId($defaultCategoryId)
                ->setDefaultCategory(true)
                ->setPosition($position)
                ->save();

            $this->setPosition($position);
        }

        return $this;
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use Product::setDefaultCategory
     * @param int $defaultCategoryId
     * @return $this
     */
    public function updateDefaultCategory($defaultCategoryId)
    {
        return $this->setDefaultCategory($defaultCategoryId);
    }

    /**
     * Create a new product, along with the default category ID
     *
     * @param  int        $defaultCategoryId the default category ID of this product
     * @param  float      $basePrice         the product base price
     * @param  int        $priceCurrencyId   the price currency Id
     * @param  int        $taxRuleId         the product tax rule ID
     * @param  float      $baseWeight        base weight in Kg
     * @param  int        $baseQuantity     the product quantity (default: 0)
     * @throws \Exception
     */
    public function create($defaultCategoryId, $basePrice, $priceCurrencyId, $taxRuleId, $baseWeight, $baseQuantity = 0)
    {
        $con = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);

        $con->beginTransaction();
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEPRODUCT, new ProductEvent($this));

        try {
            // Create the product
            $this->save($con);

            // Add the default category
            $this->setDefaultCategory($defaultCategoryId)->save($con);

            $this->setTaxRuleId($taxRuleId);

            // Create the default product sale element of this product
            $this->createProductSaleElement($con, $baseWeight, $basePrice, $basePrice, $priceCurrencyId, true, false, false, $baseQuantity);

            // Store all the stuff !
            $con->commit();

            $this->dispatchEvent(TheliaEvents::AFTER_CREATEPRODUCT, new ProductEvent($this));
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }
    }

    /**
     * Create a basic product sale element attached to this product.
     *
     * @param ConnectionInterface $con
     * @param float $weight
     * @param float $basePrice
     * @param float $salePrice
     * @param int $currencyId
     * @param int $isDefault
     * @param bool $isPromo
     * @param bool $isNew
     * @param int $quantity
     * @param string $eanCode
     * @param bool $ref
     * @return ProductSaleElements
     * @throws PropelException
     * @throws \Exception
     */
    public function createProductSaleElement(ConnectionInterface $con, $weight, $basePrice, $salePrice, $currencyId, $isDefault, $isPromo = false, $isNew = false, $quantity = 0, $eanCode = '', $ref = false)
    {
        // Create an empty product sale element
        $saleElements = new ProductSaleElements();

        $saleElements
            ->setProduct($this)
            ->setRef($ref == false ? $this->getRef() : $ref)
            ->setPromo($isPromo)
            ->setNewness($isNew)
            ->setWeight($weight)
            ->setIsDefault($isDefault)
            ->setEanCode($eanCode)
            ->setQuantity($quantity)
            ->save($con)
        ;

        // Create an empty product price in the provided currency
        $productPrice = new ProductPrice();

        $productPrice
            ->setProductSaleElements($saleElements)
            ->setPromoPrice($salePrice)
            ->setPrice($basePrice)
            ->setCurrencyId($currencyId)
            ->setFromDefaultCurrency(false)
            ->save($con)
        ;

        return $saleElements;
    }

    /**
     * Calculate next position relative to our default category
     *
     * @param ProductQuery $query
     * @deprecated since 2.3, and will be removed in 2.4
     */
    protected function addCriteriaToPositionQuery($query)
    {
        // Find products in the same category
        $products = ProductCategoryQuery::create()
            ->filterByCategoryId($this->getDefaultCategoryId())
            ->filterByDefaultCategory(true)
            ->select('product_id')
            ->find();

        if ($products != null) {
            $query->filterById($products, Criteria::IN);
        }
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEPRODUCT, new ProductEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEPRODUCT, new ProductEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        // Delete free_text feature AV for this product (see issue #2061). We have to do this before
        // deleting the product, as the delete is cascaded to the feature_product table.
        $featureAvs = FeatureAvQuery::create()
            ->useFeatureProductQuery()
            ->filterByFreeTextValue(true)
            ->filterByProductId($this->getId())
            ->endUse()
            ->find($con)
        ;

        /** @var FeatureAv $featureAv */
        foreach ($featureAvs as $featureAv) {
            $featureAv
                ->setDispatcher($this->dispatcher)
                ->delete($con)
            ;
        }

        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEPRODUCT, new ProductEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->markRewrittenUrlObsolete();

        $this->dispatchEvent(TheliaEvents::AFTER_DELETEPRODUCT, new ProductEvent($this));
    }

    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategory::setPosition
     */
    public function setPosition($v)
    {
        return parent::setPosition($v);
    }

    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategory::getPosition
     */
    public function getPosition()
    {
        return parent::getPosition();
    }

    public function postSave(ConnectionInterface $con = null)
    {
        // For BC, will be removed in 2.4
        if (!$this->isNew()) {
            if (isset($this->modifiedColumns[ProductTableMap::POSITION]) && $this->modifiedColumns[ProductTableMap::POSITION]) {
                if (null !== $productCategory = ProductCategoryQuery::create()
                        ->filterByProduct($this)
                        ->filterByDefaultCategory(true)
                        ->findOne()
                ) {
                    $productCategory->changeAbsolutePosition($this->getPosition());
                }
            }
        }
    }

    /**
     * Overload for the position management
     * @param ProductCategory $productCategory
     * @inheritdoc
     */
    protected function doAddProductCategory($productCategory)
    {
        parent::doAddProductCategory($productCategory);

        $productCategoryPosition = ProductCategoryQuery::create()
            ->filterByCategoryId($productCategory->getCategoryId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $productCategory->setPosition($productCategoryPosition !== null ? $productCategoryPosition->getPosition() + 1 : 1);
    }
}
