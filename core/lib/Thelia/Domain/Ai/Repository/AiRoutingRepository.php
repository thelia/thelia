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

use Thelia\Domain\Ai\AiTier;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModel;
use Thelia\Model\AiModelQuery;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\AiTierAssignmentQuery;

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

    public function model(int $id): ?AiModel
    {
        return AiModelQuery::create()->findPk($id);
    }
}
