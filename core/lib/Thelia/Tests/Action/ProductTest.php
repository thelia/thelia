<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Action\Product;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductSetTemplateEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\TaxRuleQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Tests\TestCaseWithURLToolSetup;

/**
 * Class ProductTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ProductTest extends TestCaseWithURLToolSetup
{

    public static function setUpBeforeClass()
    {
        ProductQuery::create()
            ->filterByRef('testCreation')
            ->delete();
    }

    public function testCreate()
    {
        $event = new ProductCreateEvent();
        $defaultCategory = CategoryQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();
        $taxRuleId = TaxRuleQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();
        $currencyId = CurrencyQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();

        $event
            ->setRef('testCreation')
            ->setLocale('fr_FR')
            ->setTitle('test create new product')
            ->setVisible(1)
            ->setDefaultCategory($defaultCategory)
            ->setBasePrice(10)
            ->setTaxRuleId($taxRuleId)
            ->setBaseWeight(10)
            ->setCurrencyId($currencyId)
            ->setDispatcher($this->getDispatcher());
        ;

        $action = new Product();
        $action->create($event);

        $createdProduct = $event->getProduct();

        $this->assertInstanceOf('Thelia\Model\Product', $createdProduct);

        $this->assertFalse($createdProduct->isNew());

        $createdProduct->setLocale('fr_FR');

        $this->assertEquals('test create new product', $createdProduct->getTitle());
        $this->assertEquals('testCreation', $createdProduct->getRef());
        $this->assertEquals(1, $createdProduct->getVisible());
        $this->assertEquals($defaultCategory, $createdProduct->getDefaultCategoryId());
        $this->assertGreaterThan(0, $createdProduct->getPosition());

        $productSaleElements = $createdProduct->getProductSaleElementss();

        $this->assertEquals(1, count($productSaleElements));

        $defaultProductSaleElement = $productSaleElements->getFirst();

        $this->assertTrue($defaultProductSaleElement->getIsDefault());
        $this->assertEquals(0, $defaultProductSaleElement->getPromo());
        $this->assertEquals(0, $defaultProductSaleElement->getNewness());
        $this->assertEquals($createdProduct->getRef(), $defaultProductSaleElement->getRef());
        $this->assertEquals(10, $defaultProductSaleElement->getWeight());

        $productPrice = $defaultProductSaleElement->getProductPrices()->getFirst();

        $this->assertEquals(10, $productPrice->getPrice());
        $this->assertEquals($currencyId, $productPrice->getCurrencyId());

        return $createdProduct;

    }

    /**
     * @depends testCreate
     */
    public function testUpdate(ProductModel $product)
    {
        $event = new ProductUpdateEvent($product->getId());
        $defaultCategory = CategoryQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();
        $brandId = BrandQuery::create()->findOne()->getId();
        $event
            ->setLocale('fr_FR')
            ->setTitle('test MAJ titre en français')
            ->setDescription('test description fr')
            ->setChapo('test chapo fr')
            ->setPostscriptum('test postscriptum fr')
            ->setVisible(1)
            ->setDefaultCategory($defaultCategory)
            ->setBrandId($brandId)
            ->setDispatcher($this->getDispatcher());
        ;

        $action = new Product();
        $action->update($event);

        $updatedProduct = $event->getProduct();

        $this->assertInstanceOf('Thelia\Model\Product', $updatedProduct);

        $updatedProduct->setLocale('fr_FR');

        $this->assertEquals('test MAJ titre en français', $updatedProduct->getTitle());
        $this->assertEquals('test description fr', $updatedProduct->getDescription());
        $this->assertEquals('test chapo fr', $updatedProduct->getChapo());
        $this->assertEquals('test postscriptum fr', $updatedProduct->getPostscriptum());
        $this->assertEquals(1, $updatedProduct->getVisible());
        $this->assertEquals($defaultCategory, $updatedProduct->getDefaultCategoryId());

        $PSE = $updatedProduct->getProductSaleElementss();

        $this->assertEquals(1, count($PSE));

        return $updatedProduct;
    }

    /**
     * @depends testUpdate
     */
    public function testToggleVisibility(ProductModel $product)
    {
        $expectedVisibility = !$product->getVisible();
        $event = new ProductToggleVisibilityEvent();

        $event
            ->setProduct($product)
            ->setDispatcher($this->getDispatcher())
        ;

        $action = new Product();
        $action->toggleVisibility($event);

        $updatedProduct = $event->getProduct();

        $this->assertEquals($expectedVisibility, $updatedProduct->getVisible());

        return $updatedProduct;

    }

    /**
     * @depends testToggleVisibility
     */
    public function testAddContent(ProductModel $product)
    {
        $contents = $product->getProductAssociatedContents();

        $this->assertEquals(0, count($contents));

        $content = ContentQuery::create()->addAscendingOrderByColumn('RAND()')->findOne();

        $event = new ProductAddContentEvent($product, $content->getId());
        $event->setDispatcher($this->getDispatcher());

        $action = new Product();
        $action->addContent($event);
        $product->clearProductAssociatedContents();
        $newContents = $product->getProductAssociatedContents();

        $this->assertEquals(1, count($newContents));

        return $product;
    }

    /**
     * @param ProductModel $product
     * @depends testAddContent
     */
    public function testRemoveContent(ProductModel $product)
    {
        $product->clearProductAssociatedContents();
        $contents = $product->getProductAssociatedContents();

        $this->assertEquals(1, count($contents));

        $content = $contents->getFirst();

        $event = new ProductDeleteContentEvent($product, $content->getContentId());
        $event->setDispatcher($this->getDispatcher());

        $action = new Product();
        $action->removeContent($event);
        $product->clearProductAssociatedContents();
        $deletedContent = $product->getProductAssociatedContents();

        $this->assertEquals(0, count($deletedContent));

        return $product;
    }

    /**
     * @depends testRemoveContent
     */
    public function testAddCategory(ProductModel $product)
    {
        $categories = $product->getProductCategories();

        $this->assertEquals(1, count($categories));

        $defaultCategory = $categories->getFirst();

        $category = CategoryQuery::create()
            ->filterById($defaultCategory->getCategoryId(), Criteria::NOT_IN)
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        $event = new ProductAddCategoryEvent($product, $category->getId());
        $event->setDispatcher($this->getDispatcher());

        $action = new Product();
        $action->addCategory($event);

        $product->clearProductCategories();

        $newCategories = $product->getProductCategories();

        $this->assertEquals(2, count($newCategories));

        return array(
            'product' => $product,
            'category' => $category
        );
    }

    /**
     * @depends testAddCategory
     */
    public function testRemoveCategory(Array $productCategory)
    {
        $product = $productCategory['product'];
        $category = $productCategory['category'];

        $product->clearProductCategories();

        $this->assertEquals(2, count($product->getProductCategories()));

        $event = new ProductDeleteCategoryEvent($product, $category->getId());
        $event->setDispatcher($this->getDispatcher());

        $action = new Product();
        $action->removeCategory($event);

        $product->clearProductCategories();

        $this->assertEquals(1, count($product->getProductCategories()));

        return $product;

    }

    /**
     * @depends testRemoveCategory
     */
    public function testAddAccessory(ProductModel $product)
    {
        $accessories = AccessoryQuery::create()->filterByProductId($product->getId())->count();
        $this->assertEquals(0, $accessories);

        $accessoryId = ProductQuery::create()
            ->select('id')
            ->filterById($product->getId(), Criteria::NOT_IN)
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        $event = new ProductAddAccessoryEvent($product, $accessoryId);
        $event->setDispatcher($this->getDispatcher());

        $action = new Product();
        $action->addAccessory($event);

        $newAccessories = AccessoryQuery::create()->filterByProductId($product->getId())->count();

        $this->assertEquals(1, $newAccessories);

        return $product;
    }

    /**
     * @depends testAddAccessory
     */
    public function testRemoveAccessory(ProductModel $product)
    {
        $accessories = AccessoryQuery::create()->filterByProductId($product->getId())->find();
        $this->assertEquals(1, count($accessories));

        $currentAccessory = $accessories->getFirst();
        $event = new ProductDeleteAccessoryEvent($product, $currentAccessory->getAccessory());
        $event->setDispatcher($this->getDispatcher());

        $action = new Product();
        $action->removeAccessory($event);

        $this->assertEquals(0, AccessoryQuery::create()->filterByProductId($product->getId())->count());

        return $product;
    }

    /**
     * @depends testRemoveAccessory
     */
    public function testSetProductTemplate(ProductModel $product)
    {

        $templateId = TemplateQuery::create()
            ->select('id')
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        $currencyId = CurrencyQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();

        $event = new ProductSetTemplateEvent($product, $templateId, $currencyId);
        $event->setDispatcher($this->getDispatcher());

        $action = new Product();
        $action->setProductTemplate($event);

        $updatedProduct = $event->getProduct();

        $this->assertEquals($templateId, $updatedProduct->getTemplateId());

        $productSaleElements = $updatedProduct->getProductSaleElementss();

        $this->assertEquals(1, count($productSaleElements), "after setting a new template, only 1 product_sale_elements must be present");

        $newProductSaleElements = $productSaleElements->getFirst();

        $this->assertEquals($updatedProduct->getRef(), $newProductSaleElements->getRef(), sprintf("PSE ref must be %s", $updatedProduct->getRef()));
        $this->assertTrue($newProductSaleElements->getIsDefault(), 'new PSE must be the default one for this product');
        $this->assertEquals(0, $newProductSaleElements->getWeight());

        $productPrice = $newProductSaleElements->getProductPrices()->getFirst();

        $this->assertEquals(0, $productPrice->getPrice());
        $this->assertEquals(0, $productPrice->getPromoPrice());
        $this->assertEquals($currencyId, $productPrice->getCurrencyId());

        return $updatedProduct;

    }

    /**
     * @depends testSetProductTemplate
     */
    public function testDelete(ProductModel $product)
    {
        $event = new ProductDeleteEvent($product->getId());
        $event->setDispatcher($this->getDispatcher());

        $action = new Product();
        $action->delete($event);

        $deletedProduct = $event->getProduct();

        $this->assertInstanceOf('Thelia\Model\Product', $deletedProduct, 'deleted product must be an instance of Thelia\Model\Product');

        $this->assertTrue($deletedProduct->isDeleted());
    }
}
