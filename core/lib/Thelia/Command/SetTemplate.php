<?php

declare(strict_types=1);

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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TheliaTemplateHelper;
use Thelia\Domain\Module\Composer\ComposerHelper;
use Thelia\Log\Tlog;
use Thelia\Model\Module;
use Thelia\Module\BaseModule;
use Thelia\Module\ModuleManagement;
use Thelia\Tools\TerminalText;

#[AsCommand(name: 'template:set', description: 'set template')]
class SetTemplate extends ContainerAwareCommand
{
    public function __construct(
        private readonly ModuleManagement $moduleManager,
        private readonly TheliaTemplateHelper $theliaTemplateHelper,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ComposerHelper $composerHelper,
        private readonly string $kernelCacheDir,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'template type : '.implode(', ', array_keys(TemplateDefinition::CONFIG_NAMES)),
            )
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'template name',
            )
        ;
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
            $packageType = $this->getComposerPackageTypeForTemplateType($type);

            $pathVendor = $this->composerHelper->findInstalledPackagePathByTypeAndInstallerName($packageType, $name);

            if (null === $pathVendor) {
                $output->writeln(\sprintf(
                    '<error>Template "%s" not found. Expected "%s" or an installed Composer package of type "%s" with installer-name "%s".</error>',
                    $name,
                    $path,
                    $packageType,
                    $name,
                ));

                return self::FAILURE;
            }

            // copy directory vendor to template
            if (!is_dir($path) && !mkdir($path, 0o777, true) && !is_dir($path)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $path));
            }

            $filesystem = new Filesystem();
            $filesystem->mirror($pathVendor, $path);

            $output->writeln(\sprintf('<fg=green>Template copied from %s to %s.</>', $pathVendor, $path));
        }

        // Required modules must be installed before the config switch: the cache rebuild
        // triggered by setConfigToTemplate would otherwise load the template bundle while
        // its modules are not yet available in the container.
        try {
            $modulesInstalled = $this->moduleManager->installModulesFromTemplatePath($path, $output);
        } catch (\Throwable $exception) {
            return $this->reportModuleInstallFailure($exception, $name, $output);
        }
        // Each inactive module was named above, once ModuleManagement had handled them all: the summary only counts.
        $activeModules = array_filter($modulesInstalled, static fn (Module $module): bool => BaseModule::IS_ACTIVATED === $module->getActivate());
        $output->writeln(\sprintf('<fg=blue>%d theme modules found, %d active.</>', \count($modulesInstalled), \count($activeModules)));

        $this->theliaTemplateHelper->enableThemeAsBundle($path);
        $this->dumpAutoload($output);

        $this->theliaTemplateHelper->setConfigToTemplate(TemplateDefinition::CONFIG_NAMES[$type], $name);
        $this->eventDispatcher->dispatch(new CacheEvent($this->kernelCacheDir), TheliaEvents::CACHE_CLEAR);

        $output->writeln('<fg=green>Template successfully changed.</>');
        $output->writeln('<fg=green>Theme ready !</>');

        return self::SUCCESS;
    }

    /**
     * A module of the theme could not be installed or activated: the command says so, logs
     * it, and stops before the theme is enabled.
     */
    private function reportModuleInstallFailure(\Throwable $exception, string $name, OutputInterface $output): int
    {
        // The message quotes a module directory, a namespace or a descriptor value, and the
        // schema reports each of its errors on a line of its own: it is printed on one.
        $message = TerminalText::onOneLine($exception->getMessage());
        $trace = self::traceWithoutArguments($exception);
        // The trace goes to the log without its arguments: getTraceAsString() would print
        // them, and one of them may be a connection password. The message stays on one line
        // there, so that a value it quotes cannot forge a log entry of its own.
        Tlog::getInstance()->addError(\sprintf('template:set could not install the modules of theme "%s"', TerminalText::singleLine($name)), $message."\n".$trace);
        $output->writeln(\sprintf('<error>ERROR: %s</error>', OutputFormatter::escape($message)));
        if ($output->isVerbose()) {
            $output->writeln(OutputFormatter::escape($trace));
        }

        return self::FAILURE;
    }

    /**
     * The frames of the trace without their arguments: getTraceAsString() prints them unless
     * zend.exception_ignore_args is on, and one of them may be a connection password.
     */
    private static function traceWithoutArguments(\Throwable $throwable): string
    {
        $frames = [];
        foreach ($throwable->getTrace() as $index => $frame) {
            // One frame per line: a file or class name cannot start a line of its own.
            $frames[] = \sprintf('#%d %s(%d): %s%s%s()', $index, TerminalText::singleLine($frame['file'] ?? '[internal function]'), $frame['line'] ?? 0, TerminalText::singleLine($frame['class'] ?? ''), $frame['type'] ?? '', TerminalText::singleLine($frame['function']));
        }

        return TerminalText::withoutControlCharacters(implode("\n", $frames));
    }

    private function dumpAutoload(OutputInterface $output): void
    {
        try {
            $this->composerHelper->dumpAutoload();
        } catch (\RuntimeException $exception) {
            $output->writeln(\sprintf('<error>Composer dump-autoload failed: %s</error>', OutputFormatter::escape(TerminalText::withoutControlCharacters($exception->getMessage()))));

            return;
        }

        $output->writeln('<fg=green>Autoload dump completed successfully</>');
    }

    private function getComposerPackageTypeForTemplateType(string $type): string
    {
        return match ($type) {
            'frontOffice' => 'thelia-frontoffice-template',
            'backOffice' => 'thelia-backoffice-template',
            'pdf' => 'thelia-pdf-template',
            'email' => 'thelia-email-template',
            default => throw new \InvalidArgumentException(\sprintf('Unsupported template type "%s".', $type)),
        };
    }
}
