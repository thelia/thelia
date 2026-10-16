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

namespace Thelia\Model\Tools;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\DataTransfer\Job\JobStatus;

/**
 * Which export or import jobs the maintenance purge removes.
 */
trait ExpiredDataTransferJobFilterTrait
{
    /**
     * The jobs created more than $days days ago; a failed one only after $failedDays
     * days, as long as it can still be replayed from the failure transport.
     */
    public function filterExpired(int $days, int $failedDays): static
    {
        $model = $this->getModelAliasOrName();

        return $this
            ->filterByCreatedAt(new \DateTime(\sprintf('-%d days', $days)), Criteria::LESS_THAN)
            // Two named conditions, so the OR is parenthesized.
            ->condition('done', $model.'.Status = ?', JobStatus::DONE->value)
            ->condition('replayExpired', $model.'.CreatedAt < ?', (new \DateTime(\sprintf('-%d days', $failedDays)))->format('Y-m-d H:i:s'))
            ->where(['done', 'replayExpired'], Criteria::LOGICAL_OR);
    }
}
