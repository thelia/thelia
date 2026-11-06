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

namespace Thelia\Domain\Order\Exception;

use Thelia\Core\Translation\Translator;

/**
 * The database broke a lock conflict between this change of status and another request on the
 * same order or the same stock: a deadlock (1213), or on MariaDB 11.6 and later a row changed
 * since the transaction took its picture (1020). The change was rolled back whole; asked again a
 * moment later it is judged on what the other request left.
 *
 * A refusal rather than a failure: nothing is broken, and a caller can retry (409 on the API).
 */
final class OrderStatusChangeConflictException extends OrderException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            Translator::getInstance()->trans('Another change of this order was made at the same moment. Please try again.'),
            previous: $previous,
        );
    }
}
