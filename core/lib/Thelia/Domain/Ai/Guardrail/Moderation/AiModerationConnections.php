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

namespace Thelia\Domain\Ai\Guardrail\Moderation;

use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\AiKeyDecryptionException;
use Thelia\Domain\Ai\Guardrail\GuardrailAvailability;
use Thelia\Domain\Ai\Provider\AiProviderPresets;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;

/**
 * Finds the connection a moderation of a provider goes through: an active connection of that provider with a key
 * the vault can read. A connection in the EU comes first. When the merchant allows EU providers only, a connection
 * outside the EU does not count, and the moderation says `jurisdiction` rather than `no_connection` when that is
 * all that stopped it.
 *
 * It reads the database and the vault, never the network.
 *
 * @internal
 */
final readonly class AiModerationConnections
{
    public function __construct(
        private AiRoutingRepository $routing,
        private AiKeyVault $vault,
        private AiProviderPresets $presets,
        private AiSettings $settings,
    ) {
    }

    public function select(AiProvider $provider): ModerationSelection
    {
        $candidates = [];

        foreach ($this->routing->activeConnections($provider) as $connection) {
            $target = $this->target($provider, $connection);

            if (null !== $target) {
                $candidates[] = $target;
            }
        }

        if ([] === $candidates) {
            return ModerationSelection::none(GuardrailAvailability::NO_CONNECTION);
        }

        // Stable sort: the oldest connection of a jurisdiction wins.
        usort($candidates, static fn (ModerationTarget $left, ModerationTarget $right): int => (AiJurisdiction::EuropeanUnion === $right->jurisdiction ? 1 : 0) <=> (AiJurisdiction::EuropeanUnion === $left->jurisdiction ? 1 : 0));

        foreach ($candidates as $candidate) {
            if (!$this->settings->isEuropeanUnionOnly() || AiJurisdiction::EuropeanUnion === $candidate->jurisdiction) {
                return ModerationSelection::of($candidate);
            }
        }

        return ModerationSelection::none(GuardrailAvailability::JURISDICTION);
    }

    private function target(AiProvider $provider, AiConnection $connection): ?ModerationTarget
    {
        try {
            $key = $this->vault->resolve($connection);
        } catch (AiKeyDecryptionException) {
            return null;
        }

        if (null === $key) {
            return null;
        }

        $configured = trim((string) $connection->getBaseUrl());
        $baseUrl = '' !== $configured ? $configured : $this->presets->defaultBaseUrl($provider);

        if (null === $baseUrl) {
            return null;
        }

        return new ModerationTarget(
            (string) $connection->getCode(),
            $baseUrl,
            $key,
            (bool) $connection->getAllowPrivateNetwork(),
            $provider->defaultJurisdiction($baseUrl) ?? AiJurisdiction::tryFrom((string) $connection->getJurisdiction()) ?? AiJurisdiction::OutsideEuropeanUnion,
        );
    }
}
