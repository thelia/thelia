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

namespace Thelia\Install;

use App\Kernel as AppKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application as FrameworkConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Install\Standalone\CommandExitCodeRecorder;

/**
 * The template step of thelia:install: template:set for each chosen theme, in the same
 * process, once the database and the environment file exist. bin/install runs the same
 * commands through its own post-install loop.
 */
final readonly class TemplateApplier
{
    /**
     * Run template:set for each theme, each in a kernel of its own, and report a theme it
     * could not apply without stopping at it.
     *
     * @param array{host: string, port: string, dbName: string, username: string, password: string} $connectionInfo
     * @param array<string, string>                                                                 $themes         template name by template type
     *
     * @return bool false when at least one template:set did not succeed
     */
    public function apply(OutputInterface $output, array $connectionInfo, array $themes): bool
    {
        $this->publishDatabaseEnvironmentForCurrentProcess($connectionInfo);
        $applied = true;

        if (!class_exists(AppKernel::class)) {
            throw new \RuntimeException('App\\Kernel is missing. Post-install steps require the application kernel.');
        }

        // template:set triggers cache:clear, which deletes container files
        // mid-process. This causes harmless PHP warnings ("Failed to open
        // stream") when the console.terminate event tries to load deleted
        // services. We suppress them — same approach as bin/install.
        set_error_handler(static fn (int $errno, string $errstr): bool => str_contains($errstr, 'Failed to open stream') || str_contains($errstr, 'Failed opening required'), \E_WARNING);

        try {
            foreach ($themes as $type => $name) {
                $name = trim((string) $name);

                if ('' === $name) {
                    continue;
                }

                $output->writeln(\sprintf(
                    '<info>Applying template "%s" for type "%s"...</info>',
                    OutputFormatter::escape($name),
                    OutputFormatter::escape((string) $type),
                ));

                // Debug off, as in bin/install: the traceable dispatcher of a debug kernel
                // resolves every lazy listener before calling the first one, so a listener
                // dying on the cleared cache would stop console.terminate before the exit
                // code is recorded.
                $kernel = new AppKernel($_SERVER['APP_ENV'], false);
                $kernel->boot();

                try {
                    // A console.terminate listener dying on a container file template:set
                    // deleted does not decide the result: the exit code it returned does.
                    $exitCode = (new CommandExitCodeRecorder())->run(
                        new FrameworkConsoleApplication($kernel),
                        new ArrayInput([
                            'command' => 'template:set',
                            'type' => $type,
                            'name' => $name,
                        ]),
                        $output,
                        $kernel->getContainer()->get('event_dispatcher'),
                    );

                    if (Command::SUCCESS !== $exitCode) {
                        $applied = false;
                        $output->writeln(
                            \sprintf(
                                '<error>Post-install step failed while applying template "%s" for type "%s".</error>',
                                OutputFormatter::escape($name),
                                OutputFormatter::escape((string) $type),
                            )
                        );
                    }
                } finally {
                    try {
                        $kernel->shutdown();
                    } catch (\Throwable) {
                    }
                }
            }
        } finally {
            restore_error_handler();
        }

        return $applied;
    }

    private function publishDatabaseEnvironmentForCurrentProcess(array $connectionInfo): void
    {
        $values = [
            'DATABASE_HOST' => (string) $connectionInfo['host'],
            'DATABASE_PORT' => (string) $connectionInfo['port'],
            'DATABASE_NAME' => (string) $connectionInfo['dbName'],
            'DATABASE_USER' => (string) $connectionInfo['username'],
            'DATABASE_PASSWORD' => (string) $connectionInfo['password'],
        ];

        foreach ($values as $name => $value) {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            putenv($name.'='.$value);
        }
    }
}
