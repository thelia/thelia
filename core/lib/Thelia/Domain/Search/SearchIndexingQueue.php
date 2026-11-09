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
use Thelia\Domain\Search\Message\SynchronizeSearchDocuments;

final class SearchIndexingQueue implements ResetInterface
{
    public const int IDS_PER_MESSAGE = 100;

    /** @var array<string, array<int, true>> */
    private array $ids = [];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function add(string $family, int $id): void
    {
        $this->ids[$family][$id] = true;
    }

    public function flush(): void
    {
        $pending = $this->ids;
        $this->reset();

        foreach ($pending as $family => $ids) {
            foreach (array_chunk(array_keys($ids), self::IDS_PER_MESSAGE) as $chunk) {
                try {
                    $this->bus->dispatch(new SynchronizeSearchDocuments($family, $chunk));
                } catch (\Throwable $exception) {
                    $this->logger->error('Search indexing of {count} {family} document(s) could not be queued: {exception}.', [
                        'count' => \count($chunk),
                        'family' => $family,
                        'exception' => self::describe($exception),
                    ]);
                }
            }
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
        $this->ids = [];
    }
}
