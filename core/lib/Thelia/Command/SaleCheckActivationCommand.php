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

namespace Thelia\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Sale\SaleActiveStatusCheckEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class SaleCheckActivationCommand
 * @package Thelia\Command
 * @author manuel raynaud <manu@raynaud.io>
 */
class SaleCheckActivationCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName("sale:check-activation")
            ->setDescription("check the activation and deactivation dates of sales, and perform the required action depending on the current date.");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->getDispatcher()->dispatch(
                TheliaEvents::CHECK_SALE_ACTIVATION_EVENT,
                new SaleActiveStatusCheckEvent()
            );

            $output->writeln("<info>Sale verification processed successfully</info>");
        } catch (\Exception $ex) {
            $output->writeln(sprintf("<error>Error : %s</error>", $ex->getMessage()));
        }
    }
}
