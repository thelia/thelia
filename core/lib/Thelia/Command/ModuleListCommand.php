<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace Thelia\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class ModuleListCommand
 * @package Thelia\Command
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ModuleListCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('module:list')
            ->setDescription('List the modules')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = new Table($output);
        $helper->addRows($this->getModulesData());

        $helper
            ->setHeaders(["Code", "Active", "Type", "Version"])
            ->render($output)
        ;
    }

    protected function getModulesData()
    {
        $moduleData = ModuleQuery::create()
            ->orderByType()
            ->addAsColumn("code", ModuleTableMap::CODE)
            ->addAsColumn("active", "IF(".ModuleTableMap::ACTIVATE.", \"Yes\", \"No\")")
            ->addAsColumn("type", ModuleTableMap::TYPE)
            ->addAsColumn("version", ModuleTableMap::VERSION)
            ->select([
                "code",
                "active",
                "type",
                "version",
            ])
            ->find()
            ->toArray()
        ;

        foreach ($moduleData as &$row) {
            switch ($row["type"]) {
                case BaseModule::CLASSIC_MODULE_TYPE:
                    $row["type"] = "classic";
                    break;
                case BaseModule::DELIVERY_MODULE_TYPE:
                    $row["type"] = "delivery";
                    break;
                case BaseModule::PAYMENT_MODULE_TYPE:
                    $row["type"] = "payment";
                    break;
            }
        }

        return $moduleData;
    }
}
