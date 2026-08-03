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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Generates the customer-facing order reference.
 *
 * Implementations are called on the order creation connection, inside the
 * order transaction, just before commit. A custom implementation aliased to
 * this interface replaces the default format shop-wide.
 */
interface OrderRefGeneratorInterface
{
    public function generate(ConnectionInterface $connection): string;
}
