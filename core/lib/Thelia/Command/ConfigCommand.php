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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\Config;
use Thelia\Model\ConfigQuery;

/**
 * command line for managing configuration variables.
 *
 * php Thelia thelia:config COMMAND [name] [value] [--secured] [--visible]
 *
 * Where COMMAND is list, get, set or delete.
 *
 * For command get and delete, you should also set the name attribute.
 *
 * For command set, you should set the name and value attributes and optionally add
 * --secured and/or --visible arguments.
 *
 * Class ConfigCommand
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConfigCommand extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->setName('thelia:config')
            ->setDescription('Manage configuration variables')
            ->addArgument(
                'COMMAND',
                InputArgument::REQUIRED,
                'Command : list, get, set, delete'
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'The variable name'
            )
            ->addArgument(
                'value',
                InputArgument::OPTIONAL,
                'The variable value'
            )
            ->addOption(
                'secured',
                null,
                InputOption::VALUE_NONE,
                'When setting a new variable tell variable is secured.'
            )
            ->addOption(
                'visible',
                null,
                InputOption::VALUE_NONE,
                'When setting a new variable tell variable is visible.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $input->getArgument('COMMAND');

        switch ($command) {
            case 'list':
                return $this->listConfig($input, $output);
            case 'get':
                return $this->getConfig($input, $output);
            case 'set':
                return $this->setConfig($input, $output);
            case 'delete':
                return $this->deleteConfig($input, $output);
            default:
                $output->writeln("<error>Unknown argument 'COMMAND' : list, get, set, delete</error>");

                return 1;
        }
    }

    private function listConfig(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            '',
            '<error>Variables list</error>',
            '',
        ]);

        $vars = ConfigQuery::create()
            ->orderByName()
            ->find()
        ;

        $rows = [];

        /** @var Config $var */
        foreach ($vars as $var) {
            $rows[] = [
                $var->getName(),
                $var->getValue(),
                $var->getSecured() !== 0 ? 'yes' : 'no',
                $var->getHidden() !== 0 ? 'yes' : 'no',
            ];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['Name', 'Value', 'secured', 'hidden'])
            ->setRows($rows)
        ;
        $table->render();

        return 0;
    }

    private function getConfig(InputInterface $input, OutputInterface $output)
    {
        $varName = $input->getArgument('name');

        if (null === $varName) {
            $output->writeln(
                "<error>Need argument 'name' for get command</error>"
            );

            return 1;
        }

        $var = ConfigQuery::create()->findOneByName($varName);

        $out = [];

        if (null === $var) {
            $out[] = sprintf(
                "<error>Unknown variable '%s'</error>",
                $varName
            );
        } else {
            $out = [
                sprintf('%12s: <%3$s>%s</%3$s>', 'Name', $var->getName(), 'info'),
                sprintf('%12s: <%3$s>%s</%3$s>', 'Value', $var->getValue(), 'info'),
                sprintf('%12s: <%3$s>%s</%3$s>', 'Secured', $var->getSecured() ? 'yes' : 'no', 'info'),
                sprintf('%12s: <%3$s>%s</%3$s>', 'Hidden', $var->getHidden() ? 'yes' : 'no', 'info'),
                sprintf('%12s: <%3$s>%s</%3$s>', 'Title', $var->getTitle(), 'info'),
                sprintf('%12s: <%3$s>%s</%3$s>', 'Description', $var->getDescription(), 'info'),
            ];
        }

        $output->writeln($out);

        return 0;
    }

    private function setConfig(InputInterface $input, OutputInterface $output)
    {
        $varName = $input->getArgument('name');
        $varValue = $input->getArgument('value');

        if (null === $varName || null === $varValue) {
            $output->writeln(
                "<error>Need argument 'name' and 'value' for set command</error>"
            );

            return 1;
        }

        ConfigQuery::write(
            $varName,
            $varValue,
            $input->getOption('secured'),
            !$input->getOption('visible')
        );

        $output->writeln('<info>Variable has been set</info>');

        return 0;
    }

    private function deleteConfig(InputInterface $input, OutputInterface $output)
    {
        $varName = $input->getArgument('name');

        if (null === $varName) {
            $output->writeln(
                "<error>Need argument 'name' for get command</error>"
            );

            return 1;
        }

        $var = ConfigQuery::create()->findOneByName($varName);

        if (null === $var) {
            $output->writeln(
                sprintf(
                    "<error>Unknown variable '%s'</error>",
                    $varName
                )
            );
        } else {
            $var->delete();
            $output->writeln(
                sprintf(
                    "<info>Variable '%s' has been deleted</info>",
                    $varName
                )
            );
        }

        return 0;
    }
}
