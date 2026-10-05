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

namespace Thelia\Messenger\Monitoring;

/**
 * A job set aside in the failure transport, as the back office lists it.
 */
final readonly class FailedJob
{
    public function __construct(
        public string $id,
        public string $messageClass,
        public string $description,
        public ?\DateTimeInterface $failedAt,
        public int $attempts,
        public string $error,
        public ?string $transport,
    ) {
    }
}
