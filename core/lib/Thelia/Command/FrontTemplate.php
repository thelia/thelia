<?php

namespace Thelia\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\ConfigQuery;

/**
* change the front template
*
* Class FrontTemplate
* @package Thelia\Command
* @author Damien Foulhoux <dfoulhoux@openstudio.fr>
*
*/
class FrontTemplate extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setName("template:front")
        ->setDescription("set front template")
        ->addArgument(
            "template",
            InputArgument::REQUIRED,
            "template to activate"
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $template = $input->getArgument("template");
        
        $templateExists = is_dir(THELIA_TEMPLATE_DIR  . 'frontOffice' . DS . $template);

        if ($templateExists) {
            ConfigQuery::write('active-front-template', $template);
        }
    }
}
