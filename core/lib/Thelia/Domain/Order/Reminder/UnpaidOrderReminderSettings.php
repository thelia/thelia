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

namespace Thelia\Domain\Order\Reminder;

use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Where the merchant's reminder schedule is kept: two settings of the shop, empty unless
 * the merchant writes them, so that a shop never starts reminding on its own after an
 * update.
 */
final readonly class UnpaidOrderReminderSettings
{
    public const SCHEDULE_KEY = 'unpaid_order_reminder_schedule';

    /**
     * Codes of the payment modules whose orders are never reminded nor cancelled, comma
     * separated: a bank transfer or a cheque is legitimately paid days later.
     */
    public const EXCLUDED_MODULES_KEY = 'unpaid_order_reminder_excluded_modules';

    /**
     * A schedule that cannot be read sends nothing: the setting is written through
     * save(), which refuses it, so only a hand edit of the database gets here.
     */
    public function schedule(): UnpaidOrderReminderSchedule
    {
        try {
            return UnpaidOrderReminderSchedule::fromSetting((string) ConfigQuery::read(self::SCHEDULE_KEY, ''));
        } catch (InvalidReminderScheduleException $exception) {
            Tlog::getInstance()->error(\sprintf('The unpaid order reminder schedule cannot be read, nothing is sent: %s', $exception->getMessage()));

            return UnpaidOrderReminderSchedule::fromSetting('');
        }
    }

    /**
     * @return list<string>
     */
    public function excludedPaymentModuleCodes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ConfigQuery::read(self::EXCLUDED_MODULES_KEY, ''))), static fn (string $code): bool => '' !== $code));
    }

    /**
     * @param list<string> $excludedPaymentModuleCodes
     */
    public function save(UnpaidOrderReminderSchedule $schedule, array $excludedPaymentModuleCodes): void
    {
        ConfigQuery::write(self::SCHEDULE_KEY, $schedule->toSetting());
        ConfigQuery::write(self::EXCLUDED_MODULES_KEY, implode(',', array_values(array_unique(array_filter(array_map('trim', $excludedPaymentModuleCodes))))));
    }
}
