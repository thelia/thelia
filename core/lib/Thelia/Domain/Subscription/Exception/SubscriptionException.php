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

namespace Thelia\Domain\Subscription\Exception;

/**
 * What the subscriptions refuse. The message can be shown to whoever asked: it never
 * names a token nor another customer.
 */
abstract class SubscriptionException extends \RuntimeException
{
}
