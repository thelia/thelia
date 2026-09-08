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

namespace Thelia\Test\Trait;

use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;

/**
 * Empties Propel's instance pools around a measured piece of work.
 *
 * A test builds its fixtures in the same process as the request it measures,
 * so the rows that request reads are already in memory: a relation answered
 * from the pool costs no query, and a count taken that way says nothing about
 * what the same page costs to a browser, which starts with nothing pooled.
 *
 * Pooling itself has to stay on. It is what hands a preloaded row back to the
 * parent that asks for it, and Propel refuses to read a relation in bulk
 * without it.
 */
trait ForgetsPooledModels
{
    /**
     * @return list<string> the statements the work ran
     */
    protected function recordSqlQueriesWithoutPooledModels(callable $work): array
    {
        $wasEnabled = Propel::isInstancePoolingEnabled();
        Propel::enableInstancePooling();
        self::forgetPooledModels();

        try {
            return $this->recordSqlQueries($work);
        } finally {
            self::forgetPooledModels();

            if (!$wasEnabled) {
                Propel::disableInstancePooling();
            }
        }
    }

    /**
     * The pools live on the generated table map classes, as statics that
     * outlive the kernel: the database map is rebuilt on every boot and knows
     * nothing of them, so the loaded classes themselves are what to ask.
     */
    protected static function forgetPooledModels(): void
    {
        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, TableMap::class) || !method_exists($class, 'clearInstancePool')) {
                continue;
            }

            $class::clearInstancePool();
        }
    }
}
