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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Feature;
use Thelia\Action\FeatureAv;
use Thelia\Action\File;
use Thelia\Action\Product;
use Thelia\Action\ProductSaleElement;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductSetTemplateEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Thelia;
use Thelia\Model\Accessory;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\FeatureProduct;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsProductDocument;
use Thelia\Model\ProductSaleElementsProductDocumentQuery;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Tests\TestCaseWithURLToolSetup;
use Thelia\Model\Category;

/**
 * Class ProductTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
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
            ->setCurrencyId($currencyId);

        $action = new Product($this->getMockEventDispatcher());
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

        /** @var ProductPrice $productPrice */
        $productPrice = $defaultProductSaleElement->getProductPrices()->getFirst();

        $this->assertEquals(10, $productPrice->getPrice());
        $this->assertEquals($currencyId, $productPrice->getCurrencyId());

        return $createdProduct;
    }

    public function testCreateWithOptionalParametersAction()
    {
        $event = new ProductCreateEvent();
        /** @var Category $defaultCategory */
        $defaultCategory = CategoryQuery::create()->addAscendingOrderByColumn('RAND()')->findOne();
        $taxRuleId = TaxRuleQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();
        $currencyId = CurrencyQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();
        $templateId = $defaultCategory->getDefaultTemplateId();

        if (null === $templateId) {
            $templateId = TemplateQuery::create()->addAscendingOrderByColumn('RAND()')->findOne()->getId();
        }

        $newRef = 'testCreateWithOptionalParameters' . uniqid('_');
        $event
            ->setRef($newRef)
            ->setLocale('fr_FR')
            ->setTitle('test create new product with optional parameters')
            ->setVisible(1)
            ->setDefaultCategory($defaultCategory->getId())
            ->setBasePrice(10)
            ->setTaxRuleId($taxRuleId)
            ->setBaseWeight(10)
            ->setCurrencyId($currencyId)
            ->setBaseQuantity(10)
            ->setTemplateId($templateId);

        $action = new Product($this->getMockEventDispatcher());
        $action->create($event);

        $createdProduct = $event->getProduct();

        $this->assertInstanceOf('Thelia\Model\Product', $createdProduct);

        $this->assertFalse($createdProduct->isNew());

        $createdProduct->setLocale('fr_FR');

        $this->assertEquals('test create new product with optional parameters', $createdProduct->getTitle());
        $this->assertEquals($newRef, $createdProduct->getRef());
        $this->assertEquals(1, $createdProduct->getVisible());
        $this->assertEquals($defaultCategory->getId(), $createdProduct->getDefaultCategoryId());
        $this->assertGreaterThan(0, $createdProduct->getPosition());
        $this->assertEquals($templateId, $createdProduct->getTemplateId());

        $productSaleElements = $createdProduct->getProductSaleElementss();

        $this->assertEquals(1, count($productSaleElements));

        $defaultProductSaleElement = $productSaleElements->getFirst();

        $this->assertTrue($defaultProductSaleElement->getIsDefault());
        $this->assertEquals(0, $defaultProductSaleElement->getPromo());
        $this->assertEquals(0, $defaultProductSaleElement->getNewness());
        $this->assertEquals($createdProduct->getRef(), $defaultProductSaleElement->getRef());
        $this->assertEquals(10, $defaultProductSaleElement->getWeight());
        $this->assertEquals(10, $defaultProductSaleElement->getQuantity());

        /** @var ProductPrice $productPrice */
        $productPrice = $defaultProductSaleElement->getProductPrices()->getFirst();

        $this->assertEquals(10, $productPrice->getPrice());
        $this->assertEquals($currencyId, $productPrice->getCurrencyId());
    }

    /**
     * @param ProductModel $product
     * @depends testCreate
     * @return ProductModel
     */
    public function testUpdate(ProductModel $product)
    {
        $event = new ProductUpdateEvent($product->getId());
        $defaultCategory = CategoryQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();
        $brandId = BrandQuery::create()->findOne()->getId();
        $newRef = $product->getRef() . '-new' . uniqid('_');
        $event
            ->setLocale('fr_FR')
            ->setTitle('test MAJ titre en français')
            ->setDescription('test description fr')
            ->setChapo('test chapo fr')
            ->setPostscriptum('test postscriptum fr')
            ->setVisible(1)
            ->setDefaultCategory($defaultCategory)
            ->setBrandId($brandId)
            ->setRef($newRef)
        ;

        $action = new Product($this->getMockEventDispatcher());
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

        /** @var ProductSaleElements $defaultPSE */
        $defaultPSE = $product->getDefaultSaleElements();
        $this->assertEquals($newRef, $defaultPSE->getRef(), "Default PSE Ref was not change when product ref is changed.");

        return $updatedProduct;
    }

    /**
     * @param ProductModel $product
     * @depends testUpdate
     * @return ProductModel
     */
    public function testToggleVisibility(ProductModel $product)
    {
        $expectedVisibility = !$product->getVisible();
        $event = new ProductToggleVisibilityEvent();

        $event
            ->setProduct($product);
        ;

        $action = new Product($this->getMockEventDispatcher());
        $action->toggleVisibility($event);

        $updatedProduct = $event->getProduct();

        $this->assertEquals($expectedVisibility, $updatedProduct->getVisible());

        return $updatedProduct;
    }

    /**
     * @param ProductModel $product
     * @depends testToggleVisibility
     * @return ProductModel
     */
    public function testAddContent(ProductModel $product)
    {
        $contents = $product->getProductAssociatedContents();

        $this->assertEquals(0, count($contents));

        /** @var Content $content */
        $content = ContentQuery::create()->addAscendingOrderByColumn('RAND()')->findOne();

        $event = new ProductAddContentEvent($product, $content->getId());

        $action = new Product($this->getMockEventDispatcher());
        $action->addContent($event);
        $product->clearProductAssociatedContents();
        $newContents = $product->getProductAssociatedContents();

        $this->assertEquals(1, count($newContents));

        return $product;
    }

    /**
     * @param ProductModel $product
     * @depends testAddContent
     * @return ProductModel
     */
    public function testRemoveContent(ProductModel $product)
    {
        $product->clearProductAssociatedContents();
        $contents = $product->getProductAssociatedContents();

        $this->assertEquals(1, count($contents));

        $content = $contents->getFirst();

        $event = new ProductDeleteContentEvent($product, $content->getContentId());

        $action = new Product($this->getMockEventDispatcher());
        $action->removeContent($event);
        $product->clearProductAssociatedContents();
        $deletedContent = $product->getProductAssociatedContents();

        $this->assertEquals(0, count($deletedContent));

        return $product;
    }

    /**
     * @param ProductModel $product
     * @depends testRemoveContent
     * @return array
     */
    public function testAddCategory(ProductModel $product)
    {
        $categories = $product->getProductCategories();

        $this->assertEquals(1, count($categories));

        $defaultCategory = $categories->getFirst();

        /** @var Category $category */
        $category = CategoryQuery::create()
            ->filterById($defaultCategory->getCategoryId(), Criteria::NOT_IN)
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        $event = new ProductAddCategoryEvent($product, $category->getId());

        $action = new Product($this->getMockEventDispatcher());
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
     * @param ProductCategory[] $productCategory
     * @depends testAddCategory
     * @return Product
     */
    public function testRemoveCategory(array $productCategory)
    {
        /** @var ProductModel $product */
        $product = $productCategory['product'];

        /** @var Category $category */
        $category = $productCategory['category'];

        $product->clearProductCategories();

        $this->assertEquals(2, count($product->getProductCategories()));

        $event = new ProductDeleteCategoryEvent($product, $category->getId());

        $action = new Product($this->getMockEventDispatcher());
        $action->removeCategory($event);

        $product->clearProductCategories();

        $this->assertEquals(1, count($product->getProductCategories()));

        return $product;
    }

    /**
     * @param ProductModel $product
     * @depends testRemoveCategory
     * @return ProductModel
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

        $action = new Product($this->getMockEventDispatcher());
        $action->addAccessory($event);

        $newAccessories = AccessoryQuery::create()->filterByProductId($product->getId())->count();

        $this->assertEquals(1, $newAccessories);

        return $product;
    }

    /**
     * @param ProductModel $product
     * @depends testAddAccessory
     * @return ProductModel
     */
    public function testRemoveAccessory(ProductModel $product)
    {
        $accessories = AccessoryQuery::create()->filterByProductId($product->getId())->find();
        $this->assertEquals(1, count($accessories));

        $currentAccessory = $accessories->getFirst();
        $event = new ProductDeleteAccessoryEvent($product, $currentAccessory->getAccessory());

        $action = new Product($this->getMockEventDispatcher());
        $action->removeAccessory($event);

        $this->assertEquals(0, AccessoryQuery::create()->filterByProductId($product->getId())->count());

        return $product;
    }

    /**
     * @param ProductModel $product
     * @depends testRemoveAccessory
     * @return ProductModel
     */
    public function testSetProductTemplate(ProductModel $product)
    {
        $templateId = TemplateQuery::create()
            ->select('id')
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        $currencyId = CurrencyQuery::create()->select('id')->addAscendingOrderByColumn('RAND()')->findOne();

        $oldProductSaleElements = $product->getDefaultSaleElements();
        $this->assertEquals("Thelia\Model\ProductSaleElements", get_class($oldProductSaleElements), "There is no default pse for this product");

        $event = new ProductSetTemplateEvent($product, $templateId, $currencyId);

        $action = new Product($this->getMockEventDispatcher());
        $action->setProductTemplate($event);

        $updatedProduct = $event->getProduct();

        $this->assertEquals($templateId, $updatedProduct->getTemplateId());

        $productSaleElements = $updatedProduct->getProductSaleElementss();

        $this->assertEquals(1, count($productSaleElements), "after setting a new template, only 1 product_sale_elements must be present");

        /** @var \Thelia\Model\ProductSaleElements $newProductSaleElements */
        $newProductSaleElements = $productSaleElements->getFirst();

        $this->assertEquals($updatedProduct->getRef(), $newProductSaleElements->getRef(), sprintf("PSE ref must be %s", $updatedProduct->getRef()));
        $this->assertTrue($newProductSaleElements->getIsDefault(), 'new PSE must be the default one for this product');

        $productPrice = $newProductSaleElements->getProductPrices()->getFirst();

        $oldProductPrice = $oldProductSaleElements->getProductPrices()->getFirst();

        $this->assertEquals($oldProductSaleElements->getWeight(), $newProductSaleElements->getWeight(), sprintf("->testSetProductTemplate new PSE weight must be %s", $oldProductSaleElements->getWeight()));

        $this->assertEquals($oldProductPrice->getPrice(), $productPrice->getPrice(), sprintf("->testSetProductTemplate price must be %s", $oldProductPrice->getPrice()));
        $this->assertEquals($oldProductPrice->getPromoPrice(), $productPrice->getPromoPrice(), sprintf("->testSetProductTemplate promo price must be %s", $oldProductPrice->getPromoPrice()));
        $this->assertEquals($oldProductPrice->getCurrencyId(), $productPrice->getCurrencyId(), sprintf("->testSetProductTemplate currency_id must be %s", $oldProductPrice->getCurrencyId()));

        return $updatedProduct;
    }

    /**
     * @param ProductModel $product
     * @depends testSetProductTemplate
     */
    public function testDelete(ProductModel $product)
    {
        $event = new ProductDeleteEvent($product->getId());

        $action = new Product($this->getMockEventDispatcher());
        $action->delete($event);

        $deletedProduct = $event->getProduct();

        $this->assertInstanceOf('Thelia\Model\Product', $deletedProduct, 'deleted product must be an instance of Thelia\Model\Product');

        $this->assertTrue($deletedProduct->isDeleted());
    }

    /*
     * Cloning process tests
     */

    public function testCreateClone()
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new Product($eventDispatcher));

        $originalProduct = ProductQuery::create()->addAscendingOrderByColumn('RAND()')->findOne();
        $newRef = uniqid('testClone-');

        $event = new ProductCloneEvent($newRef, 'fr_FR', $originalProduct);

        $originalProductDefaultI18n = ProductI18nQuery::create()->findPk([$originalProduct->getId(), $event->getLang()]);
        $originalProductDefaultPrice = ProductPriceQuery::create()->findOneByProductSaleElementsId($originalProduct->getDefaultSaleElements()->getId());

        // Call function to test
        $action = new Product($eventDispatcher);
        $action->createClone($event, $originalProductDefaultI18n, $originalProductDefaultPrice);

        /** @var ProductModel $cloneProduct */
        $cloneProduct = $event->getClonedProduct();

        // Check product information
        $this->assertInstanceOf(
            'Thelia\Model\Product',
            $cloneProduct,
            'Instance of clone product must be Thelia\Model\Product'
        );

        $this->assertFalse($cloneProduct->isNew(), 'IsNew must be false');
        $this->assertEquals('fr_FR', $cloneProduct->getLocale(), 'Locale must be equal');
        $this->assertEquals($originalProductDefaultI18n->getTitle(), $cloneProduct->getTitle(), 'Title must be equal');
        $this->assertEquals($newRef, $cloneProduct->getRef(), 'Ref must be equal');
        $this->assertEquals(0, $cloneProduct->getVisible(), 'Visible must be false');
        $this->assertEquals($originalProduct->getDefaultCategoryId(), $cloneProduct->getDefaultCategoryId(), 'Default categories must be equal');

        $this->assertGreaterThan(0, $cloneProduct->getPosition(), 'Position must be greater than 0');

        $clonedProductSaleElements = $cloneProduct->getProductSaleElementss();

        $this->assertCount(1, $clonedProductSaleElements, 'There is not only one default PSE (maybe more, maybe none)');

        $clonedDefaultPSE = $clonedProductSaleElements->getFirst();

        $this->assertTrue($clonedDefaultPSE->getIsDefault(), 'IsDefault must be true for the default PSE');
        $this->assertEquals($cloneProduct->getRef(), $clonedDefaultPSE->getRef(), 'Clone product ref and clone PSE ref must be equal');

        $clonedProductPrice = $clonedDefaultPSE->getProductPrices()->getFirst();

        $this->assertEquals($originalProductDefaultPrice->getPrice(), $clonedProductPrice->getPrice(), 'Default price must be equal');
        $this->assertEquals($originalProductDefaultPrice->getCurrencyId(), $clonedProductPrice->getCurrencyId(), 'Currency IDs must be equal');

        return $event;
    }

    /**
     * @depends testCreateClone
     * @param ProductCloneEvent $event
     * @return ProductCloneEvent
     */
    public function testUpdateClone(ProductCloneEvent $event)
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new Product($eventDispatcher));

        $originalProductPrice = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($event->getOriginalProduct()->getDefaultSaleElements()->getId());

        // Call function to test
        $action = new Product($eventDispatcher);
        $action->updateClone($event, $originalProductPrice);

        $cloneProduct = $event->getClonedProduct();

        $this->assertInstanceOf(
            'Thelia\Model\Product',
            $cloneProduct,
            'Instance of clone product must be Thelia\Model\Product'
        );

        // Get I18ns
        $originalProductI18ns = ProductI18nQuery::create()
            ->findById($event->getOriginalProduct()->getId());

        $cloneProductI18ns = ProductI18nQuery::create()
            ->filterById($cloneProduct->getId())
            ->count();

        $this->assertEquals(count($originalProductI18ns), $cloneProductI18ns, 'There must be the same quantity of I18ns');

        // Check each I18n
        /** @var ProductI18n $originalProductI18n */
        foreach ($originalProductI18ns as $originalProductI18n) {
            $cloneProductI18n = ProductI18nQuery::create()
                ->findPk([$cloneProduct->getId(), $originalProductI18n->getLocale()]);

            $this->assertInstanceOf(
                'Thelia\Model\ProductI18n',
                $cloneProductI18n,
                'Instance of clone product I18n must be Thelia\Model\ProductI18n'
            );

            // I18n
            $this->assertEquals($originalProductI18n->getLocale(), $cloneProductI18n->getLocale(), 'Locale must be equal');
            $this->assertEquals($originalProductI18n->getTitle(), $cloneProductI18n->getTitle(), 'Title must be equal');
            $this->assertEquals($originalProductI18n->getChapo(), $cloneProductI18n->getChapo(), 'Chapo must be equal');
            $this->assertEquals($originalProductI18n->getDescription(), $cloneProductI18n->getDescription(), 'Description must be equal');
            $this->assertEquals($originalProductI18n->getPostscriptum(), $cloneProductI18n->getPostscriptum(), 'Postscriptum must be equal');

            // SEO - Meta
            $this->assertEquals($originalProductI18n->getMetaTitle(), $cloneProductI18n->getMetaTitle(), 'MetaTitle must be equal');
            $this->assertEquals($originalProductI18n->getMetaDescription(), $cloneProductI18n->getMetaDescription(), 'MetaDescription must be equal');
            $this->assertEquals($originalProductI18n->getMetaKeywords(), $cloneProductI18n->getMetaKeywords(), 'MetaKeywords must be equal');

            // SEO - Rewriting URL
            $originalUrl = RewritingUrlQuery::create()
                ->filterByView('product')
                ->filterByViewId($originalProductI18n->getId())
                ->findOneByViewLocale($originalProductI18n->getLocale());

            $cloneUrl = RewritingUrlQuery::create()
                ->filterByView('product')
                ->filterByViewId($cloneProduct->getId())
                ->findOneByViewLocale($cloneProductI18n->getLocale());

            $this->assertEquals('product', $cloneUrl->getView(), 'View must be equal to \'product\'');
            $this->assertEquals($cloneProduct->getId(), $cloneUrl->getViewId(), 'ViewID must be equal');
            $this->assertEquals($originalProductI18n->getLocale(), $cloneUrl->getViewLocale(), 'ViewLocale must be equal to current I18n\'s locale');
            $this->assertEquals($originalUrl->getRedirected(), $cloneUrl->getRedirected(), 'Redirect must be equal');
        }

        // Check template
        $this->assertEquals($event->getOriginalProduct()->getTemplateId(), $cloneProduct->getTemplateId(), 'TemplateID must be equal');
        $this->assertEquals(
            $originalProductPrice->getCurrencyId(),
            $cloneProduct->getProductSaleElementss()->getFirst()->getProductPrices()->getFirst()->getCurrencyId(),
            'Currency IDs must be equal'
        );

        return $event;
    }

    /**
     * @depends testUpdateClone
     * @param ProductCloneEvent $event
     * @return ProductCloneEvent
     */
    public function testCloneFeatureCombination(ProductCloneEvent $event)
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new Product($eventDispatcher));
        $eventDispatcher->addSubscriber(new Feature($eventDispatcher));
        $eventDispatcher->addSubscriber(new FeatureAv($eventDispatcher));

        // Call function to test
        $action = new Product($eventDispatcher);
        $action->cloneFeatureCombination($event);

        // Get products' features
        $originalProductFeatures = FeatureProductQuery::create()
            ->findByProductId($event->getOriginalProduct()->getId());

        $cloneProductFeatures = FeatureProductQuery::create()
            ->filterByProductId($event->getClonedProduct()->getId())
            ->count();

        $this->assertEquals(count($originalProductFeatures), $cloneProductFeatures, 'There must be the same quantity of features');

        // Check clone product's features
        /** @var FeatureProduct $originalProductFeature */
        foreach ($originalProductFeatures as $originalProductFeature) {
            $cloneProductFeatureQuery = FeatureProductQuery::create()
                ->filterByProductId($event->getClonedProduct()->getId())
                ->filterByFeatureId($originalProductFeature->getFeatureId());

            if ($originalProductFeature->getFreeTextValue() != 1) {
                $cloneProductFeatureQuery->filterByFeatureAvId($originalProductFeature->getFeatureAvId());
            }

            $cloneProductFeature = $cloneProductFeatureQuery->findOne();

            $this->assertInstanceOf(
                'Thelia\Model\FeatureProduct',
                $cloneProductFeature,
                'Instance of clone product feature must be Thelia\Model\FeatureProduct'
            );

            $this->assertEquals($event->getClonedProduct()->getId(), $cloneProductFeature->getProductId(), 'ProductID must be equal');
            $this->assertEquals($originalProductFeature->getFeatureId(), $cloneProductFeature->getFeatureId(), 'FeatureID must be equal');

            if ($originalProductFeature->getFreeTextValue() == 1) {
                $this->assertNotEquals($originalProductFeature->getFeatureAvId(), $cloneProductFeature->getFeatureAvId(), 'FeatureAvID can\'t be equal');
            } else {
                $this->assertEquals($originalProductFeature->getFeatureAvId(), $cloneProductFeature->getFeatureAvId(), 'FeatureAvID must be equal');
            }

            $this->assertEquals($originalProductFeature->getFreeTextValue(), $cloneProductFeature->getFreeTextValue(), 'Free text value must be equal');
            $this->assertEquals($originalProductFeature->getPosition(), $cloneProductFeature->getPosition(), 'Position must be equal');
        }

        return $event;
    }

    /**
     * @depends testCloneFeatureCombination
     * @param ProductCloneEvent $event
     * @return ProductCloneEvent
     */
    public function testCloneAssociatedContent(ProductCloneEvent $event)
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new Product($eventDispatcher));

        // Call function to test
        $action = new Product($eventDispatcher);
        $action->cloneAssociatedContent($event);

        // Get products' associated contents
        $originalProductAssocConts = ProductAssociatedContentQuery::create()
            ->findByProductId($event->getOriginalProduct()->getId());

        $cloneProductAssocConts = ProductAssociatedContentQuery::create()
            ->filterByProductId($event->getClonedProduct()->getId())
            ->count();

        $this->assertEquals(count($originalProductAssocConts), $cloneProductAssocConts, 'There must be the same quantity of associated contents');

        // Check clone product's associated contents
        /** @var ProductAssociatedContent $originalProductAssocCont */
        foreach ($originalProductAssocConts as $originalProductAssocCont) {
            $cloneProductAssocCont = ProductAssociatedContentQuery::create()
                ->filterByProductId($event->getClonedProduct()->getId())
                ->filterByPosition($originalProductAssocCont->getPosition())
                ->findOneByContentId($originalProductAssocCont->getContentId());

            $this->assertInstanceOf(
                'Thelia\Model\ProductAssociatedContent',
                $cloneProductAssocCont,
                'Instance of clone product associated content must be Thelia\Model\ProductAssociatedContent'
            );

            $this->assertEquals($event->getClonedProduct()->getId(), $cloneProductAssocCont->getProductId(), 'ProductID must be equal');
            $this->assertEquals($originalProductAssocCont->getContentId(), $cloneProductAssocCont->getContentId(), 'ContentID must be equal');
            $this->assertEquals($originalProductAssocCont->getPosition(), $cloneProductAssocCont->getPosition(), 'Position must be equal');
        }

        return $event;
    }

    /**
     * @depends testCloneAssociatedContent
     * @param ProductCloneEvent $event
     * @return ProductCloneEvent
     */
    public function testCloneAccessories(ProductCloneEvent $event)
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new Product($eventDispatcher));

        // Call function to test
        $action = new Product($eventDispatcher);
        $action->cloneAccessories($event);

        // Get products' associated contents
        $originalProductAccessoryList = AccessoryQuery::create()
            ->findByProductId($event->getOriginalProduct()->getId());

        $cloneProductAccessoryList = AccessoryQuery::create()
            ->filterByProductId($event->getClonedProduct()->getId())
            ->count();

        $this->assertEquals(count($originalProductAccessoryList), $cloneProductAccessoryList, 'There must be the same quantity of accessories');

        // Check clone product's accessories
        /** @var Accessory $originalProductAccessory */
        foreach ($originalProductAccessoryList as $originalProductAccessory) {
            $cloneProductAccessory = AccessoryQuery::create()
                ->filterByProductId($event->getClonedProduct()->getId())
                ->filterByPosition($originalProductAccessory->getPosition())
                ->findOneByAccessory($originalProductAccessory->getAccessory());

            $this->assertInstanceOf(
                'Thelia\Model\Accessory',
                $cloneProductAccessory,
                'Instance of clone product accessory must be Thelia\Model\Accessory'
            );

            $this->assertEquals($event->getClonedProduct()->getId(), $cloneProductAccessory->getProductId(), 'ProductID must be equal');
            $this->assertEquals($originalProductAccessory->getAccessory(), $cloneProductAccessory->getAccessory(), 'Accessory must be equal');
            $this->assertEquals($originalProductAccessory->getPosition(), $cloneProductAccessory->getPosition(), 'Position must be equal');
        }

        return $event;
    }

    /**
     * @covers \Thelia\Action\File::cloneFile
     * @depends testCloneAccessories
     * @param ProductCloneEvent $event
     * @return ProductCloneEvent
     */
    public function testCloneFile(ProductCloneEvent $event)
    {
        $kernel = new Thelia('test', true);
        $kernel->boot();

        $action = new File();
        $action->cloneFile($event, null, $kernel->getContainer()->get('event_dispatcher'));

        $originalProductId = $event->getOriginalProduct()->getId();
        $cloneProduct = $event->getClonedProduct();

        $originalProductFiles = [];

        // For each type, check files
        foreach ($event->getTypes() as $type) {
            switch ($type) {
                case 'images':
                    $originalProductFiles = ProductImageQuery::create()
                        ->findByProductId($originalProductId);

                    $cloneProductFiles = ProductImageQuery::create()
                        ->filterByProductId($cloneProduct->getId())
                        ->count();

                    $this->assertEquals(count($originalProductFiles), $cloneProductFiles, 'There must be the same quantity of images');

                    break;

                case 'documents':
                    $originalProductFiles = ProductDocumentQuery::create()
                        ->findByProductId($originalProductId);

                    $cloneProductFiles = ProductDocumentQuery::create()
                        ->filterByProductId($cloneProduct->getId())
                        ->count();

                    $this->assertEquals(count($originalProductFiles), $cloneProductFiles, 'There must be the same quantity of documents');

                    break;
            }

            // Check each file
            /** @var ProductDocument $originalProductFile */
            foreach ($originalProductFiles as $originalProductFile) {
                $srcPath = $originalProductFile->getUploadDir() . DS . $originalProductFile->getFile();

                // Check if original file exists
                $this->assertFileExists($srcPath, 'Original file doesn\'t exist');

                // Get original file mimeType
                $finfo = new \finfo();
                $fileMimeType = $finfo->file($srcPath, FILEINFO_MIME_TYPE);

                // Check files depending on the type
                switch ($type) {
                    case 'images':
                        // Get cloned ProductImage
                        $cloneProductFile = ProductImageQuery::create()
                            ->filterByProductId($cloneProduct->getId())
                            ->filterByVisible($originalProductFile->getVisible())
                            ->findOneByPosition($originalProductFile->getPosition());

                        // Check if the cloned file exists and ProductImage info
                        $this->assertFileExists($cloneProductFile->getUploadDir().DS.$cloneProductFile->getFile(), 'Cloned image doesn\'t exist');
                        $this->assertEquals(
                            $fileMimeType,
                            $finfo->file($cloneProductFile->getUploadDir().DS.$cloneProductFile->getFile(), FILEINFO_MIME_TYPE),
                            'ProductImage\'s mime type must be equal'
                        );

                        $this->assertInstanceOf(
                            'Thelia\Model\ProductImage',
                            $cloneProductFile,
                            'Instance of clone product image must be Thelia\Model\ProductImage'
                        );
                        $this->assertEquals($cloneProduct->getId(), $cloneProductFile->getProductId(), 'ProductImage\'s productID must be equal');
                        $this->assertEquals($originalProductFile->getVisible(), $cloneProductFile->getVisible(), 'ProductImage\'s visible must be equal');
                        $this->assertEquals($originalProductFile->getPosition(), $cloneProductFile->getPosition(), 'ProductImage\'s position must be equal');

                        break;

                    case 'documents':
                        // Get cloned ProductDocument
                        $cloneProductFile = ProductDocumentQuery::create()
                            ->filterByProductId($cloneProduct->getId())
                            ->filterByVisible($originalProductFile->getVisible())
                            ->findOneByPosition($originalProductFile->getPosition());

                        // Check if the cloned file exists and ProductDocument info
                        $this->assertFileExists($cloneProductFile->getUploadDir().DS.$cloneProductFile->getFile(), 'Cloned document doesn\'t exist');
                        $this->assertEquals(
                            $fileMimeType,
                            $finfo->file($cloneProductFile->getUploadDir().DS.$cloneProductFile->getFile(), FILEINFO_MIME_TYPE),
                            'ProductDocument\'s mime type must be equal'
                        );

                        $this->assertInstanceOf(
                            'Thelia\Model\ProductDocument',
                            $cloneProductFile,
                            'Instance of clone product document must be Thelia\Model\ProductDocument'
                        );
                        $this->assertEquals($cloneProduct->getId(), $cloneProductFile->getProductId(), 'ProductDocument\'s productID must be equal');
                        $this->assertEquals($originalProductFile->getVisible(), $cloneProductFile->getVisible(), 'ProductDocument\'s  visible must be equal');
                        $this->assertEquals($originalProductFile->getPosition(), $cloneProductFile->getPosition(), 'ProductDocument\'s  position must be equal');

                        break;
                }
            }
        }
        return $event;
    }

    /**
     * @covers Thelia\Action\ProductSaleElement::createClonePSE
     * @depends testCloneFile
     * @param ProductCloneEvent $event
     * @return ProductCloneEvent
     */
    public function testCreateClonePSE(ProductCloneEvent $event)
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new Product($eventDispatcher));
        $eventDispatcher->addSubscriber(new ProductSaleElement($eventDispatcher));

        $originalProductPSE = ProductSaleElementsQuery::create()
            ->filterByProductId($event->getOriginalProduct()->getId())
            ->findOne();

        $currencyId = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($originalProductPSE->getId())
            ->select('CURRENCY_ID')
            ->findOne();

        // Call function to test
        $action = new ProductSaleElement($eventDispatcher);
        $clonedProductPSEId = $action->createClonePSE($event, $originalProductPSE, $currencyId);

        // Get created PSE
        $cloneProductPSE = ProductSaleElementsQuery::create()
            ->findOneById($clonedProductPSEId);

        // Check clone PSE information
        $this->assertInstanceOf(
            'Thelia\Model\ProductSaleElements',
            $cloneProductPSE,
            'Instance of clone PSE must be Thelia\Model\ProductSaleElements'
        );
        $this->assertEquals($event->getClonedProduct()->getId(), $cloneProductPSE->getProductId(), 'ProductID must be equal');
        $this->assertStringStartsWith($event->getClonedProduct()->getRef(), $cloneProductPSE->getRef(), 'PSE\'s ref must start with product\'s ref');
        $this->assertEquals($originalProductPSE->getWeight(), $cloneProductPSE->getWeight(), 'Weight must be equal');

        // Get attribute combination
        $originalAttributeCombination = AttributeCombinationQuery::create()
            ->findOneByProductSaleElementsId($originalProductPSE->getId());
        
        $cloneAttributeCombination = AttributeCombinationQuery::create()
            ->findOneByProductSaleElementsId($clonedProductPSEId);
        
        // Check clone PSE's attribute combination if exist
        if ($cloneAttributeCombination != null) {
            $this->assertInstanceOf(
                'Thelia\Model\AttributeCombination',
                $cloneAttributeCombination,
                'Instance of clone PSE\'s attribute combination must be Thelia\Model\AttributeCombination'
            );
            $this->assertEquals($originalAttributeCombination->getAttributeId(), $cloneAttributeCombination->getAttributeId(), 'AttributeID must be equal');
            $this->assertEquals($originalAttributeCombination->getAttributeAvId(), $cloneAttributeCombination->getAttributeAvId(), 'AttributeAvID must be equal');
            $this->assertEquals($clonedProductPSEId, $cloneAttributeCombination->getProductSaleElementsId(), 'PSE ID must be equal');
        }
        return ['event' => $event, 'originalPSE' => $originalProductPSE, 'clonePSE' => $cloneProductPSE];
    }

    /**
     * @covers \Thelia\Action\ProductSaleElement::updateClonePSE
     * @depends testCreateClonePSE
     * @param array $params
     * @return array
     */
    public function testUpdateClonePSE(array $params)
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new Product($eventDispatcher));
        $eventDispatcher->addSubscriber(new ProductSaleElement($eventDispatcher));

        /** @var ProductCloneEvent $event */
        $event = $params['event'];
        /** @var ProductSaleElements $originalPSE */
        $originalPSE = $params['originalPSE'];
        /** @var ProductSaleElements $clonePSE */
        $clonePSE = $params['clonePSE'];

        // Call function to test
        $action = new ProductSaleElement($eventDispatcher);
        $action->updateClonePSE($event, $clonePSE->getId(), $originalPSE, 1);

        // Get updated PSE
        $clonePSE = ProductSaleElementsQuery::create()
            ->findOneById($clonePSE->getId());

        // Check clone PSE information
        $this->assertInstanceOf(
            'Thelia\Model\ProductSaleElements',
            $clonePSE,
            'Instance of clone PSE must be Thelia\Model\ProductSaleElements'
        );
        $this->assertStringStartsWith($event->getClonedProduct()->getRef(), $clonePSE->getRef(), 'PSE\'s ref must start with product\'s ref');

        $this->assertEquals($originalPSE->getQuantity(), $clonePSE->getQuantity(), 'Quantity must be equal');
        $this->assertEquals($originalPSE->getPromo(), $clonePSE->getPromo(), 'Promo must be equal');
        $this->assertEquals($originalPSE->getNewness(), $clonePSE->getNewness(), 'Newness must be equal');
        $this->assertEquals($originalPSE->getWeight(), $clonePSE->getWeight(), 'Weight must be equal');
        $this->assertEquals($originalPSE->getIsDefault(), $clonePSE->getIsDefault(), 'IsDefault must be equal');
        $this->assertEquals($originalPSE->getEanCode(), $clonePSE->getEanCode(), 'EAN code must be equal');

        // Get PSE's product price
        $originalProductPrice = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($originalPSE->getId());

        $cloneProductPrice = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($clonePSE->getId());

        // Check clone PSE's product price
        $this->assertInstanceOf(
            'Thelia\Model\ProductPrice',
            $cloneProductPrice,
            'Instance of clone product price must be Thelia\Model\ProductPrice'
        );
        $this->assertEquals($originalProductPrice->getCurrencyId(), $cloneProductPrice->getCurrencyId(), 'CurrencyID must be equal');
        $this->assertEquals($originalProductPrice->getPrice(), $cloneProductPrice->getPrice(), 'Price must be equal');
        $this->assertEquals($originalProductPrice->getPromoPrice(), $cloneProductPrice->getPromoPrice(), 'Promo price must be equal');
        $this->assertEquals(0, $cloneProductPrice->getFromDefaultCurrency(), 'From default currency must be equal to 0');

        return [
            'event' => $event,
            'cloneProductId' => $event->getClonedProduct()->getId(),
            'clonePSEId' => $clonePSE->getId(),
            'originalPSE' => $originalPSE
        ];
    }

    /**
     * @covers \Thelia\Action\ProductSaleElement::clonePSEAssociatedFiles
     * @depends testUpdateClonePSE
     * @param array $params
     */
    public function testClonePSEAssociatedFiles(array $params)
    {
        /** @var ProductCloneEvent $event */
        $event = $params['event'];
        /** @var int $cloneProductId */
        $cloneProductId = $params['cloneProductId'];
        /** @var int $clonePSEId */
        $clonePSEId = $params['clonePSEId'];
        /** @var ProductSaleElements $originalPSE */
        $originalPSE = $params['originalPSE'];

        foreach ($event->getTypes() as $type) {
            switch ($type) {
                case 'images':
                    $originalPSEFiles = ProductSaleElementsProductImageQuery::create()
                        ->findByProductSaleElementsId($originalPSE->getId());

                    // Call function to test
                    $action = new ProductSaleElement($this->getMockEventDispatcher());
                    $action->clonePSEAssociatedFiles($cloneProductId, $clonePSEId, $originalPSEFiles, 'image');

                    $originalPSEImages = ProductSaleElementsProductImageQuery::create()
                        ->findByProductSaleElementsId($originalPSE->getId());

                    $clonePSEImages = ProductSaleElementsProductImageQuery::create()
                        ->findByProductSaleElementsId($clonePSEId);

                    $this->assertEquals(count($originalPSEImages), count($clonePSEImages), 'There must be the same quantity of PSE product image');

                    /** @var ProductSaleElementsProductImage $clonePSEImage */
                    foreach ($clonePSEImages as $clonePSEImage) {
                        $cloneProductImage = ProductImageQuery::create()
                            ->findOneById($clonePSEImage->getProductImageId());

                        $this->assertNotNull($cloneProductImage, 'Image linked to PSE must not be null');
                        $this->assertInstanceOf(
                            'Thelia\Model\ProductImage',
                            $cloneProductImage,
                            'Instance of clone PSE\'s product image must be Thelia\Model\ProductImage'
                        );
                        $this->assertEquals($clonePSEId, $clonePSEImage->getProductSaleElementsId(), 'PSE ID must be equal');
                    }
                    break;

                case 'documents';
                    $originalPSEFiles = ProductSaleElementsProductDocumentQuery::create()
                        ->findByProductSaleElementsId($originalPSE->getId());

                    // Call function to test
                    $action = new ProductSaleElement($this->getMockEventDispatcher());
                    $action->clonePSEAssociatedFiles($cloneProductId, $clonePSEId, $originalPSEFiles, 'document');

                    $originalPSEDocuments = ProductSaleElementsProductDocumentQuery::create()
                        ->findByProductSaleElementsId($originalPSE->getId());

                    $clonePSEDocuments = ProductSaleElementsProductDocumentQuery::create()
                        ->findByProductSaleElementsId($clonePSEId);

                    $this->assertEquals(count($originalPSEDocuments), count($clonePSEDocuments), 'There must be the same quantity of PSE product document');

                    /** @var ProductSaleElementsProductDocument $clonePSEDocument */
                    foreach ($clonePSEDocuments as $clonePSEDocument) {
                        $cloneProductDocument = ProductDocumentQuery::create()
                            ->findOneById($clonePSEDocument->getProductDocumentId());

                        $this->assertNotNull($cloneProductDocument, 'Document linked to PSE must not be null');
                        $this->assertInstanceOf(
                            'Thelia\Model\ProductDocument',
                            $cloneProductDocument,
                            'Instance of clone PSE\'s product document must be Thelia\Model\ProductDocument'
                        );
                        $this->assertEquals($clonePSEId, $clonePSEDocument->getProductSaleElementsId(), 'PSE ID must be equal');
                    }
                    break;
            }
        }
    }
}
