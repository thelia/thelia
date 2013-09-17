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

    protected $defaultCategory = null;

    /**
     * {@inheritDoc}
     */
    protected function getRewritenUrlViewName() {
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
        return round($taxCalculator->load($this, $country)->getTaxedPrice($this->getRealLowestPrice()), 2);
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

            // Store all the stuff !
            $con->commit();
        }
        catch(PropelException $ex) {

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

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->generateRewritenUrl($this->getLocale());

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEPRODUCT, new ProductEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        $this->save();

        $this->dispatchEvent(TheliaEvents::AFTER_CREATEPRODUCT, new ProductEvent($this));
    }

    /**
     * {@inheritDoc}
     */
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
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEPRODUCT, new ProductEvent($this));
    }

}
