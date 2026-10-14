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

final class WebhookStatus
{
    public const string SUBSCRIPTION_ACTIVE = 'active';
    public const string SUBSCRIPTION_INACTIVE = 'inactive';
    public const string SUBSCRIPTION_ERROR = 'error';

    public const string DELIVERY_PENDING = 'pending';
    public const string DELIVERY_SUCCEEDED = 'succeeded';
    public const string DELIVERY_FAILED = 'failed';

    public const string PAYLOAD_FULL = 'full';
    public const string PAYLOAD_IDENTIFIERS = 'identifiers';

    private function __construct()
    {
    }
}
