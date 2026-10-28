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

namespace Thelia\Domain\Ai\Provider;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiCapability;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;

/**
 * Where one call goes: the connection (endpoint, key, jurisdiction) and the model, resolved for a usage.
 * The key is held here only to be handed to the adapter; it never shows in a dump of the object.
 *
 * @internal
 */
#[Exclude]
final readonly class AiTarget
{
    public function __construct(
        public int $connectionId,
        public string $connectionCode,
        public AiProvider $provider,
        public ?string $baseUrl,
        #[\SensitiveParameter]
        private ?string $apiKey,
        public bool $allowPrivateNetwork,
        public AiJurisdiction $jurisdiction,
        public AiModelSpecification $model,
    ) {
    }

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    public function supports(AiCapability $capability): bool
    {
        return match ($capability) {
            AiCapability::StructuredOutput => $this->model->supportsStructuredOutput,
            AiCapability::Tools => $this->model->supportsTools,
            AiCapability::Vision => $this->model->supportsVision,
            AiCapability::Streaming => $this->model->supportsStreaming,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'connectionCode' => $this->connectionCode,
            'provider' => $this->provider,
            'baseUrl' => $this->baseUrl,
            'apiKey' => null === $this->apiKey ? null : '[hidden]',
            'allowPrivateNetwork' => $this->allowPrivateNetwork,
            'jurisdiction' => $this->jurisdiction,
            'model' => $this->model->name,
        ];
    }
}
