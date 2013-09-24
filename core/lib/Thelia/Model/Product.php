<?php

namespace Thelia\Model;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Base\Product as BaseProduct;
use Thelia\Tools\URL;
use Thelia\TaxEngine\Calculator;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ProductEvent;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\Map\ProductTableMap;

class Product extends BaseProduct
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    use \Thelia\Model\Tools\UrlRewritingTrait;

    /**
     * {@inheritDoc}
     */
    protected function getRewrittenUrlViewName() {
        return 'product';
    }

    public function getRealLowestPrice($virtualColumnName = 'real_lowest_price')
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);
        }
        catch(PropelException $e) {
            throw new PropelException("Virtual column `$virtualColumnName` does not exist in Product::getRealLowestPrice");
        }

        return $amount;
    }

    public function getTaxedPrice(Country $country)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this, $country)->getTaxedPrice($this->getRealLowestPrice());
    }

    /**
     * @return the current default category ID for this product
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
     * @param integer $categoryId the new default category id
     */
    public function setDefaultCategory($categoryId)
    {
        // Unset previous category
        ProductCategoryQuery::create()
            ->filterByProductId($this->getId())
            ->filterByDefaultCategory(true)
            ->find()
            ->setByDefault(false)
            ->save();

        // Set new default category
        ProductCategoryQuery::create()
            ->filterByProductId($this->getId())
            ->filterByCategoryId($categoryId)
            ->find()
            ->setByDefault(true)
            ->save();

        return $this;
    }

    /**
     * Create a new product, along with the default category ID
     *
     * @param int $defaultCategoryId the default category ID of this product
     */
    public function create($defaultCategoryId) {

        $con = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);

        $con->beginTransaction();

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEPRODUCT, new ProductEvent($this));

        try {
            // Create the product
            $this->save($con);

            // Add the default category
            $pc = new ProductCategory();

            $pc
                ->setProduct($this)
                ->setCategoryId($defaultCategoryId)
                ->setDefaultCategory(true)
                ->save($con)
            ;

            // Set the position
            $this->setPosition($this->getNextPosition())->save($con);

            // Create an empty product sale element
            $sale_elements = new ProductSaleElements();

            $sale_elements
                ->setProduct($this)
                ->setRef($this->getRef())
                ->setPromo(0)
                ->setNewness(0)
                ->setWeight(0)
                ->save($con)
            ;

            // Create an empty product price in the default currency
            $product_price = new ProductPrice();

            $product_price
                ->setProductSaleElements($sale_elements)
                ->setPromoPrice(0)
                ->setPrice(0)
                ->setCurrency(CurrencyQuery::create()->findOneByByDefault(true))
                ->save($con)
            ;

            // Store all the stuff !
            $con->commit();

            $this->dispatchEvent(TheliaEvents::AFTER_CREATEPRODUCT, new ProductEvent($this));
        }
        catch(\Exception $ex) {

            $con->rollback();

            throw $ex;
        }
    }

    /**
     * Calculate next position relative to our default category
     */
    protected function addCriteriaToPositionQuery($query)
    {
        // Find products in the same category
        $produits = ProductCategoryQuery::create()
            ->filterByCategoryId($this->getDefaultCategoryId())
            ->filterByDefaultCategory(true)
            ->select('product_id')
            ->find();

        // Filtrer la requete sur ces produits
        if ($produits != null) $query->filterById($produits, Criteria::IN);
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
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEPRODUCT, new ProductEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        RewritingUrlQuery::create()
            ->filterByView($this->getRewrittenUrlViewName())
            ->filterByViewId($this->getId())
            ->update(array(
                "View" => ConfigQuery::getPassedUrlView()
            ));
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEPRODUCT, new ProductEvent($this));
    }
}
