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

namespace Thelia\Tests\Support\Order;

use Propel\Runtime\Propel;
use Psr\Log\AbstractLogger;

/**
 * The SQL statements a callable runs on the connection of the test, for a budget of queries
 * written on what is asked rather than on a count another feature may move.
 */
trait RecordsStatements
{
    /**
     * @return list<string>
     */
    private function statementsDuring(callable $run): array
    {
        $connection = Propel::getConnection('TheliaMain');
        $logger = $connection->getLogger();
        $recorder = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lines[] = (string) $message;
            }
        };
        $connection->setLogger($recorder);
        $connection->useDebug(true);

        try {
            $run();
        } finally {
            $connection->useDebug(false);
            $connection->setLogger($logger);
        }

        return $recorder->lines;
    }
}
