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

namespace Thelia\Domain\Webhook;

use Thelia\Model\Base\WebhookDeliveryQuery as BaseWebhookDeliveryQuery;
use Thelia\Model\WebhookDeliveryQuery;

final readonly class WebhookDeliveryPurger
{
    public const string RETENTION_CONFIG_KEY = 'webhook_delivery_retention_days';
    public const int DEFAULT_RETENTION_DAYS = 30;

    public function purge(int $retentionDays, bool $dryRun): int
    {
        $query = $this->olderThan($retentionDays);

        return $dryRun ? $query->count() : $query->delete();
    }

    private function olderThan(int $retentionDays): BaseWebhookDeliveryQuery
    {
        return WebhookDeliveryQuery::create()->filterByCreatedAt(new \DateTimeImmutable(\sprintf('-%d days', max(1, $retentionDays))), WebhookDeliveryQuery::LESS_THAN);
    }
}
