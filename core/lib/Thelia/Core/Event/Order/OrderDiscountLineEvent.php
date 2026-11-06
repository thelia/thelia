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

namespace Thelia\Core\Event\Order;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;
use Thelia\Model\OrderDiscountLine;

/**
 * A discount line of a module was written on an order being placed, inside the transaction
 * of the order. The module charges what the line was granted with the connection given:
 * a listener that throws rolls the whole order back.
 */
class OrderDiscountLineEvent extends ActionEvent
{
    public function __construct(
        protected Order $order,
        protected OrderDiscountLine $line,
        protected ConnectionInterface $connection,
    ) {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getLine(): OrderDiscountLine
    {
        return $this->line;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}
