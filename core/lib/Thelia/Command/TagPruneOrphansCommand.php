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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Domain\Tagging\Service\TagService;

/**
 * Reports (and optionally deletes) tag attachments pointing at a customer that
 * no longer exists.
 *
 * A tag is attached by an element key and an identifier, not by a foreign key,
 * so nothing in the database deletes these rows with the customer.
 * Customer::postDelete() covers every deletion that goes through the object,
 * which is the normal path; a bulk ModelCriteria::delete(), a hand-written
 * DELETE or a module clearing its own data never reaches it. This command is
 * the net under those, and the way to clean a database that predates the hook.
 *
 * Dry-run by default: deleting attachments is not reversible, so the count comes
 * first and --force second.
 */
#[AsCommand(name: 'tag:prune-orphans', description: 'List or remove tag attachments pointing at a customer that no longer exists')]
class TagPruneOrphansCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly TagService $tagService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete the orphaned attachments (default: dry-run, only report)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $orphanCount = $this->tagService->pruneOrphanedCustomerLinks(!$force);

        if ($orphanCount === 0) {
            $io->success('No orphaned tag attachment found.');

            return Command::SUCCESS;
        }

        if (!$force) {
            $io->warning(\sprintf('%d orphaned tag attachment(s) found. Run again with --force to delete them.', $orphanCount));

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%d orphaned tag attachment(s) deleted.', $orphanCount));

        return Command::SUCCESS;
    }
}
