<?php

namespace Thelia\Command;

use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Propel\Generator\Command\MigrationDiffCommand as PropelMigrationDiffCommand;
use Thelia\Core\PropelInitService;

/**
 * Generate a SQL diff between the current database structure and the current global schema, using the Propel migration
 * system.
 */
class DiffDatabaseCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('thelia:dev:db:diff')
            ->setDescription('Generate SQL to update the database(s) structure to the global Propel schema');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $output->writeln($formatter->formatBlock(
            'This is an experimental command that may be changed or removed at any time.',
            'bg=yellow;fg=black'
        ));

        /** @var PropelInitService $propelInitService */
        $propelInitService = $this->getContainer()->get('thelia.propel.init');

        // rebuild the global schema
        $propelInitService->buildPropelGlobalSchema();

        // call the Propel migration:diff command
        $fs = new Filesystem();
        $fs->remove($propelInitService->getPropelMigrationDir());

        $propelInitService->runCommand(
            new PropelMigrationDiffCommand(),
            [
                '--config-dir' => $propelInitService->getPropelConfigDir(),
                '--schema-dir' => $propelInitService->getPropelSchemaDir(),
                '--output-dir' => $propelInitService->getPropelMigrationDir(),
            ]
        );

        // get the generated migration class
        $finder = new Finder();
        $finder
            ->files()
            ->in($propelInitService->getPropelMigrationDir());

        if ($finder->count() != 1) {
            $output->writeln('Could not find the generated migration class.');
            return 1;
        }

        // get the first (and only) found file
        $i = $finder->getIterator();
        $i->rewind();
        /** @var SplFileInfo $migration */
        $migration = $i->current();

        // instantiate the migration class
        require $migration->getRealPath();
        $migrationClassName = pathinfo($migration->getFilename(), PATHINFO_FILENAME);
        $migrationClass = new $migrationClassName();

        // output the generated SQL
        foreach ($migrationClass->getUpSQL() as $databaseName => $upSQL) {
            $output->writeln("-- DATABASE {$databaseName}, UP");
            $output->writeln($upSQL);
        }
        foreach ($migrationClass->getDownSQL() as $databaseName => $downSQL) {
            $output->writeln("-- DATABASE {$databaseName}, DOWN");
            $output->writeln($downSQL);
        }

        return 0;
    }
}
