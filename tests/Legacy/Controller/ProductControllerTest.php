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

namespace Thelia\Tests\Controller;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Controller\Admin\ProductController;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductSaleElementsProductDocumentQuery;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductControllerTest
 * @package Thelia\Tests\Controller
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductControllerTest extends ControllerTestBase
{
    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $parser = $this->getMockBuilder("Thelia\\Core\\Template\\ParserInterface")
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $container->set("thelia.parser", $parser);
    }

    /**
     * @return \Thelia\Controller\BaseController The controller you want to test
     */
    protected function getController()
    {
        $controller = new ProductController();

        return $controller;
    }

    public function testAssociatePSEImage()
    {
        /**
         * Get a product sale elements which has a related product image
         */
        $pse = ProductSaleElementsQuery::create()
            ->useProductQuery()
                ->joinProductImage()
            ->endUse()
            ->findOne()
        ;

        if (null === $pse) {
            $this->markTestSkipped("You must have at least one product_sale_elements which has a product_image related to it's product");
        }

        /**
         * Get this image and check if they are associated
         */
        $productImage = ProductImageQuery::create()
            ->findOneByProductId($pse->getProductId())
        ;

        $association = ProductSaleElementsProductImageQuery::create()
            ->filterByProductSaleElements($pse)
            ->findOneByProductImageId($productImage->getId())
        ;

        $isAssociated = $association !== null;

        $this->controller
            ->getAssociationResponseData(
                $pse->getId(),
                "image",
                $productImage->getId()
            );

        $newAssociation = ProductSaleElementsProductImageQuery::create()
            ->filterByProductSaleElements($pse)
            ->findOneByProductImageId($productImage->getId())
        ;

        $isNowAssociated = $newAssociation !== null;

        $this->assertFalse($isAssociated === $isNowAssociated);
    }

    public function testAssociatePSEDocument()
    {
        /**
         * Get a product sale elements which has a related product image
         */
        $pse = ProductSaleElementsQuery::create()
            ->useProductQuery()
                ->joinProductDocument()
            ->endUse()
            ->findOne()
        ;

        if (null === $pse) {
            $this->markTestSkipped("You must have at least one product_sale_elements which has a product_image related to it's product");
        }

        /**
         * Get this image and check if they are associated
         */
        $productDocument = ProductDocumentQuery::create()
            ->findOneByProductId($pse->getProductId())
        ;

        $association = ProductSaleElementsProductDocumentQuery::create()
            ->filterByProductSaleElements($pse)
            ->findOneByProductDocumentId($productDocument->getId())
        ;

        $isAssociated = $association !== null;

        $this->controller
            ->getAssociationResponseData(
                $pse->getId(),
                "document",
                $productDocument->getId()
            );

        $newAssociation = ProductSaleElementsProductDocumentQuery::create()
            ->filterByProductSaleElements($pse)
            ->findOneByProductDocumentId($productDocument->getId())
        ;

        $isNowAssociated = $newAssociation !== null;

        $this->assertFalse($isAssociated === $isNowAssociated);
    }
}
