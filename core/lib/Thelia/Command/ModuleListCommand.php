<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class ModuleListCommand.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ModuleListCommand extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->setName('module:list')
            ->setDescription('List the modules')
        ;
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new Table($output);
        $helper->addRows($this->getModulesData());

        $helper
            ->setHeaders(['Code', 'Active', 'Type', 'Version'])
            ->render()
        ;

        return 0;
    }

    protected function getModulesData()
    {
        $moduleData = ModuleQuery::create()
            ->orderByType()
            ->addAsColumn('code', ModuleTableMap::COL_CODE)
            ->addAsColumn('active', 'IF('.ModuleTableMap::COL_ACTIVATE.', "Yes", "No")')
            ->addAsColumn('type', ModuleTableMap::COL_TYPE)
            ->addAsColumn('version', ModuleTableMap::COL_VERSION)
            ->select([
                'code',
                'active',
                'type',
                'version',
            ])
            ->find()
            ->toArray()
        ;

        foreach ($moduleData as &$row) {
            switch ($row['type']) {
                case BaseModule::CLASSIC_MODULE_TYPE:
                    $row['type'] = 'classic';
                    break;
                case BaseModule::DELIVERY_MODULE_TYPE:
                    $row['type'] = 'delivery';
                    break;
                case BaseModule::PAYMENT_MODULE_TYPE:
                    $row['type'] = 'payment';
                    break;
            }
        }

        return $moduleData;
    }
}
