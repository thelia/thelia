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

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class WebhookDeliveryTransport
{
    public function __construct(
        #[Autowire('%env(default:thelia.messenger.inline_transport_dsn:MESSENGER_TRANSPORT_DSN)%')]
        #[\SensitiveParameter]
        private string $jobDsn,
    ) {
    }

    public static function isInline(string $jobDsn): bool
    {
        return str_starts_with($jobDsn, 'sync://');
    }

    public function runsInline(): bool
    {
        return self::isInline($this->jobDsn);
    }
}
