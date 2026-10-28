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

namespace Thelia\Domain\Ai\Guardrail\Moderation;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiJurisdiction;

/**
 * The connection a moderation call goes through. The key is held only to be sent; it never shows in a dump.
 *
 * @internal
 */
#[Exclude]
final readonly class ModerationTarget
{
    public function __construct(
        public string $connectionCode,
        public string $baseUrl,
        #[\SensitiveParameter]
        private ?string $apiKey,
        public bool $allowPrivateNetwork,
        public AiJurisdiction $jurisdiction,
    ) {
    }

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'connectionCode' => $this->connectionCode,
            'baseUrl' => $this->baseUrl,
            'apiKey' => null === $this->apiKey ? null : '[hidden]',
            'allowPrivateNetwork' => $this->allowPrivateNetwork,
            'jurisdiction' => $this->jurisdiction,
        ];
    }
}
