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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ConfigQuery;

/**
 * @since 2.5
 */
class SetTemplate extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('template:set')
            ->setDescription('set template')
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'template type : '.implode(', ', array_keys(TemplateDefinition::CONFIG_NAMES))
            )
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'template name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $type = (string) $input->getArgument('type');

        if (!\array_key_exists($type, TemplateDefinition::CONFIG_NAMES)) {
            $output->writeln('<error>Invalid template type.</error>');

            return self::FAILURE;
        }

        if (!is_dir(THELIA_TEMPLATE_DIR.$type.DS.$name)) {
            $output->writeln("<error>Template {$name} not found.</error>");

            return self::FAILURE;
        }

        ConfigQuery::write(TemplateDefinition::CONFIG_NAMES[$type], $name);

        $output->writeln('<info>Template successfully changed.</info>');

        return self::SUCCESS;
    }
}
