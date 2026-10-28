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

namespace Thelia\Domain\Ai\Catalog;

use Thelia\Model\AiModel;
use Thelia\Model\AiModelQuery;

/**
 * The models the layer can price and check capabilities for.
 *
 * A read-only price list ships with the core (`Resources/model-catalog.json`, rebuilt with
 * `Resources/build-model-catalog.py`); nothing of it is copied to the database. The `ai_model` rows only
 * override or add: a price the merchant negotiated, a capability the list gets wrong, a model of a
 * self-hosted or OpenAI-compatible endpoint (`connection_id` set). Precedence, the most specific first:
 * a row of the connection, a row of the provider, the embedded list. A row with `active = 0` hides the model.
 *
 * @internal
 */
class AiModelCatalog
{
    /** @var array<string, array<string, AiModelSpecification>>|null provider => name => specification */
    private ?array $embedded = null;

    public function __construct(
        private readonly ?string $catalogPath = null,
    ) {
    }

    public function find(string $provider, string $model, ?int $connectionId = null): ?AiModelSpecification
    {
        return $this->models($provider, $connectionId)[$model] ?? null;
    }

    /**
     * @return array<string, AiModelSpecification> by model name
     */
    public function models(string $provider, ?int $connectionId = null): array
    {
        $models = $this->embedded()[$provider] ?? [];

        foreach ($this->overrides($provider, null) as $name => $row) {
            $models = $this->apply($models, $name, $row);
        }

        if (null !== $connectionId) {
            foreach ($this->overrides($provider, $connectionId) as $name => $row) {
                $models = $this->apply($models, $name, $row);
            }
        }

        ksort($models);

        return $models;
    }

    /**
     * @param array<string, AiModelSpecification> $models
     *
     * @return array<string, AiModelSpecification>
     */
    private function apply(array $models, string $name, AiModel $row): array
    {
        if (!$row->getActive()) {
            unset($models[$name]);

            return $models;
        }

        $models[$name] = new AiModelSpecification(
            provider: (string) $row->getProvider(),
            name: $name,
            title: $row->getTitle(),
            inputPrice: (string) $row->getInputPrice(),
            outputPrice: (string) $row->getOutputPrice(),
            cacheReadPrice: $row->getCacheReadPrice(),
            cacheWritePrice: $row->getCacheWritePrice(),
            currency: (string) $row->getCurrency(),
            contextWindow: $row->getContextWindow(),
            maxOutputTokens: $row->getMaxOutputTokens(),
            supportsStructuredOutput: (bool) $row->getSupportsStructuredOutput(),
            supportsTools: (bool) $row->getSupportsTools(),
            supportsVision: (bool) $row->getSupportsVision(),
            supportsStreaming: (bool) $row->getSupportsStreaming(),
            source: (string) $row->getSource(),
            connectionId: $row->getConnectionId(),
        );

        return $models;
    }

    /**
     * @return array<string, AiModel>
     */
    private function overrides(string $provider, ?int $connectionId): array
    {
        $query = AiModelQuery::create()->filterByProvider($provider);
        $query->filterByConnectionId($connectionId);

        $rows = [];

        foreach ($query->find() as $row) {
            $rows[(string) $row->getName()] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, array<string, AiModelSpecification>>
     */
    private function embedded(): array
    {
        if (null !== $this->embedded) {
            return $this->embedded;
        }

        $path = $this->catalogPath ?? __DIR__.'/../Resources/model-catalog.json';
        $json = is_file($path) ? file_get_contents($path) : false;
        $data = false === $json ? null : json_decode($json, true);

        if (!\is_array($data) || !\is_array($data['models'] ?? null)) {
            throw new \RuntimeException(\sprintf('The embedded model catalogue "%s" is missing or is not valid JSON.', $path));
        }

        $embedded = [];

        foreach ($data['models'] as $entry) {
            $capabilities = $entry['capabilities'] ?? [];

            $embedded[$entry['provider']][$entry['model']] = new AiModelSpecification(
                provider: $entry['provider'],
                name: $entry['model'],
                title: null,
                inputPrice: $entry['input_usd_per_million'] ?? '0',
                outputPrice: $entry['output_usd_per_million'] ?? '0',
                cacheReadPrice: $entry['cache_read_usd_per_million'] ?? null,
                cacheWritePrice: $entry['cache_write_usd_per_million'] ?? null,
                currency: 'USD',
                contextWindow: $entry['context_window'] ?? null,
                maxOutputTokens: $entry['max_output_tokens'] ?? null,
                supportsStructuredOutput: true === ($capabilities['structured_output'] ?? null),
                supportsTools: true === ($capabilities['tools'] ?? null),
                supportsVision: true === ($capabilities['vision'] ?? null),
                supportsStreaming: true,
                source: AiModelSpecification::SOURCE_CATALOG,
            );
        }

        return $this->embedded = $embedded;
    }
}
