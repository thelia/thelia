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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;

/**
 * Calls one family of providers for the gateway. The only door to the library of the AI layer: no type of
 * `Symfony\AI` comes out of an adapter, and none goes in.
 *
 * The gateway resolves the target (AiTargetResolver), picks the adapter (AiProviderAdapters::for()), calls
 * it and turns the result into an AiResponse. The adapter does not check budgets, limits or guardrails, and
 * does not write the log.
 *
 * @internal
 */
#[AutoconfigureTag(AiProviderAdapterInterface::TAG)]
interface AiProviderAdapterInterface
{
    public const string TAG = 'thelia.ai.provider_adapter';

    public function supports(AiProvider $provider): bool;

    /**
     * @throws AiProviderUnavailableException the provider, the network or the settings of the connection refused the call;
     *                                        `retryable` tells whether trying again can work. The message never holds the key
     */
    public function complete(AiRequest $request, AiTarget $target): AiProviderResult;

    /**
     * The call starts when the iteration starts, and so do its exceptions (same as complete()).
     */
    public function stream(AiRequest $request, AiTarget $target): AiProviderStream;
}
