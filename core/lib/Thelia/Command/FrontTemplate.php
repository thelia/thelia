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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\ConfigQuery;

/**
 * change the front template.
 *
 * Class FrontTemplate
 *
 * @author Damien Foulhoux <dfoulhoux@openstudio.fr>
 */
class FrontTemplate extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
        ->setName('template:front')
        ->setDescription('set front template')
        ->addArgument(
            'template',
            InputArgument::REQUIRED,
            'template to activate'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $template = $input->getArgument('template');

        $templateExists = is_dir(THELIA_TEMPLATE_DIR.'frontOffice'.DS.$template);

        if ($templateExists) {
            ConfigQuery::write('active-front-template', $template);
        }
    }
}
