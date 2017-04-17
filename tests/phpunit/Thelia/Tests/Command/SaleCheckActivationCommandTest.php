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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Sale;
use Thelia\Command\SaleCheckActivationCommand;
use Thelia\Core\Application;
use Thelia\Model\SaleQuery;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * Class SaleCheckActivationCommandTest
 * @package Thelia\Tests\Command
 * @author manuel raynaud <manu@raynaud.io>
 */
class SaleCheckActivationCommandTest extends ContainerAwareTestCase
{
    protected static $deactivated;

    protected static $activated;

    /**
     * in this method two sales are created. The first must be activated and the second one must be deactivated
     */
    public static function setUpBeforeClass()
    {
        /** @var \Thelia\Model\Sale $sale */
        $sale = SaleQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        if (null === $sale) {
            throw new \RuntimeException('use fixtures before launching test, there is no sale in database');
        }

        $startDate = new \DateTime("@".strtotime("today - 1 month"));
        $endDate = new \DateTime("@".strtotime("today + 1 month"));

        $sale->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setActive(false)
            ->save();

        self::$deactivated = $sale->getId();

        /** @var \Thelia\Model\Sale $otherSale */
        $otherSale = SaleQuery::create()
            ->filterById($sale->getId(), Criteria::NOT_IN)
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        $startDate = new \DateTime("@".strtotime("today - 1 month"));
        $endDate = new \DateTime("@".strtotime("today - 1 day"));

        $otherSale
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setActive(true)
            ->save();


        self::$activated = $otherSale->getId();
    }

    public function testCommand()
    {
        $application = new Application($this->getKernel());

        $checkCommand = new SaleCheckActivationCommand();
        $checkCommand->setContainer($this->getContainer());

        $application->add($checkCommand);

        $command = $application->find("sale:check-activation");
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            "command" => $command->getName(),
            "--env" => "test"
        ]);

        $deactivatedSale = SaleQuery::create()->findPk(self::$deactivated);

        $this->assertTrue($deactivatedSale->getActive(), "the sale must be actived now");

        $activatedSale = SaleQuery::create()->findPk(self::$activated);

        $this->assertFalse($activatedSale->getActive(), "the sale must be deactived now");
    }

    /**
     * Use this method to build the container with the services that you need.
     * @param ContainerBuilder $container
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new Sale($eventDispatcher));

        $container->set("event_dispatcher", $eventDispatcher);
    }
}
