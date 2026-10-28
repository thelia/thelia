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

use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Exception\AiKeyDecryptionException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;
use Thelia\Model\AiModel;

/**
 * The connection and the model a usage runs on.
 *
 * The model pinned to the usage wins; without one, the model the merchant gave to the tier of the usage: the tier
 * the merchant chose for it, else the one the module declared.
 * A connection pinned to the usage replaces the one of the tier, and keeps the model of the tier only when
 * both belong to the same provider. The connection must be active; the key comes from the vault and the
 * jurisdiction from the provider and the address (the one the administrator declared, for a self-hosted or
 * compatible endpoint; outside the EU when nothing says otherwise).
 *
 * @internal
 */
final readonly class AiTargetResolver
{
    public function __construct(
        private AiUsageRegistryInterface $usages,
        private AiUsageSettingRepository $settings,
        private AiRoutingRepository $routing,
        private AiModelCatalog $catalog,
        private AiKeyVault $vault,
    ) {
    }

    /**
     * @throws UnknownAiUsageException
     * @throws InactiveAiUsageException no active connection with a model for the usage
     * @throws AiKeyDecryptionException
     */
    public function resolve(string $usageId): AiTarget
    {
        $usage = $this->usages->get($usageId);
        $setting = $this->settings->findByUsage($usageId);
        $assignment = $this->routing->tierAssignment($setting?->effectiveTier($usage->tier) ?? $usage->tier);

        $tierConnection = null === $assignment?->getConnectionId() ? null : $this->routing->connection($assignment->getConnectionId());
        $tierModel = null === $assignment?->getModelId() ? null : $this->routing->model($assignment->getModelId());

        $pinnedConnection = null === $setting?->getPinnedConnectionId() ? null : $this->routing->connection($setting->getPinnedConnectionId());
        $pinnedModel = null === $setting?->getPinnedModelId() ? null : $this->routing->model($setting->getPinnedModelId());

        if (null !== $pinnedModel) {
            $model = $pinnedModel;
            $connection = $pinnedConnection
                ?? (null === $pinnedModel->getConnectionId() ? $tierConnection : $this->routing->connection($pinnedModel->getConnectionId()));
        } elseif (null !== $pinnedConnection) {
            $connection = $pinnedConnection;
            $model = $tierModel;
        } else {
            $connection = $tierConnection;
            $model = $tierModel;
        }

        if (null === $connection || null === $model || !$this->fits($connection, $model)) {
            throw new InactiveAiUsageException($usageId);
        }

        $provider = AiProvider::tryFrom((string) $connection->getProvider()) ?? throw new InactiveAiUsageException($usageId);
        $specification = $this->catalog->find((string) $model->getProvider(), (string) $model->getName(), $connection->getId()) ?? throw new InactiveAiUsageException($usageId);
        $baseUrl = $this->nonEmpty($connection->getBaseUrl());

        return new AiTarget(
            $connection->getId(),
            (string) $connection->getCode(),
            $provider,
            $baseUrl,
            $this->vault->resolve($connection),
            (bool) $connection->getAllowPrivateNetwork(),
            $provider->defaultJurisdiction($baseUrl) ?? AiJurisdiction::tryFrom((string) $connection->getJurisdiction()) ?? AiJurisdiction::OutsideEuropeanUnion,
            $specification,
        );
    }

    private function fits(AiConnection $connection, AiModel $model): bool
    {
        return $connection->getActive()
            && $model->getActive()
            && $connection->getProvider() === $model->getProvider()
            && (null === $model->getConnectionId() || $model->getConnectionId() === $connection->getId());
    }

    private function nonEmpty(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}
