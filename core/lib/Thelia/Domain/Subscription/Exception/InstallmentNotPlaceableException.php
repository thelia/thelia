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
 * The order of an installment cannot be placed: the sale element, the addresses or the
 * carrier are gone. The installment is then a failed one, retried like a declined charge.
 */
final class InstallmentNotPlaceableException extends SubscriptionException
{
}
