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

namespace Thelia\Domain\Ai\Tool;

/**
 * A token just created: the only moment its clear text exists. Nothing but its hash and its prefix is stored.
 */
final readonly class AiCreatedToolToken
{
    public function __construct(
        public int $id,
        #[\SensitiveParameter]
        public string $token,
        public string $prefix,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * @return array<string, mixed> never the clear token: a dump of this object must not leak it
     */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'prefix' => $this->prefix, 'expiresAt' => $this->expiresAt];
    }
}
