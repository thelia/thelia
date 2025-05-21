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
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\Validator\ModuleValidator;

/**
 * @since 2.5
 */
class SetTemplate extends ContainerAwareCommand
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
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

        if (!file_exists($path.DS.'composer.json')) {
            return self::SUCCESS;
        }
        $modules = $this->listModulesFromTemplatePath($path);
        foreach ($modules as $module) {
            $output->writeln([
                '',
                sprintf('<info>Installation module %s %s</info>', $module['name'], $module['version']),
                '',
            ]);
            $module = $this->installModule($module);
            $output->writeln([
                '',
                '<info>Installation ok</info>',
                '',
            ]);

            $cacheEvent = new CacheEvent(
                $this->getContainer()->getParameter('kernel.cache_dir')
            );
            $this->eventDispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);

            if ($module->getActivate() !== BaseModule::IS_ACTIVATED) {
                try {
                    $event = new ModuleToggleActivationEvent($module->getId());
                    $event->setRecursive(true);

                    $this->eventDispatcher->dispatch($event, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
                } catch (\Exception $e) {
                    $output->writeln([
                        '',
                        sprintf(
                            'Activation fail with Exception : [%d] %s',
                            $e->getCode(),
                            $e->getMessage()
                        ),
                        ''
                    ]);
                    continue;
                }
            }
            $output->writeln([
                '',
                '<info>Activation ok</info>',
                '',
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * @throws \Exception
     */
    private function installModule(array $composerInformations): Module
    {
        $moduleValidator = new ModuleValidator($composerInformations['path']);
        $moduleValidator->loadModuleDefinition();
        $checkModule = ModuleQuery::create()->findOneByFullNamespace(
            $moduleValidator->getModuleDefinition()?->getNamespace() ?? ''
        );
        if ($checkModule) {
            return $checkModule;
        }

        $moduleDefinition = $moduleValidator->getModuleDefinition();

        $moduleInstallEvent = new ModuleInstallEvent();
        $moduleInstallEvent
            ->setModulePath($composerInformations['path'])
            ->setModuleDefinition($moduleDefinition);

        $this->eventDispatcher->dispatch($moduleInstallEvent, TheliaEvents::MODULE_INSTALL);

        return $moduleInstallEvent->getModule();
    }

    /**
     * @throws \JsonException
     */
    protected function listModulesFromTemplatePath(string $directory): array
    {
        $composerJsonPath = rtrim($directory, '/').'/composer.json';

        if (!file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException("No composer.json find in '$directory'");
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true, 512, \JSON_THROW_ON_ERROR);
        $vendorDir = $composerJson['config']['vendor-dir'] ?? THELIA_ROOT.'vendor';
        $modules = [];

        $installedJsonPath = $vendorDir.'/composer/installed.json';
        if (!file_exists($installedJsonPath)) {
            return $modules;
        }
        $installed = json_decode(file_get_contents($installedJsonPath), true, 512, \JSON_THROW_ON_ERROR);

        $packages = $installed['packages'] ?? $installed;

        foreach ($packages as $package) {
            if (isset($package['type'], $composerJson['require'][$package['name']])
                && $package['type'] === 'thelia-module') {
                $installPath = str_replace('..', '', $package['install-path']);
                $packagePath = $vendorDir.$installPath;
                $modules[] = [
                    'name' => $package['name'],
                    'version' => $package['version'],
                    'path' => $packagePath,
                    'description' => $package['description'] ?? '',
                    'extra' => $package['extra'] ?? [],
                ];
            }
        }

        return $modules;
    }
}
