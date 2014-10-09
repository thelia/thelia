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

namespace Thelia\Tests\Command;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Sale;
use Thelia\Model\SaleQuery;
use Thelia\Tests\ContainerAwareTestCase;
use Thelia\Model\Sale as SaleModel;

/**
 * Class SaleCheckActivationCommandTest
 * @package Thelia\Tests\Command
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class SaleCheckActivationCommandTest extends ContainerAwareTestCase
{

    /**
     * in this method two sales are created. The first must be activated and the second one must be deactivated
     */
    public static function setUpBeforeClass()
    {
        $sale = SaleQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();









    }

    public function testSaleCommandActivation()
    {

    }

    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new Sale());

        $container->set("event_dispatcher", $eventDispatcher);
    }
}
