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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TheliaTemplateHelper;
use Thelia\Model\ConfigQuery;
use Thelia\Service\Module\ModuleManager;

class SetTemplate extends ContainerAwareCommand
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly TheliaTemplateHelper $theliaTemplateHelper,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $kernelCacheDir,
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
            $pathVendor = THELIA_VENDOR_ROOT.$name;
            if (!is_dir($pathVendor)) {
                $output->writeln("<error>Template {$pathVendor} not found.</error>");

                return self::FAILURE;
            }
            // copy directory vendor to template
            if (!is_dir($path) && !mkdir($path) && !is_dir($path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
            $filesystem = new Filesystem();
            $filesystem->mirror($pathVendor, $path);

            $output->writeln("<fg=green>Template copied from {$pathVendor} to {$path}.</>");
        }

        $this->theliaTemplateHelper->setConfigToTemplate(TemplateDefinition::CONFIG_NAMES[$type], $name);
        $this->eventDispatcher->dispatch(new CacheEvent($this->kernelCacheDir), TheliaEvents::CACHE_CLEAR);

        $output->writeln('<fg=green>Template successfully changed.</>');
        $moduledInstalled = $this->moduleManager->installModulesFromTemplatePath($path);
        $output->writeln(sprintf('<fg=blue>%d modules installed and activated.</>', \count($moduledInstalled)));
        $this->theliaTemplateHelper->enableThemeAsBundle($path);

        $this->execDumpAutoload($output);
        $output->writeln('<fg=green>Theme ready !</>');
        return self::SUCCESS;
    }

    private function execDumpAutoload(
        OutputInterface $output,
    )
    {
        $command = THELIA_VENDOR.'bin'.DS.'composer dump-autoload 2>&1';
        $returnCode = 0;

        exec($command, $outputExec, $returnCode);

        if ($returnCode !== 0) {
            $errors = implode("\n", $outputExec);
            $output->writeln("<error>Composer dump-autoload failed: {$errors}</error>");
            return self::FAILURE;
        }
        $output->writeln('<fg=green>Autoload dump completed successfully</>');
    }
}
