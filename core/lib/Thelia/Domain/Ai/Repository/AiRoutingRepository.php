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

namespace Thelia\Domain\Ai\Repository;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiTier;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModel;
use Thelia\Model\AiModelQuery;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\AiTierAssignmentQuery;
use Thelia\Model\AiUsageSettingQuery;

/**
 * Where a call can go: the connections, their models, and the model the merchant gave to each tier.
 *
 * @internal
 */
class AiRoutingRepository
{
    public function tierAssignment(AiTier $tier): ?AiTierAssignment
    {
        return AiTierAssignmentQuery::create()->findOneByTier($tier->value);
    }

    public function connection(int $id): ?AiConnection
    {
        return AiConnectionQuery::create()->findPk($id);
    }

    /**
     * The active connections of a provider, oldest first.
     *
     * @return list<AiConnection>
     */
    public function activeConnections(AiProvider $provider): array
    {
        return array_values(AiConnectionQuery::create()
            ->filterByProvider($provider->value)
            ->filterByActive(true)
            ->orderById()
            ->find()
            ->getArrayCopy());
    }

    /**
     * The names of the models this connection already serves: the one of each range that runs on it (fast first, the
     * cheapest), then the ones a usage pins to it.
     *
     * @return list<string>
     */
    public function modelNamesServedBy(AiConnection $connection): array
    {
        $provider = (string) $connection->getProvider();
        $names = [];

        $assignments = AiTierAssignmentQuery::create()->filterByConnectionId($connection->getId())->find()->getArrayCopy();
        $order = array_flip(array_column(AiTier::cases(), 'value'));
        usort($assignments, static fn (AiTierAssignment $a, AiTierAssignment $b): int => ($order[$a->getTier()] ?? \count($order)) <=> ($order[$b->getTier()] ?? \count($order)));

        $modelIds = array_map(static fn (AiTierAssignment $assignment): int => (int) $assignment->getModelId(), $assignments);

        foreach (AiUsageSettingQuery::create()->filterByPinnedModelId(null, Criteria::ISNOTNULL)->orderById()->find() as $setting) {
            $pinned = $this->model((int) $setting->getPinnedModelId());

            if ($setting->getPinnedConnectionId() === $connection->getId() || $pinned?->getConnectionId() === $connection->getId()) {
                $modelIds[] = (int) $setting->getPinnedModelId();
            }
        }

        foreach ($modelIds as $id) {
            $model = $this->model($id);

            if (null !== $model && $model->getActive() && $model->getProvider() === $provider) {
                $names[] = (string) $model->getName();
            }
        }

        return array_values(array_unique($names));
    }

    public function model(int $id): ?AiModel
    {
        return AiModelQuery::create()->findPk($id);
    }
}
