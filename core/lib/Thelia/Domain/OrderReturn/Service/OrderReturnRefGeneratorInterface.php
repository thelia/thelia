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

namespace Thelia\Domain\OrderReturn\Service;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * What allocates the reference a return is known by.
 *
 * A shop whose accounting or after-sales tool imposes its own numbering series
 * replaces the default implementation by aliasing this interface to its own
 * service, the way the order reference is replaced.
 */
interface OrderReturnRefGeneratorInterface
{
    /**
     * Allocate the next reference.
     *
     * Each call consumes a number: only call it for a reference that will be
     * persisted, and call it on the connection that writes the return so the
     * allocation is rolled back with it.
     */
    public function generate(ConnectionInterface $connection): string;
}
