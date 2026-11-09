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
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Search\Contract\SearchEngineInterface;

#[Autoconfigure(public: true)]
final class SearchEngineRegistry
{
    private ?SearchEngineInterface $engine = null;

    private bool $resolved = false;

    /**
     * @param iterable<SearchEngineInterface> $engines
     */
    public function __construct(
        #[AutowireIterator(SearchEngineInterface::TAG)]
        private readonly iterable $engines,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function engine(): ?SearchEngineInterface
    {
        if (!$this->resolved) {
            foreach ($this->engines as $engine) {
                $this->engine = $engine;

                break;
            }

            $this->resolved = true;

            if (null !== $this->engine && is_countable($this->engines) && \count($this->engines) > 1) {
                $this->logger->warning('{count} search engines are installed, only {engine} answers: the one with the highest priority.', [
                    'count' => \count($this->engines),
                    'engine' => $this->engine::class,
                ]);
            }
        }

        return $this->engine;
    }

    public function hasEngine(): bool
    {
        if (is_countable($this->engines)) {
            return \count($this->engines) > 0;
        }

        return null !== $this->engine();
    }
}
