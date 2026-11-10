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

namespace Thelia\Domain\Subscription;

use Thelia\Model\ConfigQuery;

/**
 * How a declined installment is tried again: how many declined charges suspend the
 * subscription, and how far apart. A provider that could not be reached is tried again an
 * hour later, without counting as a declined charge.
 */
final readonly class SubscriptionRetryPolicy
{
    public const string ATTEMPTS_CONFIG = 'subscription_retry_attempts';
    public const string INTERVAL_DAYS_CONFIG = 'subscription_retry_interval_days';
    public const int DEFAULT_ATTEMPTS = 3;
    public const int DEFAULT_INTERVAL_DAYS = 2;
    public const int UNREACHABLE_PROVIDER_DELAY_SECONDS = 3600;

    public function attempts(): int
    {
        return self::positiveInteger(self::ATTEMPTS_CONFIG, self::DEFAULT_ATTEMPTS);
    }

    public function intervalDays(): int
    {
        return self::positiveInteger(self::INTERVAL_DAYS_CONFIG, self::DEFAULT_INTERVAL_DAYS);
    }

    /**
     * ConfigQuery::read() answers a stored "0" or "" as it is: anything that is not a whole
     * number of one or more falls back to the default, as the title of the setting says.
     */
    private static function positiveInteger(string $name, int $default): int
    {
        $value = trim((string) ConfigQuery::read($name, (string) $default));

        return 1 === preg_match('/^\d+$/', $value) && (int) $value > 0 ? (int) $value : $default;
    }
}
