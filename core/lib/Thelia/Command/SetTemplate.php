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
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TheliaTemplateHelper;
use Thelia\Model\ConfigQuery;
use Thelia\Service\Module\ModuleManager;

class SetTemplate extends ContainerAwareCommand
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly TheliaTemplateHelper $theliaTemplateHelper,
        string $name = null
    ) {
        parent::__construct($name);
    }

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

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $type = (string) $input->getArgument('type');

        if (!\array_key_exists($type, TemplateDefinition::CONFIG_NAMES)) {
            $output->writeln('<error>Invalid template type.</error>');

            return self::FAILURE;
        }
        $path = THELIA_TEMPLATE_DIR.$type.DS.$name;
        if (!is_dir($path)) {
            $output->writeln("<error>Template {$path} not found.</error>");

            return self::FAILURE;
        }

        ConfigQuery::write(TemplateDefinition::CONFIG_NAMES[$type], $name);

        $output->writeln('<info>Template successfully changed.</info>');
        $moduledInstalled = $this->moduleManager->installModulesFromTemplatePath($path);
        $output->writeln(sprintf('<info>%d modules installed and activated.</info>', \count($moduledInstalled)));
        $this->theliaTemplateHelper->enableThemeAsBundle($path);
        $output->writeln('<info>Theme ready</info>');

        return self::SUCCESS;
    }
}
