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

namespace Thelia\Domain\Ai\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Ai\AiProvider;

/**
 * The adapter that speaks to a provider.
 *
 * @internal
 */
final readonly class AiProviderAdapters
{
    /**
     * @param iterable<AiProviderAdapterInterface> $adapters
     */
    public function __construct(
        #[AutowireIterator(AiProviderAdapterInterface::TAG)]
        private iterable $adapters,
    ) {
    }

    public function for(AiProvider $provider): AiProviderAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($provider)) {
                return $adapter;
            }
        }

        throw new \LogicException(\sprintf('No adapter speaks to the AI provider "%s".', $provider->value));
    }
}
