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

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Thelia\Domain\Search\Contract\SearchableDocumentProviderInterface;
use Thelia\Domain\Search\Contract\SearchEngineInterface;
use Thelia\Domain\Search\Message\IndexDocument;
use Thelia\Domain\Search\SearchDocumentProviders;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Domain\Search\SearchIndexer;
use Thelia\Domain\Search\SearchIndexingQueue;
use Thelia\Domain\Search\SearchLocales;

#[AsCommand(name: 'thelia:search:reindex', description: 'Rebuild the search index of the catalogue, family by family, by batches, resuming where an interrupted run stopped.')]
class SearchReindexCommand extends ContainerAwareCommand
{
    public const string QUEUE_TRANSPORT = 'async_heavy';

    private const float LOCK_TTL_SECONDS = 600.0;

    private const int CHECKPOINT_TTL_SECONDS = 86400;

    public function __construct(
        protected SearchEngineRegistry $engines,
        protected SearchDocumentProviders $providers,
        protected SearchIndexer $indexer,
        protected SearchLocales $locales,
        protected MessageBusInterface $bus,
        protected LockFactory $lockFactory,
        #[Autowire(service: 'thelia.cache.search_reindex')]
        protected CacheItemPoolInterface $checkpoints,
        #[Autowire('%env(default:thelia.messenger.inline_transport_dsn:MESSENGER_TRANSPORT_DSN)%')]
        #[\SensitiveParameter]
        protected string $jobQueueDsn = 'sync://',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('family', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only these families (product, category, content, folder, brand…)')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only these locales, the index of the others is left as it is')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'How many documents are indexed at a time', '200')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Empty the index of each family before indexing it again, which drops the documents of deleted entities; not with --locale')
            ->addOption('restart', null, InputOption::VALUE_NONE, 'Forget where an interrupted run stopped and start over')
            ->addOption('queue', null, InputOption::VALUE_NONE, 'Hand the batches to the '.self::QUEUE_TRANSPORT.' queue instead of indexing them here')
            ->setHelp(<<<'HELP'
                The documents of each family are indexed by batches, in the order of their identifiers.

                An interrupted run resumes after its last batch when it is run again with the same families, locales, <info>--queue</info> and <info>--purge</info>, for 24 hours; the families it finished are not indexed again. <info>--restart</info> starts over.

                With <info>--queue</info>, the batches are sent to the worker in messages of at most 100 documents, and the point a run resumes from counts the batches sent, not the ones the worker has indexed. That queue does not retry: a batch the worker fails to index is set aside with the failed jobs, to replay with <info>messenger:failed:retry</info>.

                The index of a family is only emptied with <info>--purge</info>, when its indexing starts.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $engine = $this->engines->engine();

        if (null === $engine) {
            $output->writeln('<comment>No search engine is installed, nothing to index.</comment>');

            return self::SUCCESS;
        }

        if (str_starts_with($this->jobQueueDsn, 'sync://')) {
            $output->writeln('<comment>No queue holds the jobs (MESSENGER_TRANSPORT_DSN is empty): the writes of the catalogue are indexed at the end of each request or command, and a failure of the engine there is only logged. Run this command again after a large import.</comment>');
        }

        $batchSize = (string) $input->getOption('batch-size');

        if (1 !== preg_match('/^[1-9]\d*$/', $batchSize)) {
            $output->writeln(\sprintf('<error>The batch size is a whole number above 0, "%s" given.</error>', $batchSize));

            return self::FAILURE;
        }

        /** @var list<string> $families */
        $families = array_values(array_unique($input->getOption('family') ?: $this->providers->families()));
        $unknown = array_diff($families, $this->providers->families());

        if ([] !== $unknown) {
            $output->writeln(\sprintf('<error>Unknown search families: %s. Known: %s.</error>', implode(', ', $unknown), implode(', ', $this->providers->families())));

            return self::FAILURE;
        }

        /** @var list<string> $locales */
        $locales = array_values(array_unique($input->getOption('locale')));
        sort($locales);
        $indexedLocales = $this->locales->indexed();
        $unknown = array_diff($locales, $indexedLocales);

        if ([] !== $unknown) {
            $output->writeln(\sprintf('<error>Locales not indexed: %s. Indexed: %s.</error>', implode(', ', $unknown), implode(', ', $indexedLocales)));

            return self::FAILURE;
        }

        $purge = (bool) $input->getOption('purge');

        if ($purge && [] !== $locales) {
            $output->writeln('<error>--purge empties every locale of a family and cannot be used with --locale.</error>');

            return self::FAILURE;
        }

        $queue = (bool) $input->getOption('queue');
        $lock = $this->lockFactory->createLock('thelia.search.reindex', self::LOCK_TTL_SECONDS);

        if (!$lock->acquire()) {
            $output->writeln('<error>Another reindexing is in progress, nothing done.</error>');

            return self::FAILURE;
        }

        $failed = false;

        try {
            $checkpoint = $this->checkpoint($families, $locales, $queue, $purge, (bool) $input->getOption('restart'));
            $state = $this->state($checkpoint);

            foreach ($families as $family) {
                if (\in_array($family, $state['done'], true)) {
                    $output->writeln(\sprintf('<info>%s</info>, already indexed by the interrupted run', $family));

                    continue;
                }

                /** @var SearchableDocumentProviderInterface $provider */
                $provider = $this->providers->get($family);

                try {
                    $this->reindex($provider, $engine, [] === $locales ? null : $locales, (int) $batchSize, $purge, $queue, $checkpoint, $state, $lock, $output);
                } catch (LockConflictedException|LockExpiredException $exception) {
                    $failed = true;
                    $output->writeln(\sprintf('<error>%s: the lock of the reindexing was lost (%s), the run stops here.</error>', $family, $exception::class));

                    break;
                } catch (\Throwable $exception) {
                    $failed = true;
                    $output->writeln(\sprintf('<error>%s: %s (%s)</error>', $family, $exception->getMessage(), $exception::class));
                }
            }

            if (!$failed) {
                $this->checkpoints->deleteItem($checkpoint->getKey());
            }

            if ($queue) {
                $output->writeln(\sprintf('<comment>The batches were handed to the %s queue, which does not retry: a batch the worker fails to index is set aside with the failed jobs, replay it with messenger:failed:retry.</comment>', self::QUEUE_TRANSPORT));
            }
        } finally {
            $lock->release();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param list<string>|null                                      $locales
     * @param array{done: list<string>, cursors: array<string, int>} $state
     */
    private function reindex(
        SearchableDocumentProviderInterface $provider,
        SearchEngineInterface $engine,
        ?array $locales,
        int $batchSize,
        bool $purge,
        bool $queue,
        CacheItemInterface $checkpoint,
        array &$state,
        LockInterface $lock,
        OutputInterface $output,
    ): void {
        $family = $provider->family();
        $cursor = $state['cursors'][$family] ?? 0;

        $lock->refresh();

        if (0 === $cursor && $purge) {
            $engine->purge($family);
        }

        $output->writeln(0 === $cursor ? \sprintf('<info>%s</info>', $family) : \sprintf('<info>%s</info>, resuming after #%d', $family, $cursor));
        $progress = new ProgressBar($output);
        $progress->start();

        while (true) {
            $lock->refresh();
            $ids = $provider->idsAfter($cursor, $batchSize);

            if ([] === $ids) {
                break;
            }

            $ascending = $ids;
            sort($ascending);

            if ($ascending !== $ids || $ids[0] <= $cursor) {
                throw new \LogicException(\sprintf('The search document provider %s returns identifiers after #%d that are not in ascending order or not after it, the run cannot tell where it stopped.', $provider::class, $cursor));
            }

            if ($queue) {
                foreach (array_chunk($ids, SearchIndexingQueue::IDS_PER_MESSAGE) as $chunk) {
                    $this->bus->dispatch(new Envelope(new IndexDocument($family, $chunk, $locales), [new TransportNamesStamp([self::QUEUE_TRANSPORT])]));
                }
            } else {
                $this->indexer->synchronize($family, $ids, $locales);
            }

            $cursor = max($ids);
            $state['cursors'][$family] = $cursor;
            $this->save($checkpoint, $state);
            $progress->advance(\count($ids));
        }

        unset($state['cursors'][$family]);
        $state['done'][] = $family;
        $this->save($checkpoint, $state);
        $progress->finish();
        $output->writeln('');
    }

    /**
     * @param list<string> $families
     * @param list<string> $locales
     */
    private function checkpoint(array $families, array $locales, bool $queue, bool $purge, bool $restart): CacheItemInterface
    {
        $sortedFamilies = $families;
        sort($sortedFamilies);
        $key = 'reindex_'.hash('xxh3', implode(',', $sortedFamilies).'|'.implode(',', $locales).'|'.($queue ? 'queue' : 'here').'|'.($purge ? 'purge' : 'keep'));

        if ($restart) {
            $this->checkpoints->deleteItem($key);
        }

        return $this->checkpoints->getItem($key);
    }

    /**
     * @return array{done: list<string>, cursors: array<string, int>}
     */
    private function state(CacheItemInterface $checkpoint): array
    {
        $state = $checkpoint->isHit() ? $checkpoint->get() : null;

        if (!\is_array($state) || !\is_array($state['done'] ?? null) || !\is_array($state['cursors'] ?? null)) {
            return ['done' => [], 'cursors' => []];
        }

        return $state;
    }

    /**
     * @param array{done: list<string>, cursors: array<string, int>} $state
     */
    private function save(CacheItemInterface $checkpoint, array $state): void
    {
        $this->checkpoints->save($checkpoint->set($state)->expiresAfter(self::CHECKPOINT_TTL_SECONDS));
    }
}
