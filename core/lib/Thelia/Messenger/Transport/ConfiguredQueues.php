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

namespace Thelia\Messenger\Transport;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Where the transports of the shop point, as their DSNs say: what the back office
 * needs to know of them beyond what a transport tells.
 */
final readonly class ConfiguredQueues
{
    public function __construct(
        private ShopDatabaseConnection $connection,
        #[Autowire('%env(default:thelia.messenger.inline_transport_dsn:MESSENGER_TRANSPORT_DSN)%')]
        #[\SensitiveParameter]
        private string $jobDsn,
        #[Autowire('%env(thelia_heavy_queue:MESSENGER_TRANSPORT_DSN)%')]
        #[\SensitiveParameter]
        private string $heavyDsn,
        #[Autowire('%env(MESSENGER_FAILURE_TRANSPORT_DSN)%')]
        #[\SensitiveParameter]
        private string $failureDsn,
    ) {
    }

    /**
     * True when the heavy jobs wait on the very queue of the others: counting both
     * would count every job twice.
     */
    public function heavyJobsShareTheJobQueue(): bool
    {
        return $this->jobDsn === $this->heavyDsn;
    }

    /**
     * The failure queue, when it is a table of the shop database.
     */
    public function failureQueueInTheShopDatabase(): ?ShopDatabaseQueue
    {
        return ShopDatabaseQueue::of($this->connection, $this->failureDsn);
    }
}
