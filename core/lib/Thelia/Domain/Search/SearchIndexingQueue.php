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

namespace Thelia\Domain\Search;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Search\Message\IndexDocument;
use Thelia\Domain\Search\Message\RemoveDocument;

final class SearchIndexingQueue implements ResetInterface
{
    public const int IDS_PER_MESSAGE = 100;

    /** @var array<string, array<int, true>> */
    private array $toIndex = [];

    /** @var array<string, array<int, true>> */
    private array $toRemove = [];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function add(string $family, int $id): void
    {
        if (!isset($this->toRemove[$family][$id])) {
            $this->toIndex[$family][$id] = true;
        }
    }

    public function remove(string $family, int $id): void
    {
        $this->toRemove[$family][$id] = true;
        unset($this->toIndex[$family][$id]);
    }

    public function flush(): void
    {
        $toIndex = $this->toIndex;
        $toRemove = $this->toRemove;
        $this->reset();

        foreach ($toRemove as $family => $ids) {
            foreach (array_chunk(array_keys($ids), self::IDS_PER_MESSAGE) as $chunk) {
                $this->dispatch(new RemoveDocument($family, $chunk));
            }
        }

        foreach ($toIndex as $family => $ids) {
            foreach (array_chunk(array_keys($ids), self::IDS_PER_MESSAGE) as $chunk) {
                $this->dispatch(new IndexDocument($family, $chunk));
            }
        }
    }

    private function dispatch(IndexDocument|RemoveDocument $message): void
    {
        try {
            $this->bus->dispatch($message);
        } catch (\Throwable $exception) {
            $this->logger->error('Search {operation} of {count} {family} document(s) could not be queued: {exception}.', [
                'operation' => $message instanceof RemoveDocument ? 'removal' : 'indexing',
                'count' => \count($message->ids),
                'family' => $message->family,
                'exception' => self::describe($exception),
            ]);
        }
    }

    private static function describe(\Throwable $exception): string
    {
        if (!$exception instanceof HandlerFailedException) {
            return $exception::class;
        }

        $wrapped = array_map(static fn (\Throwable $inner): string => $inner::class, $exception->getWrappedExceptions(recursive: true));

        return [] === $wrapped ? $exception::class : implode(', ', array_unique($wrapped));
    }

    public function reset(): void
    {
        $this->toIndex = [];
        $this->toRemove = [];
    }
}
