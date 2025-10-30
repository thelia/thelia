<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Core\File\FileModelParentInterface;
use Thelia\Domain\Taxation\TaxEngine\Calculator;
use Thelia\Model\Base\Product as BaseProduct;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Product extends BaseProduct implements FileModelParentInterface
{
    use PositionManagementTrait;
    use UrlRewritingTrait;

    public function getRewrittenUrlViewName()
    {
        return 'product';
    }

    public function getRealLowestPrice($virtualColumnName = 'real_lowest_price')
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);
        } catch (PropelException) {
            throw new PropelException(\sprintf('Virtual column `%s` does not exist in Product::getRealLowestPrice', $virtualColumnName));
        }

        return $amount;
    }

    public function getTaxedPrice(Country $country, $price, ?State $state = null)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this, $country, $state)->getTaxedPrice((float) $price);
    }

    public function getTaxedPromoPrice(Country $country, $price, ?State $state = null)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this, $country, $state)->getTaxedPrice((float) $price);
    }

    /**
     * Return the default PSE for this product.
     */
    public function getDefaultSaleElements(): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()->filterByProductId($this->id)->filterByIsDefault(true)->findOne();
    }

    /**
     * Return PSE count fir this product.
     *
     * @param ConnectionInterface $con an optional connection object
     */
    public function countSaleElements(?ConnectionInterface $con = null): int
    {
        return ProductSaleElementsQuery::create()->filterByProductId($this->id)->count($con);
    }

    /**
     * @return int the current default category ID for this product
     */
    public function getDefaultCategoryId(): int
    {
        // Find default category
        $default_category = ProductCategoryQuery::create()
            ->filterByProductId($this->getId())
            ->filterByDefaultCategory(true)
            ->findOne();

        return null === $default_category ? 0 : $default_category->getCategoryId();
    }

    /**
     * Set default category for this product.
     *
     * @param int $defaultCategoryId the new default category id
     *
     * @return $this
     */
    public function setDefaultCategory(int $defaultCategoryId)
    {
        // Allow uncategorized products (NULL instead of 0, to bypass delete cascade constraint)
        if ($defaultCategoryId <= 0) {
            $defaultCategoryId = null;
        }

        /** @var ProductCategory $productCategory */
        $productCategory = ProductCategoryQuery::create()
            ->filterByProductId($this->getId())
            ->filterByDefaultCategory(true)
            ->findOne();

        if (null !== $productCategory && (int) $productCategory->getCategoryId() === (int) $defaultCategoryId) {
            return $this;
        }

        if (null !== $productCategory) {
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
     *
     * @return $this
     */
    public function updateDefaultCategory(int $defaultCategoryId)
    {
        return $this->setDefaultCategory($defaultCategoryId);
    }

    /**
     * Create a new product, along with the default category ID.
     *
     * @param int   $defaultCategoryId the default category ID of this product
     * @param float $basePrice         the product base price
     * @param int   $priceCurrencyId   the price currency Id
     * @param int   $taxRuleId         the product tax rule ID
     * @param float $baseWeight        base weight in Kg
     * @param int   $baseQuantity      the product quantity (default: 0)
     *
     * @throws \Exception
     */
    public function create(
        int $defaultCategoryId,
        float $basePrice,
        int $priceCurrencyId,
        int $taxRuleId,
        float $baseWeight = 0,
        int $baseQuantity = 0,
    ): void {
        $con = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);

        $con->beginTransaction();

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
        } catch (\Exception $exception) {
            $con->rollback();

            throw $exception;
        }
    }

    /**
     * Create a basic product sale element attached to this product.
     *
     * @throws PropelException
     * @throws \Exception
     */
    public function createProductSaleElement(
        ConnectionInterface $con,
        float $weight,
        float $basePrice,
        float $salePrice,
        int $currencyId,
        bool $isDefault,
        bool $isPromo = false,
        bool $isNew = false,
        int $quantity = 0,
        string $eanCode = '',
        bool|string $ref = false,
    ): ProductSaleElements {
        // Create an empty product sale element
        $saleElements = new ProductSaleElements();

        $saleElements
            ->setProduct($this)
            ->setRef(false === $ref ? $this->getRef() : $ref)
            ->setPromo($isPromo)
            ->setNewness($isNew)
            ->setWeight($weight)
            ->setIsDefault($isDefault)
            ->setEanCode($eanCode)
            ->setQuantity($quantity)
            ->save($con);

        // Create an empty product price in the provided currency
        $productPrice = new ProductPrice();

        $productPrice
            ->setProductSaleElements($saleElements)
            ->setPromoPrice($salePrice)
            ->setPrice($basePrice)
            ->setCurrencyId($currencyId)
            ->setFromDefaultCurrency(false)
            ->save($con);

        return $saleElements;
    }

    /**
     * Calculate next position relative to our default category.
     *
     * @deprecated since 2.3, and will be removed in 2.4
     */
    protected function addCriteriaToPositionQuery(ProductQuery $query): void
    {
        // Find products in the same category
        $products = ProductCategoryQuery::create()
            ->filterByCategoryId($this->getDefaultCategoryId())
            ->filterByDefaultCategory(true)
            ->select('product_id')
            ->find();

        if (null !== $products) {
            $query->filterById($products, Criteria::IN);
        }
    }

    public function preDelete(?ConnectionInterface $con = null): bool
    {
        parent::preDelete($con);

        // Delete free_text feature AV for this product (see issue #2061). We have to do this before
        // deleting the product, as the delete is cascaded to the feature_product table.
        $featureAvs = FeatureAvQuery::create()
            ->useFeatureProductQuery()
            ->filterByIsFreeText(true)
            ->filterByProductId($this->getId())
            ->endUse()
            ->find($con);

        /** @var FeatureAv $featureAv */
        foreach ($featureAvs as $featureAv) {
            $featureAv
                ->delete($con);
        }

        return true;
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategory::setPosition
     */
    public function setPosition($v)
    {
        return parent::setPosition($v);
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ProductCategory::getPosition
     */
    public function getPosition()
    {
        return parent::getPosition();
    }

    public function postSave(?ConnectionInterface $con = null): void
    {
        // For BC, will be removed in 2.4
        if (!$this->isNew() && (isset($this->modifiedColumns[ProductTableMap::COL_POSITION]) && $this->modifiedColumns[ProductTableMap::COL_POSITION]) && null !== $productCategory = ProductCategoryQuery::create()
            ->filterByProduct($this)
            ->filterByDefaultCategory(true)
            ->findOne()) {
            $productCategory->changeAbsolutePosition($this->getPosition());
        }

        parent::postSave();
    }

    /**
     * Overload for the position management.
     */
    protected function doAddProductCategory(ProductCategory $productCategory): void
    {
        parent::doAddProductCategory($productCategory);

        $productCategoryPosition = ProductCategoryQuery::create()
            ->filterByCategoryId($productCategory->getCategoryId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $productCategory->setPosition(null !== $productCategoryPosition ? $productCategoryPosition->getPosition() + 1 : 1);
    }
}
