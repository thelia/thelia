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

namespace Thelia\Tests\Model;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Product;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CategoryQuery;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureProductQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ProductTest
 * @package Thelia\Tests\Model
 */
class FeatureProductTest extends TestCase
{
    public function getContainer()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        return $container;
    }

    public function getEventDispatcher()
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new Product($eventDispatcher));

        return $eventDispatcher;
    }

    public function testProductDeleteFreeTextFeatureAv()
    {
        $con = Propel::getConnection();
        $con->beginTransaction();

        $featureProduct = FeatureProductQuery::create($con)->findOneByIsFreeText(true);
        $featureAvId = $featureProduct->getFeatureAvId();
        $this->assertNotNull($featureAvId, '`feature_av_id` in `feature_product` table cannot be null');

        $featureProduct
            ->getProduct()
            ->delete($con);

        $featureAv = FeatureAvQuery::create($con)->findPk($featureAvId);
        $this->assertNull($featureAv, 'Free text feature av does not deleted on product deletion');

        $con->rollback();
    }

    public function testCategoryDeleteFreeTextFeatureAv()
    {
        $con = Propel::getConnection();
        $con->beginTransaction();

        $featureProduct = FeatureProductQuery::create($con)->findOneByIsFreeText(true);
        $featureAvId = $featureProduct->getFeatureAvId();
        $this->assertNotNull($featureAvId, '`feature_av_id` in `feature_product` table cannot be null');

        CategoryQuery::create()
            ->findPk($featureProduct->getProduct()->getDefaultCategoryId())
            ->delete($con);

        $featureAv = FeatureAvQuery::create($con)->findPk($featureAvId);
        $this->assertNull($featureAv, 'Free text feature av does not deleted on category deletion');

        $con->rollback();
    }
}
