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

namespace Thelia\Api\Service\DataAccess;

use Psr\Log\LoggerInterface;
use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Thelia\Core\Template\Loop\LoopExecutor;

/**
 * Runs a Thelia loop and returns its rows (or their count) as plain data.
 *
 * The loop resolution and execution live in the engine-agnostic LoopExecutor; this service
 * only adds the data-access conveniences: flattening each row to an associative array and
 * tolerating an unknown loop type (logged, then an empty result) so a template does not
 * crash on a typo. The $loopName argument is kept as a label for backward compatibility.
 */
class LoopDataAccessService
{
    public function __construct(
        private readonly LoopExecutor $loopExecutor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function theliaCount(string $loopType, array $params): int
    {
        try {
            return $this->loopExecutor->count($loopType, $params);
        } catch (ElementNotFoundException $ex) {
            $this->logger->error($ex->getMessage());

            return 0;
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<int, array<string, mixed>>
     */
    public function theliaLoop(string $loopName, string $loopType, array $params): array
    {
        try {
            $loopResult = $this->loopExecutor->execute($loopType, $params);
        } catch (ElementNotFoundException $ex) {
            $this->logger->error($ex->getMessage());

            return [];
        }

        $rows = [];
        foreach ($loopResult as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
    }
}
